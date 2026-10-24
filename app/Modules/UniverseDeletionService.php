<?php
declare(strict_types=1);

namespace Core\Modules;

use Core\Core\Logger;
use Core\Storage\JsonStorage;
use RuntimeException;
use Throwable;

/** Guarded development cleanup: snapshot first, delete only exact previewed records, verify, and rollback on failure. */
final class UniverseDeletionService
{
    public function __construct(private JsonStorage $storage, private UniverseBackupService $backups) {}

    public function delete(string $universeId, string $confirmation, int $expectedStateRevision, int $expectedLifecycleRevision, array $expectedCharacterIds): array
    {
        $universeId = trim($universeId);
        if ($universeId === '') throw new RuntimeException('A universe ID is required.');
        if ($confirmation !== 'DELETE UNIVERSE ' . $universeId) throw new RuntimeException('Confirmation must exactly match DELETE UNIVERSE ' . $universeId . '.');
        if ($expectedStateRevision < 0 || $expectedLifecycleRevision < 0) throw new RuntimeException('Expected revisions must be zero or greater.');
        if (count($expectedCharacterIds) > 10000 || array_filter($expectedCharacterIds, static fn($id): bool => !is_string($id) || $id === '') !== []) throw new RuntimeException('Expected character ID list is invalid.');
        $expectedCharacterIds = array_values(array_unique($expectedCharacterIds)); sort($expectedCharacterIds, SORT_STRING);

        $universe = $this->storage->read('universes', $universeId);
        if ($universe === null || ($universe['id'] ?? null) !== $universeId) throw new RuntimeException('Universe does not exist.');
        $characters = $this->charactersFor($universeId);
        $actualIds = array_map(static fn(array $c): string => (string)($c['id'] ?? ''), $characters); sort($actualIds, SORT_STRING);
        $stateRevision = (int)($universe['state']['revision'] ?? 0);
        $lifecycleRevision = (int)($universe['lifecycle']['revision'] ?? 0);
        if ($stateRevision !== $expectedStateRevision || $lifecycleRevision !== $expectedLifecycleRevision || $actualIds !== $expectedCharacterIds) {
            throw new RuntimeException('Deletion preview is stale. Refresh inventory and generate a new preview.');
        }

        // Preflight both target collection directories before creating a backup or touching live data.
        // This catches common Apache ownership/permission problems before deletion enters rollback.
        $this->storage->assertCollectionWritable('characters');
        $this->storage->assertCollectionWritable('universes');
        foreach ($expectedCharacterIds as $characterId) {
            $this->storage->assertRecordLockUsable('characters', $characterId);
        }
        $this->storage->assertRecordLockUsable('universes', $universeId);

        // The backup must succeed before any live record is touched.
        $backup = $this->backups->create($universeId);
        $universeSnapshot = $this->storage->read('universes', $universeId);
        $characterSnapshots = $this->charactersFor($universeId);
        $snapshotIds = array_map(static fn(array $c): string => (string)($c['id'] ?? ''), $characterSnapshots); sort($snapshotIds, SORT_STRING);
        if ($universeSnapshot !== $universe || $characterSnapshots !== $characters || $snapshotIds !== $expectedCharacterIds) {
            throw new RuntimeException('Records changed while the pre-deletion backup was being created. No live records were deleted; backup ' . $backup['backup_id'] . ' was retained. Refresh the preview.');
        }

        $deletedCharacters = [];
        $deletedUniverse = false;
        try {
            foreach ($characterSnapshots as $character) {
                $id = (string)$character['id'];
                $deletedCharacters[] = $this->storage->delete('characters', $id, static function (array $current) use ($character): void {
                    if ($current !== $character) throw new RuntimeException('Deletion rejected because character data changed. Refresh the preview.');
                });
            }
            // Check for newly created dependents before deleting the parent record.
            if ($this->charactersFor($universeId) !== []) throw new RuntimeException('A character was added during deletion. Universe deletion was cancelled.');
            $this->storage->delete('universes', $universeId, static function (array $current) use ($universeSnapshot): void {
                if ($current !== $universeSnapshot) throw new RuntimeException('Deletion rejected because universe data changed. Refresh the preview.');
            });
            $deletedUniverse = true;
            if ($this->storage->read('universes', $universeId) !== null || $this->charactersFor($universeId) !== []) throw new RuntimeException('Post-deletion verification found remaining records.');
        } catch (Throwable $failure) {
            try {
                $restored = $this->backups->restore($backup['backup_id']);
                Logger::warning('Universe deletion failed; backup rollback attempted', ['universe_id' => $universeId, 'backup_id' => $backup['backup_id'], 'deleted_character_count' => count($deletedCharacters), 'universe_deleted' => $deletedUniverse, 'rollback_verified' => $restored['verified'] ?? false, 'failure' => $failure->getMessage()]);
            } catch (Throwable $rollbackFailure) {
                Logger::error('CRITICAL: universe deletion failed and automatic rollback could not be verified', ['universe_id' => $universeId, 'backup_id' => $backup['backup_id'], 'failure' => $failure->getMessage(), 'rollback_failure' => $rollbackFailure->getMessage()]);
                throw new RuntimeException('Deletion failed and automatic rollback could not be verified. Preserve backup ' . $backup['backup_id'] . ' and inspect storage/logs/universe-deletion.log and storage/logs/backups.log immediately.', 0, $failure);
            }
            throw new RuntimeException('Universe deletion was cancelled and the pre-deletion backup was used to verify/restore records. Reason: ' . $failure->getMessage() . ' Backup: ' . $backup['backup_id'], 0, $failure);
        }

        Logger::warning('Universe and associated characters permanently deleted', ['universe_id' => $universeId, 'deleted_characters' => count($deletedCharacters), 'backup_id' => $backup['backup_id'], 'verified' => true]);
        return ['universe_id' => $universeId, 'deleted_characters' => count($deletedCharacters), 'backup' => $backup, 'verified' => true];
    }

    private function charactersFor(string $universeId): array
    {
        $characters = array_values(array_filter($this->storage->all('characters'), static fn(array $character): bool => ($character['universe_id'] ?? null) === $universeId));
        usort($characters, static fn(array $a, array $b): int => strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? '')));
        return $characters;
    }
}
