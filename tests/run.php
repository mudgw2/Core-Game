<?php
declare(strict_types=1);

/**
 * CORE dependency-free test runner. All persistence tests use a temporary directory.
 * Run from the project root: php tests/run.php
 */
define('CORE_ROOT', dirname(__DIR__));
spl_autoload_register(static function (string $class): void {
    $prefix = 'Core\\';
    if (!str_starts_with($class, $prefix)) return;
    $file = CORE_ROOT . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) require_once $file;
});

use Core\Modules\Universe\UniverseGenerator;
use Core\Modules\Universe\UniverseService;
use Core\Storage\JsonStorage;
use Core\Modules\UniverseBackupService;
use Core\Modules\ContentCatalog;
use Core\Modules\ContentManagementService;
use Core\Modules\Characters\CharacterService;
use Core\Modules\Characters\CharacterScanService;
use Core\Modules\Characters\CharacterDiscoveryService;
use Core\Modules\Characters\CharacterCraftingService;

$passed = 0;
$failed = 0;
$failures = [];
$test = static function (string $name, callable $body) use (&$passed, &$failed, &$failures): void {
    try {
        $body();
        $passed++;
        echo "PASS  {$name}\n";
    } catch (Throwable $e) {
        $failed++;
        $failures[] = [$name, $e->getMessage()];
        echo "FAIL  {$name}: {$e->getMessage()}\n";
    }
};
$assert = static function (bool $condition, string $message = 'Assertion failed'): void {
    if (!$condition) throw new RuntimeException($message);
};
$equal = static function (mixed $expected, mixed $actual, string $message = ''): void {
    if ($expected !== $actual) {
        throw new RuntimeException(($message !== '' ? $message . ': ' : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

// Syntax-check every PHP source file in this project, including the test runner.
$test('PHP syntax: all project PHP files parse', static function () use ($assert): void {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(CORE_ROOT, FilesystemIterator::SKIP_DOTS));
    $files = [];
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') $files[] = $file->getPathname();
    }
    foreach ($files as $file) {
        $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1';
        exec($command, $output, $status);
        if ($status !== 0) throw new RuntimeException($file . ': ' . implode("\n", $output));
        $output = [];
    }
    $assert(count($files) > 0, 'No PHP files found to lint');
});

$test('item characteristic manager validates category schema and existing item values', static function () use ($assert): void {
    $dir = sys_get_temp_dir() . '/core-schema-qa-' . bin2hex(random_bytes(6)); mkdir($dir,0770,true);
    try {
        $schemaPath = CORE_ROOT . '/config/content/item-characteristics.json';
        $schema = json_decode((string)file_get_contents($schemaPath),true,512,JSON_THROW_ON_ERROR);
        $manager = new Core\Modules\ItemCharacteristicsManagementService(CORE_ROOT . '/config/content',$dir);
        $result=$manager->validate($schema); $assert($result['valid']===true && $result['existing_items_checked']>=1,'Schema validation did not check existing items');
    } finally { if(is_dir($dir)){foreach(glob($dir.'/*')?:[] as $f)@unlink($f);@rmdir($dir);} }
});
$test('item characteristic manager rejects a schema that removes a used field', static function () use ($assert): void {
    $dir = sys_get_temp_dir() . '/core-schema-qa-' . bin2hex(random_bytes(6)); mkdir($dir,0770,true);
    try {
        $schema=json_decode((string)file_get_contents(CORE_ROOT.'/config/content/item-characteristics.json'),true,512,JSON_THROW_ON_ERROR);
        unset($schema['categories']['resource']['fields']['rarity']);
        try {(new Core\Modules\ItemCharacteristicsManagementService(CORE_ROOT.'/config/content',$dir))->validate($schema);throw new RuntimeException('Schema removing a used field was accepted.');}
        catch(RuntimeException $e){$assert(str_contains($e->getMessage(),'invalidate existing items'),'Unexpected rejection message: '.$e->getMessage());}
    } finally { if(is_dir($dir)){foreach(glob($dir.'/*')?:[] as $f)@unlink($f);@rmdir($dir);} }
});
$test('item characteristic manager rejects invalid enum defaults', static function () use ($assert): void {
    $dir = sys_get_temp_dir() . '/core-schema-qa-' . bin2hex(random_bytes(6)); mkdir($dir,0770,true);
    try {
        $schema=json_decode((string)file_get_contents(CORE_ROOT.'/config/content/item-characteristics.json'),true,512,JSON_THROW_ON_ERROR);
        $schema['categories']['resource']['fields']['rarity']['default']='not_an_option';
        try {(new Core\Modules\ItemCharacteristicsManagementService(CORE_ROOT.'/config/content',$dir))->validate($schema);throw new RuntimeException('Invalid enum default was accepted.');}
        catch(RuntimeException $e){$assert(str_contains($e->getMessage(),'Default value'),'Unexpected rejection message: '.$e->getMessage());}
    } finally { if(is_dir($dir)){foreach(glob($dir.'/*')?:[] as $f)@unlink($f);@rmdir($dir);} }
});

$test('same seed produces identical deterministic baseline', static function () use ($equal): void {
    $generator = new UniverseGenerator();
    $equal($generator->generate('qa-seed-alpha'), $generator->generate('qa-seed-alpha'));
});
$test('seed whitespace is normalized consistently', static function () use ($equal): void {
    $generator = new UniverseGenerator();
    $equal($generator->generate('  qa-seed-alpha  '), $generator->generate('qa-seed-alpha'));
});
$test('different seeds produce different universe identities', static function () use ($assert): void {
    $generator = new UniverseGenerator();
    $assert($generator->generate('qa-seed-alpha')['id'] !== $generator->generate('qa-seed-beta')['id'], 'Different seeds unexpectedly produced the same ID');
});
$test('generated system count and fields are internally consistent', static function () use ($assert): void {
    $baseline = (new UniverseGenerator())->generate('qa-seed-alpha');
    $assert($baseline['system_count'] >= 8 && $baseline['system_count'] <= 16, 'System count outside generator bounds');
    $assert($baseline['system_count'] === count($baseline['systems']), 'system_count does not match systems array');
    $ids = [];
    foreach ($baseline['systems'] as $system) {
        $assert(isset($system['id'], $system['name'], $system['x'], $system['y'], $system['classification']), 'System missing required field');
        $assert(!in_array($system['id'], $ids, true), 'Duplicate system ID');
        $ids[] = $system['id'];
        $assert($system['x'] >= -10000 && $system['x'] <= 10000, 'X coordinate out of bounds');
        $assert($system['y'] >= -10000 && $system['y'] <= 10000, 'Y coordinate out of bounds');
    }
});
$test('create then load returns same universe ID and creation timestamp', static function () use ($equal, $assert): void {
    $dir = sys_get_temp_dir() . '/core-qa-' . bin2hex(random_bytes(8));
    try {
        $service = new UniverseService(new JsonStorage($dir));
        $first = $service->createOrLoad('qa-persistence-seed');
        $second = $service->createOrLoad('qa-persistence-seed');
        $equal($first['id'], $second['id'], 'Universe ID changed on reload');
        $equal($first['created_at'], $second['created_at'], 'created_at changed on reload');
        $equal($first['baseline'], $second['baseline'], 'Baseline changed on reload');
        $assert(is_file($dir . '/universes/' . $first['id'] . '.json'), 'Expected JSON persistence file was not created');
    } finally {
        if (is_dir($dir)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            rmdir($dir);
        }
    }
});
$test('atomic JSON storage round-trips and replaces complete records', static function () use ($equal, $assert): void {
    $dir = sys_get_temp_dir() . '/core-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $record = ['payload' => str_repeat('CORE-', 4096), 'revision' => 1];
        $storage->write('records', 'sample', $record);
        $equal($record, $storage->read('records', 'sample'));
        $record['revision'] = 2;
        $storage->write('records', 'sample', $record);
        $equal($record, $storage->read('records', 'sample'));
        $assert(is_file($dir . '/records/sample.json.lock'), 'Per-record lock file missing');
        $temps = array_values(array_filter(scandir($dir . '/records') ?: [], static fn(string $n): bool => str_starts_with($n, '.core-')));
        $assert($temps === [], 'Temporary files remain after write');
    } finally {
        if (is_dir($dir)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            rmdir($dir);
        }
    }
});
$test('storage rejects malformed JSON records', static function () use ($assert): void {
    $dir = sys_get_temp_dir() . '/core-qa-' . bin2hex(random_bytes(8));
    try {
        mkdir($dir . '/records', 0770, true);
        file_put_contents($dir . '/records/broken.json', '{invalid json');
        try { (new JsonStorage($dir))->read('records', 'broken'); }
        catch (RuntimeException $e) {
            $assert(str_contains($e->getMessage(), 'Stored JSON record is invalid'));
            return;
        }
        throw new RuntimeException('Malformed JSON was silently accepted');
    } finally {
        if (is_dir($dir)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            rmdir($dir);
        }
    }
});

$test('storage rejects unsafe path segments', static function () use ($assert): void {
    $dir = sys_get_temp_dir() . '/core-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        try {
            $storage->read('../escape', 'record');
            throw new RuntimeException('Unsafe collection unexpectedly accepted');
        } catch (RuntimeException $e) {
            $assert(str_contains($e->getMessage(), 'Invalid storage path segment.'), 'Unexpected exception for unsafe collection');
        }
    } finally {
        if (is_dir($dir)) rmdir($dir);
    }
});


$test('universe lifecycle controls are reversible and revision checked', static function () use ($equal, $assert): void {
    $dir = sys_get_temp_dir() . '/core-cli-lifecycle-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('cli-lifecycle-seed'); $service = new Core\Modules\Universe\UniverseLifecycleService($storage);
        $locked = $service->apply($universe['id'], 'lock', 0); $equal(true, $locked['universe']['lifecycle']['locked']);
        $unlocked = $service->apply($universe['id'], 'unlock', 1); $equal(false, $unlocked['universe']['lifecycle']['locked']);
        try { $service->apply($universe['id'], 'disable', 0); throw new RuntimeException('Stale lifecycle revision accepted.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'revision conflict')); }
    } finally {
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); rmdir($dir); }
    }
});

$test('universe lifecycle guard blocks mutations but preserves reads', static function () use ($assert): void {
    $universe = ['id' => 'u', 'lifecycle' => ['enabled' => false, 'locked' => false]];
    try { Core\Modules\Universe\UniverseLifecycleGuard::assertCanPerformGameplayAction($universe); throw new RuntimeException('Disabled universe passed guard.'); }
    catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'Universe is disabled')); }
    $universe['lifecycle'] = ['enabled' => true, 'locked' => true];
    try { Core\Modules\Universe\UniverseLifecycleGuard::assertCanPerformGameplayAction($universe); throw new RuntimeException('Locked universe passed guard.'); }
    catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'Universe is locked')); }
    Core\Modules\Universe\UniverseLifecycleGuard::assertCanPerformGameplayAction(['id' => 'legacy']);
});

$test('universe backup and restore preserve data and refuse conflicts', static function () use ($assert): void {
    $dir = sys_get_temp_dir() . '/core-cli-backup-' . bin2hex(random_bytes(6));
    try {
        $storage = new JsonStorage($dir . '/data');
        $universe = ['id' => 'cli_backup_universe', 'name' => 'CLI Backup', 'seed' => 'cli-backup-seed', 'baseline' => ['systems' => []], 'state' => ['changes' => []]];
        $character = ['id' => 'cli_backup_character', 'name' => 'CLI Pilot', 'universe_id' => $universe['id'], 'revision' => 1];
        $storage->write('universes', $universe['id'], $universe); $storage->write('characters', $character['id'], $character);
        $service = new UniverseBackupService($storage, $dir . '/backups'); $backup = $service->create($universe['id']);
        $storage->delete('characters', $character['id'], static fn(array $r) => null); $storage->delete('universes', $universe['id'], static fn(array $r) => null);
        $result = $service->restore($backup['backup_id']);
        $assert($result['verified'] === true, 'Restore did not verify.');
        $assert($storage->read('universes', $universe['id']) === $universe, 'Universe mismatch after restore.');
        $assert($storage->read('characters', $character['id']) === $character, 'Character mismatch after restore.');
    } finally {
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); rmdir($dir); }
    }
});

$test('atomic batch deletion removes related records and preserves lock-file identity', static function () use ($assert, $equal): void {
    $dir = sys_get_temp_dir() . '/core-atomic-delete-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir . '/data');
        $universe = ['id' => 'atomic_universe', 'revision' => 1];
        $character = ['id' => 'atomic_character', 'universe_id' => $universe['id'], 'revision' => 1];
        $storage->write('universes', $universe['id'], $universe); $storage->write('characters', $character['id'], $character);
        $result = $storage->deleteBatchAtomically([
            ['collection' => 'universes', 'id' => $universe['id'], 'expected' => $universe],
            ['collection' => 'characters', 'id' => $character['id'], 'expected' => $character],
        ], 'ud_' . bin2hex(random_bytes(12)), static function (): void {});
        $equal(2, $result['deleted_count']); $equal(true, $result['verified']);
        $equal(null, $storage->read('universes', $universe['id'])); $equal(null, $storage->read('characters', $character['id']));
        $assert(is_file($dir . '/data/universes/' . $universe['id'] . '.json.lock'), 'Universe lock tombstone was removed.');
        $assert(is_file($dir . '/data/characters/' . $character['id'] . '.json.lock'), 'Character lock tombstone was removed.');
    } finally {
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); @rmdir($dir); }
    }
});

$test('atomic batch deletion guard failure leaves every live record intact', static function () use ($assert, $equal): void {
    $dir = sys_get_temp_dir() . '/core-atomic-delete-guard-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir . '/data');
        $universe = ['id' => 'atomic_guard_universe', 'revision' => 1];
        $character = ['id' => 'atomic_guard_character', 'universe_id' => $universe['id'], 'revision' => 1];
        $storage->write('universes', $universe['id'], $universe); $storage->write('characters', $character['id'], $character);
        $transactionId = 'ud_' . bin2hex(random_bytes(12));
        try {
            $storage->deleteBatchAtomically([
                ['collection' => 'universes', 'id' => $universe['id'], 'expected' => $universe],
                ['collection' => 'characters', 'id' => $character['id'], 'expected' => $character],
            ], $transactionId, static function (): void { throw new RuntimeException('injected guard failure'); });
            throw new RuntimeException('Guard failure was ignored.');
        } catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'before any live record was moved'), 'Unexpected guard error.'); }
        $equal($universe, $storage->read('universes', $universe['id']));
        $equal($character, $storage->read('characters', $character['id']));
        $assert(!is_dir($dir . '/data/.transactions/delete_' . $transactionId), 'Empty transaction staging directory remains.');
    } finally {
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); @rmdir($dir); }
    }
});

$test('focused logs receive categorized events while preserving the central log', static function () use ($assert): void {
    $dir = sys_get_temp_dir() . '/core-cli-logs-' . bin2hex(random_bytes(8));
    if (!mkdir($dir, 0770, true) && !is_dir($dir)) throw new RuntimeException('Could not create log test directory.');
    try {
        Core\Core\Logger::configure($dir . '/core.log', 'production');
        Core\Core\Logger::info('Request handled', ['method' => 'GET', 'path' => '/api/health']);
        Core\Core\Logger::error('Storage lock file could not be opened', ['collection' => 'universes', 'record_id' => 'qa']);
        Core\Core\Logger::critical('CRITICAL: universe deletion failed and automatic rollback could not be verified', ['universe_id' => 'qa', 'backup_id' => 'bkp_qa', 'rollback_failure' => 'test']);
        foreach (['core.log', 'api.log', 'storage.log', 'universe-deletion.log', 'backups.log', 'php-errors.log'] as $name) {
            $assert(is_file($dir . '/' . $name), 'Expected categorized log missing: ' . $name);
        }
        $assert(str_contains((string)file_get_contents($dir . '/core.log'), 'Request handled'), 'Central log did not retain the API event.');
        $assert(str_contains((string)file_get_contents($dir . '/storage.log'), 'lock file could not be opened'), 'Storage log lacks lock failure.');
        $assert(str_contains((string)file_get_contents($dir . '/universe-deletion.log'), 'rollback could not be verified'), 'Deletion log lacks critical event.');
        $assert(str_contains((string)file_get_contents($dir . '/backups.log'), 'rollback could not be verified'), 'Backup log lacks rollback failure event.');
    } finally {
        foreach (glob($dir . '/*.log') ?: [] as $file) @unlink($file);
        @rmdir($dir);
    }
});

$test('read-only existing record lock remains usable without changing its permissions', static function () use ($assert, $equal): void {
    $dir = sys_get_temp_dir() . '/core-cli-lock-readonly-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir . '/data');
        $storage->write('universes', 'readonly_lock_universe', ['id' => 'readonly_lock_universe', 'revision' => 1]);
        $lock = $dir . '/data/universes/readonly_lock_universe.json.lock';
        if (!chmod($lock, 0444)) throw new RuntimeException('Could not make test lock read-only.');
        $storage->assertRecordLockUsable('universes', 'readonly_lock_universe');
        $storage->write('universes', 'readonly_lock_universe', ['id' => 'readonly_lock_universe', 'revision' => 2]);
        $equal(2, $storage->read('universes', 'readonly_lock_universe')['revision'] ?? null, 'Storage mutation failed with a readable read-only lock.');
        $assert((fileperms($lock) & 0222) === 0, 'CORE unexpectedly changed lock-file permissions.');
    } finally {
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) { if ($item->isDir()) @chmod($item->getPathname(), 0770); else @chmod($item->getPathname(), 0660); $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); } @rmdir($dir); }
    }
});

$test('storage mutation preflight validates collection write access', static function () use ($assert): void {
    $dir = sys_get_temp_dir() . '/core-cli-storage-preflight-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir . '/data');
        $storage->assertCollectionWritable('characters');
        $storage->assertCollectionWritable('universes');
        $assert(is_dir($dir . '/data/characters') && is_dir($dir . '/data/universes'), 'Preflight did not ensure collection directories exist.');
    } finally {
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); rmdir($dir); }
    }
});


$test('game content catalogs load from editable JSON files', static function () use ($assert): void {
    $docs = ContentCatalog::allDocuments();
    $assert(isset($docs['items']['entries']['raw_ore'], $docs['recipes']['entries']['refine_ore'], $docs['discoveries']['entries']['mineral_signature'], $docs['ship_modules']['entries']['propulsion_array']), 'Expected game definitions are missing from JSON catalogs.');
    foreach ($docs as $name => $doc) $assert(($doc['schema_version'] ?? null) === 1 && is_array($doc['entries'] ?? null), 'Invalid JSON catalog structure: ' . $name);
});

$test('JSON recipes reference known items and positive quantities', static function () use ($assert): void {
    $items = ContentCatalog::entries('items');
    foreach (ContentCatalog::entries('recipes') as $id => $recipe) {
        $assert(is_array($recipe['inputs'] ?? null) && $recipe['inputs'] !== [], 'Recipe has no inputs: ' . $id);
        foreach ($recipe['inputs'] as $itemId => $quantity) $assert(isset($items[$itemId]) && is_int($quantity) && $quantity > 0, 'Recipe input is invalid: ' . $id . '/' . $itemId);
        $output = $recipe['output'] ?? [];
        $assert(isset($items[$output['resource'] ?? '']) && is_int($output['quantity'] ?? null) && $output['quantity'] > 0, 'Recipe output is invalid: ' . $id);
    }
});

$test('JSON discovery rewards reference known items and valid deterministic ranges', static function () use ($assert): void {
    $items = ContentCatalog::entries('items');
    foreach (ContentCatalog::entries('discoveries') as $id => $discovery) {
        $reward = $discovery['reward'] ?? [];
        $assert(isset($items[$reward['resource'] ?? '']), 'Discovery reward references unknown item: ' . $id);
        $assert(is_int($reward['minimum'] ?? null) && is_int($reward['maximum'] ?? null) && $reward['minimum'] > 0 && $reward['maximum'] >= $reward['minimum'], 'Discovery reward range is invalid: ' . $id);
    }
});

$test('JSON ship modules reference known items and support multiple modules per slot', static function () use ($assert): void {
    $docs = ContentCatalog::allDocuments(); $items = $docs['items']['entries'];
    foreach ($docs['ship_modules']['entries'] as $id => $module) {
        $slot = $module['slot'] ?? '';
        $assert(is_string($slot) && $slot !== '', 'Ship module slot is missing: ' . $id);
        foreach (($module['inputs'] ?? []) as $itemId => $quantity) $assert(isset($items[$itemId]) && is_int($quantity) && $quantity > 0, 'Ship module input is invalid: ' . $id . '/' . $itemId);
    }
    $modules = $docs['ship_modules']['entries']; $first = reset($modules);
    $modules['qa_alternate_module'] = $first; $docs['ship_modules']['entries'] = $modules;
    ContentCatalog::validateDocuments($docs);
    $assert($modules['qa_alternate_module']['slot'] === $first['slot'], 'Regression fixture did not share a slot.');
});

$test('character creation definitions are JSON-backed and extensible', static function () use ($assert): void {
    $path = CORE_ROOT . '/storage/data/content/character-options.json';
    $options = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $assert(count($options['attributes'] ?? []) >= 6, 'Expected the six initial ability attributes.');
    $assert(count($options['roles'] ?? []) >= 1 && count($options['species'] ?? []) >= 1, 'Role/species options are missing.');
    foreach (['roles', 'species', 'attributes'] as $group) {
        $ids = [];
        foreach ($options[$group] as $entry) { $assert(is_string($entry['id'] ?? null) && $entry['id'] !== '', $group . ' entry has no stable ID.'); $assert(!isset($ids[$entry['id']]), $group . ' contains duplicate IDs.'); $ids[$entry['id']] = true; }
    }
});

$test('new character persists Starfinder-inspired attributes and data-driven species and role IDs', static function () use ($assert, $equal): void {
    $dir = sys_get_temp_dir() . '/core-character-profile-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $universe = (new UniverseService($storage))->createOrLoad('character-profile-seed');
        $character = (new CharacterService($storage))->create($universe['id'], 'Profile Tester', 'explorer', 'android', ['strength'=>12,'dexterity'=>14,'constitution'=>10,'intelligence'=>13,'wisdom'=>8,'charisma'=>15]);
        $saved = $storage->read('characters', $character['id']);
        $equal('explorer', $saved['role_id'] ?? null, 'Role ID was not persisted');
        $equal('android', $saved['species_id'] ?? null, 'Species ID was not persisted');
        $equal(14, $saved['attributes']['dexterity'] ?? null, 'Ability score was not persisted');
        $equal(6, count($saved['attributes'] ?? []), 'Unexpected initial attribute count');
        try { (new CharacterService($storage))->create($universe['id'], 'Invalid Role', 'not-a-role'); throw new RuntimeException('Unknown role was accepted.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'valid character role'), 'Unexpected role rejection: ' . $e->getMessage()); }
    } finally {
        if (is_dir($dir)) { $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($iterator as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); @rmdir($dir); }
    }
});

$test('character can be created safely without a universe association', static function () use ($assert, $equal): void {
    $dir = sys_get_temp_dir() . '/core-unassigned-character-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $character = (new CharacterService($storage))->create('', 'Unassigned Tester', 'explorer', 'human', ['strength'=>10,'dexterity'=>10,'constitution'=>10,'intelligence'=>10,'wisdom'=>10,'charisma'=>10]);
        $saved = $storage->read('characters', $character['id']);
        $equal(null, $saved['universe_id'] ?? null, 'Unassigned character unexpectedly has a universe');
        $equal(null, $saved['current_system_id'] ?? null, 'Unassigned character unexpectedly has a location');
        $equal(null, $saved['location'] ?? null, 'Unassigned character unexpectedly has location state');
        $assert(($saved['lifecycle']['last_action'] ?? '') === 'created_unassigned', 'Unassigned creation lifecycle marker missing');
    } finally {
        if (is_dir($dir)) { $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($iterator as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); @rmdir($dir); }
    }
});

$test('player-created unassigned character persists its account owner', static function () use ($assert): void {
    $dir = sys_get_temp_dir() . '/core-owner-qa-' . bin2hex(random_bytes(5));
    $storage = new \Core\Storage\JsonStorage($dir);
    try {
        $service = new \Core\Modules\Characters\CharacterService($storage);
        $character = $service->create('', 'Owner QA', 'explorer', 'human', [], 'usr_owner_qa');
        $assert(($character['owner_account_id'] ?? null) === 'usr_owner_qa', 'Character owner account ID was not persisted.');
        $assert(($character['universe_id'] ?? null) === null, 'New character should remain unassigned.');
    } finally {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
        if (is_dir($dir)) rmdir($dir);
    }
});

$test('scan and investigation state persists in character JSON and uses JSON content', static function () use ($assert): void {
    $dir = sys_get_temp_dir() . '/core-content-persist-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $universe = (new UniverseService($storage))->createOrLoad('content-persistence-seed');
        $characters = new CharacterService($storage);
        $character = $characters->create($universe['id'], 'Content Tester');
        $scan = (new CharacterScanService($storage))->scanCurrentSystem($universe['id'], $character['id'], 0);
        $definitions = ContentCatalog::entries('discoveries');
        $assert(count($scan['scan']['discoveries']) >= 1, 'Scan returned no discoveries.');
        foreach ($scan['scan']['discoveries'] as $discovery) $assert(isset($definitions[$discovery['type']]), 'Scan generated a signal absent from JSON definitions.');
        $first = $scan['scan']['discoveries'][0];
        $investigated = (new CharacterDiscoveryService($storage))->investigate($universe['id'], $character['id'], $first['id'], 1);
        $saved = $storage->read('characters', $character['id']);
        $assert(isset($saved['scans']['systems'][$first['system_id']]['discoveries'][0]['status']), 'Discovery status was not saved to character JSON.');
        $assert(!empty($saved['inventory']['ledger']), 'Discovery reward ledger was not saved to character JSON.');
        $assert(($investigated['reward']['resource'] ?? '') === ($definitions[$first['type']]['reward']['resource'] ?? null), 'Investigation reward did not follow the JSON definition.');
    } finally {
        if (is_dir($dir)) { $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($iterator as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); @rmdir($dir); }
    }
});

$test('crafting consumes JSON recipe and persists inventory changes to character JSON', static function () use ($assert, $equal): void {
    $dir = sys_get_temp_dir() . '/core-content-craft-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $universe = (new UniverseService($storage))->createOrLoad('content-crafting-seed');
        $character = (new CharacterService($storage))->create($universe['id'], 'Crafter');
        $character['inventory'] = ['resources' => ['raw_ore' => 6], 'ledger' => []];
        $storage->write('characters', $character['id'], $character);
        $recipe = ContentCatalog::entries('recipes')['refine_ore'];
        $result = (new CharacterCraftingService($storage))->craft($universe['id'], $character['id'], 'refine_ore', 2, 0);
        $saved = $storage->read('characters', $character['id']);
        $equal(0, $saved['inventory']['resources']['raw_ore'] ?? null, 'Input resource not consumed');
        $equal($recipe['output']['quantity'] * 2, $saved['inventory']['resources'][$recipe['output']['resource']] ?? null, 'Output resource not persisted');
        $assert(count($saved['inventory']['ledger']) === 2, 'Craft ledger entries were not persisted.');
        $assert(($result['recipe']['output']['resource'] ?? '') === $recipe['output']['resource'], 'Craft result differs from JSON recipe.');
    } finally {
        if (is_dir($dir)) { $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($iterator as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); @rmdir($dir); }
    }
});


$test('content management validates cross-catalog references before saving', static function () use ($assert): void {
    $dir = sys_get_temp_dir() . '/core-content-manager-' . bin2hex(random_bytes(8));
    try {
        $content = $dir . '/content'; mkdir($content, 0770, true);
        foreach (['items.json', 'recipes.json', 'discoveries.json', 'ship-modules.json'] as $file) copy(CORE_ROOT . '/config/content/' . $file, $content . '/' . $file);
        $manager = new ContentManagementService($content, $dir . '/backups');
        $recipes = json_decode((string)file_get_contents($content . '/recipes.json'), true, 512, JSON_THROW_ON_ERROR);
        $recipes['entries']['refine_ore']['inputs']['missing_resource'] = 1;
        try { $manager->validate('recipes', $recipes); throw new RuntimeException('Unknown item reference was accepted.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'Invalid recipe input'), 'Unexpected validation error: ' . $e->getMessage()); }
    } finally {
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); @rmdir($dir); }
    }
});

$test('item categories are JSON-backed and reject unknown item category IDs', static function () use ($assert, $equal): void {
    $categories = ContentCatalog::itemCategoriesDocument();
    $assert(isset($categories['entries']['resource'], $categories['entries']['material'], $categories['entries']['component']), 'Expected configured item categories are missing.');
    $all = ContentCatalog::allDocuments();
    $assert(isset($all['item_categories']['entries']['resource']), 'Item categories are not exposed through the content API document.');

    $dir = sys_get_temp_dir() . '/core-content-category-' . bin2hex(random_bytes(8));
    try {
        $content = $dir . '/content'; mkdir($content, 0770, true);
        foreach (['items.json', 'recipes.json', 'discoveries.json', 'ship-modules.json'] as $file) copy(CORE_ROOT . '/config/content/' . $file, $content . '/' . $file);
        $manager = new ContentManagementService($content, $dir . '/backups');
        $items = json_decode((string)file_get_contents($content . '/items.json'), true, 512, JSON_THROW_ON_ERROR);
        $items['entries']['raw_ore']['category'] = 'freeform_typo';
        try { $manager->validate('items', $items); throw new RuntimeException('Unknown item category was accepted.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'Invalid item category'), 'Unexpected category validation error: ' . $e->getMessage()); }
    } finally {
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); @rmdir($dir); }
    }
});

$test('item category field is a JSON-driven dropdown rather than free text', static function () use ($assert): void {
    $html = (string)file_get_contents(CORE_ROOT . '/index.php');
    $assert(str_contains($html, 'function itemCategoryOptions(selected'), 'Category dropdown helper is missing.');
    $assert(str_contains($html, 'data-field="category" required'), 'Item category form does not render a required select control.');
    $assert(str_contains($html, 'Category</label><select id="entryField_category"'), 'Item category form does not use a select element.');
    $assert(str_contains($html, 'config/content/item-categories.json'), 'Workbench does not explain the JSON-backed category source.');
    $application = (string)file_get_contents(CORE_ROOT . '/app/Application.php');
    $assert(str_contains($application, '/api/content/item-categories'), 'Dedicated item category API endpoint is missing.');
});

$test('content management atomically saves revisions and verifies previous catalog backup', static function () use ($assert, $equal): void {
    $dir = sys_get_temp_dir() . '/core-content-manager-save-' . bin2hex(random_bytes(8));
    try {
        $content = $dir . '/content'; mkdir($content, 0770, true);
        foreach (['items.json', 'recipes.json', 'discoveries.json', 'ship-modules.json'] as $file) copy(CORE_ROOT . '/config/content/' . $file, $content . '/' . $file);
        $manager = new ContentManagementService($content, $dir . '/backups');
        $doc = json_decode((string)file_get_contents($content . '/recipes.json'), true, 512, JSON_THROW_ON_ERROR);
        $doc['entries']['refine_ore']['description'] = 'Updated by regression test.';
        $result = $manager->save('recipes', 1, $doc);
        $equal(2, $result['revision']);
        $saved = json_decode((string)file_get_contents($content . '/recipes.json'), true, 512, JSON_THROW_ON_ERROR);
        $equal('Updated by regression test.', $saved['entries']['refine_ore']['description']);
        $backupPath = $dir . '/backups/content/' . $result['backup_file'];
        $assert(is_file($backupPath), 'Previous content backup is missing.');
        $old = json_decode((string)file_get_contents($backupPath), true, 512, JSON_THROW_ON_ERROR);
        $assert($old['entries']['refine_ore']['description'] !== $saved['entries']['refine_ore']['description'], 'Backup does not contain the previous definition.');
    } finally {
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); @rmdir($dir); }
    }
});

$test('content management rejects stale revisions without overwriting newer content', static function () use ($assert): void {
    $dir = sys_get_temp_dir() . '/core-content-manager-stale-' . bin2hex(random_bytes(8));
    try {
        $content = $dir . '/content'; mkdir($content, 0770, true);
        foreach (['items.json', 'recipes.json', 'discoveries.json', 'ship-modules.json'] as $file) copy(CORE_ROOT . '/config/content/' . $file, $content . '/' . $file);
        $manager = new ContentManagementService($content, $dir . '/backups');
        $doc = json_decode((string)file_get_contents($content . '/recipes.json'), true, 512, JSON_THROW_ON_ERROR);
        $doc['entries']['refine_ore']['description'] = 'First update.';
        $manager->save('recipes', 1, $doc);
        $doc['entries']['refine_ore']['description'] = 'Stale update.';
        try { $manager->save('recipes', 1, $doc); throw new RuntimeException('Stale content revision was accepted.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'revision conflict'), 'Unexpected stale revision response: ' . $e->getMessage()); }
        $saved = json_decode((string)file_get_contents($content . '/recipes.json'), true, 512, JSON_THROW_ON_ERROR);
        $assert($saved['entries']['refine_ore']['description'] === 'First update.', 'Stale update overwrote newer content.');
    } finally {
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); @rmdir($dir); }
    }
});

$test('item characteristic values persist through content save and reload', static function () use ($assert, $equal): void {
    $dir = sys_get_temp_dir() . '/core-item-characteristics-roundtrip-' . bin2hex(random_bytes(8));
    try {
        $content = $dir . '/content'; mkdir($content, 0770, true);
        foreach (['items.json', 'recipes.json', 'discoveries.json', 'ship-modules.json'] as $file) copy(CORE_ROOT . '/config/content/' . $file, $content . '/' . $file);
        $manager = new ContentManagementService($content, $dir . '/backups');
        $doc = json_decode((string)file_get_contents($content . '/items.json'), true, 512, JSON_THROW_ON_ERROR);
        $doc['entries']['raw_ore']['characteristics']['rarity'] = 'exceptional';
        $saved = $manager->save('items', 1, $doc);
        $equal(2, $saved['revision'], 'Item catalog revision did not increment.');
        $reloaded = json_decode((string)file_get_contents($content . '/items.json'), true, 512, JSON_THROW_ON_ERROR);
        $equal('exceptional', $reloaded['entries']['raw_ore']['characteristics']['rarity'], 'Characteristic value did not survive save/reload.');
        $backup = json_decode((string)file_get_contents($dir . '/backups/content/' . $saved['backup_file']), true, 512, JSON_THROW_ON_ERROR);
        $equal('common', $backup['entries']['raw_ore']['characteristics']['rarity'], 'Backup did not preserve the prior characteristic value.');
    } finally {
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); @rmdir($dir); }
    }
});

$test('item characteristics are defined in JSON and exposed by the aggregate content document', static function () use ($assert, $equal): void {
    $schema = ContentCatalog::itemCharacteristicsDocument();
    $assert(isset($schema['categories']['resource']['fields']['rarity']['options']['rare']), 'Rarity enum schema is missing.');
    $all = ContentCatalog::allDocuments();
    $assert(isset($all['item_characteristics']['entries']['resource']), 'Item characteristic schema is not exposed in aggregate content.');
    $assert(isset($all['items']['entries']['rare_ore']['characteristics']['rarity']), 'Existing item does not include characteristic metadata.');
    $equal('rare', $all['items']['entries']['rare_ore']['characteristics']['rarity']);
});

$test('required text characteristics reject blank and whitespace-only values', static function () use ($assert): void {
    $schema = ContentCatalog::itemCharacteristicsDocument();
    $schema['categories']['resource']['fields']['description_test'] = ['label'=>'Description test','type'=>'text','required'=>true,'min_length'=>1,'default'=>'valid'];
    foreach (['', '   '] as $bad) {
        $candidate = ContentCatalog::document('items');
        $candidate['entries']['raw_ore']['characteristics']['description_test'] = $bad;
        try { ContentCatalog::validateDocuments(['items'=>$candidate, 'recipes'=>ContentCatalog::document('recipes'), 'discoveries'=>ContentCatalog::document('discoveries'), 'ship_modules'=>ContentCatalog::document('ship_modules')], $schema); throw new RuntimeException('Blank required text was accepted.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'Invalid value for item characteristic'), 'Unexpected required-text validation error: '.$e->getMessage()); }
    }
});

$test('characteristic defaults and required text constraints are rendered in structured editor', static function () use ($assert): void {
    $html = (string)file_get_contents(CORE_ROOT . '/index.php');
    $assert(str_contains($html, 'field.default'), 'Schema defaults are not used to prefill characteristic controls.');
    $assert(str_contains($html, 'field.min_length'), 'Text minimum length is not rendered.');
    $assert(str_contains($html, 'if (control.required) throw new Error(`Characteristic ${key} is required.`)'), 'Required characteristic values are not enforced in the editor.');
});

$test('item characteristic values reject unknown enum values and unknown fields', static function () use ($assert): void {
    $items = ContentCatalog::document('items');
    $items['entries']['raw_ore']['characteristics']['rarity'] = 'ultra_cosmic';
    try { ContentCatalog::validateDocuments(['items'=>$items, 'recipes'=>ContentCatalog::document('recipes'), 'discoveries'=>ContentCatalog::document('discoveries'), 'ship_modules'=>ContentCatalog::document('ship_modules')]); throw new RuntimeException('Unknown characteristic enum value was accepted.'); }
    catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'Invalid value for item characteristic'), 'Unexpected characteristic validation error: ' . $e->getMessage()); }
    $items = ContentCatalog::document('items');
    $items['entries']['raw_ore']['characteristics']['made_up_field'] = 'bad';
    try { ContentCatalog::validateDocuments(['items'=>$items, 'recipes'=>ContentCatalog::document('recipes'), 'discoveries'=>ContentCatalog::document('discoveries'), 'ship_modules'=>ContentCatalog::document('ship_modules')]); throw new RuntimeException('Unknown characteristic field was accepted.'); }
    catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'Unknown item characteristic'), 'Unexpected unknown-field error: ' . $e->getMessage()); }
});

$test('structured item editor renders characteristic controls from the selected category schema', static function () use ($assert): void {
    $html = (string)file_get_contents(CORE_ROOT . '/index.php');
    $assert(str_contains($html, 'function renderCharacteristicFields(categoryId'), 'Dynamic characteristic renderer is missing.');
    $assert(str_contains($html, 'data-characteristic-type="enum"'), 'Enum characteristic dropdown rendering is missing.');
    $assert(str_contains($html, 'entry.characteristics=readCharacteristicValues()'), 'Item characteristics are not persisted from the structured form.');
    $assert(is_file(CORE_ROOT . '/config/content/item-characteristics.json'), 'Characteristic schema JSON is missing.');
    $app = (string)file_get_contents(CORE_ROOT . '/app/Application.php');
    $assert(str_contains($app, '/api/content/item-characteristics'), 'Characteristic schema API route is missing.');
});

$test('Workbench defaults to structured content editing with optional advanced JSON', static function () use ($assert): void {
    $html = (string)file_get_contents(CORE_ROOT . '/index.php');
    foreach (['Content Studio', 'contentEntrySelect', 'contentAddEntryButton', 'contentDeleteEntryButton', 'contentApplyEntryButton', 'contentAdvancedDetails', 'contentUseJsonButton', 'contentRefreshJsonButton'] as $needle) {
        $assert(str_contains($html, $needle), 'Missing structured content editor feature: ' . $needle);
    }
    $assert((bool)preg_match('/<details id="contentAdvancedDetails">/', $html), 'Advanced JSON editor should be collapsed by default.');
});


$test('module registry boots a demonstration module and exposes its service', static function () use ($assert, $equal): void {
    $services = new Core\Modules\ServiceContainer();
    $registry = new Core\Modules\ModuleRegistry(new Core\Modules\ModuleContext($services));
    $registry->register(new Core\Modules\Demo\DemoModule());
    $equal(['demo'], $registry->bootAll(), 'Unexpected module boot order.');
    $greeting = $services->get('demo.greeting');
    $assert(is_callable($greeting), 'Demo service was not registered as a callable.');
    $equal('Hello, CORE!', $greeting('CORE'), 'Demo module did not execute correctly.');
});
$test('module registry rejects duplicate module names', static function () use ($assert): void {
    $registry = new Core\Modules\ModuleRegistry(new Core\Modules\ModuleContext(new Core\Modules\ServiceContainer()));
    $registry->register(new Core\Modules\Demo\DemoModule());
    try { $registry->register(new Core\Modules\Demo\DemoModule()); }
    catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'already registered'), 'Unexpected duplicate-module error.'); return; }
    throw new RuntimeException('Duplicate module registration was accepted.');
});
$test('module registry rejects missing dependencies before booting modules', static function () use ($assert): void {
    $module = new class implements Core\Modules\ModuleInterface {
        public function name(): string { return 'needs_base'; }
        public function version(): string { return '1.0.0'; }
        public function dependencies(): array { return ['base']; }
        public function boot(Core\Modules\ModuleContext $context): void { throw new RuntimeException('Must not boot.'); }
    };
    $registry = new Core\Modules\ModuleRegistry(new Core\Modules\ModuleContext(new Core\Modules\ServiceContainer()));
    $registry->register($module);
    try { $registry->bootAll(); }
    catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), "requires missing module 'base'"), 'Unexpected missing-dependency error.'); return; }
    throw new RuntimeException('Missing module dependency was accepted.');
});
$test('module registry boots dependencies before dependent modules', static function () use ($equal): void {
    $order = [];
    $base = new class($order) implements Core\Modules\ModuleInterface {
        public array $order;
        public function __construct(array &$order) { $this->order =& $order; }
        public function name(): string { return 'base'; }
        public function version(): string { return '1.0.0'; }
        public function dependencies(): array { return []; }
        public function boot(Core\Modules\ModuleContext $context): void { $this->order[] = 'base'; }
    };
    $child = new class($order) implements Core\Modules\ModuleInterface {
        public array $order;
        public function __construct(array &$order) { $this->order =& $order; }
        public function name(): string { return 'child'; }
        public function version(): string { return '1.0.0'; }
        public function dependencies(): array { return ['base']; }
        public function boot(Core\Modules\ModuleContext $context): void { $this->order[] = 'child'; }
    };
    $registry = new Core\Modules\ModuleRegistry(new Core\Modules\ModuleContext(new Core\Modules\ServiceContainer()));
    $registry->register($child); $registry->register($base); $registry->bootAll();
    $equal(['base', 'child'], $order, 'Modules did not boot in dependency order.');
});
$test('module registry detects dependency cycles', static function () use ($assert): void {
    $a = new class implements Core\Modules\ModuleInterface {
        public function name(): string { return 'cycle_a'; }
        public function version(): string { return '1'; }
        public function dependencies(): array { return ['cycle_b']; }
        public function boot(Core\Modules\ModuleContext $context): void {}
    };
    $b = new class implements Core\Modules\ModuleInterface {
        public function name(): string { return 'cycle_b'; }
        public function version(): string { return '1'; }
        public function dependencies(): array { return ['cycle_a']; }
        public function boot(Core\Modules\ModuleContext $context): void {}
    };
    $registry = new Core\Modules\ModuleRegistry(new Core\Modules\ModuleContext(new Core\Modules\ServiceContainer()));
    $registry->register($a); $registry->register($b);
    try { $registry->bootAll(); }
    catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'dependency cycle'), 'Unexpected cycle error.'); return; }
    throw new RuntimeException('Module dependency cycle was accepted.');
});
$test('service container rejects duplicate IDs and reports missing services', static function () use ($assert, $equal): void {
    $services = new Core\Modules\ServiceContainer();
    $services->set('example.value', 42);
    $equal(42, $services->get('example.value'));
    try { $services->set('example.value', 99); throw new RuntimeException('Duplicate service ID was accepted.'); }
    catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'already registered'), 'Unexpected duplicate service error.'); }
    try { $services->get('missing'); }
    catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'not registered'), 'Unexpected missing service error.'); return; }
    throw new RuntimeException('Missing service lookup was accepted.');
});

$test('Universe assignment displays the player-entered seed instead of the internal universe ID', static function () use ($assert): void {
    $action = file_get_contents(CORE_ROOT . '/player/player-action.php');
    $selection = file_get_contents(CORE_ROOT . '/player/selection.php');
    $assert(is_string($action) && str_contains($action, "core_flash_universe_seed'] = \$seed"), 'Universe action must store the entered seed in a session flash.');
    $assert(is_string($selection) && str_contains($selection, 'Your universe seed:') && str_contains($selection, 'core_flash_universe_seed'), 'Selection screen must show and consume the seed flash.');
    $assert(str_contains($selection, 'unset($_SESSION[\'core_flash_universe_seed\'])'), 'Seed flash must be one-time.');
});
$test('Character creation includes an interactive Babylon.js primitive preview', static function () use ($assert): void {
    $page = file_get_contents(CORE_ROOT . '/player/character-create.php');
    $js = file_get_contents(CORE_ROOT . '/player/character-preview.js');
    $assert(is_string($page) && str_contains($page, 'cdn.babylonjs.com/babylon.js') && str_contains($page, 'characterPreviewCanvas'), 'Character creation page must include Babylon.js and its preview canvas.');
    foreach (['BABYLON.Engine', 'BABYLON.ArcRotateCamera', 'CreateSphere', 'CreateBox', 'attachControl', 'previewColor', '3D preview failed'] as $needle) {
        $assert(is_string($js) && str_contains($js, $needle), 'Character preview script missing marker: ' . $needle);
    }
});

$test('Player game API root uses the public URL path, not the filesystem path', static function () use ($assert): void {
    $game = file_get_contents(CORE_ROOT . '/player/game.php');
    $js = file_get_contents(CORE_ROOT . '/player/player.js');
    $assert(is_string($game) && str_contains($game, '$urlRoot = rtrim(str_replace'), 'Player game page must derive a URL root from SCRIPT_NAME.');
    $assert(str_contains($game, 'data-api-root="<?= htmlspecialchars($urlRoot'), 'Player page must pass the URL root to its JavaScript.');
    $assert(str_contains($js, 'const api = (path) => `${apiRoot}/api/${path}`;'), 'Player API requests must be based on the public URL root.');
    $assert(is_file(CORE_ROOT . '/api/character-options.php'), 'Character options endpoint wrapper must exist.');
});

$test('Universe settings are deterministic, persisted in generated baseline, and affect identity', static function () use ($assert, $equal): void {
    $generator = new Core\Modules\Universe\UniverseGenerator();
    $aSettings = ['system_density'=>20,'anomaly_rate'=>10,'binary_star_rate'=>20,'nebula_presence'=>30];
    $bSettings = ['system_density'=>80,'anomaly_rate'=>60,'binary_star_rate'=>40,'nebula_presence'=>50];
    $a = $generator->generate('same-visible-seed', $aSettings);
    $equal($a, $generator->generate('same-visible-seed', $aSettings));
    $b = $generator->generate('same-visible-seed', $bSettings);
    $assert($a['id'] !== $b['id'], 'Different settings with the same seed must produce different universe IDs.');
    $equal($aSettings, $a['settings']);
    try { $generator->generate('invalid', ['system_density'=>101]); throw new RuntimeException('Out-of-range setting accepted.'); }
    catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), '0 to 100')); }
});
$test('Play page boots assigned universe and selected character directly', static function () use ($assert): void {
    $game = file_get_contents(CORE_ROOT . '/player/game.php'); $js = file_get_contents(CORE_ROOT . '/player/player.js');
    $assert(str_contains($game, "'universe' => \$assignedUniverse") && str_contains($game, "'characterId' => \$selectedCharacterId"), 'Game page must pass its validated universe and character to JS.');
    $assert(str_contains($js, 'window.CORE_BOOT?.universe') && str_contains($js, 'activateUniverse(window.CORE_BOOT.universe'), 'Player JS must activate the actual assigned universe without reseeding.');
});
$test('Universe creation exposes settings and uses server-generated Babylon preview data', static function () use ($assert): void {
    $page = file_get_contents(CORE_ROOT . '/player/universe-create.php');
    foreach (['system_density','anomaly_rate','binary_star_rate','nebula_presence','universePreviewCanvas','api/universe-preview.php'] as $needle) $assert(str_contains($page, $needle), 'Universe creation missing '.$needle);
});

$test('Character Selection removes disabled Play and provides separate 3D character and universe previews', static function () use ($assert): void {
    $selection = (string)file_get_contents(CORE_ROOT . '/player/selection.php');
    $assert(!str_contains($selection, 'Play · Universe required'), 'Disabled Play button is still present');
    $assert(str_contains($selection, 'character-portrait-canvas'), 'Character portrait canvas missing');
    $assert(str_contains($selection, 'character-universe-canvas'), 'Universe preview canvas missing');
    $assert(str_contains($selection, 'character-roster-preview.js'), 'Roster preview script is not loaded');
});
$test('Gameplay does not expose redundant character creation action', static function () use ($assert): void {
    $game = (string)file_get_contents(CORE_ROOT . '/player/game.php');
    $js = (string)file_get_contents(CORE_ROOT . '/player/player.js');
    $assert(!str_contains($game, 'id="newCharacterButton"'), 'Gameplay still exposes Create a character button');
    $assert(!str_contains($js, "$('newCharacterButton').addEventListener"), 'Gameplay still wires the removed character creation action');
});
$test('Workbench character refresh can discover all saved characters without a preselected universe', static function () use ($assert): void {
    $workbench = (string)file_get_contents(CORE_ROOT . '/index.php');
    $assert(str_contains($workbench, "fetch('api/data-management.php'"), 'Global inventory fallback missing');
    $assert(str_contains($workbench, 'Global character inventory'), 'Global character inventory feedback missing');
});
$test('New universe records persist the original seed and generation settings', static function () use ($assert, $equal): void {
    $dir = sys_get_temp_dir() . '/core-universe-settings-' . bin2hex(random_bytes(6));
    try {
        $storage = new JsonStorage($dir);
        $settings = ['system_density'=>42,'anomaly_rate'=>18,'binary_star_rate'=>37,'nebula_presence'=>22];
        $universe = (new Core\Modules\Universe\UniverseService($storage))->createOrLoad('persistent-seed-test', $settings);
        $equal('persistent-seed-test', $universe['seed']);
        $equal($settings, $universe['settings']);
        $loaded = $storage->read('universes', $universe['id']);
        $equal('persistent-seed-test', $loaded['seed']);
        $equal($settings, $loaded['settings']);
    } finally {
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); rmdir($dir); }
    }
});

echo "\nCORE QA summary: {$passed} passed, {$failed} failed\n";
if ($failed > 0) {
    foreach ($failures as [$name, $message]) echo " - {$name}: {$message}\n";
    exit(1);
}
exit(0);
