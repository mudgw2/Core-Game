<?php
declare(strict_types=1);

namespace Core\Modules\Universe;

use Core\Storage\JsonStorage;
use RuntimeException;

/** Shared route-planning application service used by HTTP handling and QA. */
final class NavigationRouteService
{
    public function __construct(private JsonStorage $storage) {}

    /** @return array<string,mixed> */
    public function plan(string $universeId, string $fromSystemId, string $toSystemId): array
    {
        $universeId = trim($universeId);
        $fromSystemId = trim($fromSystemId);
        $toSystemId = trim($toSystemId);
        if ($universeId === '' || $fromSystemId === '' || $toSystemId === '') {
            throw new RuntimeException('Provide universe_id, from_system_id, and to_system_id.');
        }

        $universe = $this->storage->read('universes', $universeId);
        if ($universe === null || ($universe['id'] ?? null) !== $universeId) {
            throw new RuntimeException('The selected universe does not exist.');
        }
        $systems = $universe['baseline']['systems'] ?? null;
        if (!is_array($systems)) throw new RuntimeException('The selected universe has no valid system list.');

        // Build both the graph and its labels from the same saved baseline so that
        // a valid route cannot fail because IDs/names came from different sources.
        $navigation = NavigationGraph::build($systems);
        $path = NavigationGraph::shortestPath($navigation, $fromSystemId, $toSystemId);
        $systemNames = [];
        foreach ($systems as $system) {
            if (is_array($system) && isset($system['id'])) {
                $systemNames[(string)$system['id']] = (string)($system['name'] ?? $system['id']);
            }
        }
        $steps = [];
        foreach ($path as $index => $systemId) {
            $steps[] = [
                'step' => $index,
                'system_id' => $systemId,
                'name' => $systemNames[$systemId] ?? $systemId,
            ];
        }

        return [
            'ok' => true,
            'universe_id' => $universeId,
            'from_system_id' => $fromSystemId,
            'to_system_id' => $toSystemId,
            'hops' => max(0, count($path) - 1),
            'path' => $steps,
        ];
    }
}
