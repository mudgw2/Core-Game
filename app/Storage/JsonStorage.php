<?php
declare(strict_types=1);

namespace Core\Storage;

use RuntimeException;

final class JsonStorage
{
    public function __construct(private string $root)
    {
        if (!is_dir($root) && !mkdir($root, 0770, true) && !is_dir($root)) {
            throw new RuntimeException('Unable to create storage directory: ' . $root);
        }
    }

    public function read(string $collection, string $id): ?array
    {
        $path = $this->path($collection, $id);
        if (!is_file($path)) return null;

        $raw = file_get_contents($path);
        if ($raw === false) throw new RuntimeException('Unable to read stored JSON record.');
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) throw new RuntimeException('Stored JSON record is invalid: ' . basename($path));
        return $decoded;
    }

    public function write(string $collection, string $id, array $record): void
    {
        $path = $this->path($collection, $id);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create collection directory.');
        }

        $json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $temporary = tempnam($directory, '.core-');
        if ($temporary === false) throw new RuntimeException('Unable to create temporary storage file.');

        try {
            if (file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false) {
                throw new RuntimeException('Unable to write temporary storage file.');
            }
            if (!rename($temporary, $path)) throw new RuntimeException('Unable to atomically replace storage file.');
        } finally {
            if (is_file($temporary)) @unlink($temporary);
        }
    }

    private function path(string $collection, string $id): string
    {
        foreach ([$collection, $id] as $segment) {
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $segment)) {
                throw new RuntimeException('Invalid storage path segment.');
            }
        }
        return $this->root . '/' . $collection . '/' . $id . '.json';
    }
}
