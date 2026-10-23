<?php
declare(strict_types=1);

namespace Core\Modules\Characters;

use Core\Storage\JsonStorage;
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
        $record = [
            'id' => $id,
            'schema_version' => 1,
            'universe_id' => $universeId,
            'name' => $name,
            'role' => $role,
            'current_system_id' => (string)($universe['baseline']['systems'][0]['id'] ?? ''),
            'location' => [
                'system_id' => (string)($universe['baseline']['systems'][0]['id'] ?? ''),
                'updated_at' => gmdate('c'),
            ],
            'created_at' => gmdate('c'),
            'revision' => 0,
        ];
        return $this->storage->createIfAbsent('characters', $id, $record);
    }

    /** Move a character to a system in its own universe using an optimistic revision check. */
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
        $target = null;
        foreach ($systems as $system) {
            if (is_array($system) && ($system['id'] ?? null) === $systemId) { $target = $system; break; }
        }
        if ($target === null) throw new RuntimeException('Target system does not belong to the selected universe.');

        return $this->storage->transact('characters', $characterId, static function (array $character) use ($universeId, $systemId, $expectedRevision): array {
            if (($character['universe_id'] ?? null) !== $universeId) {
                throw new RuntimeException('Character does not belong to the selected universe.');
            }
            $actualRevision = (int)($character['revision'] ?? 0);
            if ($actualRevision !== $expectedRevision) {
                throw new RuntimeException('Character revision conflict: expected ' . $expectedRevision . ', current revision is ' . $actualRevision . '.');
            }
            $character['current_system_id'] = $systemId;
            $character['location'] = ['system_id' => $systemId, 'updated_at' => gmdate('c')];
            $character['revision'] = $actualRevision + 1;
            return $character;
        });
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
