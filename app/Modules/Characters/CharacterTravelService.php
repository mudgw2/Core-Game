<?php
declare(strict_types=1);

namespace Core\Modules\Characters;

use Core\Modules\Universe\UniverseLifecycleGuard;

use Core\Modules\Universe\NavigationGraph;
use Core\Modules\Universe\NavigationRouteService;
use Core\Storage\JsonStorage;
use RuntimeException;

/** Executes a planned route one connected jump at a time. */
final class CharacterTravelService
{
    private NavigationRouteService $routes;

    public function __construct(private JsonStorage $storage)
    {
        $this->routes = new NavigationRouteService($storage);
    }

    /** @return array<string,mixed> */
    public function start(string $universeId, string $characterId, string $destinationSystemId, int $expectedRevision): array
    {
        $universeId = trim($universeId);
        $characterId = trim($characterId);
        $destinationSystemId = trim($destinationSystemId);
        if ($universeId === '' || $characterId === '' || $destinationSystemId === '') {
            throw new RuntimeException('universe_id, character_id, and destination_system_id are required.');
        }
        if ($expectedRevision < 0) throw new RuntimeException('Expected character revision must be zero or greater.');

        $universe = $this->storage->read('universes', $universeId);
        if ($universe === null || ($universe['id'] ?? null) !== $universeId) throw new RuntimeException('The selected universe does not exist.');
        UniverseLifecycleGuard::assertCanPerformGameplayAction($universe);
        $systems = $universe['baseline']['systems'] ?? null;
        if (!is_array($systems)) throw new RuntimeException('The selected universe has no valid system list.');
        $systemNames = [];
        foreach ($systems as $system) {
            if (is_array($system) && isset($system['id'])) $systemNames[(string)$system['id']] = (string)($system['name'] ?? $system['id']);
        }

        // Read the character first to determine the route origin. The locked transaction
        // checks both revision and origin again to reject races between planning and commit.
        $before = $this->storage->read('characters', $characterId);
        if ($before === null) throw new RuntimeException('The selected character does not exist.');
        if (($before['universe_id'] ?? null) !== $universeId) throw new RuntimeException('Character does not belong to the selected universe.');
        if ((int)($before['revision'] ?? 0) !== $expectedRevision) {
            throw new RuntimeException('Character revision conflict: expected ' . $expectedRevision . ', current revision is ' . (int)($before['revision'] ?? 0) . '.');
        }
        $fromSystemId = (string)($before['current_system_id'] ?? $before['location']['system_id'] ?? '');
        $route = $this->routes->plan($universeId, $fromSystemId, $destinationSystemId);
        $path = array_values(array_map(static fn(array $step): string => (string)$step['system_id'], $route['path']));
        if ($path === [] || $path[0] !== $fromSystemId || end($path) !== $destinationSystemId) {
            throw new RuntimeException('Route planner returned an invalid path.');
        }

        $result = $this->storage->transact('characters', $characterId, static function (array $character) use ($universeId, $expectedRevision, $fromSystemId, $destinationSystemId, $path, $systemNames): array {
            if (($character['universe_id'] ?? null) !== $universeId) throw new RuntimeException('Character does not belong to the selected universe.');
            CharacterLifecycleGuard::assertCanPerformGameplayAction($character);
            $actualRevision = (int)($character['revision'] ?? 0);
            if ($actualRevision !== $expectedRevision) throw new RuntimeException('Character revision conflict: expected ' . $expectedRevision . ', current revision is ' . $actualRevision . '.');
            $current = (string)($character['current_system_id'] ?? $character['location']['system_id'] ?? '');
            if ($current !== $fromSystemId) throw new RuntimeException('Character location changed while the route was being planned; plan again.');

            $now = gmdate('c');
            $character['revision'] = $actualRevision + 1;
            $character['route_progress'] = [
                'route_id' => 'rte_' . bin2hex(random_bytes(10)),
                'destination_system_id' => $destinationSystemId,
                'destination_name' => $systemNames[$destinationSystemId] ?? $destinationSystemId,
                'path_system_ids' => $path,
                'current_step_index' => 0,
                'total_hops' => max(0, count($path) - 1),
                'status' => count($path) <= 1 ? 'completed' : 'active',
                'planned_from_system_id' => $fromSystemId,
                'planned_at' => $now,
                'updated_at' => $now,
            ];
            return $character;
        });

        return ['travel_status' => count($path) <= 1 ? 'already_at_destination' : 'route_started', 'character' => $result];
    }

    /** @return array<string,mixed> */
    public function advance(string $universeId, string $characterId, int $expectedRevision): array
    {
        $universeId = trim($universeId);
        $characterId = trim($characterId);
        if ($universeId === '' || $characterId === '') throw new RuntimeException('universe_id and character_id are required.');
        if ($expectedRevision < 0) throw new RuntimeException('Expected character revision must be zero or greater.');
        $universe = $this->storage->read('universes', $universeId);
        if ($universe === null || ($universe['id'] ?? null) !== $universeId) throw new RuntimeException('The selected universe does not exist.');
        $systems = $universe['baseline']['systems'] ?? null;
        if (!is_array($systems)) throw new RuntimeException('The selected universe has no valid system list.');
        $systemsById = [];
        foreach ($systems as $system) if (is_array($system) && isset($system['id'])) $systemsById[(string)$system['id']] = $system;
        $graph = NavigationGraph::build($systems);
        $travelStatus = 'jump_completed';

        $character = $this->storage->transact('characters', $characterId, static function (array $character) use ($universeId, $expectedRevision, $systemsById, $graph, &$travelStatus): array {
            if (($character['universe_id'] ?? null) !== $universeId) throw new RuntimeException('Character does not belong to the selected universe.');
            CharacterLifecycleGuard::assertCanPerformGameplayAction($character);
            $actualRevision = (int)($character['revision'] ?? 0);
            if ($actualRevision !== $expectedRevision) throw new RuntimeException('Character revision conflict: expected ' . $expectedRevision . ', current revision is ' . $actualRevision . '.');
            $route = $character['route_progress'] ?? null;
            if (!is_array($route)) throw new RuntimeException('No travel route has been started for this character.');
            if (($route['status'] ?? '') !== 'active') throw new RuntimeException('Travel route is not active; plan a new route before continuing.');
            $path = $route['path_system_ids'] ?? null;
            if (!is_array($path) || count($path) < 2) throw new RuntimeException('Saved travel route is invalid; plan a new route.');
            $index = $route['current_step_index'] ?? null;
            if (!is_int($index) || $index < 0 || $index >= count($path) - 1) throw new RuntimeException('Saved travel route progress is invalid; plan a new route.');
            $current = (string)($character['current_system_id'] ?? $character['location']['system_id'] ?? '');
            if ($current !== (string)$path[$index]) throw new RuntimeException('Character location no longer matches the planned route; plan a new route.');
            $nextId = (string)$path[$index + 1];
            if (!isset($systemsById[$current], $systemsById[$nextId]) || !in_array($nextId, $graph['neighbors'][$current] ?? [], true)) {
                throw new RuntimeException('Saved route contains an invalid connection; plan a new route.');
            }

            $now = gmdate('c');
            $character['current_system_id'] = $nextId;
            $character['location'] = ['system_id' => $nextId, 'updated_at' => $now];
            $character['revision'] = $actualRevision + 1;
            $exploration = $character['exploration'] ?? [];
            if (!is_array($exploration)) $exploration = [];
            $visited = $exploration['explored_systems'] ?? [];
            if (!is_array($visited)) $visited = [];
            $alreadyVisited = false;
            foreach ($visited as $entry) if (is_array($entry) && ($entry['system_id'] ?? null) === $nextId) { $alreadyVisited = true; break; }
            if (!$alreadyVisited) {
                $visited[] = [
                    'system_id' => $nextId,
                    'name' => (string)($systemsById[$nextId]['name'] ?? $nextId),
                    'first_visited_at' => $now,
                    'first_visited_revision' => $actualRevision + 1,
                ];
            }
            $exploration['explored_systems'] = array_values($visited);
            $character['exploration'] = $exploration;
            $route['current_step_index'] = $index + 1;
            $route['updated_at'] = $now;
            if ($route['current_step_index'] >= count($path) - 1) {
                $route['status'] = 'completed';
                $route['completed_at'] = $now;
                $travelStatus = 'route_completed';
            }
            $character['route_progress'] = $route;
            return $character;
        });

        return ['travel_status' => $travelStatus, 'character' => $character];
    }
}
