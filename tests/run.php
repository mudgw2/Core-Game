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
use Core\Modules\Universe\WorldGenerator;
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

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$test('Constellation and System views distinguish Constellations, Star Systems, and Worlds without changing persisted keys', static function () use ($assert): void {
    $view = file_get_contents(CORE_ROOT . '/player/game.php');
    $script = file_get_contents(CORE_ROOT . '/player/player.js');
    $selection = file_get_contents(CORE_ROOT . '/player/selection.php');
    $assert(str_contains($view, '<dt>Constellations</dt>'), 'Universe summary does not label the top-level count as constellations.');
    $assert(str_contains($view, 'id="selectedWorldList"') && str_contains($script, 'WORLDS IN THIS SYSTEM'), 'Selected star system does not list its generated worlds.');
    $assert(str_contains($script, "viewButton.textContent='View Selected System'"), 'Star system detail action is not labeled correctly.');
    $assert(str_contains($view, 'Current Constellation') && str_contains($view, 'Current System'), 'Character location does not display Constellation and System separately.');
    $assert(str_contains($script, 'core:world-selected'), 'World list selection does not emit the shared world-selected event.');
    $assert(str_contains($script, 'star systems in') && str_contains($script, 'visibleSystems()'), 'Map status does not scope Systems to the selected Constellation.');
    $assert(str_contains($view, "nestedSystemRecord['worlds']"), 'Player payload does not populate nested Constellation -> System -> World hierarchy.');
    $assert(str_contains($selection, 'Last known constellation'), 'Character roster location label is not constellation-aware.');
    $assert(str_contains($script, 'current_system_id') && str_contains($script, 'system_id'), 'Existing internal system identifiers were unexpectedly removed.');
    $assert(str_contains($view, "'initialSystemId' => (string)("), 'Gameplay boot still allows a stale URL system_id to override the character location.');
    $assert(str_contains($script, "dispatchEvent(new CustomEvent('core:world-selected'"), 'World list clicks do not update the selected-world state.');
    $assert(str_contains($script, 'identity.constellationId !== currentConstellationId()'), 'Multiplayer grant validation does not verify the active Constellation identity.');
    $assert(str_contains(file_get_contents(CORE_ROOT . '/app/Application.php'), '\'constellationId\' => $currentConstellationId'), 'PHP multiplayer grant omits the active Constellation identity.');
});

$test('CORE 0.1.127 keeps entity labels and side navigation aligned with the hierarchy', static function () use ($assert): void {
    $view = (string)file_get_contents(CORE_ROOT . '/player/game.php');
    $script = (string)file_get_contents(CORE_ROOT . '/player/player.js');
    $worldGenerator = (string)file_get_contents(CORE_ROOT . '/app/Modules/Universe/WorldGenerator.php');
    $shareEndpoint = (string)file_get_contents(CORE_ROOT . '/api/universe-share.php');
    $styles = (string)file_get_contents(CORE_ROOT . '/player/system-view.css');
    $assert(str_contains($view, '<h2>Constellation View</h2>') && str_contains($view, '<h2>Universe View</h2>'), 'Universe and Constellation navigation headings must remain distinct.');
    $assert(str_contains($view, 'id="constellationViewToolbar"') && str_contains($script, "$('constellationViewToolbar')?.classList.add('hidden')"), 'Constellation selection controls are not hidden in System View.');
    $assert(str_contains($view, 'system-list-panel') && strpos($view, 'id="selectedWorldList"') < strpos($view, 'id="systemViewCanvas"'), 'World navigation list is not positioned before the System visualization.');
    $assert(str_contains($view, 'id="constellationSystemList"') && str_contains($view, 'id="selectedWorldList"') && str_contains($script, "setEntityListMode('system')"), 'System View does not switch the shared left navigation panel to Worlds.');
    $assert(str_contains($worldGenerator, "'name' => 'World '"), 'Generated World names do not explicitly identify them as Worlds.');
    $assert(str_contains($shareEndpoint, "'/api/universe/share'") && str_contains($shareEndpoint, "public/index.php"), 'Direct share-code endpoint does not route through the authenticated application router.');
    $assert(str_contains($script, "request(api('universe-share.php')"), 'Gameplay Copy Key does not use the direct PHP share endpoint.');
});

$test('CORE 0.1.131 orders gameplay hierarchy and resolves selected-world entry against the active System', static function () use ($assert): void {
    $view = (string)file_get_contents(CORE_ROOT . '/player/game.php');
    $script = (string)file_get_contents(CORE_ROOT . '/player/player.js');
    $selectedPanel = strpos($view, 'id="selectedPanel"');
    $worldInspector = strpos($view, 'id="systemWorldInspector"');
    $systemList = strpos($view, 'class="panel section-panel system-list-panel"');
    $multiplayer = strpos($view, 'class="panel section-panel multiplayer-panel"');
    $travel = strpos($view, 'class="panel section-panel travel-panel"');
    $universeData = strpos($view, 'id="universeDataPanel"');
    $assert($universeData !== false && $selectedPanel !== false && $worldInspector !== false && $systemList !== false && $multiplayer !== false && $travel !== false && $universeData < $selectedPanel && $selectedPanel < $worldInspector && $worldInspector < $systemList && $systemList < $multiplayer && $multiplayer < $travel, 'Sidebar order must follow Universe before System and World details, then entity list, Multiplayer, and navigation support.');
    $assert(str_contains($script, "viewButton.hidden=state.view==='system'") && str_contains($script, "if(viewButton) viewButton.hidden=true"), 'View Selected System remains visible after entering System View.');
    foreach (['currentSystemRecord()', 'selectedWorldEntryContext()', 'canEnterSelectedWorld()', 'normalizeEntityId', 'updateWorldEntryControl()', 'window.CORE_SYSTEM_VIEW?.activeSystemId', 'context.system.id', 'context.world.id'] as $needle) {
        $assert(str_contains($script, $needle), 'World entry selection is missing active-System validation: ' . $needle);
    }
    $assert(str_contains($script, 'state.selectedSystem = normalizeEntityId(system.id);'), 'Opening System View does not synchronize the active System identity.');
    $assert(str_contains($script, '].map(normalizeEntityId).filter(Boolean)'), 'Current-System candidates are not trimmed and blank values are not discarded.');
    $assert(str_contains($script, 'candidates.find(id => systems().some(system => normalizeEntityId(system.id) === id))'), 'A stale legacy location field can mask a valid System record.');
    $assert(str_contains($script, 'Ready to enter this generated World.'), 'Enabled Test World state does not explain the action.');
    $assert(str_contains($view, 'Enter Test World opens a temporary environment'), 'The Test World action does not explain its purpose before selection.');
});

$test('Constellation View opens at the character Constellation and navigation returns through contextual controls', static function () use ($assert): void {
    $view = (string)file_get_contents(CORE_ROOT . '/player/game.php');
    $script = (string)file_get_contents(CORE_ROOT . '/player/player.js');
    foreach (['Constellation View', 'id="constellationSelect"', 'id="constellationSystemList"', 'id="selectedWorldList"', 'System View', 'Back to Constellation View'] as $needle) {
        $assert(str_contains($view, $needle), 'Navigation view is missing ' . $needle . '.');
    }
    foreach (['visibleSystems()', 'selectedConstellationId = currentConstellationId()', 'state.selectedConstellationId = system.constellation_id', "state.view = 'system'", "state.view='constellation'", "state.view = 'universe'", 'renderMap();'] as $needle) {
        $assert(str_contains($script, $needle), 'Navigation state is missing ' . $needle . '.');
    }
    $assert(!str_contains($script, 'drawSystemView(boot.initialSystemId)'), 'Play must open Constellation View rather than opening a System directly.');
    $assert(str_contains($script, 'state.character.active_universe_id || state.character.universe_id'), 'Shared-universe visitors must be validated against the active universe.');
    $assert(str_contains($script, 'state.selectedConstellationId = currentConstellationId()'), 'After travel, Constellation View must follow the character current Constellation.');
});

$test('Request strips the CORE deployment prefix when Apache rewrites to the public front controller', static function () use ($assert, $equal): void {
    $originalServer = $_SERVER;
    $originalGet = $_GET;
    try {
        // Simulate the common subdirectory deployment where DOCUMENT_ROOT is
        // already the CORE directory, making filesystem-only inference fail.
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/core-game/api/multiplayer/token';
        $_SERVER['SCRIPT_NAME'] = '/core-game/public/index.php';
        $_SERVER['DOCUMENT_ROOT'] = CORE_ROOT;
        $_GET = [];
        $request = Core\Http\Request::fromGlobals();
        $equal('POST', $request->method());
        $equal('/api/multiplayer/token', $request->path(), 'Mounted API path was not normalized');
    } finally {
        $_SERVER = $originalServer;
        $_GET = $originalGet;
    }
});

$test('player API ownership checks isolate character and universe records by account', static function () use ($assert, $equal): void {
    $dir = sys_get_temp_dir() . '/core-player-security-' . bin2hex(random_bytes(6));
    try {
        $storage = new JsonStorage($dir);
        $storage->write('characters', 'chr-owner-a', ['id' => 'chr-owner-a', 'owner_account_id' => 'usr-a', 'universe_id' => 'uni-shared']);
        $storage->write('characters', 'chr-owner-b', ['id' => 'chr-owner-b', 'owner_account_id' => 'usr-b', 'universe_id' => 'uni-shared']);
        $assert(Core\Http\PlayerApiSecurity::ownsCharacter($storage, 'chr-owner-a', 'usr-a'));
        $assert(!Core\Http\PlayerApiSecurity::ownsCharacter($storage, 'chr-owner-a', 'usr-b'));
        $assert(Core\Http\PlayerApiSecurity::ownsUniverse($storage, 'uni-shared', 'usr-a'));
        $assert(!Core\Http\PlayerApiSecurity::ownsUniverse($storage, 'uni-missing', 'usr-a'));
    } finally {
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); rmdir($dir); }
    }
});
$test('player API guard rejects unauthenticated access', static function () use ($assert, $equal): void {
    $dir = sys_get_temp_dir() . '/core-player-security-' . bin2hex(random_bytes(6));
    try {
        $storage = new JsonStorage($dir);
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION = [];
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        $request = new Core\Http\Request('GET', '/api/characters/record', ['character_id' => 'chr-any']);
        $response = Core\Http\PlayerApiSecurity::guard($request, $storage, ['app' => ['environment' => 'production']]);
        $assert($response instanceof Core\Http\Response, 'Unauthenticated request was not denied');
        $equal(401, $response->statusCode());
    } finally {
        $_SESSION = [];
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); rmdir($dir); }
    }
});
$test('player API guard blocks cross-account character reads and validates write CSRF', static function () use ($assert, $equal): void {
    $dir = sys_get_temp_dir() . '/core-player-security-' . bin2hex(random_bytes(6));
    try {
        $storage = new JsonStorage($dir);
        $storage->write('characters', 'chr-private', ['id' => 'chr-private', 'owner_account_id' => 'usr-owner', 'universe_id' => 'uni-private']);
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION = ['core_user' => ['id' => 'usr-other'], 'core_csrf' => 'test-csrf-token'];
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        $read = new Core\Http\Request('GET', '/api/characters/record', ['character_id' => 'chr-private']);
        $denied = Core\Http\PlayerApiSecurity::guard($read, $storage, ['app' => ['environment' => 'production']]);
        $assert($denied instanceof Core\Http\Response, 'Cross-account read was not denied');
        $equal(404, $denied->statusCode());
        $writeWithoutToken = new Core\Http\Request('POST', '/api/characters/scan', [], [], json_encode(['character_id' => 'chr-private']));
        $csrfDenied = Core\Http\PlayerApiSecurity::guard($writeWithoutToken, $storage, ['app' => ['environment' => 'production']]);
        $assert($csrfDenied instanceof Core\Http\Response, 'Write without CSRF token was not denied');
        $equal(403, $csrfDenied->statusCode());
        $writeWithToken = new Core\Http\Request('POST', '/api/characters/scan', [], ['X-CORE-CSRF' => 'test-csrf-token'], json_encode(['character_id' => 'chr-private']));
        $ownerDenied = Core\Http\PlayerApiSecurity::guard($writeWithToken, $storage, ['app' => ['environment' => 'production']]);
        $equal(404, $ownerDenied?->statusCode(), 'Valid CSRF does not bypass ownership');
        $_SESSION['core_user']['id'] = 'usr-owner';
        $ownerAllowed = Core\Http\PlayerApiSecurity::guard($writeWithToken, $storage, ['app' => ['environment' => 'production']]);
        $equal(null, $ownerAllowed, 'Owner with valid CSRF should be allowed');
    } finally {
        $_SESSION = [];
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); rmdir($dir); }
    }
});

$test('account archival disables sign-in while preserving identity and ownership references', static function () use ($assert, $equal): void {
    $accounts = [
        'usr-archive' => ['id' => 'usr-archive', 'email' => 'pilot@example.test', 'password_hash' => 'hash-kept', 'status' => 'active'],
    ];
    $archived = Core\Modules\Accounts\PlayerAccountArchiveService::archive($accounts, 'usr-archive', '2026-11-10T12:00:00+00:00');
    $equal('disabled', $archived['usr-archive']['status']);
    $equal('pilot@example.test', $archived['usr-archive']['email']);
    $equal('hash-kept', $archived['usr-archive']['password_hash']);
    $equal('2026-11-10T12:00:00+00:00', $archived['usr-archive']['archived_at']);
    $again = Core\Modules\Accounts\PlayerAccountArchiveService::archive($archived, 'usr-archive', '2026-11-11T12:00:00+00:00');
    $equal('2026-11-10T12:00:00+00:00', $again['usr-archive']['archived_at'], 'Repeated archival must preserve the first archive timestamp');
    $assert(isset($again['usr-archive']), 'Archived account record was removed');
});

$test('account administration archives rather than permanently deleting account records', static function () use ($assert): void {
    $api = (string)file_get_contents(CORE_ROOT . '/api/player-accounts-admin.php');
    $ui = (string)file_get_contents(CORE_ROOT . '/admin/player-account.php');
    $assert(str_contains($api, "if(\$action==='archive'||\$action==='delete')"), 'Archive action or legacy delete compatibility is missing.');
    $assert(str_contains($api, 'PlayerAccountArchiveService::archive'), 'Account API does not use the archive service.');
    $assert(str_contains($ui, 'Archive account') && str_contains($ui, "request('archive')"), 'Account UI does not use the archive workflow.');
    $assert(!str_contains($ui, 'Permanently delete this account'), 'Account UI still offers permanent deletion.');
});

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

$test('universe settings accept integer form values and reject fractional or coerced values', static function () use ($assert, $equal): void {
    $generator = new UniverseGenerator();
    $equal(42, $generator->normalizeSettings(['system_density' => '42'])['system_density']);
    foreach ([42.5, '42.5', '4.2e1', true, false, null, -1, 101] as $invalid) {
        try {
            $generator->normalizeSettings(['system_density' => $invalid]);
            throw new RuntimeException('Invalid system_density value was accepted: ' . var_export($invalid, true));
        } catch (RuntimeException $e) {
            $assert(str_contains($e->getMessage(), 'must be an integer from 0 to 100'), 'Unexpected setting validation error: ' . $e->getMessage());
        }
    }
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
    $assert($baseline['system_count'] >= 8 && $baseline['system_count'] <= 24, 'System count outside generator bounds (8–24)');
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
$test('universe generation builds deterministic Universe to Constellation to System hierarchy', static function () use ($assert, $equal): void {
    $generator = new UniverseGenerator();
    $baseline = $generator->generate('qa-universe-constellation-system-world');
    $again = $generator->generate('qa-universe-constellation-system-world');
    $equal($baseline, $again, 'Hierarchy generation is not deterministic.');
    $assert(($baseline['generator_version'] ?? 0) >= 3, 'Universe generator version was not advanced.');
    $assert(($baseline['constellation_count'] ?? 0) === count($baseline['constellations'] ?? []), 'Constellation count does not match the hierarchy.');
    $assert(($baseline['system_count'] ?? 0) === count($baseline['systems'] ?? []), 'System index count does not match the hierarchy.');
    $indexed = [];
    foreach ($baseline['constellations'] as $constellation) {
        $assert(isset($constellation['id'], $constellation['seed']) && str_starts_with($constellation['seed'], 'CON-'), 'Constellation identity or seed is missing.');
        $assert(($constellation['system_count'] ?? -1) === count($constellation['systems'] ?? []), 'Constellation system count is inconsistent.');
        foreach ($constellation['systems'] as $system) {
            $assert(($system['constellation_id'] ?? '') === $constellation['id'], 'System parent constellation ID is incorrect.');
            $assert(str_starts_with((string)($system['seed'] ?? ''), 'SYS-'), 'System seed is missing.');
            $assert(isset($system['star']['id'], $system['star']['spectral_class']), 'System primary star profile is missing.');
            $indexed[$system['id']] = $system;
        }
    }
    $assert(count($indexed) === count($baseline['systems']), 'Nested systems do not match the flat system index.');
    foreach ($baseline['systems'] as $system) {
        $assert(isset($indexed[$system['id']]), 'Flat system index contains an unparented system.');
        $assert($indexed[$system['id']] === $system, 'Flat system index differs from its nested constellation record.');
    }
    $worldGenerator = new WorldGenerator();
    foreach ($baseline['systems'] as $system) {
        foreach ($worldGenerator->generateForSystem($baseline['seed'], $system) as $world) {
            $assert(($world['system_id'] ?? '') === $system['id'], 'World does not identify its parent system.');
            $assert(($world['constellation_id'] ?? '') === $system['constellation_id'], 'World does not identify its parent constellation.');
            $assert(($world['system_seed'] ?? '') === $system['seed'], 'World does not retain its parent system seed.');
        }
    }
});

$test('world profiles are deterministic and stable for a universe seed and system ID', static function () use ($equal, $assert): void {
    $generator = new WorldGenerator();
    $system = ['id' => 'sys_world_qa', 'name' => 'QA System'];
    $first = $generator->generateForSystem('qa-world-seed', $system);
    $second = $generator->generateForSystem('qa-world-seed', $system);
    $equal($first, $second, 'Repeated world generation changed the profile.');
    $assert(count($first) >= 1 && count($first) <= 9, 'World count is outside expected bounds.');
    $ids = [];
    $uwps = [];
    foreach ($first as $world) {
        $assert(isset($world['id'], $world['name'], $world['type'], $world['uwp']), 'World is missing a required identity/profile field.');
        $assert(!in_array($world['id'], $ids, true), 'Duplicate world ID was generated.');
        $ids[] = $world['id'];
        $assert(strlen($world['uwp']) === 8, 'UWP must contain eight profile characters.');
        $assert(!in_array($world['uwp'], $uwps, true), 'Two worlds in the same system share a UWP.');
        $uwps[] = $world['uwp'];
        foreach (['size' => 10, 'atmosphere' => 15, 'hydrographics' => 10, 'population' => 10, 'government' => 15, 'law_level' => 15, 'tech_level' => 15] as $field => $maximum) {
            $assert(is_int($world[$field]) && $world[$field] >= 0 && $world[$field] <= $maximum, 'World field is outside its valid range: ' . $field);
        }
    }
    $assert($first !== $generator->generateForSystem('different-world-seed', $system), 'Different universe seeds produced identical world profiles.');
});
$test('world profiles include deterministic estimated physical data and coherent moon orbital records', static function () use ($assert, $equal): void {
    $generator = new WorldGenerator();
    $system = ['id' => 'sys_physical_qa', 'name' => 'Physical QA'];
    $first = $generator->generateForSystem('physical-world-seed', $system);
    $again = $generator->generateForSystem('physical-world-seed', $system);
    $equal($first, $again, 'Physical or moon data changed for an identical world seed.');
    foreach ($first as $world) {
        $physical = $world['physical'] ?? [];
        $assert(($physical['model'] ?? '') === 'seeded_estimate_v1' && ($physical['estimated'] ?? false) === true, 'World physical profile must identify itself as a deterministic estimate.');
        $assert(($physical['radius_km'] ?? 0) > 0 && ($physical['mass_earth'] ?? 0) > 0 && ($physical['surface_gravity_g'] ?? 0) > 0, 'World physical estimates must contain positive radius, mass, and gravity.');
        $assert(($physical['rotation_period_hours'] ?? 0) >= 8 && ($physical['axial_tilt_degrees'] ?? -1) >= 0 && ($physical['axial_tilt_degrees'] ?? 100) <= 45, 'World rotation or axial tilt estimate is outside its configured range.');
        $moons = $world['moons'] ?? null;
        $assert(is_array($moons) && count($moons) <= 3, 'World moon list must be a bounded array.');
        foreach ($moons as $index => $moon) {
            $assert(($moon['type'] ?? '') === 'moon' && ($moon['id'] ?? '') !== '', 'Generated moon is missing its stable identity or type.');
            $assert(($moon['diameter_km'] ?? 0) > 0 && ($moon['orbital_radius_planet_radii'] ?? 0) > 0 && ($moon['orbital_period_hours'] ?? 0) > 0, 'Generated moon has invalid orbital or physical data.');
            $assert(($moon['phase_degrees'] ?? -1) >= 0 && ($moon['phase_degrees'] ?? 360) <= 359, 'Generated moon phase is outside the expected range.');
        }
    }
});

$test('hierarchical procedural seeds are unique and deterministic at system and world levels', static function () use ($assert, $equal): void {
    $generator = new UniverseGenerator();
    $baseline = $generator->generate('qa-hierarchical-seed');
    $again = $generator->generate('qa-hierarchical-seed');
    $equal($baseline, $again, 'Hierarchical system seeds changed across identical generation.');
    $systemSeeds = [];
    foreach ($baseline['systems'] as $system) {
        $assert(is_string($system['seed'] ?? null) && str_starts_with($system['seed'], 'SYS-'), 'Generated system is missing its independent system seed.');
        $assert(!in_array($system['seed'], $systemSeeds, true), 'Two systems share a system seed.');
        $systemSeeds[] = $system['seed'];
    }
    $worldGenerator = new WorldGenerator();
    $worldSeeds = [];
    foreach ($baseline['systems'] as $system) {
        $worlds = $worldGenerator->generateForSystem($baseline['seed'], $system);
        foreach ($worlds as $world) {
            $assert(is_string($world['seed'] ?? null) && str_starts_with($world['seed'], 'WLD-'), 'World is missing its independent world seed.');
            $assert(($world['system_seed'] ?? '') === $system['seed'], 'World does not retain its parent system seed.');
            $assert(strlen((string)($world['uwp'] ?? '')) === 8, 'World UWP is not a complete eight-character profile.');
            $assert(!in_array($world['seed'], $worldSeeds, true), 'Two worlds share a world seed.');
            $worldSeeds[] = $world['seed'];
        }
    }
    $assert(count($worldSeeds) > count($baseline['systems']), 'The generated universe did not produce multiple individually seeded worlds.');
});
$test('universe deletion is blocked while a character from another home universe is visiting', static function () use ($assert): void {
    $dir = sys_get_temp_dir() . '/core-shared-delete-' . bin2hex(random_bytes(6));
    try {
        $storage = new JsonStorage($dir);
        $shared = (new UniverseService($storage))->createOrLoad('qa-shared-delete-target');
        $home = (new UniverseService($storage))->createOrLoad('qa-shared-delete-home');
        $storage->write('characters', 'chr-active-visitor', [
            'id' => 'chr-active-visitor', 'owner_account_id' => 'acct-visitor',
            'universe_id' => $home['id'], 'active_universe_id' => $shared['id'],
        ]);
        $service = new \Core\Modules\UniverseDeletionService($storage, new UniverseBackupService($storage, $dir . '/backups'));
        $blocked = false;
        try { $service->delete($shared['id'], 'DELETE UNIVERSE ' . $shared['id'], 0, 0, []); }
        catch (RuntimeException $e) { $blocked = str_contains($e->getMessage(), 'visiting it'); }
        $assert($blocked, 'Universe deletion did not block an active visitor.');
        $assert($storage->read('universes', $shared['id']) !== null, 'Shared universe was deleted despite its active visitor.');
        $assert($storage->read('characters', 'chr-active-visitor') !== null, 'Visiting character record was changed during blocked deletion.');
    } finally {
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); rmdir($dir); }
    }
});

$test('shared universe visit preserves home assignment and restores home location on exit', static function () use ($assert, $equal): void {
    $dir = sys_get_temp_dir() . '/core-shared-universe-' . bin2hex(random_bytes(6));
    try {
        $storage = new JsonStorage($dir);
        $ownerUniverse = (new UniverseService($storage))->createOrLoad('qa-shared-owner-seed');
        $visitorHome = (new UniverseService($storage))->createOrLoad('qa-shared-visitor-home-seed');
        $ownerSystem = $ownerUniverse['baseline']['systems'][0]['id'];
        $homeSystem = $visitorHome['baseline']['systems'][1]['id'] ?? $visitorHome['baseline']['systems'][0]['id'];
        $storage->write('characters', 'chr-share-owner', ['id' => 'chr-share-owner', 'owner_account_id' => 'acct-owner', 'universe_id' => $ownerUniverse['id'], 'current_system_id' => $ownerSystem, 'lifecycle' => ['enabled' => true, 'locked' => false]]);
        $storage->write('characters', 'chr-share-visitor', ['id' => 'chr-share-visitor', 'owner_account_id' => 'acct-visitor', 'universe_id' => $visitorHome['id'], 'current_system_id' => $homeSystem, 'location' => ['system_id' => $homeSystem, 'updated_at' => '2026-01-01T00:00:00Z'], 'lifecycle' => ['enabled' => true, 'locked' => false], 'revision' => 0]);
        $service = new \Core\Modules\Universe\SharedUniverseService($storage);
        $share = $service->createShareCode($ownerUniverse['id'], 'acct-owner');
        $assert(strlen($share['share_code']) === 32, 'Share code must contain 128 bits of random hexadecimal data.');
        $rotatedShare = $service->createShareCode($ownerUniverse['id'], 'acct-owner');
        $invalidated = false;
        try { $service->visit('chr-share-visitor', 'acct-visitor', $share['share_code']); } catch (RuntimeException) { $invalidated = true; }
        $assert($invalidated, 'Rotating the share code did not invalidate the previous code.');
        $savedUniverse = $storage->read('universes', $ownerUniverse['id']);
        $assert(!isset($savedUniverse['sharing']['share_code']), 'Raw share code must not be persisted.');
        $assert(isset($savedUniverse['sharing']['code_hash']), 'Share code hash was not persisted.');
        $visited = $service->visit('chr-share-visitor', 'acct-visitor', $rotatedShare['share_code']);
        $equal($visitorHome['id'], $visited['universe_id'], 'Visiting overwrote the character home universe.');
        $equal($ownerUniverse['id'], $visited['active_universe_id'], 'Character did not enter the shared universe.');
        $equal($ownerUniverse['baseline']['systems'][0]['constellation_id'], $visited['current_constellation_id'], 'Shared visit did not set the destination Constellation ID.');
        $equal($ownerUniverse['baseline']['systems'][0]['constellation_id'], $visited['location']['constellation_id'] ?? null, 'Shared visit location lost its Constellation ID.');
        $equal($ownerUniverse['id'], $visited['location']['universe_id'] ?? null, 'Shared visit location lost its active Universe ID.');
        $assert(\Core\Http\PlayerApiSecurity::canAccessUniverse($storage, $ownerUniverse['id'], 'acct-visitor'), 'Visitor cannot access the universe they joined.');
        $secondOwnerUniverse = (new UniverseService($storage))->createOrLoad('qa-shared-second-owner-seed');
        $storage->write('characters', 'chr-share-second-owner', ['id' => 'chr-share-second-owner', 'owner_account_id' => 'acct-second-owner', 'universe_id' => $secondOwnerUniverse['id'], 'current_system_id' => $secondOwnerUniverse['baseline']['systems'][0]['id'], 'lifecycle' => ['enabled' => true, 'locked' => false]]);
        $secondShare = $service->createShareCode($secondOwnerUniverse['id'], 'acct-second-owner');
        $switched = $service->visit('chr-share-visitor', 'acct-visitor', $secondShare['share_code']);
        $equal($visitorHome['id'], $switched['universe_id'], 'Switching shared universes changed the permanent home universe.');
        $equal($secondOwnerUniverse['id'], $switched['active_universe_id'], 'Visiting a second shared universe did not automatically switch the active universe.');
        $assert(!\Core\Http\PlayerApiSecurity::canAccessUniverse($storage, $ownerUniverse['id'], 'acct-visitor'), 'Visitor retained access to the previous shared universe after switching.');
        $assert(\Core\Http\PlayerApiSecurity::canAccessUniverse($storage, $secondOwnerUniverse['id'], 'acct-visitor'), 'Visitor did not gain access to the newly selected shared universe.');
        $failedSwitch = false;
        try { $service->visit('chr-share-visitor', 'acct-visitor', str_repeat('0', 32)); } catch (RuntimeException) { $failedSwitch = true; }
        $assert($failedSwitch, 'An invalid destination share code was accepted.');
        $stillSwitched = $storage->read('characters', 'chr-share-visitor');
        $equal($secondOwnerUniverse['id'], $stillSwitched['active_universe_id'], 'A failed destination validation changed the current active universe.');
        $returned = $service->leave('chr-share-visitor', 'acct-visitor');
        $equal($visitorHome['id'], $returned['universe_id'], 'Leaving did not preserve the home universe.');
        $assert(!isset($returned['active_universe_id']), 'Active shared universe was not cleared on exit.');
        $equal($homeSystem, $returned['current_system_id'], 'Home system was not restored on exit.');
        $assert(!\Core\Http\PlayerApiSecurity::canAccessUniverse($storage, $ownerUniverse['id'], 'acct-visitor'), 'Visitor retained shared-universe access after leaving.');
    } finally {
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); rmdir($dir); }
    }
});

$test('legacy universe baselines rebuild into the full hierarchy and reconcile active and home locations', static function () use ($assert, $equal): void {
    $dir = sys_get_temp_dir() . '/core-hierarchy-rebuild-' . bin2hex(random_bytes(6));
    try {
        $storage = new JsonStorage($dir);
        $baseline = (new UniverseGenerator())->generate('qa-legacy-hierarchy-seed');
        $legacyBaseline = $baseline;
        unset($legacyBaseline['constellations']);
        $legacyBaseline['hierarchy_version'] = 0;
        foreach ($legacyBaseline['systems'] as &$legacySystem) {
            unset($legacySystem['constellation_id'], $legacySystem['constellation_seed']);
        }
        unset($legacySystem);
        $universe = [
            'id' => $baseline['id'], 'seed' => $baseline['seed'], 'settings' => $baseline['settings'],
            'generator_version' => 2, 'hierarchy_version' => 0, 'baseline' => $legacyBaseline,
            'state' => ['schema_version' => 1, 'revision' => 9, 'changes' => [['type' => 'preserve-me']]],
            'sharing' => ['enabled' => true, 'code_hash' => hash('sha256', 'share-key')],
            'owner_account_id' => 'acct-owner',
        ];
        $storage->write('universes', $universe['id'], $universe);
        $storage->write('characters', 'chr-hierarchy-home', [
            'id' => 'chr-hierarchy-home', 'owner_account_id' => 'acct-home', 'universe_id' => $universe['id'],
            'current_system_id' => 'old-system-id', 'current_constellation_id' => 'old-constellation-id',
            'location' => ['system_id' => 'old-system-id'], 'revision' => 3,
        ]);
        $storage->write('characters', 'chr-hierarchy-visitor', [
            'id' => 'chr-hierarchy-visitor', 'owner_account_id' => 'acct-visitor', 'universe_id' => 'uni-home',
            'active_universe_id' => $universe['id'], 'current_system_id' => 'old-active-system',
            'location' => ['system_id' => 'old-active-system'], 'revision' => 4,
        ]);
        $storage->write('characters', 'chr-hierarchy-away', [
            'id' => 'chr-hierarchy-away', 'owner_account_id' => 'acct-away', 'universe_id' => $universe['id'],
            'active_universe_id' => 'uni-away', 'current_system_id' => 'active-away-system',
            'location' => ['system_id' => 'active-away-system'], 'revision' => 5,
            'home_location_snapshot' => ['current_system_id' => 'old-home-system', 'location' => ['system_id' => 'old-home-system']],
        ]);

        $rebuilt = (new UniverseService($storage))->ensureCurrentHierarchy($universe['id']);
        $first = $rebuilt['baseline']['systems'][0];
        $equal($universe['id'], $rebuilt['id'], 'Universe identity changed during hierarchy rebuild.');
        $equal(1, $rebuilt['hierarchy_version'], 'Hierarchy version was not upgraded.');
        $assert(isset($rebuilt['baseline']['constellations'][0]['systems'][0]), 'Nested Constellation -> System hierarchy was not rebuilt.');
        $equal(9, $rebuilt['state']['revision'], 'Persistent universe state revision was discarded.');
        $equal($universe['sharing'], $rebuilt['sharing'], 'Universe sharing metadata was discarded.');
        $home = $storage->read('characters', 'chr-hierarchy-home');
        $equal($first['id'], $home['current_system_id'], 'Home character was not moved to a valid regenerated System.');
        $equal($first['constellation_id'], $home['current_constellation_id'], 'Home character Constellation was not reconciled.');
        $visitor = $storage->read('characters', 'chr-hierarchy-visitor');
        $equal($first['id'], $visitor['current_system_id'], 'Active visitor was not moved to a valid regenerated System.');
        $equal($universe['id'], $visitor['location']['universe_id'], 'Active visitor location lost the regenerated Universe identity.');
        $away = $storage->read('characters', 'chr-hierarchy-away');
        $equal('active-away-system', $away['current_system_id'], 'Home-universe rebuild overwrote the visitor current location.');
        $equal($first['id'], $away['home_location_snapshot']['current_system_id'], 'Home location snapshot was not reconciled.');
    } finally {
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); rmdir($dir); }
    }
});

$test('world generation leaves the persisted universe baseline unchanged', static function () use ($assert, $equal): void {
    $dir = sys_get_temp_dir() . '/core-world-qa-' . bin2hex(random_bytes(6));
    try {
        $storage = new JsonStorage($dir);
        $universe = (new UniverseService($storage))->createOrLoad('qa-world-persistence-seed');
        $before = $storage->read('universes', $universe['id']);
        $generator = new WorldGenerator();
        foreach ($universe['baseline']['systems'] as $system) $generator->generateForSystem($universe['seed'], $system);
        $after = $storage->read('universes', $universe['id']);
        $equal($before, $after, 'Runtime world generation modified the persisted universe record.');
        $assert(!isset($after['baseline']['systems'][0]['worlds']), 'Derived worlds were written into persistent baseline data.');
    } finally {
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); rmdir($dir); }
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

$test('Starfinder-inspired point-buy costs are data-driven and reject over-budget ability arrays', static function () use ($assert, $equal): void {
    $options = \Core\Modules\Characters\CharacterCreationRules::definitions();
    $equal(10, $options['ability_generation']['points'] ?? null, 'Unexpected point-buy budget');
    $valid = \Core\Modules\Characters\CharacterCreationRules::validate($options, 'human', ['strength'=>12,'dexterity'=>14,'constitution'=>10,'intelligence'=>12,'wisdom'=>10,'charisma'=>11], 'wisdom');
    $equal(10, $valid['ability_points_spent'], 'Point-buy cost calculation is incorrect');
    $equal(10, $valid['base_attributes']['wisdom'], 'Human base Wisdom score is incorrect');
    $equal(12, $valid['attributes']['wisdom'], 'Human flexible species bonus was not applied');
    try {
        \Core\Modules\Characters\CharacterCreationRules::validate($options, 'human', ['strength'=>16,'dexterity'=>16,'constitution'=>10,'intelligence'=>10,'wisdom'=>10,'charisma'=>10], 'wisdom');
        throw new RuntimeException('Over-budget ability scores were accepted.');
    } catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'exceed the 10-point budget'), 'Unexpected over-budget rejection: ' . $e->getMessage()); }
});

$test('species ability adjustments are applied after point-buy and retained separately', static function () use ($assert, $equal): void {
    $options = \Core\Modules\Characters\CharacterCreationRules::definitions();
    $base = ['strength'=>10,'dexterity'=>12,'constitution'=>10,'intelligence'=>12,'wisdom'=>10,'charisma'=>10];
    $android = \Core\Modules\Characters\CharacterCreationRules::validate($options, 'android', $base, null);
    $equal(14, $android['attributes']['dexterity'], 'Android Dexterity adjustment is incorrect');
    $equal(14, $android['attributes']['intelligence'], 'Android Intelligence adjustment is incorrect');
    $equal(8, $android['attributes']['charisma'], 'Android Charisma adjustment is incorrect');
    $equal($base, $android['base_attributes'], 'Species adjustments overwrote base scores');
    $ysoki = \Core\Modules\Characters\CharacterCreationRules::validate($options, 'ysoki', $base, null);
    $equal(8, $ysoki['attributes']['strength'], 'Ysoki Strength adjustment is incorrect');
    $equal(14, $ysoki['attributes']['dexterity'], 'Ysoki Dexterity adjustment is incorrect');
    try { \Core\Modules\Characters\CharacterCreationRules::validate($options, 'android', $base, 'strength'); throw new RuntimeException('Non-human species accepted a flexible bonus field.'); }
    catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'does not use a flexible ability bonus'), 'Unexpected non-human bonus rejection: ' . $e->getMessage()); }
    try { \Core\Modules\Characters\CharacterCreationRules::validate($options, 'human', $base, 'unknown_attribute'); throw new RuntimeException('Unknown human bonus ability was accepted.'); }
    catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'valid ability for the human species bonus'), 'Unexpected human bonus rejection: ' . $e->getMessage()); }
    $defaultHuman = \Core\Modules\Characters\CharacterCreationRules::validate($options, 'human', $base, null);
    $equal('strength', $defaultHuman['human_bonus_attribute'], 'Human fallback ability choice was not deterministic.');
});

$test('new character persists Starfinder-inspired attributes and data-driven species and role IDs', static function () use ($assert, $equal): void {
    $dir = sys_get_temp_dir() . '/core-character-profile-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $universe = (new UniverseService($storage))->createOrLoad('character-profile-seed');
        $character = (new CharacterService($storage))->create($universe['id'], 'Profile Tester', 'explorer', 'android', ['strength'=>12,'dexterity'=>14,'constitution'=>10,'intelligence'=>12,'wisdom'=>10,'charisma'=>11]);
        $saved = $storage->read('characters', $character['id']);
        $equal('explorer', $saved['role_id'] ?? null, 'Role ID was not persisted');
        $equal('android', $saved['species_id'] ?? null, 'Species ID was not persisted');
        $equal(16, $saved['attributes']['dexterity'] ?? null, 'Android Dexterity adjustment was not applied');
        $equal(14, $saved['base_attributes']['dexterity'] ?? null, 'Unmodified Dexterity score was not retained');
        $equal(10, $saved['ability_points_spent'] ?? null, 'Ability point-buy total was not persisted');
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
        $character = (new CharacterService($storage))->create('', 'Unassigned Tester', 'explorer', 'human', ['strength'=>10,'dexterity'=>10,'constitution'=>10,'intelligence'=>10,'wisdom'=>10,'charisma'=>10], null, 'strength');
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
        $character = $service->create('', 'Owner QA', 'explorer', 'human', [], 'usr_owner_qa', 'strength');
        $assert(($character['owner_account_id'] ?? null) === 'usr_owner_qa', 'Character owner account ID was not persisted.');
        $assert(($character['universe_id'] ?? null) === null, 'New character should remain unassigned.');
    } finally {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
        if (is_dir($dir)) rmdir($dir);
    }
});
$test('character universe assignment is owner-checked, revisioned, and initializes a valid starting location', static function () use ($assert, $equal): void {
    $dir = sys_get_temp_dir() . '/core-character-assignment-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $universe = (new UniverseService($storage))->createOrLoad('assignment-service-seed');
        $character = (new CharacterService($storage))->create('', 'Assignment Tester', 'explorer', 'human', [], 'assignment-owner', 'strength');
        $assigned = (new Core\Modules\Characters\CharacterUniverseAssignmentService($storage))->assign($character['id'], 'assignment-owner', $universe['id']);
        $saved = $storage->read('characters', $character['id']);
        $equal('assigned', $assigned['_assignment_status'] ?? null, 'New assignment status was not reported.');
        $equal($universe['id'], $saved['universe_id'] ?? null, 'Universe ID was not committed.');
        $first = $universe['baseline']['systems'][0];
        $equal($first['id'], $saved['current_system_id'] ?? null, 'Character did not start in the first system.');
        $equal($first['id'], $saved['location']['system_id'] ?? null, 'Location projection does not match current system.');
        $equal(1, $saved['revision'] ?? null, 'Assignment did not increment character revision exactly once.');
        $equal('universe_assigned', $saved['lifecycle']['last_action'] ?? null, 'Assignment lifecycle action was not recorded.');
    } finally {
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); @rmdir($dir); }
    }
});

$test('repeating the same character universe assignment is idempotent and preserves progress', static function () use ($assert, $equal): void {
    $dir = sys_get_temp_dir() . '/core-character-assignment-repeat-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $universe = (new UniverseService($storage))->createOrLoad('assignment-repeat-seed');
        $character = (new CharacterService($storage))->create('', 'Repeat Assignment Tester', 'explorer', 'human', [], 'assignment-owner', 'strength');
        $service = new Core\Modules\Characters\CharacterUniverseAssignmentService($storage);
        $service->assign($character['id'], 'assignment-owner', $universe['id']);
        $secondSystem = $universe['baseline']['systems'][1];
        $storage->transact('characters', $character['id'], static function (array $record) use ($secondSystem): array {
            $record['current_system_id'] = $secondSystem['id'];
            $record['location'] = ['system_id' => $secondSystem['id'], 'updated_at' => '2026-01-01T00:00:00+00:00'];
            $record['revision'] = 7;
            $record['exploration']['explored_systems'][] = ['system_id' => $secondSystem['id'], 'name' => $secondSystem['name']];
            return $record;
        });
        $before = $storage->read('characters', $character['id']);
        $result = $service->assign($character['id'], 'assignment-owner', $universe['id']);
        $after = $storage->read('characters', $character['id']);
        $equal('already_assigned', $result['_assignment_status'] ?? null, 'Repeated assignment status was not reported.');
        $equal($before, $after, 'Repeated assignment reset or changed character progress.');
    } finally {
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); @rmdir($dir); }
    }
});

$test('character universe assignment rejects another account and a different second universe without mutation', static function () use ($assert, $equal): void {
    $dir = sys_get_temp_dir() . '/core-character-assignment-owner-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $universes = new UniverseService($storage);
        $first = $universes->createOrLoad('assignment-owner-first');
        $second = $universes->createOrLoad('assignment-owner-second');
        $character = (new CharacterService($storage))->create('', 'Ownership Assignment Tester', 'explorer', 'human', [], 'correct-owner', 'strength');
        $service = new Core\Modules\Characters\CharacterUniverseAssignmentService($storage);
        try { $service->assign($character['id'], 'wrong-owner', $first['id']); throw new RuntimeException('Foreign account assignment was accepted.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'ownership could not be verified'), 'Unexpected owner rejection.'); }
        $equal(null, $storage->read('characters', $character['id'])['universe_id'] ?? null, 'Foreign owner attempt changed the character.');
        $service->assign($character['id'], 'correct-owner', $first['id']);
        $before = $storage->read('characters', $character['id']);
        try { $service->assign($character['id'], 'correct-owner', $second['id']); throw new RuntimeException('Second universe assignment was accepted.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'already assigned to another universe'), 'Unexpected second-universe rejection.'); }
        $equal($before, $storage->read('characters', $character['id']), 'Rejected reassignment changed the character.');
    } finally {
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); @rmdir($dir); }
    }
});

$test('character universe assignment respects universe and character lifecycle locks', static function () use ($assert, $equal): void {
    $dir = sys_get_temp_dir() . '/core-character-assignment-lifecycle-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $universe = (new UniverseService($storage))->createOrLoad('assignment-lifecycle-seed');
        $character = (new CharacterService($storage))->create('', 'Lifecycle Assignment Tester', 'explorer', 'human', [], 'lifecycle-owner', 'strength');
        $storage->transact('characters', $character['id'], static function (array $record): array { $record['lifecycle']['locked'] = true; return $record; });
        try { (new Core\Modules\Characters\CharacterUniverseAssignmentService($storage))->assign($character['id'], 'lifecycle-owner', $universe['id']); throw new RuntimeException('Locked character was assigned.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'Character is locked'), 'Unexpected character lifecycle rejection.'); }
        $equal(null, $storage->read('characters', $character['id'])['universe_id'] ?? null, 'Locked character was partially assigned.');
        $storage->transact('characters', $character['id'], static function (array $record): array { $record['lifecycle']['locked'] = false; return $record; });
        $storage->transact('universes', $universe['id'], static function (array $record): array { $record['lifecycle'] = ['enabled' => true, 'locked' => true]; return $record; });
        try { (new Core\Modules\Characters\CharacterUniverseAssignmentService($storage))->assign($character['id'], 'lifecycle-owner', $universe['id']); throw new RuntimeException('Locked universe accepted assignment.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'Universe is locked'), 'Unexpected universe lifecycle rejection.'); }
        $equal(null, $storage->read('characters', $character['id'])['universe_id'] ?? null, 'Locked universe attempt partially assigned the character.');
    } finally {
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); @rmdir($dir); }
    }
});

$test('Character records cannot be permanently deleted and remain available for archival', static function () use ($assert, $equal): void {
    $dir = sys_get_temp_dir() . '/core-character-retention-' . bin2hex(random_bytes(6));
    try {
        $storage = new JsonStorage($dir); $service = new CharacterService($storage);
        $character = $service->create('', 'Retention QA Pilot', 'Explorer', 'human', [], 'qa-owner', 'strength');
        try { $service->deleteCharacter('', $character['id'], 'Retention QA Pilot', 0); throw new RuntimeException('Permanent character deletion unexpectedly succeeded.'); }
        catch (RuntimeException $e) { $assert(str_contains($e->getMessage(), 'Permanent character deletion is disabled'), 'Retention policy should reject permanent deletion explicitly.'); }
        $saved = $storage->read('characters', $character['id']);
        $equal($character['id'], $saved['id']); $equal('Retention QA Pilot', $saved['name']);
        $equal('qa-owner', $saved['owner_account_id']);
    } finally { foreach (glob($dir . '/*') ?: [] as $file) { if (is_file($file)) @unlink($file); } @rmdir($dir); }
});


$test('scan and investigation state persists in character JSON and uses JSON content', static function () use ($assert): void {
    $dir = sys_get_temp_dir() . '/core-content-persist-' . bin2hex(random_bytes(8));
    try {
        $storage = new JsonStorage($dir);
        $universe = (new UniverseService($storage))->createOrLoad('content-persistence-seed');
        $characters = new CharacterService($storage);
        $character = $characters->create($universe['id'], 'Content Tester', 'Explorer', 'human', [], null, 'strength');
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
        $character = (new CharacterService($storage))->create($universe['id'], 'Crafter', 'Explorer', 'human', [], null, 'strength');
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
    $html = (string)file_get_contents(CORE_ROOT . '/index.php') . (string)file_get_contents(CORE_ROOT . '/assets/admin.js');
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
    $html = (string)file_get_contents(CORE_ROOT . '/assets/admin.js');
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
    $html = (string)file_get_contents(CORE_ROOT . '/assets/admin.js');
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


$test('CORE runtime module provides only shared configuration and persistence', static function () use ($assert): void {
    $dir = sys_get_temp_dir() . '/core-runtime-module-' . bin2hex(random_bytes(6));
    try {
        $config = ['storage' => ['path' => $dir . '/data']];
        $services = new Core\Modules\ServiceContainer();
        $registry = new Core\Modules\ModuleRegistry(new Core\Modules\ModuleContext($services));
        $registry->register(new Core\Modules\Core\CoreRuntimeModule($config));
        $order = $registry->bootAll();
        if ($order !== ['core.runtime']) throw new RuntimeException('Unexpected CORE runtime module boot order.');
        $assert($services->has('core.config'), 'Runtime config service was not registered.');
        $assert($services->has('core.storage'), 'Runtime storage service was not registered.');
        $assert(!$services->has('universe.service'), 'Runtime module must not own universe services.');
        $assert(!$services->has('character.service'), 'Runtime module must not own character services.');
        $assert($services->get('core.storage') instanceof JsonStorage, 'CORE runtime storage service has the wrong type.');
        $assert(is_dir($dir . '/data'), 'CORE runtime module did not initialize the configured storage directory.');
    } finally {
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); rmdir($dir); }
    }
});

$test('CORE domain modules compose services in declared dependency order', static function () use ($assert, $equal): void {
    $dir = sys_get_temp_dir() . '/core-domain-modules-' . bin2hex(random_bytes(6));
    try {
        $config = ['storage' => ['path' => $dir . '/data']];
        $services = new Core\Modules\ServiceContainer();
        $registry = new Core\Modules\ModuleRegistry(new Core\Modules\ModuleContext($services));
        // Register in reverse dependency order to prove the registry resolves dependencies.
        $registry->register(new Core\Modules\Core\CoreContentModule());
        $registry->register(new Core\Modules\Core\CoreDataManagementModule());
        $registry->register(new Core\Modules\Characters\CharacterModule());
        $registry->register(new Core\Modules\Universe\UniverseModule());
        $registry->register(new Core\Modules\Core\CoreRuntimeModule($config));
        $equal(
            ['core.runtime', 'core.universe', 'core.content', 'core.data-management', 'core.characters'],
            $registry->bootAll(),
            'CORE modules did not boot in dependency order.'
        );
        foreach ([
            'universe.service', 'universe.lifecycle', 'navigation.routes', 'universe.backups', 'universe.deletion',
            'character.service', 'character.universe-assignment', 'character.travel', 'character.scan', 'character.discovery', 'character.crafting',
            'character.fitting', 'character.lifecycle', 'data.management', 'content.characteristics', 'content.management',
        ] as $id) {
            $assert($services->has($id), 'Expected domain service was not registered: ' . $id);
        }
        $assert($services->get('universe.service') instanceof Core\Modules\Universe\UniverseService, 'Universe service has the wrong type.');
        $assert($services->get('character.service') instanceof Core\Modules\Characters\CharacterService, 'Character service has the wrong type.');
        $assert($services->get('character.universe-assignment') instanceof Core\Modules\Characters\CharacterUniverseAssignmentService, 'Character assignment service has the wrong type.');
        $assert($services->get('universe.deletion') instanceof Core\Modules\UniverseDeletionService, 'Universe deletion service has the wrong type.');
    } finally {
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); rmdir($dir); }
    }
});

$test('player workflows boot and reuse the standard application module services', static function () use ($assert, $equal): void {
    $dir = sys_get_temp_dir() . '/core-player-module-boot-' . bin2hex(random_bytes(6));
    try {
        $app = new Core\Application();
        $config = ['storage' => ['path' => $dir . '/data'], 'logging' => ['path' => $dir . '/logs/core.log']];
        $order = $app->bootDefaultModules($config);
        $equal(['core.runtime', 'core.universe', 'core.characters', 'core.data-management', 'core.content'], $order, 'Unexpected player workflow module boot order.');
        $assert($app->services()->get('core.storage') instanceof JsonStorage, 'Player workflow did not reuse module storage.');
        $assert($app->services()->get('universe.service') instanceof Core\Modules\Universe\UniverseService, 'Player workflow universe service was not booted.');
        $assert($app->services()->get('character.service') instanceof Core\Modules\Characters\CharacterService, 'Player workflow character service was not booted.');
        $assert($app->services()->get('character.universe-assignment') instanceof Core\Modules\Characters\CharacterUniverseAssignmentService, 'Player workflow assignment service was not booted.');
        $equal($order, $app->bootDefaultModules($config), 'Repeated default module boot should be idempotent.');
        $action = (string)file_get_contents(CORE_ROOT . '/player/player-action.php');
        foreach (['bootDefaultModules()', "get('core.storage')", "get('character.service')", "get('universe.service')", "get('character.universe-assignment')"] as $needle) {
            $assert(str_contains($action, $needle), 'Player action does not use shared module service: ' . $needle);
        }
        foreach (['new \\Core\\Storage\\JsonStorage', 'new \\Core\\Modules\\Characters\\CharacterService', 'new \\Core\\Modules\\Universe\\UniverseService', 'new \\Core\\Modules\\Characters\\CharacterUniverseAssignmentService'] as $needle) {
            $assert(!str_contains($action, $needle), 'Player action still constructs a domain service directly: ' . $needle);
        }
    } finally {
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); rmdir($dir); }
    }
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
    foreach (['BABYLON.Engine', 'BABYLON.ArcRotateCamera', 'CreateSphere', 'CreateCylinder', 'CreateGround', 'CreateTorus', 'attachControl', 'previewColor', '3D preview failed'] as $needle) {
        $assert(is_string($js) && str_contains($js, $needle), 'Character preview script missing marker: ' . $needle);
    }
});

$test('Character creation workspace exposes explained sections and a neutral standing preview', static function () use ($assert): void {
    $page = (string)file_get_contents(CORE_ROOT . '/player/character-create.php');
    $script = (string)file_get_contents(CORE_ROOT . '/player/character-creation.js');
    $preview = (string)file_get_contents(CORE_ROOT . '/player/character-preview.js');
    foreach (['Appearance', 'Role', 'Attributes', 'Skills &amp; Feats', 'Traits &amp; Benefits', 'Inventory', 'Background', 'Misc'] as $section) {
        $assert(str_contains($page, $section), 'Character creation section is missing: ' . $section);
    }
    $assert(str_contains($page, 'role="tablist"') && str_contains($page, 'role="tabpanel"'), 'Character creation navigation needs accessible tabs and panels.');
    $assert(str_contains($script, 'activateTab') && str_contains($script, 'updateDescriptions'), 'Character creation tabs/descriptions are not wired.');
    $assert(str_contains($preview, 'Neutral standing idle') && str_contains($preview, 'Neutral rest pose'), 'Character preview must identify its neutral standing/rest state.');
    $assert(!preg_match('/walking\.start|walking\.speedRatio/i', $preview), 'Character creation preview must not start the walking animation.');
});

$test('Player game API root uses the public URL path, not the filesystem path', static function () use ($assert): void {
    $game = file_get_contents(CORE_ROOT . '/player/game.php');
    $js = file_get_contents(CORE_ROOT . '/player/player.js');
    $assert(is_string($game) && str_contains($game, '$urlRoot = rtrim(str_replace'), 'Player game page must derive a URL root from SCRIPT_NAME.');
    $assert(str_contains($game, 'data-api-root="<?= htmlspecialchars($urlRoot'), 'Player page must pass the URL root to its JavaScript.');
    $assert(str_contains($js, 'const api = path => `${apiRoot}/api/${path}`;'), 'Player API requests must be based on the public URL root.');
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
$test('Gameplay script only targets controls present on the game page and surfaces boot failures', static function () use ($assert): void {
    $game = (string)file_get_contents(CORE_ROOT . '/player/game.php'); $js = (string)file_get_contents(CORE_ROOT . '/player/player.js');
    foreach (['notice','gameLayout','renderCanvas','mapStatus','universeName','travelButton','selectedName'] as $id) $assert(str_contains($game, 'id="' . $id . '"'), 'Gameplay page is missing required element ' . $id . '.');
    $assert(!str_contains($js, "$('universeForm')") && !str_contains($js, "$('characterRole')"), 'Gameplay script must not bind controls belonging to the removed creation screen.');
    $assert(str_contains($js, 'Could not enter gameplay:') && str_contains($js, 'non-JSON data'), 'Gameplay boot and API failures must be shown clearly.');
    $assert(str_contains($js, 'AbortSignal.timeout(12000)'), 'Gameplay API requests must time out instead of leaving the loading screen indefinitely.');
    $assert(str_contains($js, 'try { await loadNavigation(); } catch (error)') && str_contains($js, 'renderMap();'), 'Navigation API failure must not prevent the assigned universe map from rendering.');
});
$test('Play page boots assigned universe and selected character directly', static function () use ($assert): void {
    $game = file_get_contents(CORE_ROOT . '/player/game.php'); $js = file_get_contents(CORE_ROOT . '/player/player.js');
    $assert(str_contains($game, "'universe' => \$playerUniverse") && str_contains($game, "'character' => \$selectedCharacter") && str_contains($game, "'characterId' => \$selectedCharacterId"), 'Game page must pass its validated universe and character to JS.');
    $assert(str_contains($js, 'const boot = window.CORE_BOOT || {}') && str_contains($js, 'try { await loadNavigation(); } catch (error)') && str_contains($js, 'updateCharacterPanel();') && str_contains($js, 'renderMap();'), 'Player JS must boot the actual assigned universe and character without reseeding.');
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
$test('Character selection has one account navigation action and shows player workflow progress', static function () use ($assert): void {
    $selection = (string)file_get_contents(CORE_ROOT . '/player/selection.php');
    $css = (string)file_get_contents(CORE_ROOT . '/player/player.css');
    $assert(str_contains($selection, 'aria-label="Player navigation"'), 'Player navigation needs an accessible label.');
    $assert(str_contains($selection, 'Account &amp; sign out'), 'Account navigation should clearly identify account/sign-out access.');
    $assert(!str_contains($selection, 'Account / sign out'), 'Duplicate account navigation item remains.');
    $assert(str_contains($selection, 'aria-label="Player setup progress"'), 'Player setup progress indicator is missing.');
    $assert(str_contains($selection, 'aria-current="step"'), 'Current workflow step must be announced accessibly.');
    $assert(str_contains($css, '.flow-steps') && str_contains($css, '@media(max-width:560px){.player-flow'), 'Responsive workflow progress styling is missing.');
});
$test('Gameplay does not expose redundant character creation action', static function () use ($assert): void {
    $game = (string)file_get_contents(CORE_ROOT . '/player/game.php');
    $js = (string)file_get_contents(CORE_ROOT . '/player/player.js');
    $assert(!str_contains($game, 'id="newCharacterButton"'), 'Gameplay still exposes Create a character button');
    $assert(!str_contains($js, "$('newCharacterButton').addEventListener"), 'Gameplay still wires the removed character creation action');
});
$test('Workbench character refresh can discover all saved characters without a preselected universe', static function () use ($assert): void {
    $workbench = (string)file_get_contents(CORE_ROOT . '/assets/admin.js');
    $assert(str_contains($workbench, "fetch('api/data-management.php'"), 'Global inventory fallback missing');
    $assert(str_contains($workbench, 'global saved-character registry'), 'Global character inventory feedback missing');
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

$test('Signed-in account portal continues to character selection', static function () use ($assert): void {
    $account = (string)file_get_contents(CORE_ROOT . '/player/account.php');
    $assert(str_contains($account, 'href="<?=$base?>/selection.php">Continue to character selection'), 'Signed-in account CTA must lead to character selection.');
    $assert(str_contains($account, 'authenticated API writes are protected by session CSRF validation'), 'Account page must not claim implemented authorization is missing.');
    $assert(!str_contains($account, 'href="<?=$base?>/game.php">Continue to game prototype'), 'Account portal must not bypass character selection.');
});
$test('Account listing is non-provisioning and exposes only safe management metadata', static function () use ($assert): void {
    $api = (string)file_get_contents(CORE_ROOT . '/api/player-accounts-admin.php');
    $workbench = (string)file_get_contents(CORE_ROOT . '/index.php') . (string)file_get_contents(CORE_ROOT . '/assets/admin.js');
    $assert(!str_contains($api, 'test_account_provisioned') && !str_contains($api, 'On a fresh development install, provision a test account'), 'Listing must not create a development account as a side effect.');
    foreach (['account_type','status','created_at'] as $field) $assert(str_contains($api, "'" . $field . "'=>"), 'Account list must expose ' . $field . ' metadata.');
    foreach (['<th>Email</th>','<th>Type</th>','<th>Status</th>','<th>Created</th>'] as $column) $assert(str_contains($workbench, $column), 'Account table missing column ' . $column . '.');
    $assert(!str_contains($workbench, 'Test credentials: <strong>test@core.local</strong>'), 'Workbench must not display reusable test credentials in the account panel.');
});
$test('Player prototype QA matches authenticated boot-data architecture', static function () use ($assert): void {
    $js = (string)file_get_contents(CORE_ROOT . '/player/player.js');
    $game = (string)file_get_contents(CORE_ROOT . '/player/game.php');
    foreach (["BABYLON.Engine", "BABYLON.ArcRotateCamera", 'api(`navigation.php?universe_id=', "mesh.metadata={entityId:system.id,entityType:'system'}", 'api(`navigation-route.php?universe_id=', "api('character-travel.php')", 'const boot = window.CORE_BOOT || {}'] as $needle) {
        $assert(str_contains($js, $needle), 'Player JavaScript is missing current architecture marker: ' . $needle);
    }
    $assert(str_contains($game, "'universe' => \$playerUniverse") && str_contains($game, "'character' => \$selectedCharacter"), 'Gameplay page must inject the selected character and assigned universe.');
});

$test('Workbench character registry uses a compact table with adjacent row actions', static function () use ($assert): void {
    $workbench = (string)file_get_contents(CORE_ROOT . '/index.php') . (string)file_get_contents(CORE_ROOT . '/assets/admin.js') . (string)file_get_contents(CORE_ROOT . '/assets/admin.css');
    foreach (['character-admin-table', '<th scope="col">Character</th>', '<th scope="col">ID</th>', '<th scope="col">Universe</th>', "lifecycleButton.textContent = character.lifecycle?.enabled === false ? 'Restore' : 'Disable'", "selectButton.textContent = 'Select'"] as $needle) {
        $assert(str_contains($workbench, $needle), 'Character registry table is missing expected feature: ' . $needle);
    }
    $assert(!str_contains($workbench, '.character-admin-list{display:grid'), 'Character registry must not use the old card grid.');
    $assert(str_contains($workbench, 'characterViewArchiveButton'), 'Character archive view should be available.');
    $assert(!str_contains($workbench, 'deleteCharacterButton'), 'Permanent character deletion controls must be absent.');
    $assert(!str_contains($workbench, 'Permanent character removal'), 'The separate permanent character deletion panel should be removed.');
});

$test('Gameplay navigation keeps Character Selection in the page header', static function () use ($assert): void {
    $html = (string)file_get_contents(CORE_ROOT . '/player/game.php');
    $assert(str_contains($html, 'class="character-selection-link"'), 'Character Selection should be a styled header navigation link.');
    $assert(!str_contains($html, 'map-back-link'), 'Character Selection must not be duplicated beside the map toolbar.');
});
$test('Gameplay map toolbar has no redundant Reset view control', static function () use ($assert): void {
    $html = (string)file_get_contents(CORE_ROOT . '/player/game.php');
    $js = (string)file_get_contents(CORE_ROOT . '/player/player.js');
    $assert(!str_contains($html, 'resetMapView'), 'Reset view control should be removed from the map toolbar.');
    $assert(!str_contains($js, "$('resetMapView')"), 'Reset view handler should be removed with its control.');
});

$test('Workbench styles and behavior are loaded from dedicated assets', static function () use ($assert): void {
    $html = (string)file_get_contents(CORE_ROOT . '/index.php');
    $assert(str_contains($html, "assets/admin.css?v=<?= htmlspecialchars(\$coreReleaseVersion, ENT_QUOTES, 'UTF-8') ?>"), 'Workbench stylesheet asset is not referenced.');
    $assert(str_contains($html, "assets/admin.js?v=<?= htmlspecialchars(\$coreReleaseVersion, ENT_QUOTES, 'UTF-8') ?>"), 'Workbench JavaScript asset is not referenced.');
    $assert(!str_contains($html, '<style>') && !str_contains($html, '<script>'), 'Workbench still embeds its main stylesheet or script.');
    $assert(is_file(CORE_ROOT . '/assets/admin.css') && is_file(CORE_ROOT . '/assets/admin.js'), 'Extracted workbench assets are missing.');
});


$test('Player-facing copy uses universe terminology and current account security state', static function () use ($assert): void {
    $files = ['player/index.php', 'player/account.php', 'player/character-create.php', 'player/universe-create.php', 'player/selection.php', 'player/game.php'];
    foreach ($files as $file) {
        $source = (string)file_get_contents(CORE_ROOT . '/' . $file);
        $assert(!preg_match('/frontier/i', $source), 'Player-facing file still contains prohibited frontier wording: ' . $file);
    }
    $account = (string)file_get_contents(CORE_ROOT . '/player/account.php');
    $assert(str_contains($account, 'authenticated API writes are protected by session CSRF validation'), 'Account security notice is stale.');
    $game = (string)file_get_contents(CORE_ROOT . '/player/game.php');
    $assert(!str_contains($game, 'characterCreationScreen') && !str_contains($game, 'Full point-buy and species adjustments are not implemented yet'), 'Gameplay still contains the obsolete duplicate character-creation screen.');
});


$test('Character previews rotate the complete imported GLB rig toward the camera', static function () use ($assert): void {
    $creation = (string)file_get_contents(CORE_ROOT . '/player/character-preview.js');
    $roster = (string)file_get_contents(CORE_ROOT . '/player/character-roster-preview.js');
    foreach ([$creation, $roster] as $index => $js) {
        $assert(str_contains($js, 'result.transformNodes || []'), 'Preview ' . $index . ' must include imported transform nodes.');
        $assert(str_contains($js, 'new BABYLON.TransformNode('), 'Preview ' . $index . ' must rotate a wrapper around the complete rig.');
        $assert(str_contains($js, 'importedRoots.forEach((node) => { node.parent = modelRoot; })'), 'Preview ' . $index . ' must parent every imported root to the wrapper.');
        $assert(str_contains($js, 'modelRoot.rotation.y += Math.PI'), 'Preview ' . $index . ' must face the known +Z model front toward its -Z camera.');
    }
});
$test('System overview orbit tubes and planets share the same local orbital plane', static function () use ($assert): void {
    $js = (string)file_get_contents(CORE_ROOT . '/player/system-view.js');
    $assert(str_contains($js, 'BABYLON.MeshBuilder.CreateTube(`orbit-${index}`'), 'System orbit must be generated from explicit path geometry.');
    $assert(str_contains($js, 'orbitPath.push(new BABYLON.Vector3('), 'System orbit path must use explicit local coordinates.');
    $assert(str_contains($js, 'window.CORE_ORBITS.layout'), 'System view must use the shared orbital layout.');
    $assert(!str_contains($js, 'plane.rotation'), 'System view must not invent orbital inclinations.');
    $assert(!str_contains($js, 'orbit.rotation.x = Math.PI / 2'), 'System orbit must not be rotated away from the planet path.');
});
$test('Character selection location preview uses shared coplanar orbital geometry', static function () use ($assert): void {
    $js = (string)file_get_contents(CORE_ROOT . '/player/character-roster-preview.js');
    $assert(str_contains($js, 'window.CORE_ORBITS.layout'), 'Location preview must use the shared orbital layout.');
    $assert(str_contains($js, 'BABYLON.MeshBuilder.CreateTube'), 'Location preview must construct explicit orbit paths.');
    $assert(!str_contains($js, 'plane.rotation'), 'Location preview must not invent orbital inclinations.');
    $assert(str_contains($js, 'Math.sin(angle) * orbitRadius'), 'Location planet must use the same circular local path as its orbit.');
    $assert(!str_contains($js, 'Math.sin(angle) * orbit * .42'), 'Location preview must not use an elliptical planet path inside a circular ring.');
});

$test('Character creation persists the selected model tint for later views', static function () use ($assert, $equal): void {
    $dir = sys_get_temp_dir() . '/core-character-appearance-' . bin2hex(random_bytes(6));
    try {
        $storage = new JsonStorage($dir);
        $service = new CharacterService($storage);
        $character = $service->create('', 'Tint Persistence', 'explorer', 'human', [], 'tint-owner', null, ['tint' => '#a14bc7', 'saturation' => 145, 'brightness' => 115]);
        $equal('#a14bc7', $character['appearance']['tint'] ?? null, 'Selected character tint was not persisted.');
        $equal(145, $character['appearance']['saturation'] ?? null, 'Selected appearance saturation was not persisted.');
        $equal(115, $character['appearance']['brightness'] ?? null, 'Selected appearance brightness was not persisted.');
        $universe = (new UniverseService($storage))->createOrLoad('core-cli-appearance-tint-assigned');
        $assigned = $service->create($universe['id'], 'Assigned Tint', 'explorer', 'human', [], 'tint-owner', null, ['tint' => '#25a0d8', 'saturation' => 80, 'brightness' => 90]);
        $equal('#25a0d8', $assigned['appearance']['tint'] ?? null, 'Assigned character tint was not persisted.');
        $equal(80, $assigned['appearance']['saturation'] ?? null, 'Assigned character saturation was not persisted.');
        $equal(90, $assigned['appearance']['brightness'] ?? null, 'Assigned character brightness was not persisted.');
        $invalid = $service->create('', 'Tint Fallback', 'explorer', 'human', [], 'tint-owner', null, ['tint' => 'not-a-color']);
        $equal('#61d8e8', $invalid['appearance']['tint'] ?? null, 'Invalid tint did not fall back to the default.');
        $equal(100, $invalid['appearance']['saturation'] ?? null, 'Invalid saturation did not fall back to the default.');
        $equal(100, $invalid['appearance']['brightness'] ?? null, 'Invalid brightness did not fall back to the default.');
    } finally {
        if (is_dir($dir)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); rmdir($dir); }
    }
});
$test('Active System resolution falls back when current_system_id is empty', static function () use ($assert, $equal): void {
    $equal('system-from-location', \Core\Modules\Characters\CharacterUniverseContext::activeSystemId([
        'current_system_id' => '',
        'location' => ['system_id' => 'system-from-location'],
    ]), 'An empty current_system_id prevented fallback to the nested active location.');
    $equal('system-from-location', \Core\Modules\Characters\CharacterUniverseContext::activeSystemId([
        'current_system_id' => '   ',
        'location' => ['system_id' => 'system-from-location'],
    ]), 'A whitespace current_system_id prevented fallback to the nested active location.');
    $equal('canonical-system', \Core\Modules\Characters\CharacterUniverseContext::activeSystemId([
        'current_system_id' => 'canonical-system',
        'location' => ['system_id' => 'other-system'],
    ]), 'A non-empty canonical current_system_id was not kept authoritative.');
    $worldPage = (string)file_get_contents(CORE_ROOT . '/player/world.php');
    $assert(str_contains($worldPage, 'CharacterUniverseContext::activeSystemId($character)'), 'Test-world server validation does not use canonical active-System resolution.');
});
$test('Universe Data is the first visible gameplay sidebar card', static function () use ($assert): void {
    $game = (string)file_get_contents(CORE_ROOT . '/player/game.php');
    $universe = strpos($game, 'id="universeDataPanel"');
    $selectedSystem = strpos($game, 'id="selectedPanel"');
    $assert($universe !== false && $selectedSystem !== false && $universe < $selectedSystem, 'Universe Data must appear before System and World detail cards in the sidebar.');
});
$test('Test-world entry is restricted to a generated world in the character current system', static function () use ($assert): void {
    $page = (string)file_get_contents(CORE_ROOT . '/player/world.php');
    $game = (string)file_get_contents(CORE_ROOT . '/player/game.php');
    $client = (string)file_get_contents(CORE_ROOT . '/player/player.js');
    $worldClient = (string)file_get_contents(CORE_ROOT . '/player/world.js');
    $environmentClient = (string)file_get_contents(CORE_ROOT . '/player/planetary-environment.js');
    foreach (['CharacterLifecycleGuard::assertCanPerformGameplayAction', 'UniverseLifecycleGuard::assertCanPerformGameplayAction', '$systemId !== $currentSystemId', 'WorldGenerator())->generateForSystem', '$worldId'] as $needle) {
        $assert(str_contains($page, $needle), 'World entry validation is missing: ' . $needle);
    }
    $assert(str_contains($game, 'id="enterWorldButton"'), 'System inspector does not expose the test-world entry action.');
    $assert(str_contains($client, "core:world-selected") && str_contains($client, "world.php"), 'Gameplay client does not route the selected world into the test environment.');
    foreach (['keydown', 'keyup', 'camera.getDirection(BABYLON.Axis.Z)', 'speedWalk', 'SceneLoader.ImportMeshAsync', 'CORE_PLANETARY_ENVIRONMENT.load', 'CORE_PLANETARY_ENVIRONMENT.create', 'CORE_PLANETARY_ENVIRONMENT.moveOnSurface'] as $needle) {
        $assert(str_contains($worldClient, $needle), 'Test-world movement/rendering is missing: ' . $needle);
    }
    foreach (['api/world-geography.php', 'create(scene)', 'surfaceAtNormal', 'surfaceField', 'sampleHeight', 'moveOnSurface', 'getCoordinates', 'core-planetary-terrain'] as $needle) {
        $assert(str_contains($environmentClient, $needle), 'Planetary geography-to-terrain integration is missing: ' . $needle);
    }
    $assert(str_contains($worldClient, 'positionCameraAboveSurface') && str_contains($worldClient, 'camera.setPosition'), 'Planetary camera is not positioned relative to the character surface.');
    $assert(str_contains($worldClient, 'rotateVectorBetweenNormals'), 'Planetary camera orientation is not transported as the character moves across the globe.');
    $assert(!str_contains($environmentClient, 'core-planetary-spawn-marker'), 'Unwanted visible spawn marker remains in the planetary environment.');
    $assert(!str_contains($worldClient, 'sampleAtCoordinates(window.CORE_PLANETARY_ENVIRONMENT.getCoordinates'), 'Per-frame movement still performs redundant terrain resampling.');
    foreach (['worldMapOverlay', 'worldMapCanvas', 'worldOverlay', 'worldCoordinates', 'worldMapPlayerMarker'] as $needle) {
        $assert(str_contains($page, $needle) || str_contains($worldClient, $needle), 'Planetary navigation UI is missing: ' . $needle);
    }
    foreach (['showMainMenu', 'showCharacterInfo', 'showInventory', "key === 'm'", "key === 'c'", "key === 'i'", "key === 'escape'"] as $needle) {
        $assert(str_contains($worldClient, $needle), 'Planetary keyboard navigation is missing: ' . $needle);
    }
    $assert(!str_contains($page, 'world-coordinate-readout') && !str_contains($page, 'TEST WORLD · SESSION ONLY') && !str_contains($page, 'world-character-label'), 'Redundant test-world banner or character label remains in the 3D viewport.');
    foreach (['universeName', 'constellationName', "'universe' =>", "'constellation' =>"] as $needle) {
        $assert(str_contains($page, $needle), 'World header hierarchy is missing: ' . $needle);
    }
});
$test('Planetary character orientation uses supported Babylon.js quaternion APIs', static function () use ($assert): void {
    $worldClient = (string)file_get_contents(CORE_ROOT . '/player/world.js');
    $assert(str_contains($worldClient, 'function alignUpToSurface(normal)'), 'Planetary surface alignment helper is missing.');
    $assert(str_contains($worldClient, 'BABYLON.Quaternion.RotationAxis'), 'Surface alignment does not use a supported Babylon.js quaternion factory.');
    $assert(!str_contains($worldClient, 'BABYLON.Quaternion.FromUnitVectors'), 'Unsupported Quaternion.FromUnitVectors call remains in the world client.');
});
$test('Character appearance controls persist and render consistently', static function () use ($assert): void {
    $create = (string)file_get_contents(CORE_ROOT . '/player/character-create.php');
    $creationJs = (string)file_get_contents(CORE_ROOT . '/player/character-creation.js');
    $appearanceJs = (string)file_get_contents(CORE_ROOT . '/player/appearance.js');
    $service = (string)file_get_contents(CORE_ROOT . '/app/Modules/Characters/CharacterService.php');
    $selection = (string)file_get_contents(CORE_ROOT . '/player/selection.php');
    $roster = (string)file_get_contents(CORE_ROOT . '/player/character-roster-preview.js');
    $world = (string)file_get_contents(CORE_ROOT . '/player/world.js');
    foreach ([[$create, 'appearance[tint]'], [$create, 'previewSaturation'], [$create, 'previewBrightness'], [$create, 'data-appearance-preset'], [$creationJs, 'characterSaturationValue'], [$creationJs, 'characterBrightnessValue'], [$appearanceJs, 'applyToMaterial'], [$service, '\'appearance\' => $savedAppearance'], [$selection, 'data-saturation='], [$selection, 'data-brightness='], [$roster, 'canvas.dataset.saturation'], [$world, 'characterAppearance'], [$world, 'snapshot.appearanceSaturation'], [$world, 'snapshot.appearanceBrightness']] as [$source, $needle]) {
        $assert(str_contains($source, $needle), 'Character appearance pipeline is missing: ' . $needle);
    }
});

$test('Humanoid GLB animation library selects exact Walk and exposes all imported clips', static function () use ($assert): void {
    $world = (string)file_get_contents(CORE_ROOT . '/player/world.js');
    foreach (["findClip('Walk')", "findClip('Run_Anime', 'Jog', 'Sprint', 'Run_Female')", 'window.CORE_ANIMATION_CATALOG', 'window.CORE_PLAY_ANIMATION', 'animationGroups.map(group =>'] as $needle) {
        $assert(str_contains($world, $needle), 'Humanoid animation selection/catalog support is missing: ' . $needle);
    }
    $assert(!str_contains($world, "find(group => /walk/i.test(group.name))"), 'Broad walk matching can select Crouch_Walk instead of Walk.');
});

$test('multiplayer token grant has a direct PHP endpoint that bypasses rewrite configuration', static function () use ($assert): void {
    $endpoint = (string)file_get_contents(CORE_ROOT . '/api/multiplayer-token.php');
    $playerClient = (string)file_get_contents(CORE_ROOT . '/player/player.js');
    $worldClient = (string)file_get_contents(CORE_ROOT . '/player/world.js');
    $environmentClient = (string)file_get_contents(CORE_ROOT . '/player/planetary-environment.js');
    $assert(str_contains($endpoint, "REQUEST_URI'] = '/api/multiplayer/token'") && str_contains($endpoint, "/public/index.php"), 'Direct multiplayer-token endpoint does not dispatch to the canonical application route.');
    $assert(str_contains($playerClient, '/api/multiplayer-token.php'), 'Player client does not use the direct multiplayer-token endpoint.');
    $assert(str_contains($worldClient, '/api/multiplayer-token.php'), 'World client does not use the direct multiplayer-token endpoint.');
});

$test('shared-universe API routes are rewritten to the application front controller', static function () use ($assert): void {
    $rules = (string)file_get_contents(CORE_ROOT . '/.htaccess');
    $client = (string)file_get_contents(CORE_ROOT . '/player/shared-universe.js');
    $assert(str_contains($rules, 'api/(universe/(share|visit|leave)|multiplayer/token)') && str_contains($rules, 'public/index.php'), 'Application-router JSON endpoints, including the multiplayer token route, are not mapped to the front controller.');
    $assert(str_contains($client, 'content-type') && str_contains($client, 'non-JSON response'), 'Share-code client does not handle HTML/non-JSON API responses safely.');
});


$test('CORE 0.1.180 renders upright, readable Universe and Constellation labels and keeps Back navigation out of Universe View', static function () use ($assert): void {
    $page = (string)file_get_contents(CORE_ROOT . '/player/game.php');
    $script = (string)file_get_contents(CORE_ROOT . '/player/player.js');
    $assert(str_contains($page, 'id="universeViewToolbar"') && str_contains($page, 'id="constellationViewToolbar" class="map-toolbar constellation-view-toolbar hidden"'), 'Universe and Constellation toolbars must have distinct initial visibility.');
    $assert(!str_contains($page, 'id="backToUniverseViewButton"'), 'Universe View navigation must not render a self-referential Back button.');
    foreach (['function renderUniverseMap()', 'constellations()', 'constellation-marker-', 'constellation-label-', 'createMapLabel(', 'texture.vScale = -1', 'selectConstellation(id)', "state.view = 'universe'"] as $needle) {
        $assert(str_contains($script, $needle), 'Universe hierarchy renderer is missing ' . $needle . '.');
    }
    $assert(str_contains($script, "if (state.view === 'universe') renderUniverseMap()"), 'Universe View must render the universe-level constellation overview.');
    $assert(str_contains($script, 'texture.vScale = -1') && str_contains($script, 'createMapLabel('), 'Map labels must be vertically corrected and use the shared readable-label renderer.');
    $assert(str_contains($script, 'constellation-label-system-') && str_contains($script, 'system.worlds?.length'), 'Constellation View must visibly identify each star system and its world count.');
    $assert(str_contains($script, 'View Selected Constellation'), 'Universe View must offer a contextual action to open the selected constellation.');
});

$test('CORE 0.1.180 preserves bright moon and orbital-path development visibility', static function () use ($assert): void {
    $astronomy = (string)file_get_contents(CORE_ROOT . '/player/astronomical-environment.js');
    foreach (['mesh.scaling.setAll(3.0)', 'radius: 54', 'moon.orbitMaterial.emissiveColor = BABYLON.Color3.White().scale(4.0)', 'const atmosphericFade ='] as $needle) {
        $assert(str_contains($astronomy, $needle), 'Planetary celestial presentation is missing ' . $needle . '.');
    }
    $assert(str_contains($astronomy, 'moon.mesh.position.copyFrom(planetCenter.add(moonDirection.scale(orbitalRadius)))'), 'Moon mesh must be positioned in planet-centered world space.');
    $assert(str_contains($astronomy, 'moon.worldOrbitPoints = pathPoints'), 'Moon orbital paths must be real 3D geometry.');
});

$test('CORE 0.1.180 keeps planetary diagnostics separate away from the bottom-right connection display', static function () use ($assert): void {
    $page = (string)file_get_contents(CORE_ROOT . '/player/world.php');
    $css = (string)file_get_contents(CORE_ROOT . '/player/world.css');
    $assert(str_contains($page, '<details><summary>Diagnostics</summary>'), 'Planetary diagnostics must be collapsed by default.');
    $assert(str_contains($css, 'left:1rem;bottom:1rem;right:auto'), 'Planetary diagnostics must use the separate bottom-left HUD position.');
});

$test('CORE 0.1.180 provides visible and hover identification for System View planets and their moons', static function () use ($assert): void {
    $page = (string)file_get_contents(CORE_ROOT . '/player/game.php');
    $script = (string)file_get_contents(CORE_ROOT . '/player/system-view.js');
    $css = (string)file_get_contents(CORE_ROOT . '/player/system-view.css');
    $assert(str_contains($page, 'id="systemViewHoverLabel"'), 'System View is missing its hover label surface.');
    foreach (['POINTERMOVE', 'parentWorld', 'moonIndex', 'CORE_SELECTED_WORLD', 'makeBodyLabel(', 'world-label-', 'moon-label-'] as $needle) $assert(str_contains($script, $needle), 'System View hover identification is missing ' . $needle . '.');
    $assert(str_contains($script, 'texture.vScale = -1'), 'System View body labels must render upright.');
    $assert(str_contains($css, '.system-view-hover-label'), 'System View hover label styling is missing.');
});

$test('CORE 0.1.203 preserves jump/run controls, lands fly mode at the current dry location, and initializes authoritative spawn', static function () use ($assert): void {
    $terrain = (string)file_get_contents(CORE_ROOT . '/player/planetary-environment.js');
    $world = (string)file_get_contents(CORE_ROOT . '/player/world.js');
    foreach (['core-local-terrain-relief', 'localPatchSizeKm = 512', 'localPatchSegments = 320', 'Math.sqrt(2 * planetRadius * 26)', 'updateLocalPatch', 'getAltitude(position)', 'includeLocalRelief', 'core-low-poly-ocean', 'findDrySpawnNormal', 'spawnSurface.water', 'updateWater'] as $needle) {
        $assert(str_contains($terrain, $needle), 'Local terrain relief/altitude implementation is missing ' . $needle . '.');
    }
    $assert(str_contains($world, 'updateLocalPatch(groundPosition)'), 'The local terrain patch must follow the current character position.');
    $assert(str_contains($world, "event.code === 'F6'"), 'Developer fly mode must have an F6 toggle.');
    $assert(str_contains($world, "pressed.has('pageup')") && str_contains($world, "pressed.has('pagedown')"), 'Developer flight must use Page Up / Page Down for vertical movement.');
    $assert(str_contains($world, 'getSurfacePosition(camera.position)'), 'Exiting fly mode must land at the current camera location.');
    $assert(str_contains($world, "multiplayerRoom.send('set-position'"), 'Authoritative multiplayer position must be initialized from the validated dry-land spawn and updated after flight.');
    $assert(str_contains($world, '!developerFlyMode && multiplayerRoom && authoritativeTransform'), 'Server position interpolation must not pull the camera/avatar back while flying.');
    $assert(str_contains($world, 'flySpeed * delta'), 'Fly speed must not be bound to Shift.');
    $assert(!str_contains($world, "if (pressed.has(' ')) travel") && !str_contains($world, "pressed.has('shift') ? 4"), 'Space and Shift must not control flight elevation or speed.');
    $assert(str_contains($world, 'developerFlyMode'), 'Developer fly mode must have a separate movement state.');
    $assert(str_contains($world, 'Altitude:'), 'The planetary map must display current surface altitude.');
    $assert(str_contains($world, 'altitudeMeters.toLocaleString()'), 'The location HUD must display altitude in meters.');
});

$test('CORE release version is centralized and matches the current release', static function () use ($assert): void {
    $version = require CORE_ROOT . '/config/version.php';
    $assert(is_string($version) && preg_match('/^0\\.1\\.205$/', $version) === 1, 'Unexpected CORE release version: ' . (string)$version);
    $worldPage = (string)file_get_contents(CORE_ROOT . '/player/world.php');
    $astronomy = (string)file_get_contents(CORE_ROOT . '/player/astronomical-environment.js');
    $assert(str_contains($worldPage, '\'seed\' => $seed'), 'World boot data must expose the assigned universe seed for scoped test presentation.');
    $assert(str_contains($astronomy, "universeSeed === 'tttttt'"), 'Lunar visibility override must be restricted to the documented test universe seed.');
    $assert(str_contains($astronomy, 'index === 0 ? 1.5 : 1.85'), 'Test moons must use corrected near-planet orbital radii.');
});

$test('Character selection provides an account-owned CSRF-protected delete action and hides archived characters', static function () use ($assert): void {
    $selection = (string)file_get_contents(CORE_ROOT . '/player/selection.php');
    foreach (['delete_character', 'hash_equals', 'owner_account_id', "['archived'] = true", 'character-delete-button', 'lifecycle'] as $needle) {
        $assert(str_contains($selection, $needle), 'Character deletion flow is missing: ' . $needle);
    }
});

$test('Test world uses the full viewport with overlay navigation and HUD', static function () use ($assert): void {
    $css = (string)file_get_contents(CORE_ROOT . '/player/world.css');
    foreach (['position:fixed;inset:0', 'width:100%;height:100%', 'world-topbar'] as $needle) {
        $assert(str_contains($css, $needle), 'Full-viewport test world styling is missing: ' . $needle);
    }
});

$test('Test world uses a full-viewport canvas without global main constraints', static function () use ($assert): void {
    $css = (string)file_get_contents(CORE_ROOT . '/player/world.css');
    foreach (['max-width:none!important', 'margin:0!important', 'padding:0!important', 'width:100vw!important', 'height:100vh!important'] as $needle) {
        $assert(str_contains($css, $needle), 'Full-viewport override is missing: ' . $needle);
    }
});

$test('Test world builds a real procedural idle animation when the GLB has no idle clip', static function () use ($assert): void {
    $world = (string)file_get_contents(CORE_ROOT . '/player/world.js');
    $assert(str_contains($world, 'createProceduralIdleAnimation'), 'Procedural idle animation builder is missing.');
    $assert(str_contains($world, 'new BABYLON.AnimationGroup'), 'Idle AnimationGroup is missing.');
    $assert(str_contains($world, 'rotationQuaternion'), 'Idle skeleton bone animation is missing.');
    $assert(str_contains($world, 'setAnimation(idleAnimation)'), 'Idle transition when movement stops is missing.');
});

$test('Character roster deletion posts to an active account-protected endpoint', static function () use ($assert): void {
    $selection = (string)file_get_contents(CORE_ROOT . '/player/selection.php');
    $endpoint = (string)file_get_contents(CORE_ROOT . '/api/character-delete.php');
    $assert(str_contains($selection, 'action="../api/character-delete.php"'), 'Roster delete form does not target the deletion endpoint.');
    $assert(str_contains($endpoint, 'REQUEST_METHOD'), 'Deletion endpoint does not restrict requests to POST.');
    $assert(str_contains($endpoint, 'hash_equals'), 'Deletion endpoint does not validate CSRF.');
    $assert(str_contains($endpoint, "'archived'"), 'Deletion endpoint does not archive the character.');
    $assert(!str_contains($endpoint, 'http_response_code(410)'), 'Deletion endpoint is still retired.');
});


$test('Character roster portrait selects the exact Walk animation, never Crouch_Walk', static function () use ($assert): void {
    $preview = (string)file_get_contents(CORE_ROOT . '/player/character-roster-preview.js');
    $assert(str_contains($preview, "String(group.name || '').trim().toLowerCase() === 'walk'"), 'Portrait animation does not select the exact Walk clip.');
    $assert(!str_contains($preview, 'find((group) => /walk/i.test(group.name))'), 'Broad walk matching can select Crouch_Walk.');
});

$test('Character selection and system view share the same coplanar orbital layout', static function () use ($assert): void {
    $helper = (string)file_get_contents(CORE_ROOT . '/player/orbital-mechanics.js');
    $roster = (string)file_get_contents(CORE_ROOT . '/player/character-roster-preview.js');
    $system = (string)file_get_contents(CORE_ROOT . '/player/system-view.js');
    $selection = (string)file_get_contents(CORE_ROOT . '/player/selection.php');
    $game = (string)file_get_contents(CORE_ROOT . '/player/game.php');
    $assert(str_contains($helper, 'window.CORE_ORBITS'), 'Shared orbital helper is missing.');
    $assert(str_contains($roster, 'window.CORE_ORBITS.layout'), 'Character selection does not use shared orbital layout.');
    $assert(str_contains($system, 'window.CORE_ORBITS.layout'), 'System view does not use shared orbital layout.');
    $assert(str_contains($selection, '/orbital-mechanics.js'), 'Character selection does not load orbital helper.');
    $assert(str_contains($game, '/orbital-mechanics.js'), 'Gameplay does not load orbital helper.');
    $assert(!str_contains($system, 'plane.rotation'), 'System view still invents inclined orbital planes.');
    $assert(!str_contains($roster, 'plane.rotation'), 'Character selection still invents inclined orbital planes.');
});


$test('Borderless interface contract removes component border declarations across all shared stylesheets', static function () use ($assert): void {
    $root = CORE_ROOT;
    $shared = (string)file_get_contents($root . '/assets/borderless.css');
    $assert(str_contains($shared, 'border: 0 !important'), 'The central borderless contract is missing its universal border reset.');
    $assert(str_contains($shared, '*::before'), 'Pseudo-elements are not covered by the borderless contract.');
    $assert(str_contains($shared, '::-webkit-slider-thumb'), 'Browser-native form-control parts are not covered.');
    foreach (['assets/admin.css', 'assets/admin-player-account.css', 'player/player.css', 'player/world.css', 'player/system-view.css'] as $relative) {
        $css = (string)file_get_contents($root . '/' . $relative);
        $assert(preg_match('/(?<![a-z-])border(?:-(?:top|right|bottom|left|width|style|color|image))?\s*:/i', $css) !== 1, 'A component border declaration remains in ' . $relative . '.');
    }
    $assert(!str_contains((string)file_get_contents($root . '/player/player.css'), 'box-shadow:inset 0 -2px'), 'The current workflow step still draws a border-like inset stripe.');
});

$test('Every CORE HTML surface loads the cache-busted borderless stylesheet last', static function () use ($assert): void {
    foreach (['index.php', 'player/index.php', 'player/account.php', 'player/selection.php', 'player/character-create.php', 'player/universe-create.php', 'player/game.php', 'player/world.php', 'admin/player-account.php'] as $relative) {
        $page = (string)file_get_contents(CORE_ROOT . '/' . $relative);
        $globalPosition = strrpos($page, 'borderless.css?v=');
        $lastStylesheetPosition = strrpos($page, 'rel="stylesheet"');
        $assert($globalPosition !== false, 'The borderless stylesheet is not loaded by ' . $relative . '.');
        $assert($lastStylesheetPosition !== false && $globalPosition > $lastStylesheetPosition, 'The borderless stylesheet is not the final stylesheet in ' . $relative . '.');
    }
});

$test('Standalone account administration uses external CSS and preserves keyboard focus indicators', static function () use ($assert): void {
    $page = (string)file_get_contents(CORE_ROOT . '/admin/player-account.php');
    $css = (string)file_get_contents(CORE_ROOT . '/assets/admin-player-account.css');
    $global = (string)file_get_contents(CORE_ROOT . '/assets/borderless.css');
    $assert(!str_contains($page, '<style>'), 'Standalone account administration still embeds its stylesheet.');
    $assert(str_contains($page, 'admin-player-account.css?v='), 'Standalone account administration does not load its external stylesheet.');
    $assert(str_contains($global, ':focus-visible'), 'The shared borderless stylesheet does not preserve keyboard focus visibility.');
    $assert(str_contains($css, 'button:focus-visible'), 'Standalone account administration has no keyboard focus styling.');
});


$test('Colyseus foundation defines isolated service and identity contracts', static function () use ($assert): void {
    $package = json_decode((string)file_get_contents(CORE_ROOT . '/multiplayer/package.json'), true);
    $identity = (string)file_get_contents(CORE_ROOT . '/multiplayer/src/contracts/MultiplayerIdentity.ts');
    $room = (string)file_get_contents(CORE_ROOT . '/multiplayer/src/contracts/RoomIdentity.ts');
    $movement = (string)file_get_contents(CORE_ROOT . '/multiplayer/src/contracts/MovementProtocol.ts');
    $architecture = (string)file_get_contents(CORE_ROOT . '/multiplayer/ARCHITECTURE.md');
    $assert(is_array($package) && ($package['private'] ?? false) === true, 'Multiplayer package must remain private.');
    $assert(str_starts_with((string)($package['dependencies']['@colyseus/core'] ?? ''), '^0.16.'), 'Colyseus core dependency must target the 0.16-compatible line.');
    $assert(!isset($package['dependencies']['colyseus']) && !isset($package['dependencies']['@colyseus/auth']), 'Umbrella Colyseus/auth dependency must not reintroduce Grant, elliptic, or uuid chains.');
    $assert(($package['overrides']['@colyseus/core']['nanoid'] ?? '') === '^3.3.18', 'Colyseus core must resolve NanoID to the patched 3.3.18 line.');
    $assert(($package['engines']['node'] ?? '') === '>=20', 'Multiplayer service must declare Node.js 20 or newer.');
    $assert(str_contains($identity, 'universeSeed') && str_contains($identity, 'universeId'), 'Identity contract must distinguish universe seed and saved universe ID.');
    $assert(str_contains($room, 'locationId: string | null'), 'Room identity must represent the currently optional location ID.');
    $assert(str_contains($movement, 'forward: -1 | 0 | 1') && !str_contains($movement, 'targetX'), 'Movement contract must express bounded input intent, not client target coordinates.');
    $assert(str_contains($architecture, 'Production joins require') && str_contains($architecture, 'HMAC-SHA256 grant'), 'Architecture must state the authentication implementation boundary.');
});

$test('Colyseus 0.16 server registers protected universe rooms with normalized location-aware matchmaking', static function () use ($assert): void {
    $root = CORE_ROOT . '/multiplayer';
    $package = json_decode((string)file_get_contents($root . '/package.json'), true);
    $server = (string)file_get_contents($root . '/src/server.ts');
    $room = (string)file_get_contents($root . '/src/rooms/UniverseRoom.ts');
    $identity = (string)file_get_contents($root . '/src/contracts/RoomIdentity.ts');
    $config = (string)file_get_contents($root . '/src/config/runtime.ts');
    $assert(is_array($package) && ($package['version'] ?? '') === '0.1.115', 'Multiplayer package version is not 0.1.115.');
    $assert(str_starts_with((string)($package['dependencies']['@colyseus/core'] ?? ''), '^0.16.'), 'Colyseus core must remain on the 0.16-compatible line.');
    $assert(str_contains($server, 'new Server(') && str_contains($server, 'createServer(app)') && str_contains($server, 'server.listen(config.port, config.host)'), 'Colyseus server does not initialize and bind using runtime configuration.');
    $assert(str_contains($server, 'new WebSocketTransport(') && str_contains($server, 'pingInterval: 10000') && str_contains($server, 'pingMaxRetries: 2'), 'WebSocket transport options are not configured on the transport instance.');
    foreach (['universeId', 'systemId', 'locationId'] as $field) {
        $assert(str_contains($server, '"' . $field . '"'), 'Matchmaking does not filter by ' . $field . '.');
    }
    foreach (['onCreate(', 'onAuth(', 'onJoin(', 'onLeave(', 'onDispose(', 'setMetadata('] as $needle) {
        $assert(str_contains($room, $needle), 'Universe room lifecycle is missing ' . $needle . '.');
    }
    $assert(str_contains($room, 'playerPresence') && str_contains($room, 'client.sessionId'), 'Transient server-side player presence is not keyed by Colyseus session ID.');
    $assert(str_contains($room, 'setSimulationInterval') && str_contains($room, 'player-transforms') && str_contains($room, 'receiveMovement'), 'Server-authoritative movement simulation and transform broadcasts are missing.');
    $assert(str_contains($room, 'isDevelopmentJoinAllowed') && str_contains($config, "nodeEnvironment !== 'production'") && str_contains($config, 'isLoopbackHost'), 'Development joins are not restricted to non-production loopback.');
    $assert(str_contains($identity, 'normalizeRoomIdentity') && str_contains($identity, 'JSON.stringify'), 'Room identity normalization or collision-safe key generation is missing.');
    $assert(str_contains((string)file_get_contents(CORE_ROOT . '/app/Application.php'), "'locationId' => \$locationId") && str_contains((string)file_get_contents(CORE_ROOT . '/player/world.php'), 'CORE_MULTIPLAYER_PUBLIC_URL'), 'PHP world-scoped grants or world multiplayer configuration are missing.');
    $assert(str_contains($server, '"/health"') && str_contains($server, '"php-hmac-grant"'), 'Health endpoint does not report the PHP authentication boundary.');
    $assert(str_contains((string)file_get_contents(CORE_ROOT . '/player/game.php'), 'colyseus.js@0.16.22'), 'The pinned Colyseus browser SDK is missing from the gameplay page.');
});

$test('Babylon gameplay can request a PHP grant and join the authenticated Colyseus universe room', static function () use ($assert): void {
    $page = (string)file_get_contents(CORE_ROOT . '/player/game.php');
    $client = (string)file_get_contents(CORE_ROOT . '/player/player.js');
    $assert(str_contains($page, 'colyseus.js@0.16.22'), 'Gameplay page does not load the pinned Colyseus 0.16 browser SDK.');
    $assert(str_contains($page, 'CORE_MULTIPLAYER_PUBLIC_URL') && str_contains($page, 'data-multiplayer-url'), 'Gameplay page does not expose the validated public multiplayer URL.');
    $assert(str_contains($page, 'id="multiplayerConnectButton"') && str_contains($page, 'id="multiplayerStatus"'), 'Gameplay page is missing multiplayer session controls.');
    $assert(str_contains($client, '/api/multiplayer-token.php') && str_contains($client, 'client.auth.token = grant.token'), 'Browser client does not request and apply the PHP-issued authorization grant through the direct PHP endpoint.');
    $assert(str_contains($client, "joinOrCreate('universe'") && str_contains($client, 'locationId:') && str_contains($client, 'identity.characterId'), 'Browser client does not join the verified character universe/system room.');
    $assert(str_contains($client, 'refreshMultiplayerSystemRoom') && str_contains($client, 'pagehide'), 'Browser client does not migrate rooms after travel or leave on page exit.');
});

$test('Multiplayer movement bounds permit spherical planetary traversal', static function () use ($assert): void {
    $protocol = (string)file_get_contents(CORE_ROOT . '/multiplayer/src/contracts/MovementProtocol.ts');
    $assert(str_contains($protocol, 'MOVEMENT_WORLD_LIMIT = 100000'), 'Multiplayer movement bounds are too small for spherical planetary traversal.');
});

$test('Planetary test world uses a location-bound grant and sends movement intent to the server', static function () use ($assert): void {
    $page = (string)file_get_contents(CORE_ROOT . '/player/world.php');
    $client = (string)file_get_contents(CORE_ROOT . '/player/world.js');
    $application = (string)file_get_contents(CORE_ROOT . '/app/Application.php');
    $assert(str_contains($page, 'colyseus.js@0.16.22') && str_contains($page, 'worldMultiplayerStatus'), 'World page does not load the pinned SDK or show shared-world status.');
    $assert(str_contains($client, "joinOrCreate('universe'") && str_contains($client, 'world_id: boot.world.id'), 'World client does not join a world-scoped room with a PHP grant.');
    $assert(str_contains($client, "send('move'") && str_contains($client, 'player-transforms') && str_contains($client, 'server-authoritative'), 'World client does not send bounded input and consume authoritative transforms.');
    $assert(str_contains($application, 'generateForSystem') && str_contains($application, "'locationId' => \$locationId"), 'PHP does not validate generated world membership before issuing a location-bound grant.');
});

$test('CORE 0.1.150 initializes eastward planetary travel and handles physical bracket keys', static function () use ($assert): void {
    $world = (string)file_get_contents(CORE_ROOT . '/player/world.js');
    $assert(str_contains($world, 'getEastTangent(playerRoot.position)'), 'Equatorial spawn must initialize an eastward tangent heading so forward travel changes longitude.');
    $assert(str_contains($world, "event.code === 'BracketLeft'") && str_contains($world, "event.code === 'BracketRight'"), 'Time controls must support physical bracket key codes.');
    $assert(str_contains($world, 'adjustTimeHours?.(timeBackward ? -1 : 1)'), 'Bracket keys must adjust simulated time in one-hour increments.');
});

$test('CORE 0.1.115 exposes shared-universe visit controls and hierarchical seed identities', static function () use ($assert): void {
    $application = (string)file_get_contents(CORE_ROOT . '/app/Application.php');
    $selection = (string)file_get_contents(CORE_ROOT . '/player/selection.php');
    $sharedClient = (string)file_get_contents(CORE_ROOT . '/player/shared-universe.js');
    $worldGenerator = (string)file_get_contents(CORE_ROOT . '/app/Modules/Universe/WorldGenerator.php');
    $systemGenerator = (string)file_get_contents(CORE_ROOT . '/app/Modules/Universe/UniverseGenerator.php');
    $security = (string)file_get_contents(CORE_ROOT . '/app/Http/PlayerApiSecurity.php');
    foreach (["'/api/universe/share'", "'/api/universe/visit'", "'/api/universe/leave'"] as $route) $assert(str_contains($application, $route), 'Missing shared-universe route ' . $route);
    $assert(str_contains($selection, 'sharedUniverseVisitForm') && str_contains($selection, 'shared-universe-share') && str_contains($selection, 'shared-universe-leave'), 'Character roster does not expose sharing, visiting, and returning controls.');
    $assert(str_contains($sharedClient, "post('universe/visit'") && str_contains($sharedClient, "post('universe/leave'") && str_contains($sharedClient, "post('universe-share.php'"), 'Shared-universe client actions are incomplete.');
    $assert(str_contains($systemGenerator, "\$systemSeed = 'SYS-"), 'Star systems are not assigned independent seeds.');
    $assert(str_contains($worldGenerator, "'seed' => \$worldSeed") && str_contains($worldGenerator, "'system_seed' => \$systemSeed"), 'Worlds do not expose their independent seed and parent system seed.');
    $assert(str_contains($security, 'canAccessUniverse') && str_contains($application, 'CharacterUniverseContext::activeUniverseId'), 'Active shared-universe access is not reflected in authorization and multiplayer grants.');
    $deletion = (string)file_get_contents(CORE_ROOT . '/app/Modules/UniverseDeletionService.php');
    $assert(str_contains($deletion, 'activeVisitorsFor') && str_contains($deletion, 'joined this shared universe'), 'Universe deletion does not protect active visiting characters.');
});

$test('CORE 0.1.114 renders remote humanoids and interpolates authoritative player transforms', static function () use ($assert): void {
    $world = (string)file_get_contents(CORE_ROOT . '/player/world.js');
    $player = (string)file_get_contents(CORE_ROOT . '/player/player.js');
    $room = (string)file_get_contents(CORE_ROOT . '/multiplayer/src/rooms/UniverseRoom.ts');
    $application = (string)file_get_contents(CORE_ROOT . '/app/Application.php');
    foreach (['createRemoteAvatar', 'createRemoteNameplate', 'updateRemotePlayers', 'shortestAngle', 'player-left', 'remote.running'] as $needle) {
        $assert(str_contains($world, $needle), 'Remote-player presentation is missing: ' . $needle);
    }
    $assert(str_contains($world, 'SceneLoader.ImportMeshAsync') && str_contains($world, 'CreateCapsule'), 'Remote players need GLB humanoids with a primitive fallback.');
    $assert(str_contains($world, 'appearanceTint') && str_contains($room, 'normalizeAppearanceTint'), 'Remote character appearance tint is not safely carried through the room snapshot.');
    $assert(str_contains($world, 'snapshot.appearanceSaturation') && str_contains($room, 'appearanceSaturation'), 'Remote character saturation is not safely carried through the room snapshot.');
    $assert(str_contains($world, 'snapshot.appearanceBrightness') && str_contains($room, 'appearanceBrightness'), 'Remote character brightness is not safely carried through the room snapshot.');
    $assert(str_contains($application, "'appearanceTint' => \$appearanceTint") && str_contains($application, "'appearanceSaturation' => \$appearanceSaturation") && str_contains($application, "'appearanceBrightness' => \$appearanceBrightness"), 'PHP grant does not derive the complete shared appearance configuration from the owned character record.');
    $game = (string)file_get_contents(CORE_ROOT . '/player/game.php');
    $version = (string)file_get_contents(CORE_ROOT . '/config/version.php');
    $assert(str_contains($game, 'id="multiplayerConnectButton"') && str_contains($game, 'id="multiplayerCopyKeyButton"'), 'Gameplay must expose Connect/Disconnect and Copy Key controls.');
    $assert(str_contains($game, "'canShareUniverse' => " . '$canShareActiveUniverse') && str_contains($game, 'PlayerApiSecurity::ownsUniverse'), 'Copy Key must be limited to the active universe owner.');
    $assert(str_contains($player, "button.textContent = connected ? 'Disconnect'") && str_contains($player, 'universe-share.php'), 'Session disconnect and universe key actions are not wired.');
    $assert(str_contains($player, 'navigator.clipboard.writeText(key)'), 'Copy Key does not write the sharing key to the clipboard.');
    $assert(!str_contains($player, 'room.roomId') && !str_contains($player, 'room.id ||'), 'Internal Colyseus room IDs must not be exposed in player-facing session status.');
});





$test('CORE 0.1.164 preserves browser modifier shortcuts from gameplay keybindings', static function () use ($assert): void {
    $world = (string)file_get_contents(CORE_ROOT . '/player/world.js');
    $assert(str_contains($world, 'if (event.ctrlKey || event.metaKey || event.altKey) return;'), 'Gameplay shortcuts must ignore Ctrl, Command, and Alt modified key events.');
    $assert(str_contains($world, 'target.isContentEditable') && str_contains($world, "['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)"), 'Gameplay shortcuts must continue to ignore editable controls.');
});

$test('CORE 0.1.142 corrects panel toggles, non-blocking transparent map HUD, and map-centered spawn', static function () use ($assert): void {
    $planet = (string)file_get_contents(CORE_ROOT . '/player/planetary-environment.js');
    $world = (string)file_get_contents(CORE_ROOT . '/player/world.js');
    $view = (string)file_get_contents(CORE_ROOT . '/player/world.php');
    $css = (string)file_get_contents(CORE_ROOT . '/player/world.css');
    $sky = (string)file_get_contents(CORE_ROOT . '/player/astronomical-environment.js');
    $assert(str_contains($planet, 'new BABYLON.Vector3(1, 0, 0)'), 'Spawn must be at latitude 0 / longitude 0, the map center.');
    $assert(str_contains($world, 'keepCameraAboveTerrain(camera)') && str_contains($world, 'camera.setPosition(normal.scale(minimumRadius))'), 'Camera terrain clearance must be enforced during gameplay.');
    $assert(str_contains($world, 'camera.maxZ = Math.max(250000') && str_contains($world, 'camera.fov = Math.max(camera.fov, 1.3)'), 'Camera far clipping must include the astronomical sky.');
    $assert(str_contains($view, '<aside id="worldMapOverlay"') && !str_contains($view, 'id="worldMapOverlay" class="world-map-overlay hidden" aria-modal'), 'Planetary map must be an in-world HUD aside, not a modal dialog.');
    $assert(str_contains($css, '.world-map-overlay{position:fixed;z-index:18;top:88px;right:18px;') && str_contains($css, 'background:transparent') && str_contains($css, 'pointer-events:none'), 'Map must be a transparent, frameless HUD that does not intercept gameplay input.');
    $assert(str_contains($world, "if (activePanel === panelKey && !overlay.classList.contains('hidden'))") && str_contains($world, "showPanel('character', 'Character Information'") && str_contains($world, "showPanel('inventory', 'Inventory'"), 'C and I panels must close when their own shortcut is pressed again.');
    $assert(str_contains($world, "if (activePanel === 'map' && !mapOverlay?.classList.contains('hidden')) closePanels()") && str_contains($world, 'The floating map is informational and must never capture/block movement.') && str_contains($world, "if (!overlay?.classList.contains('hidden')) return;"), 'M must toggle the map and movement must remain enabled while the map is visible.');
    $assert(str_contains($world, 'const x = 360 + (coordinates.longitude * 2)') && str_contains($world, 'const y = 180 - (coordinates.latitude * 2)'), 'The global map projection must place the 0°,0° spawn at the exact map center.');
    $assert(str_contains($sky, 'this.moonMeshes = moons.map') && str_contains($sky, 'core-night-sky-stars'), 'Night sky must include a visible natural-satellite presentation and fog-independent stars.');
});

$test('CORE 0.1.144 uses a Babylon.js-supported matrix constructor for character orientation', static function () use ($assert): void {
    $world = (string)file_get_contents(CORE_ROOT . '/player/world.js');
    $assert(str_contains($world, 'BABYLON.Matrix.FromValues('), 'Character orientation must construct its basis using the supported Matrix.FromValues API.');
    $assert(str_contains($world, 'right.x, right.y, right.z, 0,') && str_contains($world, 'up.x, up.y, up.z, 0,'), 'Orientation matrix must store local right/up/forward vectors as Babylon matrix columns.');
    $assert(!str_contains($world, 'BABYLON.Matrix.FromXYZAxes'), 'Character orientation must not call the unavailable Matrix.FromXYZAxes API.');
});

$test('CORE 0.1.143 preserves character heading, centers planetary coordinate frames, improves night readability, and cleans map HUD', static function () use ($assert): void {
    $world = (string)file_get_contents(CORE_ROOT . '/player/world.js');
    $planet = (string)file_get_contents(CORE_ROOT . '/player/planetary-environment.js');
    $sky = (string)file_get_contents(CORE_ROOT . '/player/astronomical-environment.js');
    $view = (string)file_get_contents(CORE_ROOT . '/player/world.php');
    $css = (string)file_get_contents(CORE_ROOT . '/player/world.css');
    $version = (string)file_get_contents(CORE_ROOT . '/config/version.php');
    $assert(str_contains($world, 'function orientCharacterToSurface(normal, heading)') && str_contains($world, 'BABYLON.Matrix.FromValues(') && !str_contains($world, 'BABYLON.Matrix.FromXYZAxes'), 'Character orientation must combine surface alignment with an independent heading direction.');
    $assert(str_contains($world, 'facingDirection = rotateVectorBetweenNormals(facingDirection, previousNormal, movedNormal)') && str_contains($world, 'orientCharacterToSurface(surfaceNormal, facingDirection)'), 'Movement must preserve and transport heading as the character traverses the spherical surface.');
    $assert(!str_contains($world, 'playerRoot.rotationQuaternion = alignUpToSurface(surfaceNormal)'), 'Per-frame surface alignment must not overwrite the character heading.');
    $assert(str_contains($planet, 'BABYLON.Axis.X.scale(Math.cos(angle))') && str_contains($planet, 'new BABYLON.Vector3(0, Number(z) / distance, Number(x) / distance)'), 'Multiplayer/local coordinates must use the same 0°,0° map-center origin as the world map.');
    $assert(str_contains($sky, 'this.ambient.intensity = .92') && str_contains($sky, 'new BABYLON.Color3(.20, .22, .27)'), 'Planetary ambient fill must keep terrain readable during night conditions.');
    $assert(!str_contains($view, 'world-map-legend') && !str_contains($view, '>Water</span>') && str_contains($view, 'id="worldMapLocation"'), 'Map terrain legend must be removed while retaining the location readout directly below the map.');
    $assert(str_contains($css, '.world-map-location{display:block;margin:2px 0 0;'), 'Current location must sit immediately beneath the map without a legend gap.');
});



$test('CORE 0.1.145 corrects avatar basis orientation, improves night visibility, and consolidates the map HUD', static function () use ($assert): void {
    $world = (string)file_get_contents(CORE_ROOT . '/player/world.js');
    $sky = (string)file_get_contents(CORE_ROOT . '/player/astronomical-environment.js');
    $view = (string)file_get_contents(CORE_ROOT . '/player/world.php');
    $css = (string)file_get_contents(CORE_ROOT . '/player/world.css');
    $version = (string)file_get_contents(CORE_ROOT . '/config/version.php');
    $assert(str_contains($world, 'right.x, right.y, right.z, 0,') && str_contains($world, 'up.x, up.y, up.z, 0,') && str_contains($world, 'correctedForward.x, correctedForward.y, correctedForward.z, 0,'), 'Avatar orientation basis must be assembled in Babylon column order.');
    $assert(str_contains($world, 'material.emissiveColor = material.diffuseColor.scale(.18)'), 'Player material must retain modest self-illumination for night visibility.');
    $assert(str_contains($sky, 'this.ambient.intensity = .92') && str_contains($sky, 'new BABYLON.Color3(.20, .22, .27)'), 'Night ambient illumination must be raised enough to preserve terrain readability.');
    $assert(!str_contains($view, 'TEST WORLD · SESSION ONLY') && !str_contains($view, 'world-map-heading'), 'Unnecessary world banner and map title must be removed from the 3D viewport.');
    $assert(str_contains($view, 'id="worldMapCanvas"') && str_contains($view, 'id="worldMapLocation"'), 'Map and current-location readout must remain grouped in one HUD element.');
    $assert(str_contains($css, '.world-map-overlay{position:absolute!important;') && str_contains($css, 'top:14px!important;left:14px!important;') && str_contains($css, 'height:auto!important;min-height:0!important;'), 'Map HUD must be positioned inside the 3D viewport with compact dimensions and no detached location gap.');
});

$test('CORE 0.1.133 integrates procedural Lifepath generation, age limits, profession direction, and locked background persistence', static function () use ($assert, $equal): void {
    $engine = new \Core\Modules\Characters\Lifepath\LifepathEngine();
    $first = $engine->generateLifepath('human', null, 'generate');
    $equal(13, $first['current_age'], 'Human Lifepath must begin at its configured post-maturation year');
    $equal(13, $first['maturation_age'], 'Maturation age metadata');
    $assert(count($first['history']) >= 2, 'Initial year must include upbringing and a yearly event.');
    $maximum = $engine->generateLifepath('human', $first['seed'], 'reload', 1000);
    $equal(85, $maximum['current_age'], 'Lifepath must stop at the species maximum age');
    $assert($maximum['at_maximum_age'] === true, 'Maximum-age state must be exposed to the client.');

    $view = (string)file_get_contents(CORE_ROOT . '/player/character-create.php');
    $client = (string)file_get_contents(CORE_ROOT . '/player/lifepath-creation.js');
    $service = (string)file_get_contents(CORE_ROOT . '/app/Modules/Characters/CharacterService.php');
    $endpoint = (string)file_get_contents(CORE_ROOT . '/api/lifepath.php');
    foreach (['backgroundSnapshot', 'lifepathTimeline', 'lifepathCareerMilestone', 'lifepathLock'] as $needle) {
        $assert(str_contains($view, $needle), 'Background tab is missing Lifepath UI element: ' . $needle);
    }
    foreach (['education', 'military', 'employment', 'independent', 'buildSnapshot', 'at_maximum_age'] as $needle) {
        $assert(str_contains($client, $needle), 'Lifepath client is missing workflow support: ' . $needle);
    }
    $assert(str_contains($endpoint, 'X_CORE_CSRF') && str_contains($endpoint, 'core_user'), 'Lifepath endpoint must require an authenticated, CSRF-protected session.');
    $assert(str_contains($service, "'background' => \$backgroundSnapshot") && str_contains($service, "'age' => \$backgroundSnapshot['age']"), 'Character records must persist the locked history snapshot and current age.');
    $assert(str_contains($service, 'validateBackgroundSnapshot') && str_contains($service, 'Lock the generated character history'), 'Server must reject incomplete or unlocked Lifepath snapshots.');

    $dir = sys_get_temp_dir() . '/core-lifepath-persist-' . bin2hex(random_bytes(6));
    try {
        $storage = new JsonStorage($dir);
        $snapshot = [
            'locked' => true, 'species_key' => 'human', 'race' => $first['race'],
            'seed' => $first['seed'], 'age' => $first['current_age'],
            'maturation_age' => $first['maturation_age'], 'graduation_age' => $first['race']['graduation_age'],
            'max_allowable_age' => $first['max_allowable_age'], 'career_path' => null,
            'siblings' => $first['siblings'], 'history' => $first['history'],
        ];
        $savedCharacter = (new CharacterService($storage))->create('', 'Lifepath Persistence', 'explorer', 'human', [], 'lifepath-owner', 'strength', [], $snapshot);
        $saved = $storage->read('characters', $savedCharacter['id']);
        $equal(13, $saved['age'] ?? null, 'Character current age was not persisted.');
        $equal(true, $saved['background']['locked'] ?? false, 'Locked history was not persisted.');
        $equal($first['seed'], $saved['background']['seed'] ?? null, 'Lifepath seed was not retained.');
        $badSnapshot = $snapshot;
        $badSnapshot['locked'] = false;
        try {
            (new CharacterService($storage))->create('', 'Unlocked Lifepath', 'explorer', 'human', [], 'lifepath-owner', 'strength', [], $badSnapshot);
            throw new RuntimeException('An unlocked Lifepath snapshot was accepted.');
        } catch (RuntimeException $e) {
            $assert(str_contains($e->getMessage(), 'Lock the generated character history'), 'Unexpected unlocked-history rejection.');
        }
    } finally {
        if (is_dir($dir)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            @rmdir($dir);
        }
    }
});


$test('QA results, changelogs, and release notes use dedicated documentation folders', static function () use ($assert): void {
    foreach (['qa/QA-RESULTS-0.1.146.txt', 'changelog/CHANGELOG-0.1.146.txt', 'release/RELEASE-NOTES-0.1.146.md'] as $path) {
        $assert(is_file(CORE_ROOT . '/' . $path), 'Missing organized release document: ' . $path);
    }
    foreach (glob(CORE_ROOT . '/QA-RESULTS*.txt') ?: [] as $path) $assert(false, 'QA result remains in project root: ' . basename($path));
    foreach (glob(CORE_ROOT . '/CHANGELOG*.txt') ?: [] as $path) $assert(false, 'Changelog remains in project root: ' . basename($path));
    foreach (glob(CORE_ROOT . '/RELEASE-NOTES-*.md') ?: [] as $path) $assert(false, 'Release note remains in project root: ' . basename($path));
});

$test('Planetary geography is deterministic, UWP-informed, and exposes stable hex/location records', static function () use ($assert, $equal): void {
    $world = [
        'id' => 'wld_test_001', 'seed' => 'WLD-TEST-SEED-001', 'uwp' => 'B867A99-C',
        'starport' => 'B', 'size' => 8, 'hydrographics' => 6, 'population' => 10,
    ];
    $generator = new \Core\Modules\Universe\PlanetaryGeographyGenerator();
    $first = $generator->generate($world);
    $second = $generator->generate($world);
    $equal($first, $second, 'Identical world inputs must produce identical geography.');
    $equal(320, $first['map']['hex_count'], 'Default map should contain 20 x 16 hexes.');
    $equal(192, $first['map']['water_hex_count'], 'Hydrographics 6 should produce 60% water coverage.');
    $equal(192, count(array_filter($first['map']['hexes'], static fn(array $hex): bool => $hex['water'])), 'Water hex count does not match hydrographics.');
    $assert(count($first['locations']['population_centers']) > 0, 'Populated world should receive population centers.');
    $equal(1, count($first['locations']['spaceports']), 'A/B/C/D/E starport should produce one geographic spaceport marker.');
    $ids = array_column($first['map']['hexes'], 'id');
    $equal(320, count(array_unique($ids)), 'Hex identifiers must be unique.');
    $different = $world;
    $different['seed'] = 'WLD-OTHER-SEED';
    $assert($first['generation_fingerprint'] !== $generator->generate($different)['generation_fingerprint'], 'Different world seeds should produce different geography.');
    $waterless = $world;
    $waterless['hydrographics'] = 0;
    $equal(0, $generator->generate($waterless)['map']['water_hex_count'], 'Hydrographics 0 should produce no water hexes.');
    $uninhabited = $world;
    $uninhabited['population'] = 0;
    $equal([], $generator->generate($uninhabited)['locations']['population_centers'], 'Population 0 should not produce population centers.');
    $noPort = $world;
    $noPort['starport'] = 'X';
    $equal([], $generator->generate($noPort)['locations']['spaceports'], 'X starport should not produce a spaceport.');
    try {
        $generator->generate(['id' => 'missing-seed', 'uwp' => 'A000000-0', 'size' => 0, 'hydrographics' => 0, 'population' => 0, 'starport' => 'A']);
        throw new RuntimeException('Missing world seed was accepted.');
    } catch (InvalidArgumentException $e) {
        $assert(str_contains($e->getMessage(), 'seed'), 'Unexpected invalid-world error.');
    }
});

$test('Planetary geography defines a physical spherical world independent of the legacy hex demo grid', static function () use ($assert, $equal): void {
    $world = ['id' => 'wld_sphere', 'seed' => 'SPHERE-SEED', 'uwp' => 'B867A99-C', 'starport' => 'B', 'size' => 8, 'hydrographics' => 6, 'population' => 10, 'type' => 'large_terrestrial'];
    $geography = (new \Core\Modules\Universe\PlanetaryGeographyGenerator())->generate($world);
    $equal(2, $geography['schema_version'], 'Spherical geography schema was not advanced.');
    $equal(6400, $geography['planet']['radius_km'], 'Planet radius must derive from the Traveller size code.');
    $equal(12800, $geography['planet']['diameter_km'], 'Planet diameter must be twice its radius.');
    $equal('spherical_latitude_longitude', $geography['planet']['coordinate_system'], 'Planetary coordinates are not spherical.');
    $equal('seeded_spherical_field', $geography['global_surface']['model'], 'Global surface generation is not seed-driven and spherical.');
    $equal(0.6, $geography['global_surface']['hydrographics_fraction'], 'Global surface does not expose its hydrographics target.');
    $equal(320, $geography['map']['hex_count'], 'Legacy hex data should remain available as a compatibility/demo reference.');
});

$test('Planetary geography API persists the first baseline without overwriting established geography', static function () use ($assert, $equal): void {
    $source = file_get_contents(CORE_ROOT . '/app/Application.php');
    $assert(is_string($source) && str_contains($source, "createIfAbsent('world_geography', \$worldId"), 'Geography API must persist its first baseline using atomic create-if-absent storage.');
    $assert(str_contains($source, "'universe_id' => \$universeId") && str_contains($source, "'system_id' => \$systemId"), 'Persisted geography must retain its owning universe and system identity.');

    $root = sys_get_temp_dir() . '/core-geography-persistence-' . bin2hex(random_bytes(5));
    try {
        $storage = new JsonStorage($root);
        $first = ['id' => 'wld_persist', 'universe_id' => 'uni_owner', 'system_id' => 'sys_owner', 'world_id' => 'wld_persist', 'geography' => ['generation_fingerprint' => 'first']];
        $second = $first;
        $second['geography']['generation_fingerprint'] = 'replacement';
        $equal($first, $storage->createIfAbsent('world_geography', 'wld_persist', $first), 'First generated geography should be stored.');
        $equal($first, $storage->createIfAbsent('world_geography', 'wld_persist', $second), 'A later generation must not overwrite the established baseline.');
    } finally {
        if (is_dir($root)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            @rmdir($root);
        }
    }
});

$test('Planetary terrain definitions are deterministic, editable, and independent from climate', static function () use ($assert, $equal): void {
    $world = ['id' => 'wld_terrain_test', 'seed' => 'TERRAIN-SEED-001', 'uwp' => 'B867A99-C', 'starport' => 'B', 'size' => 8, 'hydrographics' => 6, 'population' => 7];
    $geography = (new \Core\Modules\Universe\PlanetaryGeographyGenerator())->generate($world);
    $generator = new \Core\Modules\Universe\PlanetaryTerrainGenerator();
    $first = $generator->generate($world, $geography);
    $second = $generator->generate($world, $geography);
    $equal($first, $second, 'Terrain definitions must be deterministic for the same world.');
    $equal('spherical_domain_warped_fractal_noise', $first['generator']['model'], 'Terrain must use spherical domain-warped fractal noise.');
    $equal(0.6, $first['generator']['parameters']['hydrographics_fraction'], 'Terrain must retain the world hydrographics target.');
    $equal([], $first['modifications'], 'New worlds should start without authored terrain modifications.');
    $assert(in_array('volcano', $first['feature_schema']['supported_types'], true), 'Terrain feature schema must support volcanoes.');
    $assert(in_array('meteor_crater', $first['feature_schema']['supported_types'], true), 'Terrain feature schema must support meteor craters.');
    $climate = (new \Core\Modules\Universe\PlanetaryClimateGenerator())->generate($world, $geography);
    $assert(!isset($climate['generator']['parameters']['elevation_scale_km']), 'Climate must not own terrain elevation parameters.');
    $assert(isset($climate['parameters']['elevation_lapse_rate_c_per_km']), 'Climate should expose elevation as an environmental input.');
    $changed = $world;
    $changed['seed'] = 'TERRAIN-SEED-002';
    $assert($first['world']['terrain_seed'] !== $generator->generate($changed, $geography)['world']['terrain_seed'], 'Different world seeds must produce different terrain seeds.');
});

$test('Terrain and climate accept legacy geography records without rewriting them', static function () use ($assert, $equal): void {
    $world = ['id' => 'wld_legacy_geo', 'seed' => 'LEGACY-GEO-SEED', 'size' => 9, 'hydrographics' => 3, 'population' => 6, 'starport' => 'C'];
    // Schema v1 records predate the planet.radius_km field.
    $legacyGeography = [
        'schema_version' => 1,
        'world' => ['id' => $world['id'], 'size' => 9],
        'map' => ['columns' => 20, 'rows' => 16],
    ];
    $original = $legacyGeography;
    $terrain = (new \Core\Modules\Universe\PlanetaryTerrainGenerator())->generate($world, $legacyGeography);
    $climate = (new \Core\Modules\Universe\PlanetaryClimateGenerator())->generate($world, $legacyGeography);
    $equal(7200.0, $terrain['world']['radius_km'], 'Legacy size-9 geography should resolve to the established 7,200 km radius.');
    $equal(7200.0, $climate['parameters']['world_radius_km'], 'Climate should use the same resolved legacy radius.');
    $equal($original, $legacyGeography, 'Resolving legacy radius must not mutate or rewrite saved geography.');
});

$test('Persistent terrain and climate assets are created once and preserve developer edits', static function () use ($assert, $equal): void {
    $source = (string)file_get_contents(CORE_ROOT . '/app/Application.php');
    $assert(str_contains($source, "createIfAbsent('world_terrain', \$worldId"), 'Terrain API must persist its first definition atomically.');
    $assert(str_contains($source, "createIfAbsent('world_climate', \$worldId"), 'Climate API must persist its first definition atomically.');
    $assert(is_file(CORE_ROOT . '/api/world-terrain.php'), 'Explicit terrain API endpoint is missing.');
    $root = sys_get_temp_dir() . '/core-terrain-persistence-' . bin2hex(random_bytes(5));
    try {
        $storage = new JsonStorage($root);
        $first = ['id' => 'wld_terrain_persist', 'universe_id' => 'uni_owner', 'system_id' => 'sys_owner', 'world_id' => 'wld_terrain_persist', 'terrain' => ['modifications' => []]];
        $edited = $first;
        $edited['terrain']['modifications'][] = ['id' => 'volcano_test', 'type' => 'volcano', 'latitude' => 12, 'longitude' => -44, 'radius_km' => 8, 'height_km' => 3];
        $equal($first, $storage->createIfAbsent('world_terrain', 'wld_terrain_persist', $first), 'Initial terrain asset should be stored.');
        $storage->write('world_terrain', 'wld_terrain_persist', $edited);
        $equal($edited, $storage->createIfAbsent('world_terrain', 'wld_terrain_persist', $first), 'Subsequent terrain generation must not overwrite a developer edit.');
    } finally {
        if (is_dir($root)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            @rmdir($root);
        }
    }
});

$test('Planetary terrain renderer consumes persistent fractal definitions and authored features', static function () use ($assert): void {
    $client = (string)file_get_contents(CORE_ROOT . '/player/planetary-environment.js');
    foreach (["api/world-terrain.php", 'terrainPayload.terrain', 'continental_frequency', 'regional_frequency', 'mountain_frequency', 'detail_frequency', 'domain_warp_strength', 'meteor_crater', 'volcano', 'surfaceAtNormal', 'moveOnSurface'] as $needle) {
        $assert(str_contains($client, $needle), 'Persistent fractal terrain integration is missing: ' . $needle);
    }
    $assert(str_contains($client, 'get terrain()') && str_contains($client, 'get climate()'), 'Loaded terrain and climate definitions should be available for diagnostics and future tools.');
});

$test('Planetary terrain renderer produces visible geometric relief at adequate mesh resolution', static function () use ($assert): void {
    $environment = (string)file_get_contents(CORE_ROOT . '/player/planetary-environment.js');
    $generator = (string)file_get_contents(CORE_ROOT . '/app/Modules/Universe/PlanetaryTerrainGenerator.php');
    $assert(str_contains($environment, 'const segments = 128;'), 'The distant planetary fallback mesh should remain lightweight because the local patch supplies close detail.');
    $assert(str_contains($environment, 'relief_exaggeration') && str_contains($environment, 'elevation *= reliefExaggeration'), 'Elevation exaggeration must be applied consistently to generated terrain and authored features.');
    $assert(str_contains($environment, 'mesh.refreshBoundingInfo(true)'), 'Babylon bounds must be refreshed after terrain vertices are displaced.');
    $assert(str_contains($environment, 'build.positions[positionOffset + 1] = direction.y * radius;'), 'Generated elevation must displace actual local-patch vertex positions.');
    $assert(str_contains($environment, 'const localPatchBatchSize = 4096;') && str_contains($environment, 'requestAnimationFrame(processBatch)'), 'Local terrain sampling must be spread across frames instead of blocking movement.');
    $assert(str_contains($environment, 'Stable, world-space surface breakup') && str_contains($environment, 'textureNoise'), 'Ground surface color variation must be deterministic and world-space stable.');
    $assert(str_contains($environment, 'material.specularPower = 72') && str_contains($environment, 'waterPhase'), 'Ocean rendering must include stronger specular highlights and animated wave displacement.');
    $assert(str_contains($environment, 'valueNoise3D(normal.x * 80 + 41.7'), 'Broad landform sampling must use deterministic low-frequency spatial noise.');
    $assert(str_contains($environment, 'const landform = 0.8 + broadDetail * 0.85'), 'Local terrain patch must remain above the coarse sphere so sampled valleys are not hidden by the base mesh.');
    $assert(str_contains($environment, 'function hashTerrainSeed(seed)'), 'Terrain seeds with WLD-/SYS- prefixes must be hashed as full strings rather than parsed as hexadecimal prefixes.');
    $assert(str_contains($environment, 'terrainSeedNumber = hashTerrainSeed(seed);'), 'The full persistent world seed must drive deterministic terrain noise.');
    $assert(str_contains($environment, 'Math.cos(6 / planetRadius)'), 'Terrain patch updates must be throttled to prevent frequent CPU spikes.');
    $assert(str_contains($generator, "'relief_exaggeration' => 2.5"), 'New persistent terrain definitions must declare their relief exaggeration explicitly.');
});

$test('Planetary geography generator rejects unsafe map dimensions', static function () use ($assert): void {
    $generator = new \Core\Modules\Universe\PlanetaryGeographyGenerator();
    try {
        $generator->generate(['id' => 'wld', 'seed' => 'seed', 'uwp' => 'A000000-0', 'size' => 0, 'hydrographics' => 0, 'population' => 0, 'starport' => 'A'], 100, 100);
        throw new RuntimeException('Oversized geography dimensions were accepted.');
    } catch (InvalidArgumentException $e) {
        $assert(str_contains($e->getMessage(), 'dimensions'), 'Unexpected dimension validation error.');
    }
});


$test('Planetary test world passes complete generated system data to the astronomical renderer', static function () use ($assert): void {
    $view = file_get_contents(CORE_ROOT . '/player/world.php');
    $renderer = file_get_contents(CORE_ROOT . '/player/world.js');
    $helper = file_get_contents(CORE_ROOT . '/player/astronomical-environment.js');
    $assert(is_string($view) && str_contains($view, "'system' => array_merge(\$system"), 'The world boot payload must retain full system astronomy data.');
    $assert(strpos($view, 'astronomical-environment.js') < strpos($view, 'world.js?v='), 'The astronomical helper must load before the world controller.');
    $assert(str_contains($renderer, 'CORE_ASTRONOMICAL_ENVIRONMENT.create(') && str_contains($renderer, 'camera.getTarget().subtract(camera.position).normalize()'), 'The world scene must initialize the astronomical renderer with the entry camera heading.');
    $assert(str_contains($renderer, 'CORE_ASTRONOMICAL_ENVIRONMENT.update(') && str_contains($renderer, 'camera.getTarget().subtract(camera.position).normalize()'), 'The astronomical simulation must receive the live camera heading during gameplay.');
    $assert(str_contains($helper, 'system?.stars') && str_contains($helper, 'system?.star'), 'The renderer must support both multi-star and legacy primary-star system records.');
    $assert(str_contains($helper, 'system?.central_body') && str_contains($helper, 'is_luminous'), 'Non-stellar central bodies must be represented without automatically treating them as stars.');
});

$test('CORE 0.1.184 passes generated system worlds to the sky renderer and simulates visible planetary positions', static function () use ($assert): void {
    $page = (string)file_get_contents(CORE_ROOT . '/player/world.php');
    $astronomy = (string)file_get_contents(CORE_ROOT . '/player/astronomical-environment.js');
    $assert(str_contains($page, "'systemWorlds' => " . '$worlds'), 'Gameplay boot must include all generated worlds in the current system.');
    foreach (['systemPlanets', 'SYSTEM_PLANET_SCALE', 'const periodHours = 8760 * Math.pow(slot, 1.5)', 'entry.mesh.isVisible = true;', 'systemPlanetCount'] as $needle) {
        $assert(str_contains($astronomy, $needle), 'System sky simulation is missing ' . $needle . '.');
    }
});

$test('astronomical renderer uses planet-fixed world-space moon orbits with elapsed-time motion', static function () use ($assert): void {
    $script = (string)file_get_contents(CORE_ROOT . '/player/astronomical-environment.js');
    $assert(str_contains($script, 'this.moonMeshes = moons.map'), 'Renderer must create a mesh for every generated moon.');
    $assert(str_contains($script, 'moon.periodHours') && str_contains($script, 'moon.phase'), 'Moon orbital period and rotation phase must come from generated world data.');
    $assert(str_contains($script, 'this.orbitForward = BABYLON.Axis.Z.clone()') && str_contains($script, 'this.orbitRight = BABYLON.Axis.X.clone()'), 'Planet-fixed orbital axes must be initialized once and remain independent of later camera movement.');
    $assert(str_contains($script, 'const planetCenter = BABYLON.Vector3.Zero()') && str_contains($script, 'moon.mesh.position.copyFrom(planetCenter.add(moonDirection.scale(orbitalRadius)))'), 'Moon positions must be anchored around the planet world origin.');
    $assert(str_contains($script, 'moon.orbitRadiusWorld = orbitalRadius') && str_contains($script, 'moon.worldOrbitPoints = pathPoints'), 'Moon diagnostics and orbital tubes must use the same world-space orbital radius and points.');
    $assert(!str_contains($script, 'moonSkyBasis') && !str_contains($script, 'cameraForward.subtract(normal.scale'), 'Moon positions must not be recalculated from the live camera heading.');
    $assert(str_contains($script, 'const orbitalCycles = (this.elapsed / Math.max(1, this.dayLengthSeconds))'), 'Moon orbital position must advance from authoritative simulation elapsed time.');
    $assert(str_contains($script, 'material.disableLighting = true') && str_contains($script, 'debugMaterial.emissiveColor = BABYLON.Color3.White().scale(4.0)'), 'Development moons must remain bright and independent of scene lighting.');
    $assert(str_contains($script, 'material.depthFunction = BABYLON.Engine.ALWAYS') && str_contains($script, 'mesh.renderingGroupId = 3') && str_contains($script, 'moon.mesh.isVisible = this.isLunarVisibilityTest ? true : isAboveHorizon'), 'Development moon meshes must render after geometric horizon filtering.');
    $assert(!str_contains($script, 'core-moon-proof-overlay') && !str_contains($script, 'createElementNS(\'http://www.w3.org/2000/svg\''), 'Moon markers must not be rendered as screen-space overlays.');
    $assert(str_contains($script, 'moon.mesh?.dispose()') && str_contains($script, '(moon.orbitMeshes || [moon.orbitMesh]).forEach(mesh => mesh?.dispose())'), 'Moon meshes and all clipped orbital arcs must be disposed with the scene.');
    $assert(str_contains($script, 'worldAnchored: true') && str_contains($script, 'cameraRelative: false'), 'Diagnostics must explicitly report the world-space anchoring model.');
});

$test('planetary jump phases remain active in multiplayer and camera supports sky inspection', static function () use ($assert): void {
    $renderer = (string)file_get_contents(CORE_ROOT . '/player/world.js');
    $astronomy = (string)file_get_contents(CORE_ROOT . '/player/astronomical-environment.js');
    $assert(str_contains($renderer, "jumpAnimationPhase === 'grounded') setAnimation(idleAnimation)") &&
        str_contains($renderer, "'Standing by · server controlled'"),
        'Multiplayer idle playback must be gated by grounded jump state.');
    $assert(str_contains($renderer, "jumpAnimationPhase === 'takeoff' && jumpVelocity <= 0") &&
        str_contains($renderer, "jumpAnimationPhase = 'airborne'"),
        'Takeoff must transition to airborne at the physical jump apex.');
    $assert(str_contains($renderer, 'camera.lowerBetaLimit = .08') &&
        str_contains($renderer, 'camera.upperBetaLimit = Math.PI - .08'),
        'Planetary camera pitch must permit looking toward both sky and ground.');
    $assert(str_contains($renderer, "if (key === 'f')") &&
        str_contains($renderer, "cameraMode === 'close' ? 'firstPerson' : 'thirdPerson'") &&
        str_contains($renderer, "camera._coreFirstPersonMode = mode === 'firstPerson'") &&
        str_contains($renderer, 'camera.radius <= 2.45'),
        'F and wheel zoom must support a distinct first-person camera mode.');
    $assert(str_contains($renderer, "playerRoot.setEnabled(mode !== 'firstPerson'") &&
        str_contains($renderer, "cameraMode === 'firstPerson' ? 1.62 : 1.05"),
        'First-person mode must move the camera to eye height and hide the player model.');
    $assert(str_contains($astronomy, 'this.orbitForward = BABYLON.Axis.Z.clone()') &&
        str_contains($astronomy, 'mesh.alwaysSelectAsActiveMesh = true') &&
        str_contains($astronomy, 'material.disableLighting = true'),
        'Planetary moons must use fixed 3D orbital coordinates and remain visibly rendered at night.');
});

$test('Universe 1337 System 01 supplies deterministic test moons to its previously moonless worlds only', static function () use ($assert, $equal): void {
    $generator = new WorldGenerator();
    $system = [
        'id' => 'sys_7c6a9d2f7d9b50a9',
        'seed' => 'SYS-830B59FB1D8A57BDC294C8D9',
        'constellation_id' => 'con_a6a6cc5189b909c6',
        'constellation_seed' => 'CON-5A47BB6697D4EFE54342A3B6',
        'name' => 'System 01',
    ];
    $worlds = $generator->generateForSystem('1337', $system);
    $again = $generator->generateForSystem('1337', $system);
    $equal($worlds, $again, '1337 test moons must be deterministic.');
    $assert(count($worlds) === 6, 'The 1337 test system should retain its six generated worlds.');
    foreach ($worlds as $world) {
        $assert(count($world['moons'] ?? []) >= 1, $world['name'] . ' should have at least one testable moon.');
    }
    $other = $generator->generateForSystem('not-1337', $system);
    $moonlessOther = array_filter($other, static fn(array $world): bool => ($world['type'] ?? '') === 'asteroid' || ($world['type'] ?? '') === 'desert');
    $assert(count(array_filter($moonlessOther, static fn(array $world): bool => count($world['moons'] ?? []) === 0)) > 0, 'The fixture override must not affect other universe seeds.');
});

$test('Legacy binary systems receive a deterministic presentation companion without rewriting universe records', static function () use ($assert): void {
    $helper = file_get_contents(CORE_ROOT . '/player/astronomical-environment.js');
    $universeGenerator = file_get_contents(CORE_ROOT . '/app/Modules/Universe/UniverseGenerator.php');
    $assert(is_string($helper) && str_contains($helper, 'derived_for_legacy_binary'), 'Legacy binary records must resolve a stable companion for sky rendering.');
    $assert(is_string($universeGenerator) && str_contains($universeGenerator, "'is_binary' => \$classification === 'binary'"), 'The existing binary classification source must remain intact.');
    $assert(!str_contains($helper, 'storage/data/'), 'The renderer must not read or mutate persistent player data.');
});




$test('CORE 0.1.146 uses A/D to turn the character and positions the map at the upper-right', static function () use ($assert): void {
    $world = (string)file_get_contents(CORE_ROOT . '/player/world.js');
    $css = (string)file_get_contents(CORE_ROOT . '/player/world.css');
    $version = (string)file_get_contents(CORE_ROOT . '/config/version.php');
    $assert(str_contains($world, "const turnAmount = (pressed.has('d') || pressed.has('arrowright') ? 1 : 0)") && str_contains($world, "pressed.has('a') || pressed.has('arrowleft') ? 1 : 0"), 'A/D and left/right arrows must produce a signed turn input.');
    $assert(str_contains($world, 'BABYLON.Matrix.RotationAxis(radial, turnAmount * 2.4 * delta)') && str_contains($world, 'TransformNormal(facingDirection, turnRotation)'), 'Turning must rotate the persistent character heading around the local planetary surface normal.');
    $assert(str_contains($world, 'const direction = facingDirection.clone().scaleInPlace(forwardAmount)') && str_contains($world, 'rotateVectorBetweenNormals(facingDirection, previousNormal, movedNormal)'), 'W/S movement must follow the character heading while preserving heading over spherical movement.');
    $assert(str_contains($world, 'A / D or Left / Right') && str_contains($world, 'Turn left / right'), 'The in-game keybinding help must describe turning controls accurately.');
    $assert(str_contains($css, '.world-map-overlay{top:78px!important;right:20px!important;left:auto!important;') && str_contains($css, 'top:68px!important;right:12px!important;'), 'Map HUD must be positioned in the upper-right below the header on desktop and mobile.');
});


$test('CORE 0.1.151 persists the authoritative simulation clock across requests and adjustments', static function () use ($assert): void {
    $directory = sys_get_temp_dir() . '/core-clock-' . bin2hex(random_bytes(6));
    $first = \Core\Simulation\SimulationClock::update($directory, 'universe-test', 'world-test');
    usleep(30000);
    $second = \Core\Simulation\SimulationClock::update($directory, 'universe-test', 'world-test');
    $assert($second['elapsedSeconds'] > $first['elapsedSeconds'], 'Server clock did not advance between requests.');
    $adjusted = \Core\Simulation\SimulationClock::update($directory, 'universe-test', 'world-test', 3600);
    $assert($adjusted['elapsedSeconds'] >= $second['elapsedSeconds'] + 3599, 'Server-side time adjustment was not persisted.');
    $reloaded = \Core\Simulation\SimulationClock::update($directory, 'universe-test', 'world-test');
    $assert($reloaded['elapsedSeconds'] >= $adjusted['elapsedSeconds'], 'Clock regressed after a new request.');
    $otherWorld = \Core\Simulation\SimulationClock::update($directory, 'universe-test', 'another-world');
    $assert($otherWorld['elapsedSeconds'] < $reloaded['elapsedSeconds'], 'Clock state leaked between worlds.');
    foreach (glob($directory . '/simulation_clocks/*') ?: [] as $file) @unlink($file);
    @rmdir($directory . '/simulation_clocks');
    @rmdir($directory);
});

$test('CORE 0.1.151 bootstraps and synchronizes the world clock through the authenticated API', static function () use ($assert): void {
    $page = (string)file_get_contents(CORE_ROOT . '/player/world.php');
    $script = (string)file_get_contents(CORE_ROOT . '/player/astronomical-environment.js');
    $application = (string)file_get_contents(CORE_ROOT . '/app/Application.php');
    $security = (string)file_get_contents(CORE_ROOT . '/app/Http/PlayerApiSecurity.php');
    $assert(str_contains($page, "'clock' => \$initialClock") && str_contains($page, "'csrfToken' =>"), 'World page must bootstrap server clock state and CSRF token.');
    $assert(str_contains($script, 'setInterval(() => this.syncClock(), 15000)') && str_contains($script, 'adjustTimeHours(hours)'), 'Client must periodically synchronize and submit time adjustments.');
    $assert(str_contains($script, 'pendingClockAdjustmentHours += adjustment') && str_contains($script, 'if (!synchronized) this.pendingClockAdjustmentHours += adjustmentHours'), 'Time adjustments must be queued during in-flight sync and retained after a failed request.');
    $assert(str_contains($application, "'/api/world/clock'") && str_contains($security, "'/api/world/clock'"), 'Clock API must be registered and protected by universe access and CSRF policy.');
});

$test('CORE 0.1.151 uses an explicit positive-longitude tangent for initial planetary travel', static function () use ($assert): void {
    $environment = (string)file_get_contents(CORE_ROOT . '/player/planetary-environment.js');
    $world = (string)file_get_contents(CORE_ROOT . '/player/world.js');
    $assert(str_contains($environment, 'new BABYLON.Vector3(-normal.z, 0, normal.x)'), 'East tangent must follow the spherical longitude derivative.');
    $assert(str_contains($environment, 'getEastTangent, getNorthTangent, getLocalMovementComponents, getCoordinates'), 'Planetary tangent basis must be exposed to the player controller.');
    $assert(str_contains($world, 'getEastTangent(playerRoot.position)'), 'Spawn heading must use the explicit east tangent.');
    $assert(str_contains($world, 'facingDirection.clone().scaleInPlace(forwardAmount)'), 'Movement input must not mutate the persistent heading vector.');
    $assert(str_contains($environment, 'Math.atan2(normal.z, normal.x)'), 'Displayed longitude must be derived from the same spherical position.');
});



$test('CORE 0.1.152 maps world-space movement onto server east/north axes', static function () use ($assert): void {
    $environment = (string)file_get_contents(CORE_ROOT . '/player/planetary-environment.js');
    $world = (string)file_get_contents(CORE_ROOT . '/player/world.js');
    $protocol = (string)file_get_contents(CORE_ROOT . '/multiplayer/src/contracts/MovementProtocol.ts');
    $assert(str_contains($environment, 'BABYLON.Vector3.Dot(direction, east)') && str_contains($environment, 'BABYLON.Vector3.Dot(direction, north)'), 'World-space movement must be projected onto local east and north tangents.');
    $assert(str_contains($world, 'getLocalMovementComponents(groundPosition, direction)'), 'Multiplayer input must use planetary tangent components rather than world X/Z.');
    $assert(str_contains($world, 'inputStrafe = Math.abs(localMovement.east)') && str_contains($world, 'inputForward = Math.abs(localMovement.north)'), 'East must map to server strafe/X and north to server forward/Z.');
    $assert(str_contains($protocol, 'let x = strafe;') && str_contains($protocol, 'let z = forward;'), 'Server map axes must remain X=east and Z=north.');
});


$test('CORE 0.1.156 connects GLB jump animation phases', static function () use ($assert): void {
    $world = (string)file_get_contents(CORE_ROOT . '/player/world.js');
    $assert(str_contains($world, "if (key === ' ')") && str_contains($world, 'beginJumpAnimation()'), 'Spacebar must initiate the jump animation state.');
    $assert(str_contains($world, "findClip('Jump_Start')") && str_contains($world, "findClip('Jump_air', 'Jump_2')") && str_contains($world, "findClip('Jump_Land')"), 'The GLB takeoff, airborne, and landing clips must be connected.');
    $assert(str_contains($world, 'beginLandingAnimation()') && str_contains($world, 'jumpVelocity -= gravityAcceleration * delta'), 'Landing animation and spherical gravity must remain connected.');
});

$test('CORE 0.1.156 retains player-facing world environmental details', static function () use ($assert): void {
    $world = (string)file_get_contents(CORE_ROOT . '/player/world.js');
    $assert(str_contains($world, 'showEnvironmentalBriefing') && str_contains($world, "key === 'v'"), 'World environment briefing must be accessible in gameplay.');
    $assert(str_contains($world, 'Atmosphere') && str_contains($world, 'Axial tilt') && str_contains($world, 'Hydrographics'), 'Briefing must expose available environmental properties.');
});

$test('CORE 0.1.157 renders a deterministic screen-space night sky', static function () use ($assert): void {
    $astronomy = (string)file_get_contents(CORE_ROOT . '/player/astronomical-environment.js');
    $assert(str_contains($astronomy, 'new BABYLON.DynamicTexture') && str_contains($astronomy, 'core-night-sky-stars'), 'Stars must use a dedicated screen-space background layer.');
    $assert(str_contains($astronomy, 'for (let i = 0; i < 1800; i++)') && str_contains($astronomy, 'starTexture.update(false)'), 'The deterministic star texture must be populated and uploaded.');
    $assert(str_contains($astronomy, 'this.starLayer.alpha = clamp(1 - atmosphericFade'), 'The background layer must fade in according to nighttime illumination.');
    $assert(str_contains($astronomy, 'this.starLayer?.dispose()') && str_contains($astronomy, 'this.starTexture?.dispose()'), 'Star layer and texture must be disposed cleanly.');
});

$test('CORE 0.1.158 restores reliable GLB animation-group playback', static function () use ($assert): void {
    $world = (string)file_get_contents(CORE_ROOT . '/player/world.js');
    $assert(!str_contains($world, 'group.enableBlending = true') && !str_contains($world, 'group.blendingSpeed = 0.08'), 'The unverified per-group blending regression must remain disabled.');
    $assert(str_contains($world, 'activeAnimation.stop();') && str_contains($world, 'activeAnimation.start(true, 1'), 'Locomotion must use the established AnimationGroup stop/start path.');
    $assert(str_contains($world, 'group.start(false, 1, group.from, group.to, false)') && str_contains($world, "findClip('Jump_Start')") && str_contains($world, "findClip('Jump_air', 'Jump_2')") && str_contains($world, "findClip('Jump_Land')"), 'Takeoff, airborne, and landing clips must retain their established one-shot playback sequence.');
    $assert(str_contains($world, 'beginJumpAnimation()') && str_contains($world, 'beginLandingAnimation()'), 'Jump animation sequencing must remain connected to the jump physics state machine.');
});


$test('CORE 0.1.156 increases deterministic planetary terrain relief and color separation', static function () use ($assert): void {
    $environment = (string)file_get_contents(CORE_ROOT . '/player/planetary-environment.js');
    $assert(str_contains($environment, 'elevation_scale_km') && str_contains($environment, 'mountain_frequency'), 'Land elevation must use persistent relief and mountain parameters.');
    $assert(str_contains($environment, 'ocean_depth_scale_km'), 'Ocean depth must have visible deterministic variation.');
    $assert(str_contains($environment, 'initializeTerrain()') && str_contains($environment, 'terrainSeedNumber'), 'Terrain variation must remain deterministic from the persisted terrain seed.');
});



$test('CORE 0.1.167 planetary moon arcs use stable planet-space coordinates rather than camera-following sky coordinates', static function () use ($assert): void {
    $astronomy = (string)file_get_contents(CORE_ROOT . '/player/astronomical-environment.js');
    $assert(str_contains($astronomy, 'this.orbitForward = BABYLON.Axis.Z.clone()'), 'A planet-fixed orbital reference direction must be established from the planet coordinate axes.');
    $assert(str_contains($astronomy, 'const planetCenter = BABYLON.Vector3.Zero()'), 'The parent planet center must be the orbital anchor.');
    $assert(str_contains($astronomy, 'const equatorialU = referenceU.scale(Math.cos(node))') && str_contains($astronomy, 'const equatorialV = BABYLON.Vector3.Cross(planetPole, equatorialU)') && str_contains($astronomy, 'equatorialV.scale(Math.cos(inclination))'), 'Orbital planes must be derived from the tilted planetary equator, ascending node, and inclination.');
    $assert(!str_contains($astronomy, 'cameraForward') && !str_contains($astronomy, 'moonSkyBasis'), 'Live camera direction must not determine moon world positions.');
    $assert(str_contains($astronomy, 'aboveLocalHorizon'), 'Diagnostics must continue reporting geometric horizon clearance.');
    $assert(str_contains($astronomy, 'screenProjection'), 'Diagnostics must continue reporting the camera projection of actual moon meshes.');
});

$test('CORE 0.1.168 makes moons self-lit and checks actual scene render participation', static function () use ($assert): void {
    $astronomy = (string)file_get_contents(CORE_ROOT . '/player/astronomical-environment.js');
    $assert(str_contains($astronomy, 'material.disableLighting = true'), 'Moon material must not depend on local terrain lighting.');
    $assert(str_contains($astronomy, 'mesh.renderingGroupId = 3'), 'Moon meshes must render in the final 3D rendering group.');
    $assert(str_contains($astronomy, 'sceneContainsMoons'), 'Diagnostics must confirm moon meshes belong to the active scene.');
    $assert(str_contains($astronomy, 'activeThisFrame'), 'Diagnostics must report whether Babylon selected each moon for the frame.');
    $assert(str_contains($astronomy, 'materialReady'), 'Diagnostics must report material readiness.');
    $assert(str_contains($astronomy, 'horizonDot'), 'Diagnostics must calculate geometric horizon clearance against the planetary sphere.');
    $assert(str_contains($astronomy, 'aboveLocalHorizon'), 'Diagnostics must report whether each world-space moon is above the local horizon.');
});

$test('CORE 0.1.169 prevents terrain depth from suppressing geometrically clear moon meshes', static function () use ($assert): void {
    $astronomy = (string)file_get_contents(CORE_ROOT . '/player/astronomical-environment.js');
    $assert(str_contains($astronomy, 'material.depthFunction = BABYLON.Engine.ALWAYS') && str_contains($astronomy, 'moon.mesh.isVisible = this.isLunarVisibilityTest ? true : isAboveHorizon'), 'Moon discs must use geometric horizon filtering rather than fragile terrain depth testing.');
    $assert(str_contains($astronomy, 'material.disableDepthWrite = true'), 'Moon rendering must not overwrite the scene depth buffer.');
    $assert(str_contains($astronomy, 'mesh.renderingGroupId = 3'), 'Moon meshes must render after terrain and ordinary world geometry.');
    $assert(str_contains($astronomy, 'material.alpha = 1') && str_contains($astronomy, 'BABYLON.Material.MATERIAL_OPAQUE'), 'Moon materials must remain fully opaque.');
    $assert(str_contains($astronomy, 'materialDepthFunction') && str_contains($astronomy, 'geometryPresent'), 'Diagnostics must expose material depth state and actual mesh geometry.');
    $assert(str_contains($astronomy, 'activeCameraIsSceneCamera'), 'Diagnostics must verify the camera used for projection is the active scene camera.');
});



$test('CORE 0.1.191 keeps celestial diagnostics opt-in and exposes independent developer controls', static function () use ($assert): void {
    $astronomy = (string)file_get_contents(CORE_ROOT . '/player/astronomical-environment.js');
    $assert(str_contains($astronomy, 'MOON_DEBUG_VISUALS_DEFAULT = false'), 'Celestial diagnostics must be disabled by default for normal presentation.');
    $assert(str_contains($astronomy, 'setDeveloperOption(option, enabled)'), 'Developer options must be independently controllable.');
    $assert(str_contains($astronomy, 'physicalRadiusKm') && str_contains($astronomy, 'angularDiameter'), 'System-world apparent size must derive from physical radius and observer distance.');
    $assert(str_contains($astronomy, 'core-moon-debug-material-') && str_contains($astronomy, 'debugMaterial.emissiveColor'), 'Each moon must have a distinct bright emissive diagnostic appearance.');
    $assert(str_contains($astronomy, 'core-moon-orbit-') && str_contains($astronomy, 'BABYLON.MeshBuilder.CreateTube'), 'Each moon must have a visible orbital-path mesh.');
    $assert(str_contains($astronomy, 'setMoonDebugVisuals(enabled)'), 'Moon markers and orbital paths must have a single switch for a future settings control.');
    $assert(str_contains($astronomy, 'moonDebugVisuals: this.moonDebugVisuals') && str_contains($astronomy, 'orbitPathVisible'), 'Runtime diagnostics must report debug marker and orbit-path state.');
});



$test('CORE 0.1.182 renders white moon diagnostics as real planet-anchored 3D objects', static function () use ($assert): void {
    $astronomy = (string)file_get_contents(CORE_ROOT . '/player/astronomical-environment.js');
    $assert(str_contains($astronomy, 'BABYLON.Color3.White().scale(4.0)'), 'Moon debug meshes must retain strong white emissive output.');
    $assert(str_contains($astronomy, 'BABYLON.Color3.White().scale(4.0)'), 'Orbital tubes must retain bright white emissive output.');
    $assert(!str_contains($astronomy, 'core-moon-proof-overlay') && !str_contains($astronomy, 'moonProofSvg'), 'The camera-fixed SVG proof overlay must be removed.');
    $assert(str_contains($astronomy, 'moon.mesh.position.copyFrom(planetCenter.add(moonDirection.scale(orbitalRadius)))'), 'Moon positions must be generated in planet-centered 3D world space.');
    $assert(str_contains($astronomy, 'BABYLON.MeshBuilder.CreateTube') && str_contains($astronomy, 'moon.worldOrbitPoints'), 'Visible orbital paths must be actual 3D tube meshes.');
    $assert(str_contains($astronomy, 'worldAnchored: true') && str_contains($astronomy, 'cameraRelative: false'), 'Diagnostics must distinguish world-anchored moons from camera-relative representations.');
});

$test('CORE 0.1.177 permanently removes the Constellation View grid', static function () use ($assert): void {
    $player = (string)file_get_contents(CORE_ROOT . '/player/player.js');
    $assert(!str_contains($player, "CreateGround('map-grid'"), 'Constellation View must not create a grid ground mesh.');
    $assert(!str_contains($player, "m.name==='map-grid'"), 'Constellation View cleanup must not depend on a grid mesh existing.');
});

$test('CORE 0.1.177 renders moons around their parent planets in System View', static function () use ($assert): void {
    $system = (string)file_get_contents(CORE_ROOT . '/player/system-view.js');
    $assert(str_contains($system, 'const moons = Array.isArray(world.moons) ? world.moons : []'), 'System View must consume each generated world moon list.');
    $assert(str_contains($system, 'moon-orbit-') && str_contains($system, 'satelliteOrbitRadius') && str_contains($system, 'satelliteOrbit.parent = planet'), 'System View must draw a moon orbit attached to its parent planet.');
    $assert(str_contains($system, 'view.moonEntries.forEach'), 'System View must advance moons around their parent planets.');
    $assert(str_contains($system, 'entry.parent.position.x') && str_contains($system, 'entry.parent.position.z'), 'Moon positions must follow their parent planet as it moves.');
});

$test('CORE 0.1.177 initializes the planetary moon orbit tube with the same topology used during updates', static function () use ($assert): void {
    $astronomy = (string)file_get_contents(CORE_ROOT . '/player/astronomical-environment.js');
    $assert(str_contains($astronomy, 'MOON_ORBIT_PATH_SEGMENTS = 64'), 'Moon orbit path resolution must be defined once.');
    $assert(str_contains($astronomy, 'length: MOON_ORBIT_PATH_SEGMENTS * 4 + 1'), 'Initial moon tube path must contain 65 points.');
    $assert(str_contains($astronomy, 'step <= pathSegments'), 'Updated moon tube path must preserve the same point count.');
});


$test('CORE 0.1.178 captures browser and Babylon runtime diagnostics', static function () use ($assert): void {
    $client = (string)file_get_contents(CORE_ROOT . '/player/diagnostics.js');
    $game = (string)file_get_contents(CORE_ROOT . '/player/game.php');
    foreach (['unhandledrejection', 'webglcontextlost', 'console_error', 'snapshotScene', 'activeMeshNames', 'camera', 'webgl', 'download()', 'submit()'] as $needle) {
        $assert(str_contains($client, $needle), 'Browser diagnostics are missing ' . $needle . '.');
    }
    $assert(str_contains($client, 'screenProjection') && str_contains($client, 'inCameraFrustum') && str_contains($client, 'emissiveColor'), 'Scene diagnostics must expose mesh bounds, camera projection, and material brightness for sky-obstruction analysis.');
    $assert(str_contains($game, '/diagnostics.js?v=') && str_contains($game, 'diagnosticsDownload') && str_contains($game, 'diagnosticsSubmit'), 'Gameplay must load diagnostics and expose download/submit actions.');
    $world = (string)file_get_contents(CORE_ROOT . '/player/world.php');
    $assert(str_contains($world, '/diagnostics.js?v=') && str_contains($world, 'id="diagnosticsDownload"') && str_contains($world, 'id="diagnosticsSubmit"'), 'Planetary test world must load diagnostics and expose report actions.');
});

$test('CORE 0.1.178 registers all primary Babylon scenes with diagnostics', static function () use ($assert): void {
    foreach (['player/player.js' => 'universe-constellation', 'player/system-view.js' => 'system-view', 'player/world.js' => 'planetary-world'] as $file => $label) {
        $source = (string)file_get_contents(CORE_ROOT . '/' . $file);
        $assert(str_contains($source, "captureScene") && str_contains($source, $label), $file . ' is not registered with browser diagnostics.');
    }
});

$test('CORE 0.1.178 stores authenticated bounded diagnostic reports', static function () use ($assert): void {
    $endpoint = (string)file_get_contents(CORE_ROOT . '/api/diagnostics-report.php');
    $logger = (string)file_get_contents(CORE_ROOT . '/app/Core/Logger.php');
    foreach (['currentUserId', 'hash_equals', '262144', 'core_csrf', 'storage/diagnostics', 'report_id'] as $needle) {
        $assert(str_contains($endpoint, $needle), 'Diagnostic endpoint is missing security/storage requirement: ' . $needle . '.');
    }
    $assert(str_contains($endpoint, 'array_slice($existing, 0') && str_contains($logger, '$maxBytes = 5 * 1024 * 1024'), 'Diagnostic reports and server logs must have bounded retention.');
    $assert(str_contains($logger, 'request_id') && str_contains($logger, 'core_version') && str_contains($logger, 'session_fingerprint'), 'Server log entries must include request correlation and CORE version context.');
});


$test('celestial sky markers use visible luminous colors', static function () use ($assert): void {
    $sky = (string)file_get_contents(CORE_ROOT . '/player/astronomical-environment.js');
    $assert(str_contains($sky, "BABYLON.Color3.White().scale(4.0)"), 'Moon orbit guides must use a luminous pale color.');
    $assert(str_contains($sky, "type.includes('ocean')") && str_contains($sky, "type.includes('desert')")
        && str_contains($sky, "type.includes('volcan')") && str_contains($sky, "type.includes('exotic')"),
        'System worlds must retain distinct type-based colors.');
    $assert(str_contains($sky, 'color.scale(4.5)'), 'System worlds must use stronger emissive color.');
    $assert(str_contains($sky, 'BABYLON.Material.MATERIAL_OPAQUE'), 'Celestial markers must render as opaque materials.');
});

$test('CORE 0.1.187 renders celestial guides in white and respects the planetary horizon', static function () use ($assert): void {
    $script = file_get_contents(CORE_ROOT . '/player/astronomical-environment.js');
    $assert(str_contains($script, 'moon.orbitMaterial.diffuseColor = BABYLON.Color3.White()'), 'Lunar orbit guides must be neutral white.');
    $assert(str_contains($script, 'moon.orbitMaterial.emissiveColor = BABYLON.Color3.White().scale(4.0)'), 'Lunar orbit guides must have strong white emissive output.');
    $assert(str_contains($script, 'moon.orbitMaterial.depthFunction = BABYLON.Engine.ALWAYS') && str_contains($script, 'instance: moon.orbitMesh'), 'Orbit guides must be geometrically clipped to the above-horizon portions before rendering.');
    $assert(str_contains($script, 'scene.setRenderingAutoClearDepthStencil(3, false)'), 'Celestial rendering must preserve the terrain depth buffer.');
    $assert(str_contains($script, 'mesh.billboardMode = BABYLON.Mesh.BILLBOARDMODE_ALL'), 'Distant system worlds must remain legible as sky discs.');
    $assert(str_contains($script, 'const angularDiameter = 2 * Math.atan(entry.physicalRadiusScene / distance)'), 'System-world markers must use angular size from physical radius and observer distance.');
    $assert(str_contains($script, 'const desiredPixels = clamp(pixelDiameter, 2.5, 220)'), 'System-world visibility floor must be restrained and must not normalize all world sizes.');
    $assert(str_contains($script, 'entry.mesh.isVisible = true;'), 'System worlds must use the geometric horizon visibility test.');
    $assert(str_contains($script, '5.61 + index * .09'), 'The test moons must begin in the fixed forward-facing planet-space orbital arc.');
});

$test('CORE 0.1.189 restores persistent full 3D lunar orbit tubes and bright white rendering', static function () use ($assert): void {
    $script = (string)file_get_contents(CORE_ROOT . '/player/astronomical-environment.js');
    $assert(str_contains($script, 'moon.orbitMaterial.emissiveColor = BABYLON.Color3.White().scale(4.0)'), 'Orbit paths must be bright neutral white.');
    $assert(str_contains($script, 'instance: moon.orbitMesh'), 'Orbit geometry must update the persistent tube instead of disposing and recreating clipped fragments.');
    $assert(!str_contains($script, 'const visibleArcs = []'), 'The unstable per-frame horizon-fragment clipping implementation must be removed.');
    $assert(str_contains($script, 'moon.visibleOrbitArcCount = this.moonDebugVisuals ? 1 : 0'), 'Diagnostics must report the persistent full orbit path.');
});

$test('CORE 0.1.189 keeps colored system worlds and test moons available to sky inspection', static function () use ($assert): void {
    $script = (string)file_get_contents(CORE_ROOT . '/player/astronomical-environment.js');
    $assert(str_contains($script, 'color.scale(4.5)'), 'System-world type colors must have stronger emissive output.');
    $assert(str_contains($script, 'entry.mesh.isVisible = true;') && str_contains($script, 'entry.mesh.visibility = 1;'), 'System-world markers must not be silently removed by the simplified horizon test.');
    $assert(str_contains($script, 'this.isLunarVisibilityTest ? true : isAboveHorizon'), 'Both test moons must remain available for the documented visibility test.');
    $assert(str_contains($script, 'camera.clone().add(direction.scale(SKY_RADIUS))'), 'Celestial marker placement must not mutate the active camera position.');
    $assert(str_contains($script, 'center.clone().add(direction.scale(SKY_RADIUS))'), 'Stellar marker placement must not mutate the observer position.');
});

$test('Planetary moon orbit tubes are not rebuilt every render frame', static function () use ($assert): void {
    $astronomy = (string)file_get_contents(CORE_ROOT . '/player/astronomical-environment.js');
    $assert(str_contains($astronomy, 'orbitGeometryInitialized: false'), 'Moon orbit geometry initialization state is missing.');
    $assert(str_contains($astronomy, 'if (moon.orbitMesh && !moon.orbitGeometryInitialized)'), 'Orbit tube geometry must be created only once after orbital parameters are available.');
    $assert(str_contains($astronomy, 'moon.orbitGeometryInitialized = true;'), 'Orbit tube initialization must be marked complete.');
});

$test('CORE 0.1.190 keeps celestial materials independent of planetary scene fog', static function () use ($assert): void {
    $script = (string)file_get_contents(CORE_ROOT . '/player/astronomical-environment.js');
    foreach ([
        'body.material.fogEnabled = false',
        'this.centralMaterial.fogEnabled = false',
        'material.fogEnabled = false',
        'debugMaterial.fogEnabled = false',
        'moon.orbitMaterial.fogEnabled = false'
    ] as $needle) {
        $assert(str_contains($script, $needle), "Celestial rendering must explicitly disable scene fog: {$needle}.");
    }
    $assert(str_contains($script, 'materialFogEnabled:'), 'Runtime diagnostics must report whether fog is enabled on a moon material.');
});

$test('CORE 0.1.205 derives planetary atmospheric optics from the authoritative UWP atmosphere code', static function () use ($assert): void {
    $script = (string)file_get_contents(CORE_ROOT . '/player/astronomical-environment.js');
    foreach (['function atmosphereProfile(world)', 'world?.atmosphere', 'scattering:', 'starFade:', 'this.atmosphere = atmosphereProfile(world)', 'scene.fogDensity = this.atmosphere.fog', 'const atmosphericFade =', 'body.material.fogEnabled = false'] as $needle) {
        $assert(str_contains($script, $needle), "UWP-driven atmosphere rendering is missing requirement: {$needle}.");
    }
    $assert(str_contains($script, 'A-F remain conservative') && str_contains($script, 'explicit composition data'), 'Unspecified exotic atmosphere composition must not be invented from UWP alone.');
});

echo "\nCORE QA summary: {$passed} passed, {$failed} failed\n";

if ($failed > 0) {
    foreach ($failures as [$name, $message]) echo " - {$name}: {$message}\\n";
    exit(1);
}
exit(0);
