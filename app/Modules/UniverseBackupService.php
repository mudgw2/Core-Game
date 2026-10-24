<?php
declare(strict_types=1);

namespace Core\Modules;

use Core\Core\Logger;
use Core\Storage\JsonStorage;
use RuntimeException;

/** Development-oriented universe snapshots. Backup files live outside live data and never include operational logs. */
final class UniverseBackupService
{
    public function __construct(private JsonStorage $storage, private string $backupRoot)
    {
        if (!is_dir($backupRoot) && !@mkdir($backupRoot, 0770, true) && !is_dir($backupRoot)) {
            Logger::error('Universe backup directory could not be created', ['path' => $backupRoot]);
            throw new RuntimeException('Unable to initialize backup storage.');
        }
    }

    public function create(string $universeId): array
    {
        $universeId = trim($universeId);
        $universe = $this->storage->read('universes', $universeId);
        if ($universe === null || ($universe['id'] ?? null) !== $universeId) throw new RuntimeException('Universe does not exist.');
        $characters = array_values(array_filter($this->storage->all('characters'), static fn(array $c): bool => ($c['universe_id'] ?? null) === $universeId));
        $payload = ['universe' => $universe, 'characters' => $characters];
        $canonical = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $id = 'bkp_' . gmdate('YmdHis') . '_' . bin2hex(random_bytes(5));
        $record = [
            'schema_version' => 1, 'backup_id' => $id, 'created_at' => gmdate('c'),
            'universe_id' => $universeId, 'universe_name' => (string)($universe['name'] ?? $universe['seed'] ?? $universeId),
            'character_count' => count($characters), 'checksum_sha256' => hash('sha256', $canonical), 'payload' => $payload,
        ];
        $path = $this->path($id);
        $json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        $lock = @fopen($path . '.lock', 'x');
        if ($lock === false) throw new RuntimeException('Could not reserve a unique backup ID. Try again.');
        try {
            $temp = @tempnam($this->backupRoot, '.core-backup-');
            if ($temp === false) throw new RuntimeException('Unable to create temporary backup file.');
            try {
                if (@file_put_contents($temp, $json, LOCK_EX) !== strlen($json)) throw new RuntimeException('Backup file write was incomplete.');
                @chmod($temp, 0660);
                if (!@rename($temp, $path)) throw new RuntimeException('Unable to finalize backup file.');
            } finally { if (isset($temp) && is_file($temp)) @unlink($temp); }
        } catch (\Throwable $e) {
            Logger::error('Universe backup creation failed', ['universe_id' => $universeId, 'backup_id' => $id, 'message' => $e->getMessage()]);
            throw $e;
        } finally { fclose($lock); }
        Logger::info('Universe backup created', ['universe_id' => $universeId, 'backup_id' => $id, 'characters' => count($characters)]);
        return $this->summary($record);
    }

    public function list(): array
    {
        $files = glob($this->backupRoot . '/bkp_*.json') ?: [];
        $rows = [];
        foreach ($files as $file) {
            try {
                $row = $this->decode($file);
                $rows[] = $this->summary($row);
            } catch (\Throwable $e) {
                Logger::warning('Invalid universe backup omitted from listing', ['file' => basename($file), 'message' => $e->getMessage()]);
            }
        }
        usort($rows, static fn(array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));
        return $rows;
    }

    public function restore(string $backupId): array
    {
        $record = $this->decode($this->path(trim($backupId)));
        $payload = $record['payload'];
        $universe = $payload['universe'] ?? null;
        $characters = $payload['characters'] ?? null;
        if (!is_array($universe) || !is_string($universe['id'] ?? null) || !is_array($characters)) throw new RuntimeException('Backup payload structure is invalid.');
        $canonical = json_encode(['universe' => $universe, 'characters' => $characters], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (!hash_equals((string)($record['checksum_sha256'] ?? ''), hash('sha256', $canonical))) throw new RuntimeException('Backup checksum validation failed. The backup was not restored.');
        foreach ([['universes', $universe['id'], $universe]] as [$collection, $id, $expected]) {
            $existing = $this->storage->read($collection, $id);
            if ($existing !== null && $existing !== $expected) throw new RuntimeException('Restore conflict: a different record already exists for universe ' . $id . '. No records were overwritten.');
        }
        foreach ($characters as $character) {
            if (!is_array($character) || !is_string($character['id'] ?? null) || ($character['universe_id'] ?? null) !== $universe['id']) throw new RuntimeException('Backup contains an invalid or mismatched character record.');
            $existing = $this->storage->read('characters', $character['id']);
            if ($existing !== null && $existing !== $character) throw new RuntimeException('Restore conflict: a different character record already exists for ' . $character['id'] . '. No records were overwritten.');
        }
        $this->storage->createIfAbsent('universes', $universe['id'], $universe);
        foreach ($characters as $character) $this->storage->createIfAbsent('characters', $character['id'], $character);
        $restoredUniverse = $this->storage->read('universes', $universe['id']);
        if ($restoredUniverse !== $universe) throw new RuntimeException('Restore verification failed for universe record.');
        foreach ($characters as $character) if ($this->storage->read('characters', $character['id']) !== $character) throw new RuntimeException('Restore verification failed for character ' . $character['id'] . '.');
        Logger::warning('Universe backup restored', ['backup_id' => $record['backup_id'], 'universe_id' => $universe['id'], 'characters' => count($characters)]);
        return ['backup_id' => $record['backup_id'], 'universe_id' => $universe['id'], 'universe_name' => (string)($universe['name'] ?? $universe['seed'] ?? $universe['id']), 'characters_restored_or_verified' => count($characters), 'verified' => true];
    }

    private function decode(string $path): array
    {
        if (!is_file($path)) throw new RuntimeException('Backup does not exist.');
        $raw = @file_get_contents($path);
        if ($raw === false) throw new RuntimeException('Backup could not be read.');
        try { $record = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { Logger::error('Backup JSON is invalid', ['file' => basename($path), 'message' => $e->getMessage()]); throw new RuntimeException('Backup JSON is invalid.'); }
        if (!is_array($record) || ($record['schema_version'] ?? null) !== 1 || !is_string($record['backup_id'] ?? null) || !is_array($record['payload'] ?? null)) throw new RuntimeException('Backup format is unsupported.');
        $payload = $record['payload'];
        $canonical = json_encode(['universe' => $payload['universe'] ?? null, 'characters' => $payload['characters'] ?? null], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (!hash_equals((string)($record['checksum_sha256'] ?? ''), hash('sha256', $canonical))) throw new RuntimeException('Backup checksum validation failed.');
        return $record;
    }

    private function summary(array $record): array
    {
        return ['backup_id' => $record['backup_id'], 'created_at' => $record['created_at'], 'universe_id' => $record['universe_id'], 'universe_name' => $record['universe_name'], 'character_count' => $record['character_count'], 'checksum_sha256' => $record['checksum_sha256']];
    }

    private function path(string $id): string
    {
        if (!preg_match('/^bkp_[A-Za-z0-9_-]+$/', $id)) throw new RuntimeException('Invalid backup ID.');
        return $this->backupRoot . '/' . $id . '.json';
    }
}
