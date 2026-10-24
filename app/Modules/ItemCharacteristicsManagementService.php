<?php
declare(strict_types=1);
namespace Core\Modules;
use Core\Core\Logger;
use RuntimeException;

/** Revision-checked, backed-up management for item characteristic schemas. */
final class ItemCharacteristicsManagementService
{
    public function __construct(private string $contentDir, private string $backupDir) {}

    public function load(): array
    {
        $path = rtrim($this->contentDir, '/') . '/item-characteristics.json';
        $raw = @file_get_contents($path);
        if ($raw === false) throw new RuntimeException('Could not read item characteristic definitions.');
        try { $doc = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw new RuntimeException('Item characteristic definitions contain malformed JSON.', 0, $e); }
        if (!is_array($doc) || ($doc['schema_version'] ?? null) !== 1 || !is_array($doc['categories'] ?? null)) throw new RuntimeException('Item characteristic document has an invalid structure.');
        $doc['revision'] = is_int($doc['revision'] ?? null) && $doc['revision'] > 0 ? $doc['revision'] : 1;
        return $doc;
    }

    public function validate(array $candidate): array
    {
        $candidate['revision'] = is_int($candidate['revision'] ?? null) && $candidate['revision'] > 0 ? $candidate['revision'] : 1;
        $this->validateSchema($candidate);
        $items = ContentCatalog::document('items')['entries'];
        $invalid = [];
        foreach ($items as $id => $item) {
            $category = (string)($item['category'] ?? '');
            $fields = $candidate['categories'][$category]['fields'] ?? [];
            $values = $item['characteristics'] ?? [];
            foreach ($values as $fieldId => $value) {
                if (!isset($fields[$fieldId])) { $invalid[] = $id . ': characteristic "' . $fieldId . '" would be removed from category ' . $category; continue; }
                if (!$this->valueValid($fields[$fieldId], $value)) $invalid[] = $id . ': value for "' . $fieldId . '" would no longer be valid';
            }
            foreach ($fields as $fieldId => $field) {
                if (($field['required'] ?? false) && !array_key_exists($fieldId, $values) && !array_key_exists('default', $field)) $invalid[] = $id . ': required characteristic "' . $fieldId . '" has no value or default';
            }
        }
        if ($invalid) throw new RuntimeException('Schema change would invalidate existing items: ' . implode('; ', array_slice(array_unique($invalid), 0, 12)) . (count($invalid) > 12 ? '; and ' . (count($invalid)-12) . ' more' : '') . '. Update affected items first or revise the schema.');
        return ['valid'=>true,'category_count'=>count($candidate['categories']),'field_count'=>array_sum(array_map(static fn($c)=>count($c['fields'] ?? []),$candidate['categories'])),'existing_items_checked'=>count($items),'revision'=>$candidate['revision']];
    }

    public function save(int $expectedRevision, array $candidate): array
    {
        if ($expectedRevision < 1) throw new RuntimeException('Expected schema revision must be positive.');
        $dir = rtrim($this->contentDir, '/');
        if (!is_dir($dir) || !is_writable($dir)) throw new RuntimeException('Content directory is not writable by PHP: ' . $dir);
        $lockPath = $dir . '/.item-characteristics-management.lock';
        $lock = @fopen($lockPath, 'c+');
        if ($lock === false) throw new RuntimeException('Could not open the characteristic schema lock file.');
        try {
            if (!flock($lock, LOCK_EX)) throw new RuntimeException('Could not acquire characteristic schema lock.');
            $current = $this->load(); $revision = $current['revision'];
            if ($expectedRevision !== $revision) throw new RuntimeException('Content revision conflict: expected ' . $expectedRevision . ', current revision is ' . $revision . '. Reload before saving.');
            $candidate['schema_version'] = 1; $candidate['revision'] = $revision + 1;
            $candidate['content_version'] = is_string($candidate['content_version'] ?? null) ? $candidate['content_version'] : '0.1.45';
            $candidate['updated_at'] = gmdate('c'); unset($candidate['entries'], $candidate['catalog']);
            $this->validate($candidate);
            $path = $dir . '/item-characteristics.json'; $old = @file_get_contents($path);
            if ($old === false) throw new RuntimeException('Could not read the current schema for backup.');
            $backupDir = rtrim($this->backupDir, '/') . '/content';
            if (!is_dir($backupDir) && !@mkdir($backupDir, 0770, true) && !is_dir($backupDir)) throw new RuntimeException('Could not create content backup directory.');
            $backup = $backupDir . '/item_characteristics_r' . $revision . '_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.json';
            if (@file_put_contents($backup, $old, LOCK_EX) === false || !is_string(@file_get_contents($backup)) || !hash_equals(hash('sha256',$old),hash('sha256',(string)file_get_contents($backup)))) throw new RuntimeException('Schema backup failed verification; no schema changes were made.');
            $json = json_encode($candidate, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR) . PHP_EOL;
            $temp = $dir . '/.item-characteristics-' . bin2hex(random_bytes(8)) . '.tmp';
            if (@file_put_contents($temp,$json,LOCK_EX) === false) throw new RuntimeException('Could not write temporary schema.');
            try { $decoded=json_decode((string)file_get_contents($temp),true,512,JSON_THROW_ON_ERROR); if ($decoded !== $candidate) throw new RuntimeException('Temporary schema verification failed.'); if (!@rename($temp,$path)) throw new RuntimeException('Could not atomically replace schema.'); }
            finally { if (is_file($temp)) @unlink($temp); }
            Logger::info('Item characteristic schema saved',['revision'=>$candidate['revision'],'category_count'=>count($candidate['categories']),'backup_path'=>$backup]);
            return ['revision'=>$candidate['revision'],'category_count'=>count($candidate['categories']),'backup_file'=>basename($backup),'updated_at'=>$candidate['updated_at']];
        } catch (\Throwable $e) { Logger::warning('Item characteristic schema save rejected or failed',['expected_revision'=>$expectedRevision,'message'=>$e->getMessage()]); throw $e; }
        finally { @flock($lock,LOCK_UN); @fclose($lock); }
    }

    private function validateSchema(array $doc): void
    {
        if (($doc['schema_version'] ?? null)!==1 || !is_array($doc['categories'] ?? null) || !$doc['categories']) throw new RuntimeException('Schema must contain version 1 and at least one category.');
        $categories = ContentCatalog::itemCategoriesDocument()['entries'];
        if (array_diff(array_keys($categories),array_keys($doc['categories'])) || array_diff(array_keys($doc['categories']),array_keys($categories))) throw new RuntimeException('Characteristic schemas must exist for exactly the currently defined item categories.');
        $fieldCount=0;
        foreach ($doc['categories'] as $catId=>$category) {
            if (!is_array($category) || !is_array($category['fields'] ?? null)) throw new RuntimeException('Invalid characteristic category ' . $catId . '.');
            foreach ($category['fields'] as $id=>$field) {
                $fieldCount++;
                if (!is_string($id)||!preg_match('/^[a-z][a-z0-9_]{1,63}$/',$id)||!is_array($field)||!is_string($field['label']??null)||trim($field['label'])===''||strlen($field['label'])>100||!in_array($field['type']??null,['text','number','integer','boolean','enum'],true)||!is_bool($field['required']??null)) throw new RuntimeException('Invalid characteristic field definition: ' . $catId . '/' . (string)$id . '.');
                if (isset($field['description']) && (!is_string($field['description'])||strlen($field['description'])>500)) throw new RuntimeException('Characteristic description is too long: ' . $id . '.');
                if (isset($field['min']) && !is_numeric($field['min'])) throw new RuntimeException('Minimum must be numeric for ' . $id . '.');
                if (isset($field['max']) && !is_numeric($field['max'])) throw new RuntimeException('Maximum must be numeric for ' . $id . '.');
                if (isset($field['min'],$field['max']) && $field['min']>$field['max']) throw new RuntimeException('Minimum exceeds maximum for ' . $id . '.');
                if (in_array($field['type'],['number','integer'],true) && (isset($field['min']) || isset($field['max']))===false) { /* unbounded numeric field is valid */ }
                if ($field['type']==='enum') {
                    if (!is_array($field['options']??null)||!$field['options']) throw new RuntimeException('Enumerated fields need at least one option: ' . $id . '.');
                    foreach ($field['options'] as $optionId=>$label) if (!is_string($optionId)||!preg_match('/^[a-z][a-z0-9_]{0,63}$/',$optionId)||!is_string($label)||trim($label)===''||strlen($label)>100) throw new RuntimeException('Invalid enum option for ' . $id . '.');
                } elseif (isset($field['options'])) throw new RuntimeException('Only enumerated fields may define options: ' . $id . '.');
                if (array_key_exists('default',$field) && !$this->valueValid($field,$field['default'])) throw new RuntimeException('Default value does not match the type or limits for ' . $id . '.');
            }
        }
        if ($fieldCount>1000) throw new RuntimeException('At most 1,000 characteristic definitions are allowed.');
    }

    private function valueValid(array $field, mixed $value): bool
    {
        return match ($field['type'] ?? '') {
            'text' => is_string($value) && strlen($value) <= (int)($field['max_length'] ?? 500) && strlen($value) >= (int)($field['min_length'] ?? 0),
            'number' => (is_int($value)||is_float($value)) && is_finite((float)$value) && (!isset($field['min'])||$value >= $field['min']) && (!isset($field['max'])||$value <= $field['max']),
            'integer' => is_int($value) && (!isset($field['min'])||$value >= $field['min']) && (!isset($field['max'])||$value <= $field['max']),
            'boolean' => is_bool($value),
            'enum' => is_string($value) && isset($field['options'][$value]),
            default => false,
        };
    }
}
