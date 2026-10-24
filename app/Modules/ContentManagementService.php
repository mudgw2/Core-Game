<?php
declare(strict_types=1);

namespace Core\Modules;

use Core\Core\Logger;
use RuntimeException;

/** Safe, revision-checked editor for JSON game-content definitions. */
final class ContentManagementService
{
    private const FILES = ['items' => 'items.json', 'recipes' => 'recipes.json', 'discoveries' => 'discoveries.json', 'ship_modules' => 'ship-modules.json'];

    public function __construct(private string $contentDir, private string $backupDir)
    {
        $this->contentDir = rtrim($this->contentDir, '/');
        $this->backupDir = rtrim($this->backupDir, '/');
    }

    public function documents(): array
    {
        $documents = [];
        foreach (self::FILES as $name => $filename) $documents[$name] = $this->readDocument($name, $filename);
        ContentCatalog::validateDocuments($documents);
        return $documents;
    }

    public function validate(string $catalog, array $candidate): array
    {
        $this->assertCatalog($catalog);
        $documents = $this->documents();
        $candidate['revision'] = $documents[$catalog]['revision'] ?? 1;
        $documents[$catalog] = $candidate;
        ContentCatalog::validateDocuments($documents);
        return ['valid' => true, 'catalog' => $catalog, 'entry_count' => count($candidate['entries']), 'revision' => $candidate['revision']];
    }

    public function save(string $catalog, int $expectedRevision, array $candidate): array
    {
        $this->assertCatalog($catalog);
        if (($candidate['schema_version'] ?? null) !== 1 || !is_array($candidate['entries'] ?? null)) throw new RuntimeException('Candidate must contain schema_version 1 and an entries object.');
        if ($expectedRevision < 1) throw new RuntimeException('Expected content revision must be a positive integer.');
        $dir = $this->contentDir;
        if (!is_dir($dir) || !is_writable($dir)) throw new RuntimeException('Content directory is not writable by PHP: ' . $dir);
        $lockPath = $dir . '/.content-management.lock';
        $lock = @fopen($lockPath, 'c+');
        if ($lock === false) throw new RuntimeException('Could not open the content management lock file: ' . $lockPath);
        try {
            if (!flock($lock, LOCK_EX)) throw new RuntimeException('Could not acquire the content management lock.');
            $documents = $this->documents();
            $current = $documents[$catalog];
            $currentRevision = is_int($current['revision'] ?? null) && $current['revision'] > 0 ? $current['revision'] : 1;
            if ($expectedRevision !== $currentRevision) throw new RuntimeException('Content revision conflict: expected ' . $expectedRevision . ', current revision is ' . $currentRevision . '. Reload the catalog before saving.');

            $candidate['schema_version'] = 1;
            $candidate['revision'] = $currentRevision + 1;
            $candidate['content_version'] = is_string($candidate['content_version'] ?? null) ? $candidate['content_version'] : ($current['content_version'] ?? '1');
            $candidate['updated_at'] = gmdate('c');
            unset($candidate['catalog']);
            $documents[$catalog] = $candidate;
            ContentCatalog::validateDocuments($documents);

            $backupPath = $this->backupDir . '/content/' . $catalog . '_r' . $currentRevision . '_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.json';
            $backupParent = dirname($backupPath);
            if (!is_dir($backupParent) && !@mkdir($backupParent, 0770, true) && !is_dir($backupParent)) throw new RuntimeException('Could not create content backup directory: ' . $backupParent);
            $currentPath = $dir . '/' . self::FILES[$catalog];
            $currentBytes = @file_get_contents($currentPath);
            if ($currentBytes === false) throw new RuntimeException('Could not read current content catalog before backup: ' . $currentPath);
            if (@file_put_contents($backupPath, $currentBytes, LOCK_EX) === false) throw new RuntimeException('Could not preserve the previous valid catalog at: ' . $backupPath);
            $backupCheck = @file_get_contents($backupPath);
            if ($backupCheck === false || !hash_equals(hash('sha256', $currentBytes), hash('sha256', $backupCheck))) {
                @unlink($backupPath);
                throw new RuntimeException('Previous content catalog backup failed verification. No catalog changes were made.');
            }

            $json = json_encode($candidate, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
            $temp = $dir . '/.content-' . $catalog . '-' . bin2hex(random_bytes(8)) . '.tmp';
            if (@file_put_contents($temp, $json, LOCK_EX) === false) throw new RuntimeException('Could not write temporary content catalog. Previous catalog remains active.');
            try {
                $roundTrip = json_decode((string)file_get_contents($temp), true, 512, JSON_THROW_ON_ERROR);
                if ($roundTrip !== $candidate) throw new RuntimeException('Temporary content catalog verification failed.');
                if (!@rename($temp, $currentPath)) throw new RuntimeException('Could not atomically replace the content catalog. Previous catalog remains active.');
            } finally { if (is_file($temp)) @unlink($temp); }
            Logger::info('Game content catalog saved', ['catalog' => $catalog, 'revision' => $candidate['revision'], 'entry_count' => count($candidate['entries']), 'backup_path' => $backupPath]);
            return ['catalog' => $catalog, 'revision' => $candidate['revision'], 'entry_count' => count($candidate['entries']), 'backup_file' => basename($backupPath), 'updated_at' => $candidate['updated_at']];
        } catch (\JsonException $e) {
            Logger::error('Game content catalog JSON encoding failed', ['catalog' => $catalog, 'message' => $e->getMessage()]);
            throw new RuntimeException('Content could not be encoded as valid JSON: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            Logger::warning('Game content catalog change rejected or failed', ['catalog' => $catalog, 'expected_revision' => $expectedRevision, 'message' => $e->getMessage()]);
            throw $e;
        } finally { @flock($lock, LOCK_UN); @fclose($lock); }
    }

    private function readDocument(string $catalog, string $filename): array
    {
        $path = $this->contentDir . '/' . $filename;
        $raw = @file_get_contents($path);
        if ($raw === false) throw new RuntimeException('Could not read content catalog: ' . $path);
        try { $doc = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw new RuntimeException('Malformed JSON in content catalog ' . $catalog . ': ' . $e->getMessage(), 0, $e); }
        if (!is_array($doc)) throw new RuntimeException('Content catalog must be a JSON object: ' . $catalog);
        $doc['revision'] = is_int($doc['revision'] ?? null) && $doc['revision'] > 0 ? $doc['revision'] : 1;
        return $doc;
    }

    private function assertCatalog(string $catalog): void
    {
        if (!isset(self::FILES[$catalog])) throw new RuntimeException('Unknown content catalog.');
    }
}
