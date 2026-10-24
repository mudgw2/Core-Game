<?php
declare(strict_types=1);

namespace Core\Modules\Characters;

use Core\Storage\JsonStorage;
use Core\Modules\Universe\NavigationGraph;
use RuntimeException;

/** Development-stage character records linked to a persisted universe. */
final class CharacterService
{
    public function __construct(private JsonStorage $storage) {}

    public function create(string $universeId, string $name, string $role = 'Explorer'): array
    {
        $universeId = trim($universeId);
        $name = trim($name);
        $role = trim($role);
        if ($universeId === '') throw new RuntimeException('A universe_id is required.');
        if ($name === '') throw new RuntimeException('Character name cannot be empty.');
        $nameLength = preg_match_all('/./us', $name);
        if ($nameLength === false || $nameLength < 2 || $nameLength > 40) {
            throw new RuntimeException('Character name must be between 2 and 40 characters.');
        }
        $roleLength = preg_match_all('/./us', $role);
        if ($role === '' || $roleLength === false || $roleLength > 40) {
            throw new RuntimeException('Character role must be a non-empty string of at most 40 characters.');
        }
        $universe = $this->storage->read('universes', $universeId);
        if ($universe === null || ($universe['id'] ?? null) !== $universeId) {
            throw new RuntimeException('The selected universe does not exist.');
        }
        $id = 'chr_' . bin2hex(random_bytes(12));
        $firstSystem = $universe['baseline']['systems'][0] ?? null;
        if (!is_array($firstSystem) || !isset($firstSystem['id'])) {
            throw new RuntimeException('The selected universe has no valid starting system.');
        }
        $now = gmdate('c');
        $record = [
            'id' => $id,
            'schema_version' => 1,
            'universe_id' => $universeId,
            'name' => $name,
            'role' => $role,
            'current_system_id' => (string)$firstSystem['id'],
            'location' => [
                'system_id' => (string)$firstSystem['id'],
                'updated_at' => $now,
            ],
            'exploration' => [
                'explored_systems' => [[
                    'system_id' => (string)$firstSystem['id'],
                    'name' => (string)($firstSystem['name'] ?? $firstSystem['id']),
                    'first_visited_at' => $now,
                    'first_visited_revision' => 0,
                ]],
            ],
            'created_at' => $now,
            'revision' => 0,
        ];
        return $this->storage->createIfAbsent('characters', $id, $record);
    }

    /** Move a character within its universe. Repeated same-destination requests are idempotent. */
    public function move(string $universeId, string $characterId, string $systemId, int $expectedRevision): array
    {
        $universeId = trim($universeId);
        $characterId = trim($characterId);
        $systemId = trim($systemId);
        if ($universeId === '' || $characterId === '' || $systemId === '') {
            throw new RuntimeException('universe_id, character_id, and system_id are required.');
        }
        if ($expectedRevision < 0) throw new RuntimeException('Expected character revision must be zero or greater.');

        $universe = $this->storage->read('universes', $universeId);
        if ($universe === null || ($universe['id'] ?? null) !== $universeId) {
            throw new RuntimeException('The selected universe does not exist.');
        }
        $systems = $universe['baseline']['systems'] ?? null;
        if (!is_array($systems)) throw new RuntimeException('The selected universe has no valid system list.');
        $systemsById = [];
        foreach ($systems as $system) {
            if (is_array($system) && isset($system['id'])) $systemsById[(string)$system['id']] = $system;
        }
        $target = $systemsById[$systemId] ?? null;
        if (!is_array($target)) throw new RuntimeException('Target system does not belong to the selected universe.');
        $navigation = NavigationGraph::build($systems);
        $movementStatus = 'moved';
        $result = $this->storage->transact('characters', $characterId, static function (array $character) use ($universeId, $systemId, $expectedRevision, $target, $systemsById, $navigation, &$movementStatus): array {
            if (($character['universe_id'] ?? null) !== $universeId) {
                throw new RuntimeException('Character does not belong to the selected universe.');
            }
            $actualRevision = (int)($character['revision'] ?? 0);
            if ($actualRevision !== $expectedRevision) {
                throw new RuntimeException('Character revision conflict: expected ' . $expectedRevision . ', current revision is ' . $actualRevision . '.');
            }

            $now = gmdate('c');
            $currentSystemId = (string)($character['current_system_id'] ?? $character['location']['system_id'] ?? '');
            $exploration = $character['exploration'] ?? [];
            if (!is_array($exploration)) $exploration = [];
            $visited = $exploration['explored_systems'] ?? [];
            if (!is_array($visited)) $visited = [];

            // Backfill the current location for older character records without exploration data.
            if ($visited === [] && $currentSystemId !== '' && isset($systemsById[$currentSystemId])) {
                $currentSystem = $systemsById[$currentSystemId];
                $visited[] = [
                    'system_id' => $currentSystemId,
                    'name' => (string)($currentSystem['name'] ?? $currentSystemId),
                    'first_visited_at' => (string)($character['location']['updated_at'] ?? $character['created_at'] ?? $now),
                    'first_visited_revision' => $actualRevision,
                ];
            }

            $alreadyVisited = false;
            foreach ($visited as $entry) {
                if (is_array($entry) && ($entry['system_id'] ?? null) === $systemId) { $alreadyVisited = true; break; }
            }
            $alreadyAtDestination = $currentSystemId === $systemId;
            if (!$alreadyAtDestination && !in_array($systemId, $navigation['neighbors'][$currentSystemId] ?? [], true)) {
                throw new RuntimeException('Target system is not directly reachable from the character current system.');
            }
            if (!$alreadyAtDestination) {
                $character['current_system_id'] = $systemId;
                $character['location'] = ['system_id' => $systemId, 'updated_at' => $now];
                $character['revision'] = $actualRevision + 1;
                // Manual movement must not leave an old multi-hop route looking executable.
                $routeProgress = $character['route_progress'] ?? null;
                if (is_array($routeProgress) && ($routeProgress['status'] ?? '') === 'active') {
                    $routeProgress['status'] = 'cancelled';
                    $routeProgress['cancelled_at'] = $now;
                    $routeProgress['cancellation_reason'] = 'manual_movement';
                    $routeProgress['updated_at'] = $now;
                    $character['route_progress'] = $routeProgress;
                }
            }
            if (!$alreadyVisited) {
                $visited[] = [
                    'system_id' => $systemId,
                    'name' => (string)($target['name'] ?? $systemId),
                    'first_visited_at' => $now,
                    'first_visited_revision' => $alreadyAtDestination ? $actualRevision : $actualRevision + 1,
                ];
            }
            $exploration['explored_systems'] = array_values($visited);
            $character['exploration'] = $exploration;
            $movementStatus = $alreadyAtDestination ? 'already_at_destination' : 'moved';
            return $character;
        });

        $result['_movement_status'] = $movementStatus;
        return $result;
    }

    /** Permanently removes one character after exact-name confirmation and revision validation. */
    public function deleteCharacter(string $universeId, string $characterId, string $confirmationName, int $expectedRevision): array
    {
        $universeId = trim($universeId);
        $characterId = trim($characterId);
        $confirmationName = trim($confirmationName);
        if ($universeId === '' || $characterId === '' || $confirmationName === '') {
            throw new RuntimeException('universe_id, character_id, and confirmation_name are required.');
        }
        if ($expectedRevision < 0) throw new RuntimeException('Expected character revision must be zero or greater.');
        $universe = $this->storage->read('universes', $universeId);
        if ($universe === null || ($universe['id'] ?? null) !== $universeId) {
            throw new RuntimeException('The selected universe does not exist.');
        }
        $deleted = $this->storage->delete('characters', $characterId, static function (array $character) use ($universeId, $confirmationName, $expectedRevision): void {
            if (($character['universe_id'] ?? null) !== $universeId) {
                throw new RuntimeException('Character does not belong to the selected universe.');
            }
            if (!hash_equals((string)($character['name'] ?? ''), $confirmationName)) {
                throw new RuntimeException('Confirmation name does not match the character name.');
            }
            $actualRevision = (int)($character['revision'] ?? 0);
            if ($actualRevision !== $expectedRevision) {
                throw new RuntimeException('Character revision conflict: expected ' . $expectedRevision . ', current revision is ' . $actualRevision . '.');
            }
        });
        return ['deleted' => true, 'character_id' => $characterId, 'universe_id' => $universeId, 'name' => (string)($deleted['name'] ?? ''), 'revision' => (int)($deleted['revision'] ?? 0)];
    }

    public function navigationForUniverse(string $universeId): array
    {
        $universeId = trim($universeId);
        if ($universeId === '') throw new RuntimeException('A universe_id is required.');
        $universe = $this->storage->read('universes', $universeId);
        if ($universe === null || ($universe['id'] ?? null) !== $universeId) {
            throw new RuntimeException('The selected universe does not exist.');
        }
        return NavigationGraph::build($universe['baseline']['systems'] ?? []);
    }

    public function listForUniverse(string $universeId): array
    {
        $universeId = trim($universeId);
        if ($universeId === '') throw new RuntimeException('A universe_id is required.');
        $universe = $this->storage->read('universes', $universeId);
        if ($universe === null || ($universe['id'] ?? null) !== $universeId) {
            throw new RuntimeException('The selected universe does not exist.');
        }
        $characters = array_values(array_filter(
            $this->storage->all('characters'),
            static fn(array $character): bool => ($character['universe_id'] ?? null) === $universeId
        ));
        usort($characters, static fn(array $a, array $b): int => strcmp((string)($a['created_at'] ?? ''), (string)($b['created_at'] ?? '')));
        return $characters;
    }
}
