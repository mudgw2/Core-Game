<?php
declare(strict_types=1);

namespace Core\Modules\Universe;

use Core\Modules\Characters\CharacterLifecycleGuard;
use Core\Modules\Characters\CharacterUniverseContext;
use Core\Storage\JsonStorage;
use RuntimeException;

/** Share-code access and reversible visits; home universe identity is never overwritten. */
final class SharedUniverseService
{
    public function __construct(private JsonStorage $storage) {}

    /** Creates or rotates a single-use-display share code; only its hash is persisted. */
    public function createShareCode(string $universeId, string $ownerAccountId): array
    {
        $universeId = trim($universeId);
        $ownerAccountId = trim($ownerAccountId);
        if ($universeId === '' || $ownerAccountId === '') throw new RuntimeException('Universe and owner account are required.');
        if (!\Core\Http\PlayerApiSecurity::ownsUniverse($this->storage, $universeId, $ownerAccountId)) throw new RuntimeException('Universe not found for this account.');
        $code = strtoupper(bin2hex(random_bytes(16)));
        $universe = $this->storage->transact('universes', $universeId, static function (array $record) use ($ownerAccountId, $code): array {
            if ((string)($record['owner_account_id'] ?? '') !== '' && (string)$record['owner_account_id'] !== $ownerAccountId) throw new RuntimeException('Universe owner could not be verified.');
            $record['sharing'] = [
                'enabled' => true,
                'code_hash' => hash('sha256', $code),
                'code_version' => (int)($record['sharing']['code_version'] ?? 0) + 1,
                'updated_at' => gmdate('c'),
            ];
            return $record;
        });
        return ['universe_id' => $universeId, 'share_code' => $code, 'sharing' => $universe['sharing']];
    }

    /** Join the universe identified by a share code while preserving the character's home association. */
    public function visit(string $characterId, string $accountId, string $shareCode): array
    {
        $characterId = trim($characterId);
        $accountId = trim($accountId);
        $shareCode = strtoupper(trim($shareCode));
        if ($characterId === '' || $accountId === '' || !preg_match('/^[A-F0-9]{32}$/', $shareCode)) throw new RuntimeException('A valid character and 32-character share code are required.');
        $character = $this->storage->read('characters', $characterId);
        if (!is_array($character) || (string)($character['owner_account_id'] ?? '') !== $accountId) throw new RuntimeException('Character not found for this account.');
        CharacterLifecycleGuard::assertCanPerformGameplayAction($character);
        $homeUniverseId = trim((string)($character['universe_id'] ?? ''));
        if ($homeUniverseId === '') throw new RuntimeException('The character must have a home universe before visiting another universe.');
        $codeHash = hash('sha256', $shareCode);
        $target = null;
        foreach ($this->storage->all('universes') as $candidate) {
            if (!is_array($candidate) || ($candidate['sharing']['enabled'] ?? false) !== true) continue;
            $storedHash = (string)($candidate['sharing']['code_hash'] ?? '');
            if ($storedHash !== '' && hash_equals($storedHash, $codeHash)) { $target = $candidate; break; }
        }
        if (!is_array($target)) throw new RuntimeException('Share code is invalid, expired, or no longer active.');
        $targetId = (string)($target['id'] ?? '');
        if ($targetId === '' || $targetId === $homeUniverseId) throw new RuntimeException('This character already belongs to that universe.');
        (new UniverseService($this->storage))->ensureCurrentHierarchy($targetId);
        return $this->storage->withRecordLockOperation('universes', $targetId, function () use ($targetId, $codeHash, $characterId, $accountId, $homeUniverseId): array {
            // Recheck the code while holding the universe lock so rotation cannot race a join.
            $lockedUniverse = $this->storage->read('universes', $targetId);
            $lockedHash = (string)($lockedUniverse['sharing']['code_hash'] ?? '');
            if (!is_array($lockedUniverse) || ($lockedUniverse['sharing']['enabled'] ?? false) !== true || $lockedHash === '' || !hash_equals($lockedHash, $codeHash)) {
                throw new RuntimeException('Share code is invalid, expired, or no longer active.');
            }
            UniverseLifecycleGuard::assertCanPerformGameplayAction($lockedUniverse);
            $systems = $lockedUniverse['baseline']['systems'] ?? [];
            $first = is_array($systems) ? ($systems[0] ?? null) : null;
            if (!is_array($first) || trim((string)($first['id'] ?? '')) === '') throw new RuntimeException('The shared universe has no valid starting system.');

            return $this->storage->transact('characters', $characterId, static function (array $current) use ($accountId, $homeUniverseId, $targetId, $first): array {
                if ((string)($current['owner_account_id'] ?? '') !== $accountId || (string)($current['universe_id'] ?? '') !== $homeUniverseId) throw new RuntimeException('Character identity changed while joining the shared universe.');
                if (CharacterUniverseContext::activeUniverseId($current) === $targetId) throw new RuntimeException('Character is already in this shared universe.');
                // Determine whether this is a first visit before changing active_universe_id.
                $wasVisiting = CharacterUniverseContext::isVisiting($current);
                $current['active_universe_id'] = $targetId;
                // Preserve the original home snapshot when switching directly between shared universes.
                if (!$wasVisiting) {
                    $current['home_location_snapshot'] = [
                        'current_system_id' => (string)($current['current_system_id'] ?? ''),
            'current_constellation_id' => (string)($current['current_constellation_id'] ?? $current['location']['constellation_id'] ?? ''),
                        'location' => is_array($current['location'] ?? null) ? $current['location'] : [],
                        'route_progress' => is_array($current['route_progress'] ?? null) ? $current['route_progress'] : null,
                    ];
                }
                $current['current_system_id'] = (string)$first['id'];
                $current['current_constellation_id'] = (string)($first['constellation_id'] ?? '');
                $current['location'] = [
                    'constellation_id' => (string)($first['constellation_id'] ?? ''),
                    'system_id' => (string)$first['id'],
                    'universe_id' => $targetId,
                    'updated_at' => gmdate('c'),
                ];
                $current['route_progress'] = null;
                $current['active_universe_visit'] = ['universe_id' => $targetId, 'joined_at' => gmdate('c')];
                $current['revision'] = (int)($current['revision'] ?? 0) + 1;
                return $current;
            });
        });
    }

    /** Return to the permanent home universe and restore the saved home location. */
    public function leave(string $characterId, string $accountId): array
    {
        $characterId = trim($characterId); $accountId = trim($accountId);
        if ($characterId === '' || $accountId === '') throw new RuntimeException('Character and account are required.');
        return $this->storage->transact('characters', $characterId, static function (array $character) use ($accountId): array {
            if ((string)($character['owner_account_id'] ?? '') !== $accountId) throw new RuntimeException('Character not found for this account.');
            if (!CharacterUniverseContext::isVisiting($character)) throw new RuntimeException('Character is already in its home universe.');
            $snapshot = is_array($character['home_location_snapshot'] ?? null) ? $character['home_location_snapshot'] : [];
            $homeSystemId = trim((string)($snapshot['current_system_id'] ?? ''));
            if ($homeSystemId !== '') $character['current_system_id'] = $homeSystemId;
        $homeConstellationId = trim((string)($snapshot['current_constellation_id'] ?? ''));
        if ($homeConstellationId !== '') $character['current_constellation_id'] = $homeConstellationId;
            if (is_array($snapshot['location'] ?? null)) $character['location'] = $snapshot['location'];
            $character['route_progress'] = is_array($snapshot['route_progress'] ?? null) ? $snapshot['route_progress'] : null;
            unset($character['active_universe_id'], $character['home_location_snapshot'], $character['active_universe_visit']);
            $character['revision'] = (int)($character['revision'] ?? 0) + 1;
            return $character;
        });
    }
}
