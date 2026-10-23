<?php
declare(strict_types=1);

namespace Core\Storage;

use Core\Core\Logger;
use RuntimeException;
use Throwable;

final class JsonStorage
{
    public function __construct(private string $root)
    {
        if (!is_dir($root) && !@mkdir($root, 0770, true) && !is_dir($root)) {
            Logger::error('Storage root could not be created', ['path' => $root]);
            throw new RuntimeException('Unable to create storage directory.');
        }
    }

    public function read(string $collection, string $id): ?array
    {
        $path = $this->path($collection, $id);
        if (!is_file($path)) return null;

        $raw = @file_get_contents($path);
        if ($raw === false) {
            Logger::error('Stored JSON record could not be read', ['collection' => $collection, 'record_id' => $id]);
            throw new RuntimeException('Unable to read stored JSON record.');
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            Logger::error('Stored JSON record is invalid', ['collection' => $collection, 'record_id' => $id, 'error' => $e->getMessage()]);
            throw new RuntimeException('Stored JSON record is invalid: ' . basename($path), 0, $e);
        }
        if (!is_array($decoded)) {
            Logger::error('Stored JSON record has an unexpected structure', ['collection' => $collection, 'record_id' => $id]);
            throw new RuntimeException('Stored JSON record is invalid: ' . basename($path));
        }
        return $decoded;
    }

    public function write(string $collection, string $id, array $record): void
    {
        $path = $this->path($collection, $id);
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) {
            Logger::error('Storage collection directory could not be created', ['collection' => $collection]);
            throw new RuntimeException('Unable to create collection directory.');
        }

        $lockPath = $path . '.lock';
        $lock = @fopen($lockPath, 'c');
        if ($lock === false) {
            Logger::error('Storage lock file could not be opened', ['collection' => $collection, 'record_id' => $id]);
            throw new RuntimeException('Unable to open storage lock file.');
        }
        $temporary = null;
        try {
            if (!flock($lock, LOCK_EX)) {
                Logger::error('Storage record lock could not be acquired', ['collection' => $collection, 'record_id' => $id]);
                throw new RuntimeException('Unable to lock stored JSON record.');
            }
            $json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
            $temporary = @tempnam($directory, '.core-');
            if ($temporary === false) {
                Logger::error('Temporary storage file could not be created', ['collection' => $collection, 'record_id' => $id]);
                throw new RuntimeException('Unable to create temporary storage file.');
            }
            $handle = @fopen($temporary, 'wb');
            if ($handle === false) {
                Logger::error('Temporary storage file could not be opened', ['collection' => $collection, 'record_id' => $id]);
                throw new RuntimeException('Unable to open temporary storage file.');
            }
            try {
                $length = strlen($json);
                $offset = 0;
                while ($offset < $length) {
                    $written = fwrite($handle, substr($json, $offset));
                    if ($written === false || $written === 0) {
                        Logger::error('Temporary storage write was incomplete', ['collection' => $collection, 'record_id' => $id, 'bytes_written' => $offset, 'bytes_expected' => $length]);
                        throw new RuntimeException('Unable to write complete temporary storage file.');
                    }
                    $offset += $written;
                }
                if (!fflush($handle)) throw new RuntimeException('Unable to flush temporary storage file.');
                if (function_exists('fsync') && !@fsync($handle)) throw new RuntimeException('Unable to synchronize temporary storage file.');
            } finally {
                fclose($handle);
            }
            @chmod($temporary, 0660);
            if (!@rename($temporary, $path)) {
                Logger::error('Atomic storage replacement failed', ['collection' => $collection, 'record_id' => $id]);
                throw new RuntimeException('Unable to atomically replace storage file.');
            }
            $temporary = null;
        } catch (Throwable $e) {
            if (!$e instanceof RuntimeException && !$e instanceof \JsonException) {
                Logger::error('Unexpected storage write failure', ['collection' => $collection, 'record_id' => $id, 'exception' => $e::class, 'message' => $e->getMessage()]);
            }
            throw $e;
        } finally {
            if (is_string($temporary) && is_file($temporary)) @unlink($temporary);
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function path(string $collection, string $id): string
    {
        foreach ([$collection, $id] as $segment) {
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $segment)) {
                Logger::warning('Unsafe storage path segment rejected', ['segment_type' => $segment === $collection ? 'collection' : 'record_id']);
                throw new RuntimeException('Invalid storage path segment.');
            }
        }
        return $this->root . '/' . $collection . '/' . $id . '.json';
    }
}
