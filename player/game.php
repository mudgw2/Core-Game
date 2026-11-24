<?php
declare(strict_types=1);
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/player/game.php')), '/');
$urlRoot = rtrim(str_replace('\\', '/', dirname($base)), '/');
if (session_status() !== PHP_SESSION_ACTIVE) { $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'); session_set_cookie_params(['httponly'=>true,'secure'=>$secure,'samesite'=>'Lax','path'=>'/']); session_start(); }
if (empty($_SESSION['core_csrf'])) $_SESSION['core_csrf'] = bin2hex(random_bytes(32));
if (empty($_SESSION['core_user']['id'])) { header('Location: ' . $base . '/account.php'); exit; }
$root = dirname(__DIR__); define('CORE_ROOT', $root); require $root . '/bootstrap.php'; $cfg = require $root . '/config/app.php'; $portalStorage = new \Core\Storage\JsonStorage((string)$cfg['storage']['path']); $selectedCharacterId = (string)($_GET['character_id'] ?? '');
if ($selectedCharacterId === '') { header('Location: ' . $base . '/selection.php'); exit; }
$selectedCharacter = $portalStorage->read('characters', $selectedCharacterId);
if (!$selectedCharacter || ($selectedCharacter['owner_account_id'] ?? null) !== (string)$_SESSION['core_user']['id']) { http_response_code(404); exit('Character not found for this account.'); }
if (empty($selectedCharacter['universe_id'])) { header('Location: ' . $base . '/selection.php?error=unassigned'); exit; }
$activeUniverseId = \Core\Modules\Characters\CharacterUniverseContext::activeUniverseId($selectedCharacter);
if ($activeUniverseId !== (string)$selectedCharacter['universe_id'] && !\Core\Http\PlayerApiSecurity::canAccessUniverse($portalStorage, $activeUniverseId, (string)$_SESSION['core_user']['id'])) { http_response_code(403); exit('Shared universe access is no longer valid. Return to Character Selection.'); }
$assignedUniverse = $portalStorage->read('universes', $activeUniverseId);
$canShareActiveUniverse = \Core\Http\PlayerApiSecurity::ownsUniverse($portalStorage, $activeUniverseId, (string)$_SESSION['core_user']['id']);
if (is_array($assignedUniverse)) {
    $assignedUniverse = (new \Core\Modules\Universe\UniverseService($portalStorage))->ensureCurrentHierarchy($activeUniverseId);
    $selectedCharacter = $portalStorage->read('characters', $selectedCharacterId) ?? $selectedCharacter;
}
if (!$assignedUniverse || !is_array($assignedUniverse['baseline']['systems'] ?? null)) { http_response_code(409); exit('The assigned universe could not be loaded. Return to Character Selection and verify the universe association.'); }
$assignedSeed = (string)($assignedUniverse['seed'] ?? $assignedUniverse['baseline']['seed'] ?? '');
$worldGenerator = new \Core\Modules\Universe\WorldGenerator();
$playerUniverse = $assignedUniverse;
foreach ($playerUniverse['baseline']['systems'] as &$systemRecord) {
    if (is_array($systemRecord)) {
        if (trim((string)($systemRecord['seed'] ?? '')) === '') {
            $parentSeed = trim((string)($systemRecord['constellation_seed'] ?? '')) ?: $assignedSeed;
            $systemRecord['seed'] = 'SYS-' . strtoupper(substr(hash(
                'sha256',
                "CORE-SYSTEM-SEED-V2\n" . $parentSeed . "\n" . (string)($systemRecord['id'] ?? '')
            ), 0, 24));
        }
        $systemRecord['worlds'] = $worldGenerator->generateForSystem($assignedSeed, $systemRecord);
    }
}
unset($systemRecord);
// Build the complete runtime hierarchy for the player payload. The persisted
// baseline remains seed-derived and compact; Worlds are generated at runtime
// and attached to their canonical nested Constellation -> System parents.
$runtimeSystemsById = [];
foreach ($playerUniverse['baseline']['systems'] as $runtimeSystem) {
    if (is_array($runtimeSystem) && isset($runtimeSystem['id'])) $runtimeSystemsById[(string)$runtimeSystem['id']] = $runtimeSystem;
}
foreach ($playerUniverse['baseline']['constellations'] ?? [] as &$constellationRecord) {
    if (!is_array($constellationRecord) || !is_array($constellationRecord['systems'] ?? null)) continue;
    foreach ($constellationRecord['systems'] as &$nestedSystemRecord) {
        $nestedId = (string)($nestedSystemRecord['id'] ?? '');
        if ($nestedId !== '' && isset($runtimeSystemsById[$nestedId])) {
            $nestedSystemRecord['worlds'] = $runtimeSystemsById[$nestedId]['worlds'];
        }
    }
    unset($nestedSystemRecord);
}
unset($constellationRecord);
$releaseVersion = require dirname(__DIR__) . '/config/version.php';
$multiplayerPublicUrl = trim((string)(getenv('CORE_MULTIPLAYER_PUBLIC_URL') ?: ''));
if ($multiplayerPublicUrl !== '') {
    $parsedMultiplayerUrl = parse_url($multiplayerPublicUrl);
    if (!is_array($parsedMultiplayerUrl) || !in_array(strtolower((string)($parsedMultiplayerUrl['scheme'] ?? '')), ['ws', 'wss', 'http', 'https'], true)
        || empty($parsedMultiplayerUrl['host']) || isset($parsedMultiplayerUrl['user']) || isset($parsedMultiplayerUrl['pass'])
        || isset($parsedMultiplayerUrl['query']) || isset($parsedMultiplayerUrl['fragment'])) {
        $multiplayerPublicUrl = '';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CORE — Player Portal</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/player.css?v=<?= rawurlencode((string)$releaseVersion) ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/system-view.css?v=<?= rawurlencode((string)$releaseVersion) ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/../assets/borderless.css?v=<?= rawurlencode((string)$releaseVersion) ?>">
  <script src="https://cdn.babylonjs.com/babylon.js"></script>
  <script defer src="https://unpkg.com/colyseus.js@0.16.22/dist/colyseus.js" onerror="window.CORE_COLYSEUS_SDK_ERROR=true"></script>
  <script>window.CORE_BOOT = <?= json_encode(['universe' => $playerUniverse, 'character' => $selectedCharacter, 'characterId' => $selectedCharacterId, 'initialSystemId' => (string)($selectedCharacter['current_system_id'] ?? $selectedCharacter['location']['system_id'] ?? ''), 'currentConstellationId' => (string)($selectedCharacter['current_constellation_id'] ?? $selectedCharacter['location']['constellation_id'] ?? ''), 'multiplayerUrl' => $multiplayerPublicUrl, 'canShareUniverse' => $canShareActiveUniverse], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;</script>
  <script>
    window.CORE_PLAYER_SCRIPT_LOADED = false;
    window.setTimeout(function () {
      if (!window.CORE_PLAYER_SCRIPT_LOADED) {
        var notice = document.getElementById('notice');
        if (notice && notice.textContent.indexOf('Loading your selected character') !== -1) {
          notice.textContent = 'Gameplay JavaScript did not start. Check that player.js loads successfully, then hard-refresh this page.';
          notice.className = 'notice error';
        }
      }
    }, 4000);
    window.addEventListener('error', function (event) {
      var notice = document.getElementById('notice');
      if (notice && notice.textContent.indexOf('Loading your selected character') !== -1) {
        notice.textContent = 'Gameplay startup error: ' + (event.message || 'A script failed to load. Check the browser Console and Network tabs.');
        notice.className = 'notice error';
      }
    });
  </script>
  <script defer src="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/diagnostics.js?v=<?= rawurlencode((string)$releaseVersion) ?>"></script>
  <script defer src="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/orbital-mechanics.js?v=<?= rawurlencode((string)$releaseVersion) ?>"></script>
  <script defer src="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/system-view.js?v=<?= rawurlencode((string)$releaseVersion) ?>"></script>
  <script defer src="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/player.js?v=<?= rawurlencode((string)$releaseVersion) ?>" onload="window.CORE_PLAYER_SCRIPT_LOADED = true" onerror="(function(){var n=document.getElementById('notice');if(n){n.textContent='Could not load player.js. Verify the player script URL and Apache file permissions.';n.className='notice error';}})()"></script>
</head>
<body data-csrf-token="<?= htmlspecialchars((string)$_SESSION['core_csrf'], ENT_QUOTES, 'UTF-8') ?>" data-api-root="<?= htmlspecialchars($urlRoot, ENT_QUOTES, 'UTF-8') ?>" data-character-id="<?= htmlspecialchars($selectedCharacterId, ENT_QUOTES, 'UTF-8') ?>" data-universe-seed="<?= htmlspecialchars($assignedSeed, ENT_QUOTES, 'UTF-8') ?>" data-multiplayer-url="<?= htmlspecialchars($multiplayerPublicUrl, ENT_QUOTES, 'UTF-8') ?>">
  <header class="topbar">
    <a class="brand" href="<?=htmlspecialchars($base,ENT_QUOTES,'UTF-8')?>/index.php"><span class="brand-mark">C</span><span>CORE <small>PLAYER PORTAL</small></span></a>
    <nav class="portal-nav" aria-label="Player navigation"><a class="character-selection-link" href="<?=$base?>/selection.php">← Character Selection</a><a href="<?=$base?>/account.php">Account / sign out</a></nav>
    <span class="build-tag"><?= htmlspecialchars($releaseVersion, ENT_QUOTES, 'UTF-8') ?> · PROTOTYPE</span>
  </header>
  <main id="top">
    <div id="notice" class="notice" role="status" aria-live="polite">Loading your selected character and assigned universe…</div>
    <section id="gameLayout" class="game-layout hidden">
      <aside class="sidebar">
        <div class="hidden"><select id="characterSelect" disabled><option value="">Selected character</option></select></div>
        <section id="universeDataPanel" class="panel section-panel" aria-label="Universe Data">
          <div class="eyebrow">UNIVERSE DATA</div><h2 id="universeName">No universe loaded</h2>
          <dl class="stats"><div><dt>Constellations</dt><dd id="constellationCount">—</dd></div><div><dt>Star Systems</dt><dd id="systemCount">—</dd></div><div><dt>Seed fingerprint</dt><dd id="seedFingerprint">—</dd></div><div><dt>Current Constellation</dt><dd id="characterConstellation">—</dd></div><div><dt>Current System</dt><dd id="characterLocation">—</dd></div></dl>
        </section>
        <section id="selectedPanel" class="panel section-panel selected-panel">
          <div class="eyebrow" id="selectedEntityEyebrow">SELECTED CONSTELLATION</div><h2 id="selectedName">Nothing selected</h2>
          <p id="selectedDescription">Select a constellation in the universe map to inspect its star systems.</p>
          <dl class="stats"><div><dt id="selectedStatOneLabel">Star systems</dt><dd id="selectedClassification">—</dd></div><div><dt>Coordinates</dt><dd id="selectedCoordinates">—</dd></div><div><dt id="selectedStatThreeLabel">Total worlds</dt><dd id="selectedWorlds">—</dd></div></dl>
          <code id="selectedId" class="entity-id">—</code>
          <button type="button" id="viewSystemButton" class="button-secondary" disabled>View Selected Constellation</button>
        </section>
        <section id="coreDiagnosticsPanel" class="panel section-panel" aria-label="CORE Diagnostics">
          <div class="eyebrow">DEVELOPMENT SUPPORT</div><h2>Diagnostics</h2>
          <p>Capture browser errors, rendering state, camera details, WebGL status, and scene mesh information.</p>
          <div class="diagnostics-actions"><button type="button" id="diagnosticsDownload" class="button-secondary">Download Report</button><button type="button" id="diagnosticsSubmit" class="button-secondary">Submit to Server</button></div>
          <small id="diagnosticsStatus" role="status" aria-live="polite">Reports include recent browser errors and current Babylon.js scene state.</small>
        </section>
        <section id="systemWorldInspector" class="panel section-panel system-world-inspector hidden" aria-label="Selected World">
          <div class="eyebrow">SELECTED WORLD</div>
          <h3 id="systemWorldName">Select a world</h3>
          <p id="systemWorldDescription">Select a planet in the 3D system view to inspect its generated profile.</p>
          <dl class="stats">
            <div><dt>World type</dt><dd id="systemWorldType">—</dd></div>
            <div><dt>System seed</dt><dd id="systemWorldSystemSeed">—</dd></div>
            <div><dt>World seed</dt><dd id="systemWorldSeed">—</dd></div>
            <div><dt>UWP</dt><dd id="systemWorldUwp">—</dd></div>
            <div><dt>Starport</dt><dd id="systemWorldStarport">—</dd></div>
            <div><dt>Size</dt><dd id="systemWorldSize">—</dd></div>
            <div><dt>Atmosphere</dt><dd id="systemWorldAtmosphere">—</dd></div>
            <div><dt>Hydrographics</dt><dd id="systemWorldHydrographics">—</dd></div>
            <div><dt>Population</dt><dd id="systemWorldPopulation">—</dd></div>
            <div><dt>Tech level</dt><dd id="systemWorldTech">—</dd></div>
          </dl>
          <button type="button" id="enterWorldButton" class="button-secondary" disabled>Enter Test World</button>
          <p id="worldEntryStatus" class="fine-print">Enter Test World opens a temporary environment for the selected generated World. It is available only when that World belongs to your character's current star system.</p>
        </section>
        <section class="panel section-panel system-list-panel">
          <div id="entityListHeading" class="eyebrow">STAR SYSTEMS IN THIS CONSTELLATION</div>
          <ul id="constellationSystemList" class="world-list"><li>Loading constellations in this Universe…</li></ul>
          <ul id="selectedWorldList" class="world-list hidden" aria-label="Worlds in this System"><li>Select a world to inspect its profile.</li></ul>
        </section>
        <section class="panel section-panel multiplayer-panel">
          <div class="multiplayer-controls" aria-labelledby="multiplayerHeading">
            <div class="eyebrow">REAL-TIME SESSION</div>
            <h3 id="multiplayerHeading">Multiplayer presence</h3>
            <p id="multiplayerStatus" class="travel-status" role="status" aria-live="polite">Not connected. Presence only; gameplay synchronization is not active yet.</p>
            <div class="multiplayer-actions">
              <button type="button" id="multiplayerConnectButton" class="button-secondary" disabled>Connect</button>
              <?php if ($canShareActiveUniverse): ?><button type="button" id="multiplayerCopyKeyButton" class="button-secondary">Copy Key</button><?php endif; ?>
            </div>
            <p id="multiplayerKeyStatus" class="travel-status" role="status" aria-live="polite"></p>
          </div>
        </section>
        <section class="panel section-panel travel-panel">
          <div class="travel-controls">
            <div class="eyebrow">INTERSTELLAR NAVIGATION · DEVELOPMENT TEST</div>
            <label for="travelDestination">Destination star system</label>
            <select id="travelDestination" disabled><option value="">Select a destination</option></select>
            <button type="button" id="travelButton" class="button-secondary" disabled>Plan route</button>
            <p id="travelStatus" class="travel-status" aria-live="polite">Choose a destination to inspect a route.</p>
          </div>
        </section>
      </aside>
      <section class="map-panel panel">
        <div id="universeViewToolbar" class="map-toolbar"><div><div class="eyebrow">UNIVERSE NAVIGATION</div><h2>Universe View</h2><p id="mapUniverseName" class="fine-print">Constellations in this Universe</p></div><span class="map-legend"><i></i> Constellations</span></div>
        <div id="constellationViewToolbar" class="map-toolbar constellation-view-toolbar hidden"><div><div class="eyebrow">CONSTELLATION NAVIGATION</div><h2>Constellation View</h2><p id="mapConstellationName" class="fine-print">Current Constellation</p></div><div class="map-toolbar-actions"><label class="constellation-picker-label" for="constellationSelect">Constellation</label><select id="constellationSelect" aria-label="Choose a Constellation"><?php foreach (($playerUniverse['baseline']['constellations'] ?? []) as $constellationOption): ?><option value="<?= htmlspecialchars((string)($constellationOption['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"<?= (string)($constellationOption['id'] ?? '') === (string)($selectedCharacter['current_constellation_id'] ?? $selectedCharacter['location']['constellation_id'] ?? '') ? ' selected' : '' ?>><?= htmlspecialchars((string)($constellationOption['name'] ?? $constellationOption['id'] ?? 'Constellation'), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><span class="map-legend"><i></i> Star Systems</span></div></div>
        <div class="canvas-wrap"><canvas id="renderCanvas" aria-label="Interactive Universe and Constellation navigation map"></canvas><div id="canvasFallback" class="canvas-fallback hidden">Babylon.js could not initialize. Check network access to the Babylon.js CDN.</div></div>
        <section id="systemViewPanel" class="system-view-panel hidden" aria-label="Selected System View">
          <div class="map-toolbar"><div><div class="eyebrow">SYSTEM DETAILS</div><h2 id="systemViewTitle">System View</h2><p id="systemViewName" class="fine-print">Selected System</p><p class="fine-print">System seed: <code id="systemViewSeed">—</code></p></div><button type="button" id="backToUniverseButton" class="button-secondary">← Back to Constellation View</button></div>
          <div class="system-view-layout">
            <div class="system-canvas-wrap"><canvas id="systemViewCanvas" aria-label="Interactive 3D star system"></canvas><div id="systemViewHoverLabel" class="system-view-hover-label hidden" role="status" aria-live="polite"></div><div id="systemViewFallback" class="canvas-fallback hidden"></div></div>
          </div>
          <p id="systemViewLegend" class="fine-print">Orbital positions and world profiles are generated from the assigned universe seed.</p>
        </section>
        <div class="map-footer"><span id="mapStatus">Waiting for universe data</span><span>Drag to pan · Scroll to zoom · Select a System</span></div>
      </section>
    </section>
    <footer>CORE <?= htmlspecialchars($releaseVersion, ENT_QUOTES, 'UTF-8') ?> · Player portal prototype. Player API requests are authenticated and protected by session ownership and CSRF validation.</footer>
  </main>
</body>
</html>
