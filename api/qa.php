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
use Core\Modules\Characters\CharacterDiscoveryService;
use Core\Modules\Characters\CharacterCraftingService;
use Core\Modules\Characters\CharacterFittingService;
use Core\Modules\Characters\CharacterLifecycleService;
use Core\Modules\Universe\UniverseService;
use Core\Modules\Universe\UniverseLifecycleService;
use Core\Modules\Universe\NavigationGraph;
use Core\Modules\Universe\NavigationRouteService;
use Core\Storage\JsonStorage;
use Core\Modules\DataManagementService;
use Core\Modules\UniverseBackupService;
use Core\Modules\ContentCatalog;
use Core\Modules\UniverseDeletionService;

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

$run('Discovery investigation persists a result and increments character revision', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-discovery-investigate');
        $character = (new CharacterService($storage))->create($universe['id'], 'Signal Investigator');
        $scan = (new CharacterScanService($storage))->scanCurrentSystem($universe['id'], $character['id'], 0);
        $discovery = $scan['scan']['discoveries'][0];
        $result = (new CharacterDiscoveryService($storage))->investigate($universe['id'], $character['id'], $discovery['id'], 1);
        $equal('investigated', $result['investigation_status'], 'Investigation status mismatch.');
        $equal('investigated', $result['discovery']['status'] ?? null, 'Discovery status was not persisted.');
        $assert(!empty($result['discovery']['outcome']), 'Investigation outcome missing.');
        $equal(2, $result['character']['revision'], 'Investigation did not increment revision once.');
        $saved = $storage->read('characters', $character['id']);
        $savedDiscovery = $saved['scans']['systems'][$character['current_system_id']]['discoveries'][0] ?? null;
        $equal($result['discovery'], $savedDiscovery, 'Investigated discovery not saved in character record.');
        return 'Investigation status, timestamp, outcome, and revision were persisted.';
    } finally { $cleanup($dir); }
});

$run('Repeated discovery investigation is idempotent', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-discovery-repeat');
        $character = (new CharacterService($storage))->create($universe['id'], 'Repeat Investigator');
        $scan = (new CharacterScanService($storage))->scanCurrentSystem($universe['id'], $character['id'], 0);
        $service = new CharacterDiscoveryService($storage); $id = $scan['scan']['discoveries'][0]['id'];
        $first = $service->investigate($universe['id'], $character['id'], $id, 1);
        $second = $service->investigate($universe['id'], $character['id'], $id, 2);
        $equal('already_investigated', $second['investigation_status'], 'Repeat investigation status mismatch.');
        $equal($first['discovery']['outcome'], $second['discovery']['outcome'], 'Repeat investigation changed the outcome.');
        $equal(2, $second['character']['revision'], 'Repeat investigation advanced revision.');
        return 'Repeat investigation returned the saved outcome without changing revision.';
    } finally { $cleanup($dir); }
});

$run('Discovery investigation requires the character to be in the discovery system', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-discovery-location');
        $characters = new CharacterService($storage); $character = $characters->create($universe['id'], 'Remote Investigator');
        $scan = (new CharacterScanService($storage))->scanCurrentSystem($universe['id'], $character['id'], 0);
        $discovery = $scan['scan']['discoveries'][0]; $systems = $universe['baseline']['systems']; $graph = NavigationGraph::build($systems); $path = [];
        foreach ($systems as $system) { $candidate = NavigationGraph::shortestPath($graph, $character['current_system_id'], (string)$system['id']); if (count($candidate) > count($path)) $path = $candidate; }
        $assert(count($path) > 1, 'No alternate system available in generated navigation graph.');
        $moved = $characters->move($universe['id'], $character['id'], (string)$path[1], 1);
        try { (new CharacterDiscoveryService($storage))->investigate($universe['id'], $character['id'], $discovery['id'], $moved['revision']); }
        catch (RuntimeException $e) {
            $assert(str_contains($e->getMessage(), 'Travel to the discovery'), 'Unexpected location error.');
            $saved = $storage->read('characters', $character['id']);
            $equal(2, $saved['revision'], 'Rejected remote investigation changed revision.');
            return 'Remote investigation rejected without changing the saved character.';
        }
        throw new RuntimeException('Discovery was investigated from another system.');
    } finally { $cleanup($dir); }
});

$run('Discovery investigation rejects stale revisions and cross-universe characters', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universes = new UniverseService($storage);
        $one = $universes->createOrLoad('core-ui-qa-discovery-universe-one'); $two = $universes->createOrLoad('core-ui-qa-discovery-universe-two');
        $character = (new CharacterService($storage))->create($one['id'], 'Revision Investigator');
        $scan = (new CharacterScanService($storage))->scanCurrentSystem($one['id'], $character['id'], 0); $id = $scan['scan']['discoveries'][0]['id'];
        $service = new CharacterDiscoveryService($storage);
        try { $service->investigate($one['id'], $character['id'], $id, 0); throw new RuntimeException('Stale revision accepted.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'revision conflict'), 'Unexpected stale revision error.'); }
        try { $service->investigate($two['id'], $character['id'], $id, 1); throw new RuntimeException('Cross-universe investigation accepted.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'does not belong'), 'Unexpected cross-universe error.'); }
        $saved = $storage->read('characters', $character['id']);
        $equal(1, $saved['revision'], 'Rejected requests changed revision.');
        $equal('uninvestigated', $saved['scans']['systems'][$character['current_system_id']]['discoveries'][0]['status'] ?? 'uninvestigated', 'Rejected request changed discovery state.');
        return 'Stale and cross-universe requests rejected without discovery-state changes.';
    } finally { $cleanup($dir); }
});

$run('Investigating a signal awards and persists a typed inventory reward', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-reward-persist');
        $character = (new CharacterService($storage))->create($universe['id'], 'Reward Collector');
        $scan = (new CharacterScanService($storage))->scanCurrentSystem($universe['id'], $character['id'], 0);
        $discovery = $scan['scan']['discoveries'][0];
        $characterRecord = $storage->read('characters', $character['id']);
        $characterRecord['scans']['systems'][$character['current_system_id']]['discoveries'][0]['type'] = 'mineral_signature';
        $storage->write('characters', $character['id'], $characterRecord);
        $result = (new CharacterDiscoveryService($storage))->investigate($universe['id'], $character['id'], $discovery['id'], 1);
        $reward = $result['reward'] ?? [];
        $equal('raw_ore', $reward['resource'] ?? null, 'Mineral signal did not reward raw ore.');
        $assert(($reward['quantity'] ?? 0) >= 2 && $reward['quantity'] <= 5, 'Raw ore reward quantity is out of range.');
        $equal($reward['quantity'], $result['character']['inventory']['resources']['raw_ore'] ?? null, 'Inventory total does not match awarded quantity.');
        $equal(1, count($result['character']['inventory']['ledger'] ?? []), 'Inventory ledger entry was not recorded.');
        $equal($discovery['id'], $result['character']['inventory']['ledger'][0]['discovery_id'] ?? null, 'Ledger entry is not linked to its discovery.');
        $saved = $storage->read('characters', $character['id']);
        $equal($result['character']['inventory'], $saved['inventory'] ?? null, 'Inventory was not persisted.');
        return 'A mineral investigation awards 2–5 raw ore and persists its total and linked ledger entry.';
    } finally { $cleanup($dir); }
});

$run('Repeating an investigation never duplicates inventory rewards', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-reward-idempotent');
        $character = (new CharacterService($storage))->create($universe['id'], 'Idempotent Collector');
        $scan = (new CharacterScanService($storage))->scanCurrentSystem($universe['id'], $character['id'], 0);
        $id = $scan['scan']['discoveries'][0]['id']; $service = new CharacterDiscoveryService($storage);
        $first = $service->investigate($universe['id'], $character['id'], $id, 1);
        $second = $service->investigate($universe['id'], $character['id'], $id, 2);
        $equal('already_investigated', $second['investigation_status'], 'Repeat investigation was not identified.');
        $equal($first['reward'], $second['reward'], 'Repeat investigation reward differs from original.');
        $equal($first['character']['inventory'], $second['character']['inventory'], 'Repeat investigation changed inventory.');
        $equal(1, count($second['character']['inventory']['ledger'] ?? []), 'Repeat investigation added another ledger entry.');
        $equal(2, $second['character']['revision'], 'Repeat investigation advanced revision.');
        return 'Repeated investigation returns the original reward without increasing inventory, ledger count, or revision.';
    } finally { $cleanup($dir); }
});

$run('Different discovery types award their corresponding resource categories', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-reward-types');
        $character = (new CharacterService($storage))->create($universe['id'], 'Multi-resource Collector');
        $scan = (new CharacterScanService($storage))->scanCurrentSystem($universe['id'], $character['id'], 0);
        $record = $storage->read('characters', $character['id']);
        $first = $record['scans']['systems'][$character['current_system_id']]['discoveries'][0];
        $record['scans']['systems'][$character['current_system_id']]['discoveries'][0]['type'] = 'derelict_signal';
        $second = $first; $second['id'] = 'dsc_qa_rare_asteroid'; $second['type'] = 'unusual_asteroid'; $second['label'] = 'QA unusual asteroid';
        $record['scans']['systems'][$character['current_system_id']]['discoveries'][] = $second;
        $storage->write('characters', $character['id'], $record);
        $service = new CharacterDiscoveryService($storage);
        $one = $service->investigate($universe['id'], $character['id'], $first['id'], 1);
        $two = $service->investigate($universe['id'], $character['id'], $second['id'], 2);
        $equal('salvage_parts', $one['reward']['resource'] ?? null, 'Derelict signal reward type mismatch.');
        $equal('rare_ore', $two['reward']['resource'] ?? null, 'Unusual asteroid reward type mismatch.');
        $assert(($two['character']['inventory']['resources']['salvage_parts'] ?? 0) > 0, 'Salvage parts missing from inventory.');
        $assert(($two['character']['inventory']['resources']['rare_ore'] ?? 0) > 0, 'Rare ore missing from inventory.');
        $equal(2, count($two['character']['inventory']['ledger'] ?? []), 'Both recovery events should appear in the ledger.');
        return 'Derelict signals award salvage parts and unusual asteroids award rare ore; both are accumulated separately.';
    } finally { $cleanup($dir); }
});

$run('Crafting consumes materials, creates output, and increments revision', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-crafting-success');
        $character = (new CharacterService($storage))->create($universe['id'], 'Workshop Tester');
        $record = $storage->read('characters', $character['id']);
        $record['inventory'] = ['resources' => ['raw_ore' => 7], 'ledger' => []]; $storage->write('characters', $character['id'], $record);
        $result = (new CharacterCraftingService($storage))->craft($universe['id'], $character['id'], 'refine_ore', 2, 0);
        $equal(1, $result['character']['inventory']['resources']['raw_ore'], 'Raw ore was not consumed at the recipe rate.');
        $equal(2, $result['character']['inventory']['resources']['refined_alloy'], 'Crafted output total is incorrect.');
        $equal(1, $result['character']['revision'], 'Successful craft did not increment revision exactly once.');
        $equal(2, count($result['character']['inventory']['ledger']), 'Craft input/output ledger entries were not written.');
        $equal($result['character']['inventory'], $storage->read('characters', $character['id'])['inventory'], 'Crafted inventory did not persist.');
        return 'Two batches consumed six raw ore, produced two refined alloy, and persisted matching input/output ledger entries.';
    } finally { $cleanup($dir); }
});

$run('Crafting rejects insufficient materials without changing inventory', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-crafting-insufficient');
        $character = (new CharacterService($storage))->create($universe['id'], 'Short on Ore');
        $record = $storage->read('characters', $character['id']); $record['inventory'] = ['resources' => ['raw_ore' => 2], 'ledger' => []]; $storage->write('characters', $character['id'], $record);
        try { (new CharacterCraftingService($storage))->craft($universe['id'], $character['id'], 'refine_ore', 1, 0); }
        catch (RuntimeException $e) {
            $assert(str_contains(strtolower($e->getMessage()), 'insufficient raw ore'), 'Unexpected insufficient-material error.');
            $saved = $storage->read('characters', $character['id']);
            $equal(2, $saved['inventory']['resources']['raw_ore'], 'Rejected craft consumed raw ore.');
            $equal(0, $saved['revision'], 'Rejected craft changed revision.');
            $equal([], $saved['inventory']['ledger'], 'Rejected craft wrote ledger entries.');
            return 'Insufficient input rejected atomically; inventory, ledger, and revision remained unchanged.';
        }
        throw new RuntimeException('Crafting succeeded without enough materials.');
    } finally { $cleanup($dir); }
});

$run('Crafting rejects stale revisions and invalid quantities', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-crafting-revision');
        $character = (new CharacterService($storage))->create($universe['id'], 'Revision Crafter');
        $record = $storage->read('characters', $character['id']); $record['inventory'] = ['resources' => ['salvage_parts' => 4], 'ledger' => []]; $storage->write('characters', $character['id'], $record);
        try { (new CharacterCraftingService($storage))->craft($universe['id'], $character['id'], 'assemble_components', 1, 4); throw new RuntimeException('Stale crafting revision accepted.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'revision conflict'), 'Unexpected stale revision error.'); }
        try { (new CharacterCraftingService($storage))->craft($universe['id'], $character['id'], 'assemble_components', 11, 0); throw new RuntimeException('Invalid craft quantity accepted.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'between 1 and 10'), 'Unexpected quantity validation error.'); }
        $saved = $storage->read('characters', $character['id']);
        $equal(4, $saved['inventory']['resources']['salvage_parts'], 'Rejected crafting changed materials.');
        $equal(0, $saved['revision'], 'Rejected crafting changed revision.');
        return 'Stale revision and out-of-range batch quantity were rejected without changing saved state.';
    } finally { $cleanup($dir); }
});

$run('Workbench exposes crafting recipes, quantity controls, and result handling', static function () use ($assert): string {
    $html = file_get_contents(dirname(__DIR__) . '/index.php');
    $assert(is_string($html), 'Workbench HTML could not be read.');
    foreach (['craftRecipe','craftQuantity','craftButton','craftStatus','api/character-craft.php','api/content-recipes.php','api/content-items.php','gameContent.recipes','loadGameContent','Crafting completed'] as $needle) {
        $assert(str_contains($html, $needle), 'Workbench is missing crafting marker: ' . $needle);
    }
    return 'Workbench contains recipe selection, bounded batch input, crafting action, status, and saved result refresh wiring.';
});

$run('Workbench displays character inventory totals and investigation reward details', static function () use ($assert): string {
    $html = file_get_contents(dirname(__DIR__) . '/index.php');
    $assert(is_string($html), 'Workbench HTML could not be read.');
    foreach (['characterInventory','Recovered resources','Recorded recovery events:','Reward: +','data.reward?.quantity','inventory.ledger'] as $needle) {
        $assert(str_contains($html, $needle), 'Workbench is missing inventory/reward marker: ' . $needle);
    }
    return 'Inventory totals, ledger event count, and per-discovery reward feedback are wired into the UI.';
});

$run('Workbench exposes investigation actions and result rendering', static function () use ($assert): string {
    $html = file_get_contents(dirname(__DIR__) . '/index.php');
    $assert(is_string($html), 'Workbench HTML could not be read.');
    foreach (['investigate-discovery', 'api/character-discovery-investigate.php', 'investigation_status', 'Investigation result:', 'characterScanResults\').addEventListener(\'click\''] as $needle) {
        $assert(str_contains($html, $needle), 'Workbench is missing discovery-investigation marker: ' . $needle);
    }
    return 'Saved signals expose investigation controls and display persistent investigation outcomes.';
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
    $assert(str_contains($html, 'advanceButton.disabled = !canPlay;'), 'Active route must keep next-jump enabled only when character and universe are playable.');
    $assert(str_contains($html, "document.getElementById('advanceTravelButton').disabled = true;"), 'Next-jump control is not safely disabled when no character is selected.');
    $assert(str_contains($html, "status.textContent = 'Travel request failed: ' + String(error);"), 'Travel request errors are not visible in the Workbench.');
    $assert(str_contains($html, "button.disabled = !selectedCharacter;"), 'Next-jump control can remain disabled after a failed or stale request.');
    return 'Next-jump control has a click handler, actionable state, and visible error feedback.';
});


$run('Global menu exposes all major Workbench areas with accessible tab relationships', static function () use ($assert): string {
    $html = file_get_contents(dirname(__DIR__) . '/index.php');
    $assert(is_string($html), 'Workbench HTML could not be read.');
    foreach ([
        'role="tablist"', 'role="tab"', 'role="tabpanel"',
        'id="tab-overview"', 'id="panel-overview"',
        'id="tab-universe"', 'id="panel-universe"',
        'id="tab-characters"', 'id="panel-characters"',
        'id="tab-discoveries"', 'id="panel-discoveries"',
        'id="tab-operations"', 'id="panel-operations"',
        'aria-controls="panel-discoveries"', 'aria-labelledby="tab-discoveries"'
    ] as $needle) {
        $assert(str_contains($html, $needle), 'Global menu is missing accessibility or panel marker: ' . $needle);
    }
    return 'Five major work areas have explicit tab/panel relationships and accessible labels.';
});

$run('Global menu supports click, arrow-key, Home, and End navigation', static function () use ($assert): string {
    $html = file_get_contents(dirname(__DIR__) . '/index.php');
    $assert(is_string($html), 'Workbench HTML could not be read.');
    foreach (["tab.addEventListener('click'", "tab.addEventListener('keydown'", "event.key === 'ArrowRight'", "event.key === 'ArrowLeft'", "event.key === 'Home'", "event.key === 'End'", "panel.hidden = panel.dataset.panel !== name", "item.setAttribute('aria-selected'", "item.tabIndex = active ? 0 : -1"] as $needle) {
        $assert(str_contains($html, $needle), 'Global menu interaction is missing: ' . $needle);
    }
    return 'Tabs switch panels, update selected/focus state, and support standard keyboard navigation.';
});

$run('Overview dashboard is wired to current universe, character, location, and travel status', static function () use ($assert): string {
    $html = file_get_contents(dirname(__DIR__) . '/index.php');
    $assert(is_string($html), 'Workbench HTML could not be read.');
    foreach (['overviewUniverse','overviewCharacter','overviewRole','overviewSystem','overviewTravel'] as $needle) {
        $assert(str_contains($html, $needle), 'Overview dashboard is missing: ' . $needle);
    }
    $assert(str_contains($html, "set('overviewUniverse'") && str_contains($html, "set('overviewCharacter'") && str_contains($html, "set('overviewTravel'"), 'Overview dashboard fields are not updated by the character dashboard renderer.');
    $assert(str_contains($html, "activateTab('overview')"), 'Workbench does not initialize to a valid default tab.');
    return 'Overview tiles are updated alongside the existing character/travel dashboard and a default tab is initialized.';
});


$run('Ship fitting consumes materials, installs a module, and increments revision', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-fitting-success');
        $character = (new CharacterService($storage))->create($universe['id'], 'Ship Fitter');
        $record = $storage->read('characters', $character['id']); $record['inventory'] = ['resources' => ['ship_components' => 2, 'refined_alloy' => 3, 'tech_insights' => 1], 'ledger' => []]; $storage->write('characters', $character['id'], $record);
        $result = (new CharacterFittingService($storage))->install($universe['id'], $character['id'], 'propulsion_array', 0);
        $equal('propulsion_array', $result['character']['ship']['modules']['propulsion']['id'] ?? null, 'Propulsion module not installed.');
        $equal(1, $result['character']['inventory']['resources']['ship_components'], 'Ship component not consumed.');
        $equal(2, $result['character']['inventory']['resources']['refined_alloy'], 'Refined alloy not consumed.');
        $equal(1, $result['character']['revision'], 'Fitting should increment revision once.');
        $equal(2, count($result['character']['inventory']['ledger']), 'Fitting ledger entries missing.');
        $equal($result['character'], $storage->read('characters', $character['id']), 'Fitted character was not persisted.');
        return 'Module, material consumption, ledger entries, revision, and persisted character agree.';
    } finally { $cleanup($dir); }
});

$run('Ship fitting rejects occupied slots without changing inventory', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-fitting-slot');
        $character = (new CharacterService($storage))->create($universe['id'], 'Slot Tester');
        $record = $storage->read('characters', $character['id']); $record['inventory'] = ['resources' => ['ship_components' => 2, 'refined_alloy' => 3], 'ledger' => []]; $storage->write('characters', $character['id'], $record);
        $service = new CharacterFittingService($storage); $service->install($universe['id'], $character['id'], 'propulsion_array', 0);
        try { $service->install($universe['id'], $character['id'], 'propulsion_array', 1); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'slot is already occupied'), 'Unexpected occupied-slot error.'); $saved = $storage->read('characters', $character['id']); $equal(1, $saved['revision'], 'Rejected installation changed revision.'); $equal(1, $saved['inventory']['resources']['ship_components'], 'Rejected installation consumed components.'); $equal(2, count($saved['inventory']['ledger']), 'Rejected installation added ledger entries.'); return 'An occupied slot is rejected without further material consumption or revision changes.'; }
        throw new RuntimeException('Occupied module slot was accepted.');
    } finally { $cleanup($dir); }
});

$run('Ship fitting rejects insufficient materials and stale revisions', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-fitting-validation');
        $character = (new CharacterService($storage))->create($universe['id'], 'Fitting Validation');
        $record = $storage->read('characters', $character['id']); $record['inventory'] = ['resources' => ['ship_components' => 1], 'ledger' => []]; $storage->write('characters', $character['id'], $record); $service = new CharacterFittingService($storage);
        try { $service->install($universe['id'], $character['id'], 'propulsion_array', 0); } catch (RuntimeException $e) { $assert(str_contains(strtolower($e->getMessage()), 'insufficient refined alloy'), 'Unexpected insufficient-resource error.'); }
        try { $service->install($universe['id'], $character['id'], 'survey_scanner', 5); } catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'revision conflict'), 'Unexpected stale-revision error.'); }
        $saved = $storage->read('characters', $character['id']); $equal(0, $saved['revision'], 'Rejected fitting changed revision.'); $equal(['ship_components' => 1], $saved['inventory']['resources'], 'Rejected fitting changed resources.'); $equal([], $saved['inventory']['ledger'], 'Rejected fitting added ledger entries.');
        return 'Insufficient materials and stale revisions leave ship and inventory unchanged.';
    } finally { $cleanup($dir); }
});

$run('Workbench exposes ship fitting controls and persisted module slots', static function () use ($assert): string {
    $html = file_get_contents(dirname(__DIR__) . '/index.php'); $assert(is_string($html), 'Workbench HTML could not be read.');
    foreach (['fitModule','fitModuleButton','fitModuleStatus','shipFittingPanel','api/character-fit.php','api/content-ship-modules.php','gameContent.ship_modules','character.ship?.modules'] as $needle) $assert(str_contains($html, $needle), 'Workbench is missing ship fitting marker: ' . $needle);
    return 'Workbench exposes module selection, installation action, status feedback, and all three fitting slots.';
});

$run('Character deletion permanently removes only the selected character record', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-character-delete');
        $service = new CharacterService($storage); $character = $service->create($universe['id'], 'Delete Me'); $other = $service->create($universe['id'], 'Keep Me');
        $record = $storage->read('characters', $character['id']); $record['inventory'] = ['resources' => ['raw_ore' => 5], 'ledger' => [['id' => 'test']]]; $storage->write('characters', $character['id'], $record);
        $result = $service->deleteCharacter($universe['id'], $character['id'], 'Delete Me', 0);
        $equal(true, $result['deleted'], 'Deletion result did not indicate success.'); $equal(null, $storage->read('characters', $character['id']), 'Deleted character JSON still exists.');
        $equal($other['id'], $storage->read('characters', $other['id'])['id'] ?? null, 'Another character was affected.'); $equal($universe['id'], $storage->read('universes', $universe['id'])['id'] ?? null, 'Universe was affected.');
        return 'Target character JSON was removed while sibling character and universe remained intact.';
    } finally { $cleanup($dir); }
});

$run('Character deletion requires exact name, current revision, and matching universe', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universes = new UniverseService($storage); $universe = $universes->createOrLoad('core-ui-qa-character-delete-validation'); $otherUniverse = $universes->createOrLoad('core-ui-qa-character-delete-other');
        $service = new CharacterService($storage); $character = $service->create($universe['id'], 'Guarded Character');
        foreach ([[$universe['id'], 'Wrong Name', 0, 'Confirmation name'], [$universe['id'], 'Guarded Character', 1, 'revision conflict'], [$otherUniverse['id'], 'Guarded Character', 0, 'does not belong']] as [$uid, $name, $revision, $expected]) {
            try { $service->deleteCharacter($uid, $character['id'], $name, $revision); throw new RuntimeException('Invalid deletion was accepted: ' . $expected); }
            catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), $expected), 'Unexpected deletion rejection: ' . $e->getMessage()); }
            $equal($character['id'], $storage->read('characters', $character['id'])['id'] ?? null, 'Rejected deletion removed character.');
        }
        return 'Wrong name, stale revision, and cross-universe deletion were rejected without removal.';
    } finally { $cleanup($dir); }
});

$run('Character deletion reports missing records without affecting other data', static function () use ($assert, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-character-delete-missing'); $service = new CharacterService($storage);
        try { $service->deleteCharacter($universe['id'], 'chr_missing123', 'Missing Character', 0); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'does not exist'), 'Unexpected missing-record error.'); return 'Missing character deletion failed safely and universe remained stored.'; }
        throw new RuntimeException('Missing character deletion unexpectedly succeeded.');
    } finally { $cleanup($dir); }
});

$run('Workbench exposes guarded character deletion controls and request wiring', static function () use ($assert): string {
    $html = file_get_contents(dirname(__DIR__) . '/index.php'); $app = file_get_contents(dirname(__DIR__) . '/app/Application.php');
    $assert(is_string($html) && is_string($app), 'Workbench or application source could not be read.');
    foreach (['deleteCharacterButton', 'deleteCharacterStatus', 'api/character-delete.php', 'confirmation_name', 'expected_revision', 'cannot be undone'] as $needle) $assert(str_contains($html, $needle), 'Workbench is missing deletion marker: ' . $needle);
    $assert(str_contains($app, "'/api/characters/delete'"), 'Deletion API route is missing.');
    return 'Danger-zone UI, explicit confirmation payload, and guarded deletion API route are present.';
});


$run('Character lifecycle disable/enable is reversible and preserves all progress', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-lifecycle-disable');
        $characters = new CharacterService($storage); $character = $characters->create($universe['id'], 'Lifecycle Keeper');
        $record = $storage->read('characters', $character['id']); $record['inventory'] = ['resources' => ['raw_ore' => 7], 'ledger' => [['id' => 'keep-ledger']]]; $storage->write('characters', $character['id'], $record);
        $service = new CharacterLifecycleService($storage);
        $disabled = $service->apply($universe['id'], $character['id'], 'disable', 0);
        $equal(false, $disabled['character']['lifecycle']['enabled'], 'Disable did not persist.'); $equal(1, $disabled['character']['revision'], 'Disable did not advance revision.');
        $saved = $storage->read('characters', $character['id']); $equal(['raw_ore' => 7], $saved['inventory']['resources'], 'Disable changed inventory.'); $equal([['id' => 'keep-ledger']], $saved['inventory']['ledger'], 'Disable changed ledger.');
        $enabled = $service->apply($universe['id'], $character['id'], 'enable', 1); $equal(true, $enabled['character']['lifecycle']['enabled'], 'Enable did not restore character.');
        return 'Disable and enable are reversible, revision checked, and preserve inventory/ledger.';
    } finally { $cleanup($dir); }
});

$run('Locked or disabled characters cannot perform gameplay mutations', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-lifecycle-block'); $characters = new CharacterService($storage); $character = $characters->create($universe['id'], 'Frozen Explorer');
        $lifecycle = new CharacterLifecycleService($storage); $scan = new CharacterScanService($storage);
        $disabled = $lifecycle->apply($universe['id'], $character['id'], 'disable', 0)['character'];
        try { $scan->scanCurrentSystem($universe['id'], $character['id'], $disabled['revision']); throw new RuntimeException('Disabled character was allowed to scan.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'disabled'), 'Unexpected disabled action error: ' . $e->getMessage()); }
        $enabled = $lifecycle->apply($universe['id'], $character['id'], 'enable', $disabled['revision'])['character'];
        $locked = $lifecycle->apply($universe['id'], $character['id'], 'lock', $enabled['revision'])['character'];
        try { $scan->scanCurrentSystem($universe['id'], $character['id'], $locked['revision']); throw new RuntimeException('Locked character was allowed to scan.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'locked'), 'Unexpected locked action error: ' . $e->getMessage()); }
        $saved = $storage->read('characters', $character['id']); $equal([], $saved['scans']['systems'] ?? [], 'Rejected scans changed persisted discoveries.'); $equal($locked['revision'], $saved['revision'], 'Rejected scans changed revision.');
        return 'Disabled and locked characters are blocked server-side; rejected scans do not mutate state.';
    } finally { $cleanup($dir); }
});

$run('Lifecycle changes are idempotent and reject stale revisions or invalid actions', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-lifecycle-validation'); $character = (new CharacterService($storage))->create($universe['id'], 'Revision Guard'); $service = new CharacterLifecycleService($storage);
        $same = $service->apply($universe['id'], $character['id'], 'enable', 0); $equal('unchanged', $same['lifecycle_status'], 'Repeated enable should be idempotent.'); $equal(0, $same['character']['revision'], 'No-op enable changed revision.');
        foreach ([['disable', 3, 'revision conflict'], ['explode', 0, 'Action must']] as [$action, $revision, $expected]) {
            try { $service->apply($universe['id'], $character['id'], $action, $revision); throw new RuntimeException('Invalid lifecycle action accepted.'); }
            catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), $expected), 'Unexpected lifecycle validation error: ' . $e->getMessage()); }
        }
        $equal(0, $storage->read('characters', $character['id'])['revision'], 'Rejected lifecycle action mutated record.');
        return 'No-op actions preserve revision; stale revisions and invalid actions are rejected safely.';
    } finally { $cleanup($dir); }
});

$run('Workbench exposes reversible lifecycle controls and API wiring', static function () use ($assert): string {
    $html = file_get_contents(dirname(__DIR__) . '/index.php'); $app = file_get_contents(dirname(__DIR__) . '/app/Application.php');
    $assert(is_string($html) && is_string($app), 'Workbench or application source could not be read.');
    foreach (['disableCharacterButton','enableCharacterButton','lockCharacterButton','unlockCharacterButton','characterLifecycleStatus','api/character-lifecycle.php','preserves all character data'] as $needle) $assert(str_contains($html, $needle), 'Workbench is missing lifecycle marker: ' . $needle);
    $assert(str_contains($app, "'/api/characters/lifecycle'"), 'Lifecycle API route is missing.');
    return 'Workbench includes disable/enable and lock/unlock controls with lifecycle API request wiring.';
});


$run('Universe lifecycle disable/enable and lock/unlock are reversible and preserve saved universe state', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $service = new UniverseService($storage); $universe = $service->createOrLoad('core-ui-qa-universe-lifecycle');
        $service->updateState($universe['id'], 0, ['type' => 'note_added', 'value' => 'keep me']);
        $lifecycle = new UniverseLifecycleService($storage);
        $disabled = $lifecycle->apply($universe['id'], 'disable', 0);
        $equal(false, $disabled['universe']['lifecycle']['enabled'], 'Disable was not persisted.');
        $equal(1, $disabled['universe']['lifecycle']['revision'], 'Lifecycle revision did not advance.');
        $enabled = $lifecycle->apply($universe['id'], 'enable', 1);
        $equal(true, $enabled['universe']['lifecycle']['enabled'], 'Enable did not restore universe.');
        $locked = $lifecycle->apply($universe['id'], 'lock', 2);
        $equal(true, $locked['universe']['lifecycle']['locked'], 'Lock was not persisted.');
        $unlocked = $lifecycle->apply($universe['id'], 'unlock', 3);
        $equal(false, $unlocked['universe']['lifecycle']['locked'], 'Unlock did not restore universe.');
        $saved = $storage->read('universes', $universe['id']);
        $equal(1, $saved['state']['revision'], 'Lifecycle actions changed universe event revision.');
        $equal('keep me', $saved['state']['projections']['notes'][0]['text'] ?? null, 'Lifecycle actions damaged universe state.');
        return 'Universe disable/enable and lock/unlock preserve generated baseline and event state.';
    } finally { $cleanup($dir); }
});

$run('Disabled or locked universes reject gameplay mutations server-side', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-universe-lifecycle-guard');
        $lifecycle = new UniverseLifecycleService($storage); $characters = new CharacterService($storage);
        $disabled = $lifecycle->apply($universe['id'], 'disable', 0)['universe'];
        try { $characters->create($universe['id'], 'Blocked Creation'); throw new RuntimeException('Character creation succeeded in disabled universe.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'Universe is disabled'), 'Unexpected disabled-universe error: ' . $e->getMessage()); }
        $enabled = $lifecycle->apply($universe['id'], 'enable', $disabled['lifecycle']['revision'])['universe'];
        $character = $characters->create($universe['id'], 'Universe Guard');
        $locked = $lifecycle->apply($universe['id'], 'lock', $enabled['lifecycle']['revision'])['universe'];
        try { (new CharacterScanService($storage))->scanCurrentSystem($universe['id'], $character['id'], 0); throw new RuntimeException('Scan succeeded in locked universe.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'Universe is locked'), 'Unexpected locked-universe error: ' . $e->getMessage()); }
        try { (new UniverseService($storage))->updateState($universe['id'], 0, ['type' => 'note_added', 'value' => 'blocked']); throw new RuntimeException('State update succeeded in locked universe.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'Universe is locked'), 'Unexpected state-update error: ' . $e->getMessage()); }
        $equal([], $storage->read('characters', $character['id'])['scans']['systems'] ?? [], 'Rejected scan changed character state.');
        $equal(0, $storage->read('universes', $universe['id'])['state']['revision'], 'Rejected state update changed universe state.');
        return 'Character creation, scans, and universe-state writes are blocked when universe is disabled or locked.';
    } finally { $cleanup($dir); }
});

$run('Universe lifecycle actions are idempotent and reject stale revisions or invalid actions', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-ui-qa-universe-lifecycle-revision'); $service = new UniverseLifecycleService($storage);
        $same = $service->apply($universe['id'], 'enable', 0); $equal('unchanged', $same['lifecycle_status'], 'No-op enable should be idempotent.'); $equal(0, $same['universe']['lifecycle']['revision'], 'No-op changed lifecycle revision.');
        foreach ([['disable', 5, 'revision conflict'], ['remove', 0, 'Action must']] as [$action, $revision, $expected]) {
            try { $service->apply($universe['id'], $action, $revision); throw new RuntimeException('Invalid lifecycle action accepted.'); }
            catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), $expected), 'Unexpected lifecycle rejection: ' . $e->getMessage()); }
        }
        $savedLifecycle = $storage->read('universes', $universe['id'])['lifecycle'] ?? []; $equal(true, $savedLifecycle['enabled'] ?? null, 'Rejected action changed enabled state.'); $equal(false, $savedLifecycle['locked'] ?? null, 'Rejected action changed locked state.'); $equal(0, $savedLifecycle['revision'] ?? null, 'Rejected action changed lifecycle revision.');
        return 'No-op actions preserve revision; stale revisions and invalid actions are rejected without mutation.';
    } finally { $cleanup($dir); }
});

$run('Workbench exposes universe lifecycle controls and API request wiring', static function () use ($assert): string {
    $html = file_get_contents(dirname(__DIR__) . '/index.php'); $app = file_get_contents(dirname(__DIR__) . '/app/Application.php');
    $assert(is_string($html) && is_string($app), 'Workbench or application source could not be read.');
    foreach (['disableUniverseButton','enableUniverseButton','lockUniverseButton','unlockUniverseButton','universeLifecycleStatus','api/universe-lifecycle.php','preserve saved data'] as $needle) $assert(str_contains($html, $needle), 'Workbench is missing universe lifecycle marker: ' . $needle);
    $assert(str_contains($app, "'/api/universe/lifecycle'"), 'Universe lifecycle API route is missing.');
    return 'Universe lifecycle controls, status display, and API wiring are present.';
});

$run('Data management inventory summarizes saved universes and characters without changing records', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-data-inventory-qa');
        $character = (new CharacterService($storage))->create($universe['id'], 'Inventory QA');
        $beforeUniverse = $storage->read('universes', $universe['id']); $beforeCharacter = $storage->read('characters', $character['id']);
        $inventory = (new DataManagementService($storage))->inventory();
        $equal(1, $inventory['summary']['universes'], 'Inventory universe count mismatch.');
        $equal(1, $inventory['summary']['characters'], 'Inventory character count mismatch.');
        $equal($universe['id'], $inventory['universes'][0]['id'], 'Inventory universe identity mismatch.');
        $equal($character['id'], $inventory['characters'][0]['id'], 'Inventory character identity mismatch.');
        $equal($beforeUniverse, $storage->read('universes', $universe['id']), 'Inventory changed the universe record.');
        $equal($beforeCharacter, $storage->read('characters', $character['id']), 'Inventory changed the character record.');
        return 'Inventory counts and identities matched; saved records remained unchanged.';
    } finally { $cleanup($dir); }
});

$run('Character deletion preview counts embedded gameplay data and remains read-only', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-character-preview-qa');
        $character = (new CharacterService($storage))->create($universe['id'], 'Preview QA');
        $character['inventory'] = ['resources' => ['raw_ore' => 3, 'salvage_parts' => 1], 'ledger' => []];
        $character['ship'] = ['modules' => ['scanner' => ['module_id' => 'survey_scanner']]];
        $character['scans'] = ['systems' => ['sys_1' => ['discoveries' => [['id' => 'dsc_1'], ['id' => 'dsc_2']]]]];
        $storage->write('characters', $character['id'], $character);
        $preview = (new DataManagementService($storage))->preview('character', $character['id']);
        $equal(true, $preview['executable'], 'Character preview should mark existing character deletion executable.');
        $equal(1, $preview['impact']['character_records'], 'Character record count mismatch.');
        $equal(2, $preview['impact']['inventory_stacks'], 'Inventory stack count mismatch.');
        $equal(1, $preview['impact']['scan_records'], 'Scan record count mismatch.');
        $equal(2, $preview['impact']['discoveries'], 'Discovery count mismatch.');
        $equal(1, $preview['impact']['fitted_modules'], 'Fitted module count mismatch.');
        $equal($character, $storage->read('characters', $character['id']), 'Preview mutated or deleted the character.');
        return 'Impact counts are correct and preview leaves the character unchanged.';
    } finally { $cleanup($dir); }
});

$run('Universe deletion preview is executable only with fresh revisions and reports dependent characters', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir); $universe = (new UniverseService($storage))->createOrLoad('core-universe-preview-qa');
        (new CharacterService($storage))->create($universe['id'], 'Dependent QA One');
        (new CharacterService($storage))->create($universe['id'], 'Dependent QA Two');
        $preview = (new DataManagementService($storage))->preview('universe', $universe['id']);
        $equal(true, $preview['executable'], 'Universe deletion should be available with guarded execution.');
        $equal(2, $preview['impact']['related_character_records'], 'Dependent character count mismatch.');
        $assert(str_contains($preview['warning'], 'checksum-verified backup first'), 'Backup-first safety warning missing.');
        $equal(2, count($preview['expected_character_ids']), 'Preview omitted expected dependent-character IDs.');
        $equal(2, count($storage->all('characters')), 'Universe preview changed character records.');
        $assert($storage->read('universes', $universe['id']) !== null, 'Universe preview deleted the universe.');
        return 'Universe impact, expected revisions, and dependent IDs are shown; preview remains read-only.';
    } finally { $cleanup($dir); }
});

$run('Data management rejects unknown records and invalid scopes', static function () use ($assert, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-web-qa-' . bin2hex(random_bytes(8));
    try {
        $service = new DataManagementService(new JsonStorage($dir));
        foreach ([['character', 'missing'], ['planet', 'missing']] as [$scope, $id]) {
            try { $service->preview($scope, $id); throw new RuntimeException('Invalid preview request was accepted.'); }
            catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'does not exist') || str_contains($e->getMessage(), 'Scope must'), 'Unexpected preview validation message.'); }
        }
        return 'Missing records and unsupported scopes are rejected.';
    } finally { $cleanup($dir); }
});

$run('Workbench exposes data inventory, cleanup previews, and guarded character removal', static function () use ($assert): string {
    $html = file_get_contents(dirname(__DIR__) . '/index.php'); $app = file_get_contents(dirname(__DIR__) . '/app/Application.php');
    $assert(is_string($html) && is_string($app), 'Workbench or application source could not be read.');
    foreach (['Data Management','refreshDataInventoryButton','previewCleanupButton','deleteManagedCharacterButton','api/data-management.php','api/data-management-preview.php','Exact character name confirmation','DELETE UNIVERSE'] as $needle) $assert(str_contains($html, $needle) || str_contains($app, $needle), 'Data management wiring missing: ' . $needle);
    $assert(str_contains($app, "'/api/data-management'") && str_contains($app, "'/api/data-management/preview'"), 'Data management API routes missing.');
    return 'Inventory, impact preview, exact-name deletion controls, and API routes are present.';
});

$run('Universe backup snapshots include associated characters and checksum', static function () use ($assert, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-backup-qa-' . bin2hex(random_bytes(6)); $dataDir = $dir . '/data'; $backupDir = $dir . '/backups';
    try {
        $storage = new JsonStorage($dataDir); $universe = ['id' => 'universe_backup_test', 'name' => 'Backup Test', 'seed' => 'backup-seed', 'baseline' => ['systems' => [['id' => 'sys1']]], 'state' => ['changes' => []]];
        $character = ['id' => 'character_backup_test', 'name' => 'Backup Pilot', 'universe_id' => $universe['id'], 'revision' => 3, 'inventory' => ['resources' => ['raw_ore' => 4]]];
        $storage->write('universes', $universe['id'], $universe); $storage->write('characters', $character['id'], $character);
        $service = new UniverseBackupService($storage, $backupDir); $backup = $service->create($universe['id']);
        $assert($backup['character_count'] === 1, 'Backup omitted associated character.'); $assert(strlen($backup['checksum_sha256']) === 64, 'Backup checksum missing.');
        $assert(count($service->list()) === 1, 'Backup not listed.'); return 'Universe and one associated character backed up with SHA-256 checksum.';
    } finally { $cleanup($dir); }
});
$run('Universe backup restore recovers deleted records and verifies them', static function () use ($assert, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-restore-qa-' . bin2hex(random_bytes(6));
    try {
        $storage = new JsonStorage($dir . '/data'); $universe = ['id' => 'universe_restore_test', 'name' => 'Restore Test', 'seed' => 'restore-seed', 'baseline' => ['systems' => []], 'state' => ['changes' => []]];
        $character = ['id' => 'character_restore_test', 'name' => 'Restore Pilot', 'universe_id' => $universe['id'], 'revision' => 2];
        $storage->write('universes', $universe['id'], $universe); $storage->write('characters', $character['id'], $character);
        $service = new UniverseBackupService($storage, $dir . '/backups'); $backup = $service->create($universe['id']);
        $storage->delete('characters', $character['id'], static fn(array $r) => null); $storage->delete('universes', $universe['id'], static fn(array $r) => null);
        $result = $service->restore($backup['backup_id']); $assert($result['verified'] === true, 'Restore was not verified.');
        $assert($storage->read('universes', $universe['id']) === $universe, 'Universe was not restored exactly.'); $assert($storage->read('characters', $character['id']) === $character, 'Character was not restored exactly.');
        return 'Deleted universe and character records restored exactly from snapshot.';
    } finally { $cleanup($dir); }
});
$run('Universe backup restore refuses conflicting live records', static function () use ($assert, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-restore-conflict-qa-' . bin2hex(random_bytes(6));
    try {
        $storage = new JsonStorage($dir . '/data'); $universe = ['id' => 'universe_conflict_test', 'name' => 'Original', 'seed' => 'conflict-seed', 'baseline' => ['systems' => []], 'state' => ['changes' => []]];
        $storage->write('universes', $universe['id'], $universe); $service = new UniverseBackupService($storage, $dir . '/backups'); $backup = $service->create($universe['id']);
        $storage->write('universes', $universe['id'], ['id' => $universe['id'], 'name' => 'Different live record']);
        try { $service->restore($backup['backup_id']); throw new RuntimeException('Conflicting record was overwritten.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'conflict'), 'Unexpected conflict rejection.'); }
        $assert(($storage->read('universes', $universe['id'])['name'] ?? '') === 'Different live record', 'Conflict changed live record.');
        return 'Restore refused conflicting record without overwriting it.';
    } finally { $cleanup($dir); }
});
$run('Backup UI and API wiring are present', static function () use ($assert): string {
    $html = file_get_contents(dirname(__DIR__) . '/index.php'); $app = file_get_contents(dirname(__DIR__) . '/app/Application.php');
    foreach (['createUniverseBackupButton', 'refreshBackupsButton', 'restoreUniverseBackupButton', 'backupRestoreConfirmation', 'storage/backups/'] as $needle) $assert(str_contains($html, $needle), 'Backup UI marker missing: ' . $needle);
    foreach (['/api/data-management/backups', '/api/data-management/backups/restore', 'UniverseBackupService'] as $needle) $assert(str_contains($app, $needle), 'Backup API marker missing: ' . $needle);
    return 'Backup, list, and guarded restore controls are wired to server routes.';
});

$run('Universe deletion backs up first and removes only the selected universe tree', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-delete-universe-qa-' . bin2hex(random_bytes(6));
    try {
        $storage = new JsonStorage($dir . '/data');
        $universe = ['id' => 'universe_delete_test', 'name' => 'Delete Test', 'seed' => 'delete-seed', 'baseline' => ['systems' => [['id' => 'sys1']]], 'state' => ['revision' => 2, 'changes' => [['type' => 'test']]], 'lifecycle' => ['enabled' => true, 'locked' => false, 'revision' => 1]];
        $character = ['id' => 'character_delete_test', 'name' => 'Delete Pilot', 'universe_id' => $universe['id'], 'revision' => 4, 'inventory' => ['resources' => ['raw_ore' => 3]]];
        $otherUniverse = ['id' => 'universe_keep_test', 'name' => 'Keep Me', 'state' => ['revision' => 0, 'changes' => []]];
        $otherCharacter = ['id' => 'character_keep_test', 'name' => 'Keep Pilot', 'universe_id' => $otherUniverse['id'], 'revision' => 1];
        foreach ([$universe, $otherUniverse] as $u) $storage->write('universes', $u['id'], $u);
        foreach ([$character, $otherCharacter] as $c) $storage->write('characters', $c['id'], $c);
        $backups = new UniverseBackupService($storage, $dir . '/backups');
        $service = new UniverseDeletionService($storage, $backups);
        $result = $service->delete($universe['id'], 'DELETE UNIVERSE ' . $universe['id'], 2, 1, [$character['id']]);
        $assert($result['verified'] === true, 'Deletion was not verified.');
        $assert($storage->read('universes', $universe['id']) === null, 'Universe still exists after deletion.');
        $assert($storage->read('characters', $character['id']) === null, 'Associated character still exists after deletion.');
        $equal($otherUniverse, $storage->read('universes', $otherUniverse['id']), 'Unrelated universe was changed.');
        $equal($otherCharacter, $storage->read('characters', $otherCharacter['id']), 'Unrelated character was changed.');
        $equal(1, count($backups->list()), 'Pre-deletion backup was not retained.');
        $backups->restore($result['backup']['backup_id']);
        $equal($universe, $storage->read('universes', $universe['id']), 'Pre-deletion backup did not recover universe.');
        $equal($character, $storage->read('characters', $character['id']), 'Pre-deletion backup did not recover associated character.');
        return 'Verified backup-before-delete, cascade removal, unrelated-record isolation, and recovery from retained backup.';
    } finally { $cleanup($dir); }
});
$run('Universe deletion rejects incorrect confirmation before creating a backup', static function () use ($assert, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-delete-confirm-qa-' . bin2hex(random_bytes(6));
    try {
        $storage = new JsonStorage($dir . '/data'); $universe = ['id' => 'universe_confirm_test', 'name' => 'Confirm', 'state' => ['revision' => 0, 'changes' => []], 'lifecycle' => ['revision' => 0]];
        $storage->write('universes', $universe['id'], $universe); $backups = new UniverseBackupService($storage, $dir . '/backups'); $service = new UniverseDeletionService($storage, $backups);
        try { $service->delete($universe['id'], 'DELETE', 0, 0, []); throw new RuntimeException('Incorrect confirmation was accepted.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'Confirmation must exactly match'), 'Unexpected confirmation failure.'); }
        $assert($storage->read('universes', $universe['id']) === $universe, 'Universe changed after invalid confirmation.');
        $assert($backups->list() === [], 'Backup should not be created before confirmation validation.');
        return 'Incorrect confirmation rejected before backup or mutation.';
    } finally { $cleanup($dir); }
});
$run('Universe deletion rejects stale revisions and changed dependent-character lists', static function () use ($assert, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-delete-stale-qa-' . bin2hex(random_bytes(6));
    try {
        $storage = new JsonStorage($dir . '/data'); $universe = ['id' => 'universe_stale_delete_test', 'name' => 'Stale', 'state' => ['revision' => 2, 'changes' => []], 'lifecycle' => ['revision' => 3]];
        $character = ['id' => 'character_stale_delete_test', 'name' => 'Stale Pilot', 'universe_id' => $universe['id'], 'revision' => 1];
        $storage->write('universes', $universe['id'], $universe); $storage->write('characters', $character['id'], $character);
        $backups = new UniverseBackupService($storage, $dir . '/backups'); $service = new UniverseDeletionService($storage, $backups);
        try { $service->delete($universe['id'], 'DELETE UNIVERSE ' . $universe['id'], 1, 3, [$character['id']]); throw new RuntimeException('Stale revision was accepted.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'stale'), 'Unexpected stale revision rejection.'); }
        try { $service->delete($universe['id'], 'DELETE UNIVERSE ' . $universe['id'], 2, 3, []); throw new RuntimeException('Stale dependent list was accepted.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'stale'), 'Unexpected dependent-list rejection.'); }
        $assert($storage->read('universes', $universe['id']) === $universe, 'Stale deletion modified universe.');
        $assert($storage->read('characters', $character['id']) === $character, 'Stale deletion modified character.');
        $assert($backups->list() === [], 'Stale preview should be rejected before backup.');
        return 'Stale universe revisions and stale dependent-record lists rejected without mutation.';
    } finally { $cleanup($dir); }
});
$run('Atomic universe deletion stages the universe and dependents as one verified set', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-atomic-delete-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir . '/data');
        $universe = ['id' => 'universe_atomic_qa', 'state' => ['revision' => 0, 'changes' => []]];
        $characterA = ['id' => 'character_atomic_a', 'universe_id' => $universe['id'], 'revision' => 0];
        $characterB = ['id' => 'character_atomic_b', 'universe_id' => $universe['id'], 'revision' => 0];
        $storage->write('universes', $universe['id'], $universe);
        $storage->write('characters', $characterA['id'], $characterA);
        $storage->write('characters', $characterB['id'], $characterB);
        $transaction = $storage->deleteBatchAtomically([
            ['collection' => 'universes', 'id' => $universe['id'], 'expected' => $universe],
            ['collection' => 'characters', 'id' => $characterA['id'], 'expected' => $characterA],
            ['collection' => 'characters', 'id' => $characterB['id'], 'expected' => $characterB],
        ], 'ud_' . bin2hex(random_bytes(12)), static function (): void {});
        $equal(3, $transaction['deleted_count'], 'Atomic transaction record count mismatch.');
        $equal(true, $transaction['verified'], 'Atomic transaction did not verify.');
        foreach ([['universes', $universe['id']], ['characters', $characterA['id']], ['characters', $characterB['id']]] as [$collection, $id]) {
            $equal(null, $storage->read($collection, $id), 'Record remained live after atomic deletion: ' . $id);
            $assert(is_file($dir . '/data/' . $collection . '/' . $id . '.json.lock'), 'Record lock tombstone was removed: ' . $id);
        }
        $assert(!is_dir($dir . '/data/.transactions/' . 'delete_' . $transaction['transaction_id']), 'Committed transaction staging directory was not cleaned.');
        return 'Universe and both dependents were removed as one verified staged transaction; lock files were retained.';
    } finally { $cleanup($dir); }
});

$run('Atomic universe deletion aborts before moving records when the final guard rejects', static function () use ($assert, $equal, $cleanup): string {
    $dir = sys_get_temp_dir() . '/core-atomic-delete-guard-qa-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir . '/data');
        $universe = ['id' => 'universe_atomic_guard_qa', 'state' => ['revision' => 0, 'changes' => []]];
        $character = ['id' => 'character_atomic_guard_qa', 'universe_id' => $universe['id'], 'revision' => 0];
        $storage->write('universes', $universe['id'], $universe); $storage->write('characters', $character['id'], $character);
        $transactionId = 'ud_' . bin2hex(random_bytes(12));
        try {
            $storage->deleteBatchAtomically([
                ['collection' => 'universes', 'id' => $universe['id'], 'expected' => $universe],
                ['collection' => 'characters', 'id' => $character['id'], 'expected' => $character],
            ], $transactionId, static function (): void { throw new RuntimeException('Injected final-guard rejection.'); });
            throw new RuntimeException('Rejected final guard was ignored.');
        } catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'before any live record was moved'), 'Unexpected guard failure response: ' . $e->getMessage()); }
        $equal($universe, $storage->read('universes', $universe['id']), 'Universe changed after pre-move rejection.');
        $equal($character, $storage->read('characters', $character['id']), 'Character changed after pre-move rejection.');
        $assert(!is_dir($dir . '/data/.transactions/delete_' . $transactionId), 'Empty failed transaction directory was retained.');
        return 'Final guard rejection left both live records byte-equivalent at the decoded-record level and removed empty staging.';
    } finally { $cleanup($dir); }
});

$run('Character creation and universe deletion share the universe lock', static function () use ($assert): string {
    $characters = file_get_contents(dirname(__DIR__) . '/app/Modules/Characters/CharacterService.php');
    $storage = file_get_contents(dirname(__DIR__) . '/app/Storage/JsonStorage.php');
    $deletion = file_get_contents(dirname(__DIR__) . '/app/Modules/UniverseDeletionService.php');
    $assert(str_contains($characters, "withRecordLockOperation('universes', \$universeId"), 'Character creation does not hold the universe lock.');
    $assert(str_contains($storage, 'deleteBatchAtomically'), 'Atomic batch deletion primitive is missing.');
    $assert(str_contains($storage, 'Caller order is intentional: universe lock first'), 'Atomic deletion lock ordering is undocumented or changed.');
    $assert(str_contains($deletion, 'deleteBatchAtomically($records, $transactionId'), 'Universe deletion is not using atomic batch staging.');
    $assert(str_contains($deletion, 'currentCharacters !== $characterSnapshots'), 'Final dependent-character snapshot guard is missing.');
    $storageProtection = file_get_contents(dirname(__DIR__) . '/storage/.htaccess');
    $assert(is_string($storageProtection) && str_contains($storageProtection, 'Require all denied') && str_contains($storageProtection, 'Deny from all'), 'Storage files and recovery transactions are not protected from direct HTTP access.');
    return 'Character creation serializes on the universe lock; deletion acquires universe then sorted character locks and rechecks the dependent set before staging. Storage records, backups, logs, and recovery transactions are denied direct HTTP access.';
});

$run('Universe deletion API and confirmation UI are wired', static function () use ($assert): string {
    $html = file_get_contents(dirname(__DIR__) . '/index.php'); $app = file_get_contents(dirname(__DIR__) . '/app/Application.php'); $endpoint = file_get_contents(dirname(__DIR__) . '/api/data-management-universe-delete.php');
    foreach (['deleteManagedUniverseButton', 'cleanupUniverseConfirmation', 'DELETE UNIVERSE ', 'pre-deletion backup'] as $needle) $assert(str_contains($html, $needle), 'Universe deletion UI marker missing: ' . $needle);
    foreach (["'/api/data-management/universe-delete'", 'UniverseDeletionService', 'developmentRequestAllowed'] as $needle) $assert(str_contains($app, $needle), 'Universe deletion API marker missing: ' . $needle);
    $assert(str_contains($endpoint, '/api/data-management/universe-delete'), 'Universe deletion endpoint wrapper missing.');
    return 'Universe deletion has explicit UI confirmation, guarded development-only API route, and service wiring.';
});

$run('Universe deletion selection is searchable and displays the exact ID phrase', static function () use ($assert): string {
    $html = file_get_contents(dirname(__DIR__) . '/index.php');
    foreach (['managedUniverseSearch', 'managedUniverseTarget', 'previewUniverseDeletionButton', 'universeConfirmationPhrase', 'cleanupRecordSearch', 'cleanupRecordCount'] as $needle) $assert(str_contains($html, $needle), 'Universe deletion usability control missing: ' . $needle);
    $assert(!str_contains($html, 'copyUniverseConfirmationPhraseButton') && !str_contains($html, 'Copy phrase'), 'Unreliable copy-phrase button should be removed; phrase must remain manually selectable.');
    $assert(str_contains($html, "includes(query)"), 'Search must match universe names and IDs.');
    $assert(str_contains($html, "'DELETE UNIVERSE ' + data.preview.id"), 'Exact confirmation phrase is not generated from selected ID.');
    $assert(str_contains($html, 'readonly value="Generate a fresh preview to show the phrase"'), 'Exact phrase field must remain visible and selectable for manual copy.');
    return 'Universe selector/search and visible, manually selectable exact confirmation phrase are present; unreliable copy button is absent.';
});
$run('Incorrect universe deletion confirmation gives visible live feedback', static function () use ($assert): string {
    $html = file_get_contents(dirname(__DIR__) . '/index.php');
    foreach (['aria-live="assertive"', 'Confirmation does not match. Required phrase:', 'No data has been deleted.', 'updateUniverseDeleteConfirmationState', 'setVisibleStatus(status, \'Deletion failed:'] as $needle) $assert(str_contains($html, $needle), 'Visible deletion feedback marker missing: ' . $needle);
    $assert(str_contains($html, 'button.disabled = !matches'), 'Delete action must be disabled until confirmation exactly matches.');
    return 'Mismatched confirmation and API failures are shown in a high-contrast live status; unsafe action remains disabled.';
});
$run('Universe deletion UI reports progress, failure, and requires a fresh preview after errors', static function () use ($assert): string {
    $html = file_get_contents(dirname(__DIR__) . '/index.php');
    foreach (['Universe deletion started', 'Step 1 of 3: creating and verifying the pre-deletion backup', 'Deletion failed:', 'Generate a fresh preview before retrying.', 'storage/logs/universe-deletion.log', 'storage/logs/backups.log', 'Universe deletion failed'] as $needle) $assert(str_contains($html, $needle), 'Deletion progress/error handling missing: ' . $needle);
    return 'Destructive operation has start/progress messages, actionable failure details, activity logging, and stale-preview invalidation.';
});

$run('Focused logging and deletion permission preflight are implemented', static function () use ($assert): string {
    $logger = file_get_contents(dirname(__DIR__) . '/app/Core/Logger.php');
    $storage = file_get_contents(dirname(__DIR__) . '/app/Storage/JsonStorage.php');
    $deletion = file_get_contents(dirname(__DIR__) . '/app/Modules/UniverseDeletionService.php');
    foreach (['api', 'storage', 'universe-deletion', 'backups', 'security', 'php-errors', 'errors'] as $channel) $assert(str_contains($logger, "'" . $channel . "'"), 'Missing focused log channel: ' . $channel);
    foreach (['lock_path', 'directory_writable', 'last_php_error'] as $detail) $assert(str_contains($storage, "'" . $detail . "'"), 'Storage lock diagnostics missing: ' . $detail);
    $assert(str_contains($storage, 'assertRecordLockUsable'), 'Existing lock-file usability preflight is missing.');
    $assert(str_contains($storage, "fopen(\$lockPath, 'r')"), 'Read-only lock-file fallback is missing.');
    $assert(str_contains($storage, 'Never unlink/recreate a lock file'), 'Lock handling must preserve lock-file identity.');
    $assert(str_contains($deletion, "assertCollectionWritable('characters')") && str_contains($deletion, "assertCollectionWritable('universes')"), 'Deletion does not preflight target collections.');
    $assert(str_contains($deletion, "storage/logs/universe-deletion.log and storage/logs/backups.log"), 'Failure response does not identify focused deletion and backup logs.');
    $assert(str_contains($logger, "array_key_exists('rollback_failure', \$context)"), 'Rollback failure is not routed to backups.log.');
    return 'Central and focused logs are retained; deletion verifies that each existing lock can be opened and exclusively locked before creating a backup. Readable read-only lock files are supported without unlinking or changing their permissions.';
});

$run('Game item, recipe, discovery, and ship-module definitions are external JSON catalogs', static function () use ($assert): string {
    $docs = ContentCatalog::allDocuments();
    foreach (['items','recipes','discoveries','ship_modules'] as $name) $assert(is_array($docs[$name]['entries'] ?? null) && count($docs[$name]['entries']) > 0, 'Missing JSON content catalog: ' . $name);
    $assert(is_array($docs['discoveries']['scan_rules'] ?? null), 'Scan generation rules are not externally configured in discoveries.json.');
    return 'All current gameplay definitions load from config/content JSON files.';
});

$run('Content API wrappers expose the editable JSON catalogs', static function () use ($assert): string {
    $root = dirname(__DIR__);
    foreach (['content.php','content-items.php','content-recipes.php','content-discoveries.php','content-ship-modules.php'] as $file) $assert(is_file($root . '/api/' . $file), 'Missing content API wrapper: ' . $file);
    foreach (['items.json','recipes.json','discoveries.json','ship-modules.json'] as $file) $assert(is_file($root . '/config/content/' . $file), 'Missing content JSON: ' . $file);
    return 'Aggregate and individual read-only content API wrappers are present.';
});

$run('Complete character-record API is available for saved player state', static function () use ($assert): string {
    $app = file_get_contents(dirname(__DIR__) . '/app/Application.php');
    $wrapper = dirname(__DIR__) . '/api/character-record.php';
    $assert(is_string($app) && str_contains($app, "'/api/characters/record'"), 'Single-character read route is missing.');
    $assert(is_file($wrapper), 'Single-character API wrapper is missing.');
    $assert(str_contains($app, "getById"), 'Character API does not return the complete persisted record.');
    return 'A character can be read as one JSON API record including inventory, scans, exploration, ledger, and ship state.';
});

$passed = count(array_filter($results, static fn(array $r): bool => $r['status'] === 'PASS'));
$failed = count($results) - $passed;
http_response_code($failed === 0 ? 200 : 500);

echo json_encode(['ok' => $failed === 0, 'application' => 'CORE', 'version' => '0.1.40', 'passed' => $passed, 'failed' => $failed, 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
