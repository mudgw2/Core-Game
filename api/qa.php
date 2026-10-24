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
use Core\Modules\Characters\CharacterService;
use Core\Modules\Characters\CharacterTravelService;
use Core\Modules\Characters\CharacterScanService;
use Core\Modules\Universe\UniverseService;
use Core\Modules\Universe\NavigationGraph;
use Core\Modules\Universe\NavigationRouteService;
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


$run('system_explored events add a validated system to the exploration projection', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $service = new UniverseService(new JsonStorage($dir));
        $universe = $service->createOrLoad('core-ui-qa-exploration-valid');
        $system = $universe['baseline']['systems'][0];
        $updated = $service->updateState($universe['id'], 0, ['type' => 'system_explored', 'system_id' => $system['id']]);
        $explored = $updated['state']['projections']['explored_systems'] ?? [];
        $equal(1, count($explored), 'Exploration projection should contain one system.');
        $equal($system['id'], $explored[0]['system_id'] ?? null, 'Explored system ID did not match.');
        $equal($system['name'], $explored[0]['name'] ?? null, 'Explored system name did not match baseline.');
        $equal('applied', $updated['state']['event_processing'][0]['status'] ?? null, 'Exploration event was not applied.');
        return 'A valid generated system was added to explored_systems with baseline details.';
    } finally { $cleanup($dir); }
});

$run('system_explored rejects system IDs outside the universe without mutation', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $service = new UniverseService(new JsonStorage($dir));
        $universe = $service->createOrLoad('core-ui-qa-exploration-invalid');
        try { $service->updateState($universe['id'], 0, ['type' => 'system_explored', 'system_id' => 'sys_not_in_this_universe']); }
        catch (RuntimeException $e) {
            $assert(str_contains($e->getMessage(), 'does not belong to this universe'), 'Unexpected invalid-system error.');
            $saved = (new JsonStorage($dir))->read('universes', $universe['id']);
            $equal(0, $saved['state']['revision'], 'Rejected exploration advanced revision.');
            $equal([], $saved['state']['changes'], 'Rejected exploration was appended to history.');
            $assert(!isset($saved['state']['projections']['explored_systems']), 'Rejected exploration changed projection.');
            return 'Foreign system ID rejected and transaction left the universe unchanged.';
        }
        throw new RuntimeException('Foreign system ID was accepted.');
    } finally { $cleanup($dir); }
});

$run('re-exploring a system does not duplicate its exploration projection', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $service = new UniverseService(new JsonStorage($dir));
        $universe = $service->createOrLoad('core-ui-qa-exploration-duplicate');
        $systemId = $universe['baseline']['systems'][0]['id'];
        $first = $service->updateState($universe['id'], 0, ['type' => 'system_explored', 'system_id' => $systemId]);
        $second = $service->updateState($universe['id'], 1, ['type' => 'system_explored', 'system_id' => $systemId]);
        $equal(1, count($second['state']['projections']['explored_systems'] ?? []), 'Duplicate exploration created duplicate projection entries.');
        $equal(2, count($second['state']['changes'] ?? []), 'Both submitted events should remain in event history.');
        $equal('already_explored', $second['state']['event_processing'][1]['status'] ?? null, 'Repeat exploration status was not explicit.');
        $equal(1, $first['state']['revision'], 'First exploration revision incorrect.');
        $equal(2, $second['state']['revision'], 'Second exploration revision incorrect.');
        return 'Repeated exploration remains in history but does not duplicate the explored-system projection.';
    } finally { $cleanup($dir); }
});

$run('Character creation links a valid character to its universe', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-character-create');
        $character = (new CharacterService($storage))->create($universe['id'], 'Mira Voss', 'Explorer');
        $equal($universe['id'], $character['universe_id'], 'Character linked to the wrong universe.');
        $equal('Mira Voss', $character['name'], 'Character name was not preserved.');
        $equal('Explorer', $character['role'], 'Character role was not preserved.');
        $assert(preg_match('/^chr_[a-f0-9]{24}$/', $character['id']) === 1, 'Character ID format is invalid.');
        $equal(0, $character['revision'], 'New character revision must start at zero.');
        return 'Character record contains a unique ID and valid universe link.';
    } finally { $cleanup($dir); }
});

$run('Character records persist and list only within their universe', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $universes = new UniverseService($storage);
        $one = $universes->createOrLoad('core-ui-qa-character-list-one');
        $two = $universes->createOrLoad('core-ui-qa-character-list-two');
        $service = new CharacterService($storage);
        $created = $service->create($one['id'], 'Aster Vale', 'Scout');
        $service->create($two['id'], 'Niko Renn', 'Engineer');
        $listed = $service->listForUniverse($one['id']);
        $equal(1, count($listed), 'Universe character list contains an unexpected number of records.');
        $equal($created['id'], $listed[0]['id'], 'Persisted character ID changed.');
        $equal($one['id'], $listed[0]['universe_id'], 'Character from another universe leaked into the list.');
        return 'Character persists and universe-scoped listing excludes other universes.';
    } finally { $cleanup($dir); }
});

$run('Character creation rejects a nonexistent universe', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        try { (new CharacterService($storage))->create('uni_missing', 'Mira Voss'); }
        catch (RuntimeException $e) {
            $assert(str_contains($e->getMessage(), 'universe does not exist'), 'Unexpected missing-universe error.');
            $equal([], $storage->all('characters'), 'Invalid character was persisted.');
            return 'Unknown universe rejected without creating a character record.';
        }
        throw new RuntimeException('Character was created for a nonexistent universe.');
    } finally { $cleanup($dir); }
});

$run('Character name and role validation rejects invalid records', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-character-validation');
        $service = new CharacterService($storage);
        foreach ([['A', 'Explorer'], ['Valid Name', str_repeat('x', 41)]] as [$name, $role]) {
            try { $service->create($universe['id'], $name, $role); }
            catch (RuntimeException $e) { continue; }
            throw new RuntimeException('Invalid character fields were accepted.');
        }
        $equal([], $storage->all('characters'), 'Invalid character records were persisted.');
        return 'Too-short names and oversized roles are rejected without persistence.';
    } finally { $cleanup($dir); }
});

$run('Character listing rejects an unknown universe', static function () use ($assert, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $service = new CharacterService(new JsonStorage($dir));
        try { $service->listForUniverse('uni_missing'); }
        catch (RuntimeException $e) {
            $assert(str_contains($e->getMessage(), 'universe does not exist'), 'Unexpected missing-universe error.');
            return 'Character listing requires an existing universe.';
        }
        throw new RuntimeException('Unknown universe listing was accepted.');
    } finally { $cleanup($dir); }
});

$run('New characters start in the universe first system', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-character-start');
        $character = (new CharacterService($storage))->create($universe['id'], 'Location Test');
        $firstSystemId = $universe['baseline']['systems'][0]['id'];
        $equal($firstSystemId, $character['current_system_id'] ?? null, 'Character did not start in the first system.');
        $equal($firstSystemId, $character['location']['system_id'] ?? null, 'Location model does not match current system.');
        return 'Character starts in the first generated system with a location timestamp.';
    } finally { $cleanup($dir); }
});

$run('Character can move to another system in its universe', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-character-move');
        $service = new CharacterService($storage);
        $character = $service->create($universe['id'], 'Mover');
        $graph = NavigationGraph::build($universe['baseline']['systems']);
        $target = $graph['neighbors'][$character['current_system_id']][0];
        $moved = $service->move($universe['id'], $character['id'], $target, 0);
        $equal($target, $moved['current_system_id'] ?? null, 'Current system was not updated.');
        $equal($target, $moved['location']['system_id'] ?? null, 'Location projection was not updated.');
        $equal(1, $moved['revision'], 'Character revision did not increment.');
        $persisted = $moved; unset($persisted['_movement_status']);
        $equal($persisted, $storage->read('characters', $character['id']), 'Moved character was not persisted.');
        return 'Location persisted and character revision advanced from 0 to 1.';
    } finally { $cleanup($dir); }
});

$run('Character cannot move to a system from another universe', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $universes = new UniverseService($storage);
        $first = $universes->createOrLoad('core-ui-qa-character-universe-a');
        $second = $universes->createOrLoad('core-ui-qa-character-universe-b');
        $service = new CharacterService($storage);
        $character = $service->create($first['id'], 'Boundary Test');
        try { $service->move($first['id'], $character['id'], $second['baseline']['systems'][0]['id'], 0); }
        catch (RuntimeException $e) {
            $assert(str_contains($e->getMessage(), 'does not belong'), 'Unexpected cross-universe validation error.');
            $saved = $storage->read('characters', $character['id']);
            $equal($character['current_system_id'], $saved['current_system_id'], 'Rejected move changed character location.');
            $equal(0, $saved['revision'], 'Rejected move advanced character revision.');
            return 'Cross-universe destination rejected without modifying the character.';
        }
        throw new RuntimeException('Cross-universe system was accepted.');
    } finally { $cleanup($dir); }
});

$run('Character movement rejects stale revisions', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-character-stale');
        $service = new CharacterService($storage);
        $character = $service->create($universe['id'], 'Revision Test');
        $systems = $universe['baseline']['systems'];
        $graph = NavigationGraph::build($systems);
        $firstDestination = $graph['neighbors'][$character['current_system_id']][0];
        $service->move($universe['id'], $character['id'], $firstDestination, 0);
        try { $service->move($universe['id'], $character['id'], $systems[2]['id'], 0); }
        catch (RuntimeException $e) {
            $assert(str_contains($e->getMessage(), 'revision conflict'), 'Unexpected stale revision error.');
            $saved = $storage->read('characters', $character['id']);
            $equal($firstDestination, $saved['current_system_id'], 'Stale move changed the current system.');
            $equal(1, $saved['revision'], 'Stale move changed the revision.');
            return 'Stale character movement rejected without overwriting the newer location.';
        }
        throw new RuntimeException('Stale character movement was accepted.');
    } finally { $cleanup($dir); }
});


$run('Repeating a move to the current system is idempotent', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-idempotent-move');
        $service = new CharacterService($storage);
        $character = $service->create($universe['id'], 'Idempotent Mover');
        $systemId = $character['current_system_id'];
        $result = $service->move($universe['id'], $character['id'], $systemId, 0);
        $equal('already_at_destination', $result['_movement_status'] ?? null, 'Same-system movement status was not explicit.');
        $equal(0, $result['revision'], 'Same-system movement incremented the revision.');
        $saved = $storage->read('characters', $character['id']);
        $equal(0, $saved['revision'], 'Same-system movement changed the saved revision.');
        $assert(!array_key_exists('_movement_status', $saved), 'Internal movement metadata was persisted.');
        return 'Repeated same-destination move returns already_at_destination and leaves revision unchanged.';
    } finally { $cleanup($dir); }
});

$run('Character creation records its starting system as explored', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-start-exploration');
        $character = (new CharacterService($storage))->create($universe['id'], 'Starting Explorer');
        $visited = $character['exploration']['explored_systems'] ?? [];
        $equal(1, count($visited), 'Starting system was not recorded exactly once.');
        $equal($character['current_system_id'], $visited[0]['system_id'] ?? null, 'Starting explored system does not match current location.');
        $assert(isset($visited[0]['first_visited_at'], $visited[0]['first_visited_revision']), 'Starting visit metadata is incomplete.');
        return 'Starting system appears once in character-specific exploration history.';
    } finally { $cleanup($dir); }
});

$run('Moving to a new system adds it to character exploration history', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-exploration-history');
        $service = new CharacterService($storage);
        $character = $service->create($universe['id'], 'History Explorer');
        $graph = NavigationGraph::build($universe['baseline']['systems']);
        $targetId = $graph['neighbors'][$character['current_system_id']][0];
        $target = array_values(array_filter($universe['baseline']['systems'], static fn(array $system): bool => $system['id'] === $targetId))[0];
        $moved = $service->move($universe['id'], $character['id'], $target['id'], 0);
        $visited = $moved['exploration']['explored_systems'] ?? [];
        $equal(2, count($visited), 'Exploration history should contain the start and destination systems.');
        $equal($target['id'], $visited[1]['system_id'] ?? null, 'Destination was not added to exploration history.');
        $equal($target['name'], $visited[1]['name'] ?? null, 'Exploration entry did not capture the system name.');
        $equal(1, $visited[1]['first_visited_revision'] ?? null, 'Exploration entry has incorrect first-visit revision.');
        return 'Moving to a new system records its ID, name, timestamp, and first-visit revision.';
    } finally { $cleanup($dir); }
});

$run('Returning to an explored system does not duplicate exploration history', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-exploration-dedup');
        $service = new CharacterService($storage);
        $character = $service->create($universe['id'], 'Returning Explorer');
        $systems = $universe['baseline']['systems'];
        $graph = NavigationGraph::build($systems);
        $startId = $character['current_system_id'];
        $neighborId = $graph['neighbors'][$startId][0];
        $firstMove = $service->move($universe['id'], $character['id'], $neighborId, 0);
        $back = $service->move($universe['id'], $character['id'], $startId, 1);
        $equal(2, count($back['exploration']['explored_systems'] ?? []), 'Returning to the starting system created a duplicate entry.');
        $equal(2, $back['revision'], 'Returning to a previously explored system did not advance movement revision.');
        $equal($firstMove['exploration']['explored_systems'][0]['first_visited_at'], $back['exploration']['explored_systems'][0]['first_visited_at'], 'Original first-visit timestamp changed.');
        return 'Returning to a known system changes location but keeps one stable first-visit record per system.';
    } finally { $cleanup($dir); }
});

$run('Navigation graph connects every generated system', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $universe = (new UniverseService(new JsonStorage($dir)))->createOrLoad('core-ui-qa-navigation-connected');
        $systems = $universe['baseline']['systems'];
        $graph = NavigationGraph::build($systems);
        $equal(count($systems) - 1, count($graph['routes']), 'MST should contain N-1 routes.');
        $seen = []; $queue = [$systems[0]['id']];
        while ($queue) { $id = array_shift($queue); if (isset($seen[$id])) continue; $seen[$id] = true; foreach ($graph['neighbors'][$id] ?? [] as $next) if (!isset($seen[$next])) $queue[] = $next; }
        $equal(count($systems), count($seen), 'Navigation graph is not fully connected.');
        return count($systems) . ' systems connected by ' . count($graph['routes']) . ' deterministic routes.';
    } finally { $cleanup($dir); }
});
$run('Navigation graph generation is deterministic', static function () use ($equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try { $universe = (new UniverseService(new JsonStorage($dir)))->createOrLoad('core-ui-qa-navigation-deterministic'); $systems = $universe['baseline']['systems']; $equal(NavigationGraph::build($systems), NavigationGraph::build($systems), 'Graph output changed for same systems.'); return 'Repeated graph generation matched exactly.'; }
    finally { $cleanup($dir); }
});
$run('Character can move to a directly connected system only', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-navigation-move'); $service = new CharacterService($storage); $character = $service->create($universe['id'], 'Route Mover'); $graph = NavigationGraph::build($universe['baseline']['systems']); $neighbor = $graph['neighbors'][$character['current_system_id']][0]; $moved = $service->move($universe['id'], $character['id'], $neighbor, 0); $equal($neighbor, $moved['current_system_id'], 'Connected movement failed.'); $equal(1, $moved['revision'], 'Connected movement revision incorrect.'); return 'Connected neighbor accepted and revision advanced.';
    } finally { $cleanup($dir); }
});
$run('Character cannot jump across a route without a direct connection', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-navigation-block'); $service = new CharacterService($storage); $character = $service->create($universe['id'], 'No Jump'); $graph = NavigationGraph::build($universe['baseline']['systems']); $neighbors = $graph['neighbors'][$character['current_system_id']]; $nonNeighbor = null; foreach ($universe['baseline']['systems'] as $system) if ($system['id'] !== $character['current_system_id'] && !in_array($system['id'], $neighbors, true)) { $nonNeighbor = $system['id']; break; } if ($nonNeighbor === null) throw new RuntimeException('Fixture unexpectedly has no non-neighbor system.');
        try { $service->move($universe['id'], $character['id'], $nonNeighbor, 0); } catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'not directly reachable'), 'Unexpected route validation error.'); $saved = $storage->read('characters', $character['id']); $equal($character['current_system_id'], $saved['current_system_id'], 'Rejected jump changed location.'); $equal(0, $saved['revision'], 'Rejected jump advanced revision.'); return 'Unconnected destination rejected without changing character state.'; } throw new RuntimeException('Unconnected destination was accepted.');
    } finally { $cleanup($dir); }
});

$run('Shortest route includes both endpoints and valid connected hops', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $universe = (new UniverseService(new JsonStorage($dir)))->createOrLoad('core-ui-qa-shortest-route');
        $systems = $universe['baseline']['systems'];
        $graph = NavigationGraph::build($systems);
        $from = $systems[0]['id']; $to = $systems[count($systems) - 1]['id'];
        $path = NavigationGraph::shortestPath($graph, $from, $to);
        $equal($from, $path[0] ?? null, 'Route does not start at the requested system.');
        $equal($to, $path[count($path) - 1] ?? null, 'Route does not end at the requested system.');
        for ($i = 1; $i < count($path); $i++) $assert(in_array($path[$i], $graph['neighbors'][$path[$i - 1]] ?? [], true), 'Route contains a non-connected hop.');
        return count($path) - 1 . ' connected hop(s) returned from start to destination.';
    } finally { $cleanup($dir); }
});
$run('Route service plans between two valid saved systems', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-route-service-integration');
        $systems = $universe['baseline']['systems'];
        if (count($systems) < 2) throw new RuntimeException('Fixture needs at least two generated systems.');
        $from = (string)$systems[0]['id'];
        $to = (string)$systems[count($systems) - 1]['id'];
        $result = (new NavigationRouteService($storage))->plan($universe['id'], $from, $to);
        $equal(true, $result['ok'] ?? false, 'Route service did not report success.');
        $equal($from, $result['from_system_id'] ?? null, 'Route service returned the wrong start system.');
        $equal($to, $result['to_system_id'] ?? null, 'Route service returned the wrong destination system.');
        $path = $result['path'] ?? [];
        $assert(count($path) >= 2, 'Route between different valid systems must contain at least two path entries.');
        $equal($from, $path[0]['system_id'] ?? null, 'Route path does not begin at the requested system.');
        $equal($to, $path[count($path) - 1]['system_id'] ?? null, 'Route path does not end at the requested system.');
        $equal(count($path) - 1, $result['hops'] ?? null, 'Hop count does not match path length.');
        $graph = NavigationGraph::build($systems);
        for ($i = 1; $i < count($path); $i++) {
            $previousId = $path[$i - 1]['system_id'] ?? '';
            $nextId = $path[$i]['system_id'] ?? '';
            $assert(in_array($nextId, $graph['neighbors'][$previousId] ?? [], true), 'Route service returned a hop that is not connected.');
        }
        return 'Shared route-planning service returned a valid multi-system route between two saved, valid system IDs.';
    } finally { $cleanup($dir); }
});

$run('Route planning from a system to itself returns zero hops', static function () use ($equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $universe = (new UniverseService(new JsonStorage($dir)))->createOrLoad('core-ui-qa-zero-hop-route');
        $graph = NavigationGraph::build($universe['baseline']['systems']);
        $systemId = $universe['baseline']['systems'][0]['id'];
        $equal([$systemId], NavigationGraph::shortestPath($graph, $systemId, $systemId), 'Same-system route should contain one node.');
        return 'Same-system route contains one endpoint and zero hops.';
    } finally { $cleanup($dir); }
});
$run('Route planner rejects system IDs outside the graph', static function () use ($assert, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $universe = (new UniverseService(new JsonStorage($dir)))->createOrLoad('core-ui-qa-invalid-route-endpoint');
        $graph = NavigationGraph::build($universe['baseline']['systems']);
        try { NavigationGraph::shortestPath($graph, $universe['baseline']['systems'][0]['id'], 'sys_not_in_graph'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'endpoints'), 'Unexpected invalid route error.'); return 'Unknown destination rejected by route planner.'; }
        throw new RuntimeException('Unknown route destination was accepted.');
    } finally { $cleanup($dir); }
});


$run('Starting travel stores a route without moving the character', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-travel-start');
        $systems = $universe['baseline']['systems']; $graph = NavigationGraph::build($systems);
        $character = (new CharacterService($storage))->create($universe['id'], 'Travel Starter');
        $from = $character['current_system_id']; $destination = null; $path = [];
        foreach ($systems as $system) { $candidate = NavigationGraph::shortestPath($graph, $from, (string)$system['id']); if (count($candidate) > count($path)) { $path = $candidate; $destination = (string)$system['id']; } }
        $assert(count($path) >= 3, 'Fixture needs a destination at least two jumps away.');
        $result = (new CharacterTravelService($storage))->start($universe['id'], $character['id'], $destination, 0);
        $equal('route_started', $result['travel_status'], 'Travel route did not start.');
        $equal($from, $result['character']['current_system_id'], 'Starting travel moved the character immediately.');
        $equal(1, $result['character']['revision'], 'Starting travel should advance the character revision.');
        $equal($path, $result['character']['route_progress']['path_system_ids'], 'Stored route does not match the shortest path.');
        $equal('active', $result['character']['route_progress']['status'], 'Multi-hop route should be active.');
        return (count($path) - 1) . ' hop route saved; character remained at the starting system.';
    } finally { $cleanup($dir); }
});

$run('Travel next jump advances exactly one hop and records exploration', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-travel-one-hop');
        $systems = $universe['baseline']['systems']; $graph = NavigationGraph::build($systems); $characterService = new CharacterService($storage);
        $character = $characterService->create($universe['id'], 'One Hop'); $from = $character['current_system_id']; $path = [];
        foreach ($systems as $system) { $candidate = NavigationGraph::shortestPath($graph, $from, (string)$system['id']); if (count($candidate) > count($path)) $path = $candidate; }
        $assert(count($path) >= 3, 'Fixture needs a route of at least two hops.');
        $travel = new CharacterTravelService($storage); $started = $travel->start($universe['id'], $character['id'], (string)end($path), 0);
        $advanced = $travel->advance($universe['id'], $character['id'], $started['character']['revision']);
        $equal($path[1], $advanced['character']['current_system_id'], 'One action did not move exactly to the next system.');
        $equal(2, $advanced['character']['revision'], 'One jump should advance revision exactly once after route start.');
        $equal(1, $advanced['character']['route_progress']['current_step_index'], 'Route progress index did not advance by one.');
        $visited = array_column($advanced['character']['exploration']['explored_systems'], 'system_id');
        $assert(in_array($path[1], $visited, true), 'Destination system was not added to exploration history.');
        $equal(count($visited), count(array_unique($visited)), 'Exploration history contains duplicate systems.');
        return 'Exactly one jump executed; revision and exploration history updated.';
    } finally { $cleanup($dir); }
});

$run('Travel completes at the planned destination and cannot advance again', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-travel-complete');
        $systems = $universe['baseline']['systems']; $graph = NavigationGraph::build($systems); $character = (new CharacterService($storage))->create($universe['id'], 'Route Finisher');
        $path = []; foreach ($systems as $system) { $candidate = NavigationGraph::shortestPath($graph, $character['current_system_id'], (string)$system['id']); if (count($candidate) > count($path)) $path = $candidate; }
        $assert(count($path) >= 3, 'Fixture needs a route of at least two hops.'); $destination = (string)end($path); $travel = new CharacterTravelService($storage);
        $result = $travel->start($universe['id'], $character['id'], $destination, 0); $current = $result['character']; $iterations = 0;
        while (($current['route_progress']['status'] ?? '') === 'active' && $iterations < 20) { $result = $travel->advance($universe['id'], $character['id'], (int)$current['revision']); $current = $result['character']; $iterations++; }
        $equal('completed', $current['route_progress']['status'] ?? null, 'Route did not reach completed status.');
        $equal($destination, $current['current_system_id'], 'Character did not reach the selected destination.');
        $equal('route_completed', $result['travel_status'], 'Final jump did not return route_completed.');
        $revision = $current['revision'];
        try { $travel->advance($universe['id'], $character['id'], $revision); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'not active'), 'Unexpected completed-route rejection.'); $saved = $storage->read('characters', $character['id']); $equal($revision, $saved['revision'], 'Rejected advance changed revision.'); $equal($destination, $saved['current_system_id'], 'Rejected advance changed location.'); return $iterations . ' jump(s) completed; completed route cannot be replayed.'; }
        throw new RuntimeException('Completed route accepted another jump.');
    } finally { $cleanup($dir); }
});

$run('Travel rejects stale revisions without moving the character', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-travel-stale'); $systems = $universe['baseline']['systems']; $graph = NavigationGraph::build($systems);
        $character = (new CharacterService($storage))->create($universe['id'], 'Stale Traveller'); $path = [];
        foreach ($systems as $system) { $candidate = NavigationGraph::shortestPath($graph, $character['current_system_id'], (string)$system['id']); if (count($candidate) > count($path)) $path = $candidate; }
        $travel = new CharacterTravelService($storage); $started = $travel->start($universe['id'], $character['id'], (string)end($path), 0); $before = $started['character'];
        try { $travel->advance($universe['id'], $character['id'], 0); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'revision conflict'), 'Unexpected stale-revision error.'); $saved = $storage->read('characters', $character['id']); $equal($before['current_system_id'], $saved['current_system_id'], 'Stale request moved the character.'); $equal($before['revision'], $saved['revision'], 'Stale request changed revision.'); return 'Stale jump rejected without changing location or revision.'; }
        throw new RuntimeException('Stale travel revision was accepted.');
    } finally { $cleanup($dir); }
});

$run('Travel rejects a route after manual movement changes the route origin', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-travel-desync'); $systems = $universe['baseline']['systems']; $graph = NavigationGraph::build($systems); $characters = new CharacterService($storage);
        $character = $characters->create($universe['id'], 'Route Desync'); $path = [];
        foreach ($systems as $system) { $candidate = NavigationGraph::shortestPath($graph, $character['current_system_id'], (string)$system['id']); if (count($candidate) > count($path)) $path = $candidate; }
        $travel = new CharacterTravelService($storage); $started = $travel->start($universe['id'], $character['id'], (string)end($path), 0);
        $characters->move($universe['id'], $character['id'], (string)$path[1], (int)$started['character']['revision']);
        $afterManualMove = $storage->read('characters', $character['id']);
        try { $travel->advance($universe['id'], $character['id'], (int)$afterManualMove['revision']); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'not active'), 'Unexpected route desynchronization error.'); $saved = $storage->read('characters', $character['id']); $equal($afterManualMove['current_system_id'], $saved['current_system_id'], 'Rejected stale route changed location.'); $equal($afterManualMove['revision'], $saved['revision'], 'Rejected stale route changed revision.'); $equal('cancelled', $saved['route_progress']['status'] ?? null, 'Manual movement did not cancel the old route.'); return 'Manual movement cancels the previous route and it cannot be resumed.'; }
        throw new RuntimeException('Out-of-sync route was allowed to continue.');
    } finally { $cleanup($dir); }
});

$run('System scan persists deterministic discoveries and increments revision once', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-scan-persistence');
        $character = (new CharacterService($storage))->create($universe['id'], 'Signal Seeker');
        $scanner = new CharacterScanService($storage); $first = $scanner->scanCurrentSystem($universe['id'], $character['id'], 0);
        $equal('scanned', $first['scan_status'], 'First scan did not report scanned status.');
        $assert(count($first['scan']['discoveries'] ?? []) >= 1 && count($first['scan']['discoveries']) <= 3, 'Discovery count outside expected range.');
        $equal(1, $first['character']['revision'], 'First scan did not increment character revision.');
        $saved = $storage->read('characters', $character['id']);
        $equal($first['scan'], $saved['scans']['systems'][$character['current_system_id']] ?? null, 'Scan results were not persisted.');
        return count($first['scan']['discoveries']) . ' deterministic discovery signal(s) persisted on the character record.';
    } finally { $cleanup($dir); }
});

$run('Repeated scan is idempotent and returns saved results', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-scan-repeat');
        $character = (new CharacterService($storage))->create($universe['id'], 'Repeat Scanner'); $scanner = new CharacterScanService($storage);
        $first = $scanner->scanCurrentSystem($universe['id'], $character['id'], 0);
        $second = $scanner->scanCurrentSystem($universe['id'], $character['id'], 1);
        $equal('already_scanned', $second['scan_status'], 'Repeat scan did not identify an existing scan.');
        $equal($first['scan'], $second['scan'], 'Repeat scan did not return the original discoveries.');
        $equal(1, $second['character']['revision'], 'Repeat scan unexpectedly incremented character revision.');
        return 'Repeated scan returned identical saved discoveries without advancing revision.';
    } finally { $cleanup($dir); }
});

$run('System scanning rejects stale revisions and characters from other universes', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universes = new UniverseService($storage);
        $one = $universes->createOrLoad('core-ui-qa-scan-universe-one'); $two = $universes->createOrLoad('core-ui-qa-scan-universe-two');
        $character = (new CharacterService($storage))->create($one['id'], 'Revision Guard'); $scanner = new CharacterScanService($storage);
        try { $scanner->scanCurrentSystem($one['id'], $character['id'], 4); throw new RuntimeException('Stale scan revision was accepted.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'revision conflict'), 'Unexpected stale revision error.'); }
        try { $scanner->scanCurrentSystem($two['id'], $character['id'], 0); throw new RuntimeException('Cross-universe scan was accepted.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'does not belong'), 'Unexpected cross-universe error.'); }
        $saved = $storage->read('characters', $character['id']);
        $equal(0, $saved['revision'], 'Rejected scan changed revision.');
        $assert(empty($saved['scans']['systems']), 'Rejected scan wrote discovery data.');
        return 'Stale revision and cross-universe scans rejected without persistence changes.';
    } finally { $cleanup($dir); }
});

$run('Workbench exposes accessible system-scan controls and saved discovery rendering', static function () use ($assert): string {
    $html = file_get_contents(dirname(__DIR__) . '/index.php');
    $assert(is_string($html), 'Workbench HTML could not be read.');
    foreach (['scanSystemButton','characterScanStatus','characterScanResults','api/character-scan.php','already_scanned'] as $needle) {
        $assert(str_contains($html, $needle), 'Workbench is missing scan feature marker: ' . $needle);
    }
    return 'Scan action, status region, result rendering, and repeat-scan feedback are wired into the Workbench.';
});

$run('Character dashboard exposes status, travel, and distinct exploration summaries', static function () use ($assert): string {
    $html = file_get_contents(dirname(__DIR__) . '/index.php');
    $assert(is_string($html), 'Workbench HTML could not be read.');
    foreach (['dashboardCharacter','dashboardLocation','dashboardRevision','dashboardTravelStatus','dashboardTravelDetail','dashboardExploration','dashboardVisitedList','dashboardGlobalList','renderCharacterDashboard'] as $needle) {
        $assert(str_contains($html, $needle), 'Dashboard is missing required field or renderer: ' . $needle);
    }
    $assert(str_contains($html, 'updateCharacterDetails();') && str_contains($html, 'populateCharacterChoices();'), 'Dashboard is not connected to character selection refresh.');
    return 'Dashboard status fields and character-selection refresh hooks are present.';
});

$run('Dashboard source data changes after movement, travel completion, and global exploration', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $universes = new UniverseService($storage);
        $universe = $universes->createOrLoad('core-ui-qa-dashboard-data');
        $systems = $universe['baseline']['systems'];
        $graph = NavigationGraph::build($systems);
        $characters = new CharacterService($storage);
        $character = $characters->create($universe['id'], 'Dashboard Explorer');
        $from = $character['current_system_id'];
        $longestPath = [];
        foreach ($systems as $system) {
            $candidate = NavigationGraph::shortestPath($graph, $from, (string)$system['id']);
            if (count($candidate) > count($longestPath)) $longestPath = $candidate;
        }
        $assert(count($longestPath) >= 3, 'Dashboard fixture needs a route of at least two hops.');
        $firstDestination = (string)$longestPath[1];
        $moved = $characters->move($universe['id'], $character['id'], $firstDestination, 0);
        $visitedIds = array_column($moved['exploration']['explored_systems'], 'system_id');
        $assert(in_array($firstDestination, $visitedIds, true), 'Movement did not update character exploration data.');
        $equal(1, $moved['revision'], 'Movement revision not reflected in dashboard source data.');

        $travel = new CharacterTravelService($storage);
        $fresh = $storage->read('characters', $character['id']);
        $route = [];
        foreach ($systems as $system) {
            $candidate = NavigationGraph::shortestPath($graph, $fresh['current_system_id'], (string)$system['id']);
            if (count($candidate) > count($route)) $route = $candidate;
        }
        $started = $travel->start($universe['id'], $character['id'], (string)end($route), (int)$fresh['revision']);
        $current = $started['character'];
        $iterations = 0;
        while (($current['route_progress']['status'] ?? '') === 'active' && $iterations < 20) {
            $result = $travel->advance($universe['id'], $character['id'], (int)$current['revision']);
            $current = $result['character'];
            $iterations++;
        }
        $equal('completed', $current['route_progress']['status'] ?? null, 'Completed route status missing from dashboard source data.');
        $equal((string)end($route), $current['current_system_id'], 'Dashboard location source did not reach the destination.');

        $globalTarget = (string)$systems[count($systems) - 1]['id'];
        $updatedUniverse = $universes->updateState($universe['id'], (int)$universe['state']['revision'], ['type' => 'system_explored', 'system_id' => $globalTarget]);
        $globalIds = array_column($updatedUniverse['state']['projections']['explored_systems'] ?? [], 'system_id');
        $assert(in_array($globalTarget, $globalIds, true), 'Global exploration projection was not updated.');
        return 'Character visit history, completed travel progress, and universe-wide exploration projection all update in persisted source data.';
    } finally { $cleanup($dir); }
});

$run('Travel next-jump control remains actionable and reports request failures', static function () use ($assert): string {
    $html = file_get_contents(dirname(__DIR__) . '/index.php');
    $assert(is_string($html), 'Workbench HTML could not be read.');
    $assert(str_contains($html, "document.getElementById('advanceTravelButton').addEventListener('click', () => submitTravelAction('advance'))"), 'Travel next-jump click handler is missing.');
    $assert(str_contains($html, "advanceButton.disabled = false;"), 'Active route does not leave next-jump control enabled.');
    $assert(str_contains($html, "advanceButton.disabled = !characterId;"), 'Next-jump control does not remain actionable for a selected character.');
    $assert(str_contains($html, "status.textContent = 'Travel request failed: ' + String(error);"), 'Travel request errors are not visible in the Workbench.');
    $assert(str_contains($html, "button.disabled = !selectedCharacter;"), 'Next-jump control can remain disabled after a failed or stale request.');
    return 'Next-jump control has a click handler, actionable state, and visible error feedback.';
});

$passed = count(array_filter($results, static fn(array $r): bool => $r['status'] === 'PASS'));
$failed = count($results) - $passed;
http_response_code($failed === 0 ? 200 : 500);
echo json_encode(['ok' => $failed === 0, 'application' => 'CORE', 'version' => '0.1.22', 'passed' => $passed, 'failed' => $failed, 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
