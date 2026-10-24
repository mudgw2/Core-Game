<?php
declare(strict_types=1);

namespace Core\Modules;

use Core\Storage\JsonStorage;
use RuntimeException;

/** Read-only inventory and deletion-impact previews. Never traverses or mutates storage files directly. */
final class DataManagementService
{
    public function __construct(private JsonStorage $storage) {}

    public function inventory(): array
    {
        $universes = $this->storage->all('universes');
        $characters = $this->storage->all('characters');
        $charactersByUniverse = [];
        foreach ($characters as $character) {
            $universeId = (string)($character['universe_id'] ?? '');
            $charactersByUniverse[$universeId][] = $character;
        }
        $universeRows = [];
        foreach ($universes as $universe) {
            $id = (string)($universe['id'] ?? '');
            $related = $charactersByUniverse[$id] ?? [];
            $universeRows[] = [
                'id' => $id,
                'name' => (string)($universe['name'] ?? $universe['seed'] ?? $id),
                'seed' => (string)($universe['seed'] ?? ''),
                'systems' => count($universe['baseline']['systems'] ?? []),
                'events' => count($universe['state']['changes'] ?? []),
                'characters' => count($related),
                'lifecycle' => $universe['lifecycle'] ?? ['enabled' => true, 'locked' => false, 'revision' => 0],
                'created_at' => (string)($universe['created_at'] ?? ''),
            ];
        }
        $characterRows = [];
        foreach ($characters as $character) {
            $scanRecords = $character['scans']['systems'] ?? [];
            $discoveryCount = 0;
            foreach ($scanRecords as $scan) $discoveryCount += count($scan['discoveries'] ?? []);
            $inventory = $character['inventory']['resources'] ?? [];
            $characterRows[] = [
                'id' => (string)($character['id'] ?? ''),
                'name' => (string)($character['name'] ?? 'Unnamed character'),
                'universe_id' => (string)($character['universe_id'] ?? ''),
                'revision' => (int)($character['revision'] ?? 0),
                'lifecycle' => $character['lifecycle'] ?? ['enabled' => true, 'locked' => false],
                'scanned_systems' => count($scanRecords),
                'discoveries' => $discoveryCount,
                'inventory_stacks' => is_array($inventory) ? count($inventory) : 0,
                'fitted_modules' => count($character['ship']['modules'] ?? []),
                'created_at' => (string)($character['created_at'] ?? ''),
            ];
        }
        usort($universeRows, static fn(array $a, array $b): int => strcmp($a['id'], $b['id']));
        usort($characterRows, static fn(array $a, array $b): int => strcmp($a['id'], $b['id']));
        return [
            'summary' => ['universes' => count($universeRows), 'characters' => count($characterRows), 'systems' => array_sum(array_column($universeRows, 'systems')), 'events' => array_sum(array_column($universeRows, 'events'))],
            'universes' => $universeRows,
            'characters' => $characterRows,
            'generated_at' => gmdate('c'),
        ];
    }

    public function preview(string $scope, string $id): array
    {
        $scope = trim($scope); $id = trim($id);
        if ($id === '') throw new RuntimeException('A record ID is required for a cleanup preview.');
        if ($scope === 'character') {
            $character = $this->storage->read('characters', $id);
            if ($character === null || ($character['id'] ?? null) !== $id) throw new RuntimeException('Character does not exist.');
            $inventory = $character['inventory']['resources'] ?? [];
            $scanRecords = $character['scans']['systems'] ?? [];
            $discoveries = 0; foreach ($scanRecords as $scan) $discoveries += count($scan['discoveries'] ?? []);
            return [
                'scope' => 'character', 'id' => $id, 'name' => (string)($character['name'] ?? 'Unnamed character'),
                'action' => 'permanent_character_deletion', 'executable' => true,
                'impact' => [
                    'character_records' => 1, 'inventory_stacks' => is_array($inventory) ? count($inventory) : 0,
                    'scan_records' => count($scanRecords), 'discoveries' => $discoveries,
                    'fitted_modules' => count($character['ship']['modules'] ?? []),
                ],
                'warning' => 'This permanently removes the character JSON record and all character data embedded in it. This action cannot be undone.',
            ];
        }
        if ($scope === 'universe') {
            $universe = $this->storage->read('universes', $id);
            if ($universe === null || ($universe['id'] ?? null) !== $id) throw new RuntimeException('Universe does not exist.');
            $characters = array_values(array_filter($this->storage->all('characters'), static fn(array $c): bool => ($c['universe_id'] ?? null) === $id));
            return [
                'scope' => 'universe', 'id' => $id, 'name' => (string)($universe['name'] ?? $universe['seed'] ?? $id),
                'action' => 'universe_and_related_character_deletion', 'executable' => false,
                'impact' => ['universe_records' => 1, 'systems' => count($universe['baseline']['systems'] ?? []), 'events' => count($universe['state']['changes'] ?? []), 'related_character_records' => count($characters)],
                'warning' => 'Preview only in this release. Universe deletion is intentionally not executable until cascading deletion, backup/restore, and concurrency guarantees are implemented. No data was changed.',
            ];
        }
        throw new RuntimeException('Scope must be character or universe.');
    }
}
