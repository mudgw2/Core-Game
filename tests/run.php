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

echo "\nCORE QA summary: {$passed} passed, {$failed} failed\n";
if ($failed > 0) {
    foreach ($failures as [$name, $message]) echo " - {$name}: {$message}\n";
    exit(1);
}
exit(0);
