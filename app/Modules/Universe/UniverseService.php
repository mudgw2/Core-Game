<?php
declare(strict_types=1);

namespace Core\Modules\Universe;

use Core\Storage\JsonStorage;
use Core\Modules\Characters\CharacterUniverseContext;
use RuntimeException;

final class UniverseService
{
    private UniverseGenerator $generator;
    private UniverseEventProcessor $eventProcessor;

    public function __construct(private JsonStorage $storage)
    {
        $this->generator = new UniverseGenerator();
        $this->eventProcessor = new UniverseEventProcessor();
    }

    public function preview(string $seed, array $settings = []): array
    {
        return $this->generator->generate($seed, $settings);
    }

    public function createOrLoad(string $seed, array $settings = []): array
    {
        $baseline = $this->generator->generate($seed, $settings);
        $record = [
            'id' => $baseline['id'],
            'seed' => trim($seed),
            'settings' => $baseline['settings'],
            'seed_fingerprint' => $baseline['seed_fingerprint'],
            'generator_version' => $baseline['generator_version'],
            'hierarchy_version' => (int)($baseline['hierarchy_version'] ?? 0),
            'created_at' => gmdate('c'),
            'baseline' => $baseline,
            'state' => [
                'schema_version' => 1,
                'revision' => 0,
                'changes' => [],
            ],
        ];
        $saved = $this->storage->createIfAbsent('universes', $record['id'], $record);
        return $this->ensureCurrentHierarchy((string)$saved['id']);
    }

    /**
     * Rebuilds obsolete generated baselines in place while preserving the stable
     * Universe identity and persistent universe state. Generated records are
     * disposable during this development phase; character records are retained
     * and their active/home location snapshots are moved to the new first System.
     */
    public function ensureCurrentHierarchy(string $universeId): array
    {
        $universeId = trim($universeId);
        if ($universeId === '') throw new RuntimeException('Universe ID is required for hierarchy validation.');
        $existing = $this->storage->read('universes', $universeId);
        if (!is_array($existing) || (string)($existing['id'] ?? '') !== $universeId) {
            throw new RuntimeException('The selected universe does not exist.');
        }
        if ($this->hasCurrentHierarchy($existing['baseline'] ?? null)) return $existing;

        $seed = trim((string)($existing['seed'] ?? $existing['baseline']['seed'] ?? ''));
        if ($seed === '') throw new RuntimeException('The existing universe has no seed and cannot be regenerated.');
        $settings = is_array($existing['settings'] ?? null) ? $existing['settings'] : [];
        $rebuilt = $this->generator->generate($seed);
        if ((string)$rebuilt['id'] !== $universeId) $rebuilt = $this->generator->generate($seed, $settings);
        if ((string)$rebuilt['id'] !== $universeId) {
            throw new RuntimeException('The universe seed/settings no longer reproduce its stable identity; automatic hierarchy rebuild was stopped.');
        }

        // Reconcile character locations before marking the baseline current. If
        // the process stops midway, the next request sees the old baseline and
        // safely retries the idempotent location updates.
        $firstSystem = $rebuilt['systems'][0] ?? null;
        if (is_array($firstSystem) && isset($firstSystem['id'])) {
            $constellationId = (string)($firstSystem['constellation_id'] ?? '');
            $systemId = (string)$firstSystem['id'];
            foreach ($this->storage->all('characters') as $characterRecord) {
                if (!is_array($characterRecord) || empty($characterRecord['id'])) continue;
                $characterId = (string)$characterRecord['id'];
                $homeUniverseId = (string)($characterRecord['universe_id'] ?? '');
                $activeUniverseId = CharacterUniverseContext::activeUniverseId($characterRecord);
                if ($homeUniverseId !== $universeId && $activeUniverseId !== $universeId) continue;
                $this->storage->transact('characters', $characterId, static function (array $character) use ($universeId, $constellationId, $systemId): array {
                    $now = gmdate('c');
                    $homeId = (string)($character['universe_id'] ?? '');
                    $activeId = CharacterUniverseContext::activeUniverseId($character);
                    $changed = false;
                    if ($activeId === $universeId) {
                        $location = is_array($character['location'] ?? null) ? $character['location'] : [];
                        if ((string)($character['current_system_id'] ?? '') !== $systemId
                            || (string)($character['current_constellation_id'] ?? '') !== $constellationId
                            || (string)($location['system_id'] ?? '') !== $systemId
                            || (string)($location['constellation_id'] ?? '') !== $constellationId
                            || (string)($location['universe_id'] ?? '') !== $universeId
                            || is_array($character['route_progress'] ?? null)) {
                            $character['current_system_id'] = $systemId;
                            $character['current_constellation_id'] = $constellationId;
                            $character['location'] = ['constellation_id' => $constellationId, 'system_id' => $systemId, 'universe_id' => $universeId, 'updated_at' => $now];
                            $character['route_progress'] = null;
                            $changed = true;
                        }
                    } elseif ($homeId === $universeId) {
                        if (!is_array($character['home_location_snapshot'] ?? null)) $character['home_location_snapshot'] = [];
                        $snapshot = $character['home_location_snapshot'];
                        $location = is_array($snapshot['location'] ?? null) ? $snapshot['location'] : [];
                        if ((string)($snapshot['current_system_id'] ?? '') !== $systemId
                            || (string)($snapshot['current_constellation_id'] ?? '') !== $constellationId
                            || (string)($location['system_id'] ?? '') !== $systemId
                            || (string)($location['constellation_id'] ?? '') !== $constellationId
                            || (string)($location['universe_id'] ?? '') !== $universeId
                            || is_array($snapshot['route_progress'] ?? null)) {
                            $character['home_location_snapshot']['current_system_id'] = $systemId;
                            $character['home_location_snapshot']['current_constellation_id'] = $constellationId;
                            $character['home_location_snapshot']['location'] = ['constellation_id' => $constellationId, 'system_id' => $systemId, 'universe_id' => $universeId, 'updated_at' => $now];
                            $character['home_location_snapshot']['route_progress'] = null;
                            $changed = true;
                        }
                    }
                    if ($changed) $character['revision'] = (int)($character['revision'] ?? 0) + 1;
                    return $character;
                });
            }
        }

        $updated = $this->storage->transact('universes', $universeId, function (array $record) use ($rebuilt): array {
            if ($this->hasCurrentHierarchy($record['baseline'] ?? null)) return $record;
            $record['baseline'] = $rebuilt;
            $record['generator_version'] = (int)$rebuilt['generator_version'];
            $record['hierarchy_version'] = (int)$rebuilt['hierarchy_version'];
            $record['settings'] = $rebuilt['settings'];
            $record['seed_fingerprint'] = $rebuilt['seed_fingerprint'];
            $record['hierarchy_rebuilt_at'] = gmdate('c');
            return $record;
        });
        return $updated;
    }

    private function hasCurrentHierarchy(mixed $baseline): bool
    {
        if (!is_array($baseline) || (int)($baseline['hierarchy_version'] ?? 0) < 1) return false;
        $constellations = $baseline['constellations'] ?? null;
        $systems = $baseline['systems'] ?? null;
        if (!is_array($constellations) || $constellations === [] || !is_array($systems) || $systems === []) return false;
        $flatIds = [];
        $flatById = [];
        foreach ($systems as $system) {
            if (!is_array($system) || trim((string)($system['id'] ?? '')) === '' || trim((string)($system['constellation_id'] ?? '')) === '' || trim((string)($system['constellation_seed'] ?? '')) === '' || trim((string)($system['seed'] ?? '')) === '') return false;
            $flatIds[(string)$system['id']] = true;
            $flatById[(string)$system['id']] = $system;
        }
        $nestedIds = [];
        $nestedSystemsById = [];
        $nestedCount = 0;
        foreach ($constellations as $constellation) {
            if (!is_array($constellation) || trim((string)($constellation['id'] ?? '')) === '' || trim((string)($constellation['seed'] ?? '')) === '' || !is_array($constellation['systems'] ?? null) || $constellation['systems'] === []) return false;
            foreach ($constellation['systems'] as $system) {
                if (!is_array($system) || trim((string)($system['id'] ?? '')) === '' || (string)($system['constellation_id'] ?? '') !== (string)$constellation['id'] || (string)($system['constellation_seed'] ?? '') !== (string)$constellation['seed']) return false;
                $nestedIds[(string)$system['id']] = true;
                $nestedSystemsById[(string)$system['id']] = $system;
                $nestedCount++;
            }
        }
        if ($nestedCount !== count($nestedIds) || count($flatIds) !== count($nestedIds) || array_diff_key($flatIds, $nestedIds) !== []) return false;
        foreach ($flatById as $systemId => $flatSystem) {
            $nestedSystem = $nestedSystemsById[$systemId] ?? null;
            if (!is_array($nestedSystem)
                || (string)$flatSystem['constellation_id'] !== (string)$nestedSystem['constellation_id']
                || (string)$flatSystem['constellation_seed'] !== (string)$nestedSystem['constellation_seed']
                || (string)$flatSystem['seed'] !== (string)$nestedSystem['seed']) return false;
        }
        return true;
    }

    /** Applies one universe-state change only when the caller's revision is current. */
    public function updateState(string $universeId, int $expectedRevision, array $change): array
    {
        if ($expectedRevision < 0) throw new RuntimeException('Expected revision must be zero or greater.');
        if ($change === []) throw new RuntimeException('Universe change cannot be empty.');
        // Validate and normalize the formal event envelope before acquiring the record lock.
        // The revision and timestamp are finalized inside the transaction below.
        if (!isset($change['type']) || !is_string($change['type']) || trim($change['type']) === '') {
            throw new RuntimeException('Universe change must include a non-empty string type.');
        }
        try {
            $encodedChange = json_encode($change, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('Universe change must contain valid JSON data.', 0, $e);
        }
        if (strlen($encodedChange) > 16384) throw new RuntimeException('Universe change exceeds the 16 KB limit.');

        $eventProcessor = $this->eventProcessor;
        return $this->storage->transact('universes', $universeId, static function (array $record) use ($expectedRevision, $change, $eventProcessor): array {
            UniverseLifecycleGuard::assertCanPerformGameplayAction($record);
            $state = $record['state'] ?? null;
            if (!is_array($state) || !isset($state['revision']) || !is_array($state['changes'] ?? null)) {
                throw new RuntimeException('Universe state structure is invalid.');
            }
            $actualRevision = (int)$state['revision'];
            if ($actualRevision !== $expectedRevision) {
                throw new RuntimeException('Revision conflict: expected ' . $expectedRevision . ', current revision is ' . $actualRevision . '.');
            }
            $event = UniverseEvent::fromChange($change, $actualRevision + 1);
            // Validate/apply event effects before committing history and revision. A handler failure
            // aborts the storage transaction, leaving the prior universe state unchanged.
            $baseline = is_array($record['baseline'] ?? null) ? $record['baseline'] : [];
            $state = $eventProcessor->process($event, $state, $baseline);
            $state['changes'][] = $event;
            $state['revision'] = $actualRevision + 1;
            $record['state'] = $state;
            return $record;
        });
    }
}
