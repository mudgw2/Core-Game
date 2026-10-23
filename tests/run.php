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

echo "\nCORE QA summary: {$passed} passed, {$failed} failed\n";
if ($failed > 0) {
    foreach ($failures as [$name, $message]) echo " - {$name}: {$message}\n";
    exit(1);
}
exit(0);
