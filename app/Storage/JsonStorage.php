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

    /** Preflight an existing record lock before a destructive multi-record operation.
     * Lock files need to be lockable, not writable: flock() works with a readable,
     * read-only lock file on supported Unix filesystems. This allows CORE to safely
     * use legacy lock files created by another local account without unlinking them.
     */
    public function assertRecordLockUsable(string $collection, string $id): void
    {
        $recordPath = $this->path($collection, $id);
        $lockPath = $recordPath . '.lock';
        if (!file_exists($lockPath)) return;

        $lock = $this->openLockFile($lockPath);
        if ($lock === false) {
            $this->logLockOpenFailure($collection, $id, $lockPath);
            throw new RuntimeException('Existing storage lock file cannot be opened for locking by PHP: ' . $lockPath . '. No destructive changes were made. CORE can use a readable read-only lock file, but this file is not accessible to the web-server process.');
        }
        try {
            if (!@flock($lock, LOCK_EX | LOCK_NB)) {
                Logger::warning('Storage lock is currently held during destructive-operation preflight', ['collection' => $collection, 'record_id' => $id, 'lock_path' => $lockPath]);
                throw new RuntimeException('Storage record is currently in use and could not be locked for deletion: ' . $id . '. No destructive changes were made. Wait for the active operation to finish and generate a fresh preview.');
            }
            @flock($lock, LOCK_UN);
        } finally {
            fclose($lock);
        }
    }

    public function assertCollectionWritable(string $collection): void
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $collection)) {
            Logger::warning('Unsafe storage collection rejected', ['collection' => $collection]);
            throw new RuntimeException('Invalid storage path segment.');
        }
        $directory = $this->root . '/' . $collection;
        if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) {
            $lastError = error_get_last();
            Logger::error('Storage collection directory is unavailable for mutation', [
                'collection' => $collection, 'path' => $directory, 'directory_exists' => false,
                'directory_writable' => false, 'last_php_error' => $lastError['message'] ?? null,
            ]);
            throw new RuntimeException('Storage collection directory is unavailable for mutation: ' . $directory);
        }
        if (!is_writable($directory)) {
            Logger::error('Storage collection directory is not writable by PHP', [
                'collection' => $collection, 'path' => $directory, 'directory_exists' => true,
                'directory_writable' => false,
            ]);
            throw new RuntimeException('Storage collection directory is not writable by PHP: ' . $directory . '. No destructive changes were made. Check the web-server user ownership and permissions.');
        }
    }

    public function read(string $collection, string $id): ?array
    {
        $path = $this->path($collection, $id);
        if (!is_file($path)) return null;
        return $this->decodeFile($path, $collection, $id);
    }

    public function write(string $collection, string $id, array $record): void
    {
        $path = $this->path($collection, $id);
        $this->withRecordLock($path, $collection, $id, function () use ($path, $collection, $id, $record): void {
            $this->atomicWrite($path, $collection, $id, $record);
        });
    }

    /** Returns decoded records from a validated collection, ignoring lock files. */
    public function all(string $collection): array
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $collection)) {
            Logger::warning('Unsafe storage collection rejected', ['collection' => $collection]);
            throw new RuntimeException('Invalid storage path segment.');
        }
        $directory = $this->root . '/' . $collection;
        if (!is_dir($directory)) return [];
        $records = [];
        $files = @glob($directory . '/*.json');
        if ($files === false) {
            Logger::error('Storage collection could not be listed', ['collection' => $collection]);
            throw new RuntimeException('Unable to list storage collection.');
        }
        sort($files, SORT_STRING);
        foreach ($files as $file) {
            $id = basename($file, '.json');
            $records[] = $this->decodeFile($file, $collection, $id);
        }
        return $records;
    }

    /** Atomically creates a record only if no record already exists. Returns the stored record. */
    public function createIfAbsent(string $collection, string $id, array $record): array
    {
        $path = $this->path($collection, $id);
        return $this->withRecordLock($path, $collection, $id, function () use ($path, $collection, $id, $record): array {
            if (is_file($path)) return $this->decodeFile($path, $collection, $id);
            $this->atomicWrite($path, $collection, $id, $record);
            return $record;
        });
    }

    /** Locks, reads, validates and replaces one record as a single read-modify-write operation. */
    public function transact(string $collection, string $id, callable $mutator): array
    {
        $path = $this->path($collection, $id);
        return $this->withRecordLock($path, $collection, $id, function () use ($path, $collection, $id, $mutator): array {
            if (!is_file($path)) throw new RuntimeException('Stored JSON record does not exist.');
            $current = $this->decodeFile($path, $collection, $id);
            $next = $mutator($current);
            if (!is_array($next)) throw new RuntimeException('Storage transaction must return an array record.');
            $this->atomicWrite($path, $collection, $id, $next);
            return $next;
        });
    }

    /** Deletes one record while holding its record lock; the guard validates identity/revision before removal. */
    public function delete(string $collection, string $id, callable $guard): array
    {
        $path = $this->path($collection, $id);
        return $this->withRecordLock($path, $collection, $id, function () use ($path, $collection, $id, $guard): array {
            if (!is_file($path)) throw new RuntimeException('Stored JSON record does not exist.');
            $current = $this->decodeFile($path, $collection, $id);
            $guard($current);
            if (!@unlink($path)) {
                Logger::error('Stored JSON record could not be deleted', ['collection' => $collection, 'record_id' => $id]);
                throw new RuntimeException('Unable to delete stored JSON record.');
            }
            return $current;
        });
    }

    private function decodeFile(string $path, string $collection, string $id): array
    {
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

    private function withRecordLock(string $path, string $collection, string $id, callable $operation): mixed
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) {
            Logger::error('Storage collection directory could not be created', ['collection' => $collection]);
            throw new RuntimeException('Unable to create collection directory.');
        }
        $lockPath = $path . '.lock';
        $lock = $this->openLockFile($lockPath);
        if ($lock === false) {
            $this->logLockOpenFailure($collection, $id, $lockPath);
            throw new RuntimeException('Unable to open storage lock file for locking: ' . $lockPath . '. The file must be accessible for locking by PHP.');
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                Logger::error('Storage record lock could not be acquired', ['collection' => $collection, 'record_id' => $id]);
                throw new RuntimeException('Unable to lock stored JSON record.');
            }
            return $operation();
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Open a lock without requiring write access to an existing lock file.
     * Never unlink/recreate a lock file as that can split concurrent locks across inodes.
     * New lock files are created normally; existing read-only files are opened read-only.
     *
     * @return resource|false
     */
    private function openLockFile(string $lockPath)
    {
        if (is_file($lockPath)) {
            $permissions = @fileperms($lockPath);
            $hasWriteBits = $permissions !== false && (($permissions & 0222) !== 0);
            if (!is_writable($lockPath) || !$hasWriteBits) {
                $readOnly = @fopen($lockPath, 'r');
                if ($readOnly !== false) return $readOnly;
            }
        }

        $lock = @fopen($lockPath, 'c');
        if ($lock !== false) return $lock;

        // Another local account may own an existing read-only lock. flock() does not
        // require writing its contents, so use a read handle when possible.
        if (is_file($lockPath) && is_readable($lockPath)) return @fopen($lockPath, 'r');
        return false;
    }

    private function logLockOpenFailure(string $collection, string $id, string $lockPath): void
    {
        $lastError = error_get_last();
        $directory = dirname($lockPath);
        Logger::error('Storage lock file could not be opened for locking', [
            'collection' => $collection, 'record_id' => $id, 'lock_path' => $lockPath,
            'collection_directory' => $directory, 'directory_exists' => is_dir($directory),
            'directory_writable' => is_writable($directory),
            'lock_exists' => file_exists($lockPath), 'lock_readable' => is_readable($lockPath),
            'lock_writable' => is_writable($lockPath), 'last_php_error' => $lastError['message'] ?? null,
        ]);
    }

    private function atomicWrite(string $path, string $collection, string $id, array $record): void
    {
        $directory = dirname($path);
        $temporary = null;
        try {
            $json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
            $temporary = @tempnam($directory, '.core-');
            if ($temporary === false) {
                Logger::error('Temporary storage file could not be created', ['collection' => $collection, 'record_id' => $id]);
                throw new RuntimeException('Unable to create temporary storage file.');
            }
            $handle = @fopen($temporary, 'wb');
            if ($handle === false) throw new RuntimeException('Unable to open temporary storage file.');
            try {
                $length = strlen($json); $offset = 0;
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
            } finally { fclose($handle); }
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
