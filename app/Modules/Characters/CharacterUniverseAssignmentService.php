<?php
declare(strict_types=1);

namespace Core\Modules\Characters;

use Core\Storage\JsonStorage;
use Core\Modules\Universe\UniverseLifecycleGuard;
use RuntimeException;

/** Assigns an owned character to a persisted universe without resetting existing state. */
final class CharacterUniverseAssignmentService
{
    public function __construct(private JsonStorage $storage) {}

    /**
     * Lock order is universe then character, matching universe deletion and character creation.
     * Repeating the same assignment is idempotent; changing universes is deliberately rejected.
     */
    public function assign(string $characterId, string $ownerAccountId, string $universeId): array
    {
        $characterId = trim($characterId);
        $ownerAccountId = trim($ownerAccountId);
        $universeId = trim($universeId);
        if ($characterId === '' || $ownerAccountId === '' || $universeId === '') {
            throw new RuntimeException('Character, owner account, and universe IDs are required for assignment.');
        }

        return $this->storage->withRecordLockOperation('universes', $universeId, function () use ($characterId, $ownerAccountId, $universeId): array {
            $universe = $this->storage->read('universes', $universeId);
            if ($universe === null || ($universe['id'] ?? null) !== $universeId) {
                throw new RuntimeException('The selected universe does not exist.');
            }

            UniverseLifecycleGuard::assertCanPerformGameplayAction($universe);
            $systems = $universe['baseline']['systems'] ?? null;
            $first = is_array($systems) ? ($systems[0] ?? null) : null;
            if (!is_array($first) || !is_string($first['id'] ?? null) || trim($first['id']) === '') {
                throw new RuntimeException('The selected universe has no valid starting system.');
            }

            $status = 'assigned';
            $character = $this->storage->transact('characters', $characterId, static function (array $current) use ($characterId, $ownerAccountId, $universeId, $first, &$status): array {
                if (($current['id'] ?? null) !== $characterId) {
                    throw new RuntimeException('Character record identity does not match the requested character.');
                }
                if (($current['owner_account_id'] ?? null) !== $ownerAccountId) {
                    throw new RuntimeException('Character ownership could not be verified; no universe association was made.');
                }

                $existingUniverseId = $current['universe_id'] ?? null;
                if (is_string($existingUniverseId) && $existingUniverseId !== '') {
                    if ($existingUniverseId !== $universeId) {
                        throw new RuntimeException('This character is already assigned to another universe. Safe unassignment is not available.');
                    }
                    $status = 'already_assigned';
                    return $current;
                }

                if ($existingUniverseId !== null && $existingUniverseId !== '') {
                    throw new RuntimeException('Character universe association is invalid; no changes were made.');
                }
                CharacterLifecycleGuard::assertCanPerformGameplayAction($current);

                $now = gmdate('c');
                $systemId = (string)$first['id'];
                $current['universe_id'] = $universeId;
                $current['current_system_id'] = $systemId;
                $current['location'] = ['system_id' => $systemId, 'updated_at' => $now];
                $current['exploration'] = ['explored_systems' => [[
                    'system_id' => $systemId,
                    'name' => (string)($first['name'] ?? $systemId),
                    'first_visited_at' => $now,
                    'first_visited_revision' => (int)($current['revision'] ?? 0) + 1,
                ]]];
                $current['revision'] = (int)($current['revision'] ?? 0) + 1;
                if (!is_array($current['lifecycle'] ?? null)) $current['lifecycle'] = ['enabled' => true, 'locked' => false];
                $current['lifecycle']['last_action'] = 'universe_assigned';
                $current['lifecycle']['updated_at'] = $now;
                return $current;
            });

            $character['_assignment_status'] = $status;
            return $character;
        });
    }
}
