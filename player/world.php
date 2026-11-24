<?php
declare(strict_types=1);

$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/player/world.php')), '/');
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params(['httponly' => true, 'secure' => $secure, 'samesite' => 'Lax', 'path' => '/']);
    session_start();
}
if (empty($_SESSION['core_csrf'])) $_SESSION['core_csrf'] = bin2hex(random_bytes(32));
if (empty($_SESSION['core_user']['id'])) {
    header('Location: ' . $base . '/account.php');
    exit;
}

$root = dirname(__DIR__);
define('CORE_ROOT', $root);
require $root . '/bootstrap.php';
$config = require $root . '/config/app.php';
$storage = new \Core\Storage\JsonStorage((string)$config['storage']['path']);
$accountId = (string)$_SESSION['core_user']['id'];
$characterId = trim((string)($_GET['character_id'] ?? ''));
$systemId = trim((string)($_GET['system_id'] ?? ''));
$worldId = trim((string)($_GET['world_id'] ?? ''));

$character = $characterId !== '' ? $storage->read('characters', $characterId) : null;
if (!is_array($character) || ($character['owner_account_id'] ?? null) !== $accountId) {
    http_response_code(404);
    exit('Character not found for this account.');
}
if (empty($character['universe_id'])) {
    header('Location: ' . $base . '/selection.php?error=unassigned');
    exit;
}
try {
    \Core\Modules\Characters\CharacterLifecycleGuard::assertCanPerformGameplayAction($character);
} catch (Throwable $e) {
    http_response_code(409);
    exit(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
$currentSystemId = \Core\Modules\Characters\CharacterUniverseContext::activeSystemId($character);
if ($systemId === '' || $systemId !== $currentSystemId || $worldId === '') {
    http_response_code(400);
    exit('Select a world in the character current star system before entering the test environment.');
}
$activeUniverseId = \Core\Modules\Characters\CharacterUniverseContext::activeUniverseId($character);
if ($activeUniverseId !== (string)$character['universe_id'] && !\Core\Http\PlayerApiSecurity::canAccessUniverse($storage, $activeUniverseId, $accountId)) { http_response_code(403); exit('Shared universe access is no longer valid. Return to Character Selection.'); }
$universe = $storage->read('universes', $activeUniverseId);
if (!is_array($universe) || ($universe['id'] ?? null) !== $activeUniverseId) {
    http_response_code(404);
    exit('The assigned universe could not be loaded.');
}
try {
    \Core\Modules\Universe\UniverseLifecycleGuard::assertCanPerformGameplayAction($universe);
} catch (Throwable $e) {
    http_response_code(409);
    exit(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
$system = null;
foreach (($universe['baseline']['systems'] ?? []) as $candidate) {
    if (is_array($candidate) && (string)($candidate['id'] ?? '') === $systemId) {
        $system = $candidate;
        break;
    }
}
if (!is_array($system)) {
    http_response_code(404);
    exit('The selected system does not belong to the assigned universe.');
}
$seed = (string)($universe['seed'] ?? $universe['baseline']['seed'] ?? '');
$worlds = (new \Core\Modules\Universe\WorldGenerator())->generateForSystem($seed, $system);
$world = null;
foreach ($worlds as $candidate) {
    if ((string)($candidate['id'] ?? '') === $worldId) {
        $world = $candidate;
        break;
    }
}
if (!is_array($world)) {
    http_response_code(404);
    exit('The selected world could not be found in the generated system data.');
}
$constellation = null;
foreach (($universe['baseline']['constellations'] ?? []) as $candidate) {
    if (is_array($candidate) && (string)($candidate['id'] ?? '') === (string)($system['constellation_id'] ?? '')) {
        $constellation = $candidate;
        break;
    }
}
$constellationName = (string)($constellation['name'] ?? $system['constellation_id'] ?? 'Unknown constellation');
$universeName = (string)($universe['name'] ?? ('Universe ' . substr((string)($universe['seed'] ?? $universe['id']), 0, 18)));
// Developer diagnostics are available only on a development installation
// accessed from a trusted private/local network address.
$remoteAddress = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$isDeveloperSession = ($config['app']['environment'] ?? 'production') === 'development'
    && (in_array($remoteAddress, ['127.0.0.1', '::1'], true)
        || preg_match('/^(10\\.|192\\.168\\.|172\\.(1[6-9]|2[0-9]|3[01])\\.)/', $remoteAddress) === 1
        || str_starts_with(strtolower($remoteAddress), 'fc')
        || str_starts_with(strtolower($remoteAddress), 'fd'));
$releaseVersion = require $root . '/config/version.php';
$initialClock = \Core\Simulation\SimulationClock::update((string)$config['storage']['path'], $activeUniverseId, $worldId);
$urlRoot = rtrim(str_replace('\\', '/', dirname($base)), '/');
$multiplayerPublicUrl = trim((string)(getenv('CORE_MULTIPLAYER_PUBLIC_URL') ?: ''));
if ($multiplayerPublicUrl !== '') {
    $parsedMultiplayerUrl = parse_url($multiplayerPublicUrl);
    if (!is_array($parsedMultiplayerUrl) || !in_array(strtolower((string)($parsedMultiplayerUrl['scheme'] ?? '')), ['ws', 'wss', 'http', 'https'], true)
        || empty($parsedMultiplayerUrl['host']) || isset($parsedMultiplayerUrl['user']) || isset($parsedMultiplayerUrl['pass'])
        || isset($parsedMultiplayerUrl['query']) || isset($parsedMultiplayerUrl['fragment'])) {
        $multiplayerPublicUrl = '';
    }
}
function world_h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$boot = [
    'character' => [
        'id' => (string)$character['id'],
        'name' => (string)($character['name'] ?? 'Unknown character'),
        'appearance' => is_array($character['appearance'] ?? null) ? $character['appearance'] : [],
        'species' => (string)($character['species_name'] ?? $character['species'] ?? 'Not specified'),
        'role' => (string)($character['role_name'] ?? $character['role'] ?? 'Not specified'),
        'age' => $character['age'] ?? null,
        'level' => $character['level'] ?? null,
        'inventory' => is_array($character['inventory'] ?? null) ? $character['inventory'] : [],
    ],
    'universeId' => (string)$universe['id'],
    'apiRoot' => $urlRoot,
    'csrfToken' => (string)($_SESSION['core_csrf'] ?? ''),
    'clock' => $initialClock,
    'multiplayerUrl' => $multiplayerPublicUrl,
    'universe' => ['id' => $activeUniverseId, 'name' => $universeName, 'seed' => $seed],
    'constellation' => ['id' => (string)($system['constellation_id'] ?? ''), 'name' => $constellationName],
    'system' => array_merge($system, ['id' => $systemId, 'name' => (string)($system['name'] ?? $systemId)]),
    'world' => $world,
    'systemWorlds' => $worlds,
    'developerMode' => $isDeveloperSession,
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?=world_h($world['name'] ?? 'Test World')?> · CORE</title>
  <link rel="stylesheet" href="<?=world_h($base)?>/player.css?v=<?=rawurlencode((string)$releaseVersion)?>">
  <link rel="stylesheet" href="<?=world_h($base)?>/world.css?v=<?=rawurlencode((string)$releaseVersion)?>">
  <link rel="stylesheet" href="<?=world_h($base)?>/../assets/borderless.css?v=<?=rawurlencode((string)$releaseVersion)?>">
  <script src="https://cdn.babylonjs.com/babylon.js"></script>
  <script src="https://cdn.babylonjs.com/loaders/babylonjs.loaders.min.js"></script>
  <script defer src="https://unpkg.com/colyseus.js@0.16.22/dist/colyseus.js" onerror="window.CORE_COLYSEUS_SDK_ERROR=true"></script>
  <script>window.CORE_WORLD_BOOT = <?=json_encode($boot, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES)?>;</script>
  <script defer src="<?=world_h($base)?>/diagnostics.js?v=<?=rawurlencode((string)$releaseVersion)?>"></script>
  <script defer src="<?=world_h($base)?>/appearance.js?v=<?=rawurlencode((string)$releaseVersion)?>"></script>
  <script defer src="<?=world_h($base)?>/planetary-environment.js?v=<?=rawurlencode((string)$releaseVersion)?>"></script>
  <script defer src="<?=world_h($base)?>/astronomical-environment.js?v=<?=rawurlencode((string)$releaseVersion)?>"></script>
  <script defer src="<?=world_h($base)?>/world.js?v=<?=rawurlencode((string)$releaseVersion)?>"></script>
</head>
<body class="world-page" data-csrf-token="<?=world_h((string)($_SESSION['core_csrf'] ?? ''))?>" data-api-root="<?=world_h($base)?>" data-character-id="<?=world_h($characterId)?>">
  <header class="topbar world-topbar">
    <a class="brand" href="<?=world_h($base)?>/selection.php"><span class="brand-mark">C</span><span>CORE <small>TEST ENVIRONMENT</small></span></a>
    <div class="world-topbar-info"><strong><?=world_h($world['name'] ?? 'Unknown world')?></strong><span><?=world_h($boot['universe']['name'])?> · <?=world_h($boot['constellation']['name'])?> · <?=world_h($boot['system']['name'])?></span></div>
    <a class="character-selection-link" href="<?=world_h($base)?>/game.php?character_id=<?=rawurlencode($characterId)?>&amp;system_id=<?=rawurlencode($systemId)?>">← System Overview</a>
  </header>
  <main class="world-stage">
    <canvas id="worldCanvas" aria-label="Interactive test world"></canvas>
    <div id="worldLocationIndicator" class="world-location-indicator" aria-live="off"><div class="world-location-coordinates" id="worldCoordinates">0.000° · 0.000°</div><div class="world-local-time"><span id="worldDayIcon" aria-hidden="true">☀</span><strong id="worldLocalTime">00:00:00</strong><span id="worldDayCount">Day 1</span></div></div>
    <div id="worldLoading" class="world-loading" role="status">Preparing test world and spawning <?=world_h($character['name'] ?? 'character')?>…</div>
    <div class="world-status-strip"><span id="worldMotionStatus">Standing by</span><span id="worldMultiplayerStatus">Checking shared-world session…</span></div>
    <section id="worldOverlay" class="world-overlay hidden" aria-modal="true" role="dialog" aria-labelledby="worldOverlayTitle">
      <div class="world-overlay-backdrop" data-overlay-close></div>
      <div class="world-overlay-panel"><div class="world-overlay-heading"><div><span class="eyebrow">CORE · TEST ENVIRONMENT</span><h2 id="worldOverlayTitle">Menu</h2></div><button type="button" class="world-overlay-close" data-overlay-close aria-label="Close">×</button></div><div id="worldOverlayContent" class="world-overlay-content"></div></div>
    </section>
    <aside id="worldMapOverlay" class="world-map-overlay hidden" aria-label="Planetary map" aria-live="off">
      <div id="worldMapCanvas" class="world-map-canvas" aria-label="Planetary map"></div>
      <div id="worldMapLocation" class="world-map-location">Current location: loading…</div>
    </aside>
    <div id="worldError" class="world-error hidden" role="alert"></div>
    <aside id="coreWorldDiagnostics" class="core-world-diagnostics" aria-label="CORE Diagnostics"><details><summary>Diagnostics</summary><div class="core-world-diagnostics-content"><div><button type="button" id="diagnosticsDownload" class="button-secondary">Download JSON</button><button type="button" id="diagnosticsSubmit" class="button-secondary">Submit</button></div><small id="diagnosticsStatus" role="status" aria-live="polite">Browser and planetary scene capture is active.</small></div></details></aside>
  </main>
</body>
</html>
