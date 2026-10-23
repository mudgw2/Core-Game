<?php
declare(strict_types=1);

/**
 * Browser-triggered, development-only QA suite. Tests use temporary storage.
 * Disable config.qa.enabled before exposing CORE outside a trusted development LAN.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$root = dirname(__DIR__);
define('CORE_ROOT', $root);
require $root . '/bootstrap.php';

$configFile = $root . '/config/app.php';
$config = is_file($configFile) ? require $configFile : [];
$remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$isLocal = in_array($remote, ['127.0.0.1', '::1'], true)
    || preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $remote) === 1
    || str_starts_with(strtolower($remote), 'fc')
    || str_starts_with(strtolower($remote), 'fd');
if (empty($config['qa']['enabled']) || ($config['app']['environment'] ?? 'production') !== 'development' || !$isLocal) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => ['code' => 'qa_disabled', 'message' => 'Browser QA is disabled or this client is not on a permitted local network.']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'Use POST to run QA.']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

use Core\Modules\Universe\UniverseGenerator;
use Core\Modules\Universe\UniverseService;
use Core\Storage\JsonStorage;

$results = [];
$run = static function (string $name, callable $test) use (&$results): void {
    try {
        $details = $test();
        $results[] = ['name' => $name, 'status' => 'PASS', 'details' => is_string($details) ? $details : 'Passed'];
    } catch (Throwable $e) {
        $results[] = ['name' => $name, 'status' => 'FAIL', 'details' => $e->getMessage()];
    }
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$equal = static function (mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) throw new RuntimeException($message);
};
$cleanup = static function (string $dir): void {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($dir);
};

$run('Same seed produces identical baseline', static function () use ($equal): string {
    $g = new UniverseGenerator();
    $equal($g->generate('core-ui-qa-seed'), $g->generate('core-ui-qa-seed'), 'Baseline differs for the same seed.');
    return 'Repeated generation matched exactly.';
});
$run('Seed whitespace is normalized', static function () use ($equal): string {
    $g = new UniverseGenerator();
    $equal($g->generate('  core-ui-qa-seed  '), $g->generate('core-ui-qa-seed'), 'Whitespace normalization mismatch.');
    return 'Trimmed and untrimmed seeds matched.';
});
$run('Different seeds produce different universe IDs', static function () use ($assert): string {
    $g = new UniverseGenerator();
    $assert($g->generate('core-ui-qa-alpha')['id'] !== $g->generate('core-ui-qa-beta')['id'], 'Different seeds returned the same ID.');
    return 'Distinct seeds produced distinct IDs.';
});
$run('Generated system structure and coordinates are valid', static function () use ($assert): string {
    $baseline = (new UniverseGenerator())->generate('core-ui-qa-seed');
    $assert($baseline['system_count'] === count($baseline['systems']), 'System count does not match systems array.');
    $assert($baseline['system_count'] >= 8 && $baseline['system_count'] <= 16, 'System count is outside expected bounds.');
    $ids = [];
    foreach ($baseline['systems'] as $system) {
        $assert(isset($system['id'], $system['name'], $system['x'], $system['y'], $system['classification']), 'System missing required fields.');
        $assert(!in_array($system['id'], $ids, true), 'Duplicate system ID.');
        $ids[] = $system['id'];
        $assert($system['x'] >= -10000 && $system['x'] <= 10000 && $system['y'] >= -10000 && $system['y'] <= 10000, 'Coordinates out of bounds.');
    }
    return count($ids) . ' systems checked.';
});
$run('Create and reload preserve identity and baseline', static function () use ($equal, $assert, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $service = new UniverseService(new JsonStorage($dir));
        $first = $service->createOrLoad('core-ui-qa-persistence');
        $second = $service->createOrLoad('core-ui-qa-persistence');
        $equal($first['id'], $second['id'], 'Universe ID changed on reload.');
        $equal($first['created_at'], $second['created_at'], 'Creation timestamp changed on reload.');
        $equal($first['baseline'], $second['baseline'], 'Baseline changed on reload.');
        $assert(is_file($dir . '/universes/' . $first['id'] . '.json'), 'Persistence file was not created.');
        return 'Saved and reloaded in a temporary directory.';
    } finally { $cleanup($dir); }
});
$run('Unsafe storage paths are rejected', static function () use ($assert, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        try { $storage->read('../escape', 'record'); }
        catch (RuntimeException $e) {
            $assert(str_contains($e->getMessage(), 'Invalid storage path segment.'), 'Unexpected validation exception.');
            return 'Traversal-like collection name rejected.';
        }
        throw new RuntimeException('Unsafe collection name was accepted.');
    } finally { $cleanup($dir); }
});

$run('JSON writes are complete and readable after replacement', static function () use ($equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $record = ['id' => 'record-1', 'payload' => str_repeat('CORE-persistence-', 2048), 'revision' => 1];
        $storage->write('records', 'record-1', $record);
        $equal($record, $storage->read('records', 'record-1'), 'Written record did not round-trip exactly.');
        $record['revision'] = 2;
        $storage->write('records', 'record-1', $record);
        $equal($record, $storage->read('records', 'record-1'), 'Replaced record did not round-trip exactly.');
        return 'Large record and replacement both round-tripped exactly.';
    } finally { $cleanup($dir); }
});
$run('Writes use a per-record lock and leave no temporary files', static function () use ($assert, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $storage->write('records', 'locked-record', ['value' => 'stable']);
        $assert(is_file($dir . '/records/locked-record.json.lock'), 'Per-record lock file was not created.');
        $files = array_values(array_filter(scandir($dir . '/records') ?: [], static fn(string $name): bool => str_starts_with($name, '.core-')));
        $assert($files === [], 'Temporary files were left behind after a successful write.');
        return 'Record lock exists and successful writes leave no temporary files.';
    } finally { $cleanup($dir); }
});
$run('Concurrent-style create attempts preserve the first stored record', static function () use ($equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $first = $storage->createIfAbsent('records', 'shared-id', ['id' => 'shared-id', 'owner' => 'first']);
        $second = $storage->createIfAbsent('records', 'shared-id', ['id' => 'shared-id', 'owner' => 'second']);
        $equal('first', $second['owner'], 'A later create attempt replaced the first record.');
        $equal($first, $storage->read('records', 'shared-id'), 'Stored record changed after a duplicate create attempt.');
        return 'Duplicate create returned the original record without overwriting it.';
    } finally { $cleanup($dir); }
});
$run('Universe state updates enforce revision checks', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $service = new UniverseService(new JsonStorage($dir));
        $universe = $service->createOrLoad('core-ui-qa-revision');
        $updated = $service->updateState($universe['id'], 0, ['type' => 'qa_change', 'value' => 1]);
        $equal(1, $updated['state']['revision'], 'Revision did not increment.');
        $equal(1, count($updated['state']['changes']), 'Change was not appended.');
        try { $service->updateState($universe['id'], 0, ['type' => 'stale_change']); }
        catch (RuntimeException $e) {
            $assert(str_contains($e->getMessage(), 'Revision conflict:'), 'Unexpected stale revision error.');
            $reloaded = (new JsonStorage($dir))->read('universes', $universe['id']);
            $equal(1, $reloaded['state']['revision'], 'Stale update modified the revision.');
            $equal(1, count($reloaded['state']['changes']), 'Stale update was applied.');
            return 'Current revision committed; stale revision rejected without changing saved state.';
        }
        throw new RuntimeException('Stale revision was accepted.');
    } finally { $cleanup($dir); }
});

$run('Universe state changes require a type', static function () use ($assert, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $service = new UniverseService(new JsonStorage($dir));
        $universe = $service->createOrLoad('core-ui-qa-change-type');
        try { $service->updateState($universe['id'], 0, ['value' => 'missing type']); }
        catch (RuntimeException $e) {
            $assert(str_contains($e->getMessage(), 'non-empty string type'), 'Unexpected validation error.');
            $saved = (new JsonStorage($dir))->read('universes', $universe['id']);
            $assert($saved['state']['revision'] === 0, 'Invalid change modified saved state.');
            return 'Missing change type rejected without modifying the universe.';
        }
        throw new RuntimeException('Change without type was accepted.');
    } finally { $cleanup($dir); }
});

$run('Oversized universe state changes are rejected', static function () use ($assert, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $service = new UniverseService(new JsonStorage($dir));
        $universe = $service->createOrLoad('core-ui-qa-change-size');
        try { $service->updateState($universe['id'], 0, ['type' => 'oversized', 'payload' => str_repeat('x', 17000)]); }
        catch (RuntimeException $e) {
            $assert(str_contains($e->getMessage(), '16 KB limit'), 'Unexpected size validation error.');
            $saved = (new JsonStorage($dir))->read('universes', $universe['id']);
            $assert($saved['state']['revision'] === 0, 'Oversized change modified saved state.');
            return 'Changes larger than 16 KB rejected without modifying the universe.';
        }
        throw new RuntimeException('Oversized change was accepted.');
    } finally { $cleanup($dir); }
});

$run('State changes are stored as versioned event records', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $service = new UniverseService(new JsonStorage($dir));
        $universe = $service->createOrLoad('core-ui-qa-event-schema');
        $updated = $service->updateState($universe['id'], 0, ['type' => 'note_added', 'value' => 'hello', 'source' => 'qa']);
        $event = $updated['state']['changes'][0] ?? null;
        $assert(is_array($event), 'No event record was stored.');
        foreach (['schema_version', 'event_id', 'type', 'payload', 'revision', 'recorded_at'] as $field) {
            $assert(array_key_exists($field, $event), 'Event is missing ' . $field . '.');
        }
        $equal(1, $event['schema_version'], 'Unexpected event schema version.');
        $equal('note_added', $event['type'], 'Event type was not normalized.');
        $equal(['value' => 'hello', 'source' => 'qa'], $event['payload'], 'Event payload does not preserve custom fields.');
        $equal(1, $event['revision'], 'Event revision does not match committed revision.');
        $assert(preg_match('/^[a-f0-9]{32}$/', $event['event_id']) === 1, 'Event ID is not a 128-bit hexadecimal ID.');
        $assert(strtotime($event['recorded_at']) !== false, 'Event timestamp is invalid.');
        return 'Stored event has schema version, unique ID, type, payload, revision, and timestamp.';
    } finally { $cleanup($dir); }
});

$run('Invalid event type formats are rejected without mutation', static function () use ($assert, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $service = new UniverseService(new JsonStorage($dir));
        $universe = $service->createOrLoad('core-ui-qa-event-type');
        try { $service->updateState($universe['id'], 0, ['type' => 'Not Valid!', 'value' => 1]); }
        catch (RuntimeException $e) {
            $assert(str_contains($e->getMessage(), 'Event type must start'), 'Unexpected invalid event type error.');
            $saved = (new JsonStorage($dir))->read('universes', $universe['id']);
            $assert($saved['state']['revision'] === 0, 'Invalid event changed the revision.');
            $assert($saved['state']['changes'] === [], 'Invalid event was appended.');
            return 'Invalid event type rejected without changing saved state.';
        }
        throw new RuntimeException('Invalid event type was accepted.');
    } finally { $cleanup($dir); }
});

$run('Corrupt JSON is rejected instead of silently accepted', static function () use ($assert, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $collection = $dir . '/records';
        if (!mkdir($collection, 0770, true) && !is_dir($collection)) throw new RuntimeException('Could not prepare QA fixture.');
        file_put_contents($collection . '/broken.json', '{invalid json');
        try { (new JsonStorage($dir))->read('records', 'broken'); }
        catch (RuntimeException $e) {
            $assert(str_contains($e->getMessage(), 'Stored JSON record is invalid'), 'Unexpected corrupt-record error.');
            return 'Malformed JSON raises an explicit storage error.';
        }
        throw new RuntimeException('Malformed JSON was silently accepted.');
    } finally { $cleanup($dir); }
});

$run('note_added events update the notes projection', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $service = new UniverseService(new JsonStorage($dir));
        $universe = $service->createOrLoad('core-ui-qa-note-projection');
        $updated = $service->updateState($universe['id'], 0, ['type' => 'note_added', 'value' => 'Hello from QA']);
        $notes = $updated['state']['projections']['notes'] ?? [];
        $equal(1, count($notes), 'Note event did not create one projected note.');
        $equal('Hello from QA', $notes[0]['text'] ?? null, 'Projected note text did not match.');
        $equal(1, $notes[0]['revision'] ?? null, 'Projected note revision did not match.');
        $equal('applied', $updated['state']['event_processing'][0]['status'] ?? null, 'Event processing status was not applied.');
        return 'note_added created a derived note and recorded successful processing.';
    } finally { $cleanup($dir); }
});

$run('Invalid note_added payload aborts the transaction', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $service = new UniverseService(new JsonStorage($dir));
        $universe = $service->createOrLoad('core-ui-qa-invalid-note');
        try { $service->updateState($universe['id'], 0, ['type' => 'note_added', 'value' => '   ']); }
        catch (RuntimeException $e) {
            $assert(str_contains($e->getMessage(), 'non-empty text or value string'), 'Unexpected invalid note error.');
            $saved = (new JsonStorage($dir))->read('universes', $universe['id']);
            $equal(0, $saved['state']['revision'], 'Rejected note advanced the revision.');
            $equal([], $saved['state']['changes'], 'Rejected note was appended to event history.');
            $assert(!isset($saved['state']['projections']['notes']), 'Rejected note changed the notes projection.');
            return 'Invalid note payload rejected with no committed state changes.';
        }
        throw new RuntimeException('Empty note payload was accepted.');
    } finally { $cleanup($dir); }
});

$run('Unknown event types are recorded as unhandled', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $service = new UniverseService(new JsonStorage($dir));
        $universe = $service->createOrLoad('core-ui-qa-unhandled-event');
        $updated = $service->updateState($universe['id'], 0, ['type' => 'exploration_started', 'system_id' => 'system-test']);
        $equal(1, $updated['state']['revision'], 'Unknown but valid event was not recorded.');
        $equal('unhandled', $updated['state']['event_processing'][0]['status'] ?? null, 'Unknown event was not marked unhandled.');
        $assert(count($updated['state']['changes']) === 1, 'Unknown event was not preserved in history.');
        return 'Valid future event preserved in history and explicitly marked unhandled.';
    } finally { $cleanup($dir); }
});

$passed = count(array_filter($results, static fn(array $r): bool => $r['status'] === 'PASS'));
$failed = count($results) - $passed;
http_response_code($failed === 0 ? 200 : 500);
echo json_encode(['ok' => $failed === 0, 'application' => 'CORE', 'version' => '0.1.12', 'passed' => $passed, 'failed' => $failed, 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
