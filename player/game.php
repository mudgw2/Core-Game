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
$assignedUniverse = $portalStorage->read('universes', (string)$selectedCharacter['universe_id']);
if (!$assignedUniverse || !is_array($assignedUniverse['baseline']['systems'] ?? null)) { http_response_code(409); exit('The assigned universe could not be loaded. Return to Character Selection and verify the universe association.'); }
$assignedSeed = (string)($assignedUniverse['seed'] ?? $assignedUniverse['baseline']['seed'] ?? '');
$releaseVersion = require dirname(__DIR__) . '/config/version.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CORE — Player Portal</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/player.css">
  <script src="https://cdn.babylonjs.com/babylon.js"></script>
  <script>window.CORE_BOOT = <?= json_encode(['universe' => $assignedUniverse, 'character' => $selectedCharacter, 'characterId' => $selectedCharacterId], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;</script>
  <script>
    // Fail visibly if the external gameplay script is blocked, stale, or fails before startup.
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
  <script defer src="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/player.js?v=<?= rawurlencode((string)$releaseVersion) ?>" onload="window.CORE_PLAYER_SCRIPT_LOADED = true" onerror="(function(){var n=document.getElementById('notice');if(n){n.textContent='Could not load player.js. Verify the player script URL and Apache file permissions.';n.className='notice error';}})()"></script>
</head>
<body data-csrf-token="<?= htmlspecialchars((string)$_SESSION['core_csrf'], ENT_QUOTES, 'UTF-8') ?>" data-api-root="<?= htmlspecialchars($urlRoot, ENT_QUOTES, 'UTF-8') ?>" data-character-id="<?= htmlspecialchars($selectedCharacterId, ENT_QUOTES, 'UTF-8') ?>" data-universe-seed="<?= htmlspecialchars($assignedSeed, ENT_QUOTES, 'UTF-8') ?>">
  <header class="topbar">
    <a class="brand" href="<?=htmlspecialchars($base,ENT_QUOTES,'UTF-8')?>/index.php"><span class="brand-mark">C</span><span>CORE <small>PLAYER PORTAL</small></span></a>
    <nav class="portal-nav" aria-label="Player navigation"><a class="character-selection-link" href="<?=$base?>/selection.php">← Character Selection</a><a href="<?=$base?>/account.php">Account / sign out</a></nav>
    <span class="build-tag"><?= htmlspecialchars($releaseVersion, ENT_QUOTES, 'UTF-8') ?> · PROTOTYPE</span>
  </header>
  <main id="top">
    <div id="notice" class="notice" role="status" aria-live="polite">Loading your selected character and assigned universe…</div>

    <section id="characterCreationScreen" class="panel creation-screen hidden" aria-labelledby="creationTitle">
      <div class="eyebrow">NEW CHARACTER · INITIAL PROFILE</div>
      <h2 id="creationTitle">Create your character</h2>
      <p class="creation-intro">This first profile uses six Starfinder 1E-inspired abilities. Values and choices are validated by the server and defined in editable JSON data.</p>
      <form id="characterForm" class="creation-form">
        <div class="creation-grid">
          <div class="creation-field"><label for="characterName">Character name</label><input id="characterName" maxlength="40" minlength="2" required placeholder="Enter a character name" autocomplete="off"></div>
          <div class="creation-field"><label for="characterRole">Starting role</label><select id="characterRole" required></select></div>
          <div class="creation-field"><label for="characterSpecies">Species</label><select id="characterSpecies" required></select></div>
        </div>
        <h3>Abilities</h3><p class="fine-print">Starting defaults are 10. Current permitted range: 1–30. Full point-buy and species adjustments are not implemented yet.</p>
        <div id="attributeFields" class="attribute-grid"></div>
        <div class="creation-actions"><button type="button" id="cancelCharacterCreation" class="button-secondary">Back to selection</button><button type="submit" id="createCharacterButton" disabled>Create character</button></div>
      </form>
    </section>

    <section id="gameLayout" class="game-layout hidden">
      <aside class="sidebar">
<div class="hidden"><select id="characterSelect" disabled><option value="">Selected character</option></select></div>
        <section class="panel section-panel">
          <div class="eyebrow">UNIVERSE DATA</div><h2 id="universeName">No universe loaded</h2>
          <dl class="stats"><div><dt>Systems</dt><dd id="systemCount">—</dd></div><div><dt>Seed fingerprint</dt><dd id="seedFingerprint">—</dd></div><div><dt>Character location</dt><dd id="characterLocation">—</dd></div></dl>
        </section>
        <section class="panel section-panel selected-panel">
          <div class="eyebrow">SELECTED OBJECT</div><h2 id="selectedName">Nothing selected</h2>
          <p id="selectedDescription">Select a system marker in the 3D map to inspect its backend-defined details.</p>
          <dl class="stats"><div><dt>Classification</dt><dd id="selectedClassification">—</dd></div><div><dt>Coordinates</dt><dd id="selectedCoordinates">—</dd></div><div><dt>Worlds</dt><dd id="selectedWorlds">Not generated yet</dd></div></dl>
          <code id="selectedId" class="entity-id">—</code>
          <button type="button" id="travelButton" class="button-secondary" disabled>Travel to selected system</button>
          <p id="travelHint" class="fine-print">Select a character and a reachable destination to travel.</p>
        </section>
      </aside>

      <section class="map-panel panel">
        <div class="map-toolbar"><div><div class="eyebrow">TACTICAL OVERVIEW</div><h2>Universe map</h2></div><div class="map-toolbar-actions"><span class="map-legend"><i></i> Star systems</span></div></div>
        <div class="canvas-wrap"><canvas id="renderCanvas" aria-label="Interactive 3D universe map"></canvas><div id="canvasFallback" class="canvas-fallback hidden">Babylon.js could not initialize. Check network access to the Babylon.js CDN.</div></div>
        <div class="map-footer"><span id="mapStatus">Waiting for universe data</span><span>Drag to pan · Scroll to zoom · Select a star system</span></div>
      </section>
    </section>
    <footer>CORE <?= htmlspecialchars($releaseVersion, ENT_QUOTES, 'UTF-8') ?> · Player portal prototype. Player API requests are authenticated and protected by session ownership and CSRF validation.</footer>
  </main>
</body>
</html>
