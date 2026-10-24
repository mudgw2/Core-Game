<?php
declare(strict_types=1);

namespace Core\Modules\Universe;

use RuntimeException;

/** Deterministic minimum-spanning-tree navigation graph derived from system coordinates. */
final class NavigationGraph
{
    /** Return a deterministic shortest route as ordered system IDs, including both endpoints. */
    public static function shortestPath(array $graph, string $from, string $to): array
    {
        $neighbors = $graph['neighbors'] ?? null;
        if (!is_array($neighbors) || !array_key_exists($from, $neighbors) || !array_key_exists($to, $neighbors)) {
            throw new RuntimeException('Route endpoints must belong to the navigation graph.');
        }
        if ($from === $to) return [$from];

        $queue = [$from];
        $visited = [$from => true];
        $previous = [];
        while ($queue !== []) {
            $current = array_shift($queue);
            $nextSystems = $neighbors[$current] ?? [];
            sort($nextSystems, SORT_STRING);
            foreach ($nextSystems as $next) {
                if (isset($visited[$next])) continue;
                $visited[$next] = true;
                $previous[$next] = $current;
                if ($next === $to) {
                    $path = [$to];
                    $cursor = $to;
                    while ($cursor !== $from) {
                        $cursor = $previous[$cursor];
                        array_unshift($path, $cursor);
                    }
                    return $path;
                }
                $queue[] = $next;
            }
        }
        throw new RuntimeException('No route exists between the selected systems.');
    }

    /** @param array<int,array<string,mixed>> $systems */
    public static function build(array $systems): array
    {
        $valid = [];
        foreach ($systems as $system) {
            if (!is_array($system) || !isset($system['id'], $system['x'], $system['y'])) continue;
            $valid[(string)$system['id']] = [
                'id' => (string)$system['id'],
                'name' => (string)($system['name'] ?? $system['id']),
                'x' => (float)$system['x'],
                'y' => (float)$system['y'],
            ];
        }
        if ($valid === []) throw new RuntimeException('Navigation requires at least one valid system.');
        ksort($valid, SORT_STRING);
        $nodes = array_values($valid);
        $parent = [];
        foreach ($nodes as $node) $parent[$node['id']] = $node['id'];
        $find = static function (string $id) use (&$parent, &$find): string {
            if ($parent[$id] !== $id) $parent[$id] = $find($parent[$id]);
            return $parent[$id];
        };
        $edges = [];
        $n = count($nodes);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $dx = $nodes[$i]['x'] - $nodes[$j]['x'];
                $dy = $nodes[$i]['y'] - $nodes[$j]['y'];
                $edges[] = ['from' => $nodes[$i]['id'], 'to' => $nodes[$j]['id'], 'distance' => sqrt($dx * $dx + $dy * $dy)];
            }
        }
        usort($edges, static function (array $a, array $b): int {
            $distance = $a['distance'] <=> $b['distance'];
            return $distance !== 0 ? $distance : strcmp($a['from'] . ':' . $a['to'], $b['from'] . ':' . $b['to']);
        });
        $routes = [];
        $neighbors = array_fill_keys(array_keys($valid), []);
        foreach ($edges as $edge) {
            $a = $find($edge['from']); $b = $find($edge['to']);
            if ($a === $b) continue;
            $parent[$a] = $b;
            $route = [
                'from' => $edge['from'], 'to' => $edge['to'],
                'distance' => round($edge['distance'], 2),
            ];
            $routes[] = $route;
            $neighbors[$edge['from']][] = $edge['to'];
            $neighbors[$edge['to']][] = $edge['from'];
            if (count($routes) === $n - 1) break;
        }
        foreach ($neighbors as &$ids) sort($ids, SORT_STRING);
        unset($ids);
        return ['algorithm' => 'coordinate_mst_v1', 'routes' => $routes, 'neighbors' => $neighbors];
    }
}
