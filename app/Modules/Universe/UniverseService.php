<?php
declare(strict_types=1);

namespace Core\Modules\Universe;

use Core\Storage\JsonStorage;

final class UniverseService
{
    private UniverseGenerator $generator;

    public function __construct(private JsonStorage $storage)
    {
        $this->generator = new UniverseGenerator();
    }

    public function preview(string $seed): array
    {
        return $this->generator->generate($seed);
    }

    public function createOrLoad(string $seed): array
    {
        $baseline = $this->generator->generate($seed);
        $existing = $this->storage->read('universes', $baseline['id']);
        if ($existing !== null) return $existing;

        $record = [
            'id' => $baseline['id'],
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
        $this->storage->write('universes', $record['id'], $record);
        return $record;
    }
}
