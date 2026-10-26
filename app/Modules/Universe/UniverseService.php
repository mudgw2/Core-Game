<?php
declare(strict_types=1);

namespace Core\Modules\Universe;

use Core\Storage\JsonStorage;
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
            'created_at' => gmdate('c'),
            'baseline' => $baseline,
            'state' => [
                'schema_version' => 1,
                'revision' => 0,
                'changes' => [],
            ],
        ];
        return $this->storage->createIfAbsent('universes', $record['id'], $record);
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
