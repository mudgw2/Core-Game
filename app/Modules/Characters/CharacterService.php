<?php
declare(strict_types=1);

namespace Core\Modules\Characters;

use Core\Modules\Universe\UniverseLifecycleGuard;

use Core\Storage\JsonStorage;
use Core\Modules\Universe\NavigationGraph;
use RuntimeException;

/** Development-stage character records linked to a persisted universe. */
final class CharacterService
{
    public function __construct(private JsonStorage $storage) {}

    public function create(string $universeId, string $name, string $role = 'Explorer', string $speciesId = 'human', array $attributes = [], ?string $ownerAccountId = null): array
    {
        $universeId = trim($universeId);
        $name = trim($name);
        $role = trim($role);
        $optionsPath = CORE_ROOT . '/storage/data/content/character-options.json';
        $options = is_file($optionsPath) ? json_decode((string)file_get_contents($optionsPath), true) : null;
        if (!is_array($options)) throw new RuntimeException('Character creation definitions are missing or invalid.');
        $roles = is_array($options['roles'] ?? null) ? $options['roles'] : [];
        $species = is_array($options['species'] ?? null) ? $options['species'] : [];
        $roleDefinition = null;
        foreach ($roles as $entry) { if (is_array($entry) && (($entry['id'] ?? null) === $role || ($entry['label'] ?? null) === $role)) { $roleDefinition = $entry; break; } }
        if ($roleDefinition === null) throw new RuntimeException('Select a valid character role from the available options.');
        $speciesId = trim($speciesId);
        $speciesDefinition = null;
        foreach ($species as $entry) { if (is_array($entry) && ($entry['id'] ?? null) === $speciesId) { $speciesDefinition = $entry; break; } }
        if ($speciesDefinition === null) throw new RuntimeException('Select a valid character species from the available options.');
        $validatedAttributes = [];
        foreach (($options['attributes'] ?? []) as $definition) {
            if (!is_array($definition) || !is_string($definition['id'] ?? null)) continue;
            $attributeId = $definition['id'];
            $value = $attributes[$attributeId] ?? $definition['default'] ?? 10;
            if (filter_var($value, FILTER_VALIDATE_INT) === false) throw new RuntimeException('Attribute ' . $attributeId . ' must be a whole number.');
            $value = (int)$value;
            $minimum = (int)($definition['minimum'] ?? 1); $maximum = (int)($definition['maximum'] ?? 30);
            if ($value < $minimum || $value > $maximum) throw new RuntimeException('Attribute ' . $attributeId . ' must be between ' . $minimum . ' and ' . $maximum . '.');
            $validatedAttributes[$attributeId] = $value;
        }
        foreach ($attributes as $attributeId => $_) { if (!array_key_exists((string)$attributeId, $validatedAttributes)) throw new RuntimeException('Unknown character attribute: ' . (string)$attributeId . '.'); }
        $role = (string)$roleDefinition['label'];
        if ($name === '') throw new RuntimeException('Character name cannot be empty.');
        $nameLength = preg_match_all('/./us', $name);
        if ($nameLength === false || $nameLength < 2 || $nameLength > 40) {
            throw new RuntimeException('Character name must be between 2 and 40 characters.');
        }
        $roleLength = preg_match_all('/./us', $role);
        if ($role === '' || $roleLength === false || $roleLength > 40) {
            throw new RuntimeException('Character role must be a non-empty string of at most 40 characters.');
        }
        // Unassigned characters are valid persistent identities. They deliberately have no
        // location/exploration state and cannot enter gameplay until safely assigned later.
        if ($universeId === '') {
            $id = 'chr_' . bin2hex(random_bytes(12));
            $now = gmdate('c');
            $record = [
                'id' => $id, 'schema_version' => 1, 'universe_id' => null, 'owner_account_id' => $ownerAccountId,
                'name' => $name, 'role' => (string)$roleDefinition['label'],
                'role_id' => (string)$roleDefinition['id'],
                'species_id' => (string)$speciesDefinition['id'],
                'species' => (string)$speciesDefinition['label'],
                'attributes' => $validatedAttributes,
                'lifecycle' => ['enabled' => true, 'locked' => false, 'updated_at' => $now, 'last_action' => 'created_unassigned'],
                'location' => null, 'current_system_id' => null,
                'created_at' => $now, 'revision' => 0,
            ];
            return $this->storage->createIfAbsent('characters', $id, $record);
        }
        // Character creation shares the universe lock with universe deletion. This closes the race
        // where a new dependent character could appear after deletion enumerates its dependents.
        return $this->storage->withRecordLockOperation('universes', $universeId, function () use ($universeId, $name, $role, $roleDefinition, $speciesDefinition, $validatedAttributes, $ownerAccountId): array {
            $universe = $this->storage->read('universes', $universeId);
            if ($universe === null || ($universe['id'] ?? null) !== $universeId) {
                throw new RuntimeException('The selected universe does not exist.');
            }
            UniverseLifecycleGuard::assertCanPerformGameplayAction($universe);
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
                'owner_account_id' => $ownerAccountId,
                'name' => $name,
                'role' => $role,
                'role_id' => (string)$roleDefinition['id'],
                'species_id' => (string)$speciesDefinition['id'],
                'species' => (string)$speciesDefinition['label'],
                'attributes' => $validatedAttributes,
                'lifecycle' => ['enabled' => true, 'locked' => false, 'updated_at' => $now, 'last_action' => 'created'],
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
        });
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
        UniverseLifecycleGuard::assertCanPerformGameplayAction($universe);
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
            CharacterLifecycleGuard::assertCanPerformGameplayAction($character);
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
        if ($characterId === '' || $confirmationName === '') {
            throw new RuntimeException('character_id and confirmation_name are required.');
        }
        if ($expectedRevision < 0) throw new RuntimeException('Expected character revision must be zero or greater.');
        if ($universeId !== '') {
            $universe = $this->storage->read('universes', $universeId);
            if ($universe === null || ($universe['id'] ?? null) !== $universeId) throw new RuntimeException('The selected universe does not exist.');
        }
        $deleted = $this->storage->delete('characters', $characterId, static function (array $character) use ($universeId, $confirmationName, $expectedRevision): void {
            $actualUniverseId = (string)($character['universe_id'] ?? '');
            if ($actualUniverseId !== $universeId) throw new RuntimeException($actualUniverseId === '' ? 'Character is unassigned; leave universe_id empty.' : 'Character does not belong to the selected universe.');
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
        $universe = $this->storage->read('universes', $universeId);
        if ($universe === null || ($universe['id'] ?? null) !== $universeId) {
            throw new RuntimeException('The selected universe does not exist.');
        }
        return NavigationGraph::build($universe['baseline']['systems'] ?? []);
    }

    /** Return the complete persisted character record, including inventory, exploration, scans, and ship fittings. */
    public function getById(string $characterId): array
    {
        $characterId = trim($characterId);
        if ($characterId === '') throw new RuntimeException('A character_id is required.');
        $character = $this->storage->read('characters', $characterId);
        if ($character === null || ($character['id'] ?? null) !== $characterId) throw new RuntimeException('The selected character does not exist.');
        return $character;
    }

    public function listForUniverse(string $universeId): array
    {
        $universeId = trim($universeId);
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
