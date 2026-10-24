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

        $transactionId = 'ud_' . bin2hex(random_bytes(12));
        $records = [['collection' => 'universes', 'id' => $universeId, 'expected' => $universeSnapshot]];
        foreach ($characterSnapshots as $character) {
            $records[] = ['collection' => 'characters', 'id' => (string)$character['id'], 'expected' => $character];
        }

        try {
            // The storage layer acquires the universe lock first, then character locks in stable order.
            // CharacterService uses the same universe lock when creating characters, preventing a new
            // dependent character from appearing between the final dependency check and deletion.
            $transaction = $this->storage->deleteBatchAtomically($records, $transactionId, function () use ($universeId, $expectedCharacterIds, $universeSnapshot, $characterSnapshots, $backup): void {
                $currentUniverse = $this->storage->read('universes', $universeId);
                $currentCharacters = $this->charactersFor($universeId);
                $currentIds = array_map(static fn(array $c): string => (string)($c['id'] ?? ''), $currentCharacters);
                sort($currentIds, SORT_STRING);
                if ($currentUniverse !== $universeSnapshot || $currentCharacters !== $characterSnapshots || $currentIds !== $expectedCharacterIds) {
                    throw new RuntimeException('Records or dependent characters changed before atomic deletion. No live records were removed; backup ' . $backup['backup_id'] . ' was retained. Generate a fresh preview.');
                }
            });

            if ($this->storage->read('universes', $universeId) !== null || $this->charactersFor($universeId) !== []) {
                throw new RuntimeException('Atomic deletion committed but post-commit verification found live records. Preserve backup ' . $backup['backup_id'] . '.');
            }
        } catch (Throwable $failure) {
            if (str_contains($failure->getMessage(), 'before any live record was moved')) {
                // The storage layer guarantees that no live JSON was staged in this path. In particular,
                // a character created just before the universe lock was acquired is a stale-preview case,
                // not a rollback failure; keep it and require a new preview.
                Logger::warning('Universe deletion cancelled before any records were removed', [
                    'universe_id' => $universeId, 'backup_id' => $backup['backup_id'],
                    'transaction_id' => $transactionId, 'failure' => $failure->getMessage(),
                ]);
                throw new RuntimeException('Universe deletion was cancelled before any records were removed. The live data was not changed by this attempt. Reason: ' . $failure->getMessage() . ' Backup retained: ' . $backup['backup_id'] . '. Generate a fresh preview before retrying.', 0, $failure);
            }
            if (str_contains($failure->getMessage(), 'all live records were verified restored')) {
                $universeRestored = $this->storage->read('universes', $universeId) === $universeSnapshot;
                $charactersRestored = true;
                foreach ($characterSnapshots as $expectedCharacter) {
                    if ($this->storage->read('characters', (string)$expectedCharacter['id']) !== $expectedCharacter) {
                        $charactersRestored = false;
                        break;
                    }
                }
                if ($universeRestored && $charactersRestored) {
                    Logger::warning('Universe deletion aborted; storage transaction verified all original records restored', [
                        'universe_id' => $universeId, 'backup_id' => $backup['backup_id'],
                        'transaction_id' => $transactionId, 'failure' => $failure->getMessage(),
                        'rollback_verified' => true,
                    ]);
                    throw new RuntimeException('Universe deletion was cancelled; all original universe and character records were verified restored. Reason: ' . $failure->getMessage() . ' Backup retained: ' . $backup['backup_id'] . '. Generate a fresh preview before retrying.', 0, $failure);
                }
            }
            try {
                // The storage transaction normally restores moved files itself. This checksum-verified
                // backup restore is a second recovery layer for missing records if filesystem rollback failed.
                $restored = $this->backups->restore($backup['backup_id']);
                $universeRestored = $this->storage->read('universes', $universeId) === $universeSnapshot;
                $charactersRestored = true;
                foreach ($characterSnapshots as $expectedCharacter) {
                    if ($this->storage->read('characters', (string)$expectedCharacter['id']) !== $expectedCharacter) {
                        $charactersRestored = false;
                        break;
                    }
                }
                if (!($restored['verified'] ?? false) || !$universeRestored || !$charactersRestored) {
                    throw new RuntimeException('Backup restore returned without verifying the original universe and every pre-deletion character.');
                }
                Logger::warning('Universe deletion failed; atomic rollback and backup recovery verified', [
                    'universe_id' => $universeId, 'backup_id' => $backup['backup_id'],
                    'transaction_id' => $transactionId, 'failure' => $failure->getMessage(),
                    'rollback_verified' => true,
                ]);
            } catch (Throwable $rollbackFailure) {
                Logger::critical('CRITICAL: universe deletion failed and automatic rollback could not be verified', [
                    'universe_id' => $universeId, 'backup_id' => $backup['backup_id'],
                    'transaction_id' => $transactionId, 'failure' => $failure->getMessage(),
                    'rollback_failure' => $rollbackFailure->getMessage(),
                ]);
                throw new RuntimeException('Deletion failed and recovery could not be verified. Preserve backup ' . $backup['backup_id'] . ' and inspect storage/logs/universe-deletion.log and storage/logs/backups.log immediately. Recovery transaction: ' . $transactionId . '. Generate a fresh preview before retrying.', 0, $failure);
            }
            throw new RuntimeException('Universe deletion was cancelled; the original universe and character records were verified restored. Reason: ' . $failure->getMessage() . ' Backup: ' . $backup['backup_id'], 0, $failure);
        }

        Logger::warning('Universe and associated characters permanently deleted', ['universe_id' => $universeId, 'deleted_characters' => count($characterSnapshots), 'backup_id' => $backup['backup_id'], 'transaction_id' => $transactionId, 'verified' => true]);
        return ['universe_id' => $universeId, 'deleted_characters' => count($characterSnapshots), 'backup' => $backup, 'transaction_id' => $transactionId, 'verified' => true];
    }

    private function charactersFor(string $universeId): array
    {
        $characters = array_values(array_filter($this->storage->all('characters'), static fn(array $character): bool => ($character['universe_id'] ?? null) === $universeId));
        usort($characters, static fn(array $a, array $b): int => strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? '')));
        return $characters;
    }
}
