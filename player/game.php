<?php
declare(strict_types=1);
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/player/game.php')), '/');
if (session_status() !== PHP_SESSION_ACTIVE) { $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'); session_set_cookie_params(['httponly'=>true,'secure'=>$secure,'samesite'=>'Lax','path'=>'/']); session_start(); }
if (empty($_SESSION['core_user'])) { header('Location: ' . $base . '/account.php'); exit; }
$root = preg_replace('#/player$#', '', $base) ?? '';
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
  <script defer src="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/player.js"></script>
</head>
<body data-api-root="<?= htmlspecialchars($root, ENT_QUOTES, 'UTF-8') ?>">
  <header class="topbar">
    <a class="brand" href="<?=htmlspecialchars($base,ENT_QUOTES,'UTF-8')?>/index.php"><span class="brand-mark">C</span><span>CORE <small>PLAYER PORTAL</small></span></a>
    <nav class="portal-nav"><a href="<?=$base?>/index.php">Public site</a><a href="<?=$base?>/account.php">Account / Profile</a></nav>
    <span class="build-tag"><?= htmlspecialchars($releaseVersion, ENT_QUOTES, 'UTF-8') ?> · PROTOTYPE</span>
  </header>
  <main id="top">
    <section class="welcome panel">
      <div class="eyebrow">PLAYER PORTAL · SIGNED IN</div>
      <h1>Choose your <span>frontier.</span></h1>
      <p>Load a seeded universe, select a character, inspect star systems, and travel between connected systems. Character appearance and world-level content will be added as their data systems are implemented.</p>
      <form id="universeForm" class="universe-form">
        <label for="universeSeed">Universe seed</label>
        <div class="input-row"><input id="universeSeed" name="seed" maxlength="120" required placeholder="Enter a seed phrase" autocomplete="off"><button type="submit">Load universe</button></div>
        <small>The same seed resolves to the same generated universe. This prototype uses the existing development API.</small>
      </form>
    </section>

    <div id="notice" class="notice" role="status" aria-live="polite">Load a universe to begin. Existing backend records are not changed until you create a character.</div>

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
        <section class="panel section-panel">
          <div class="section-heading"><div><div class="eyebrow">COMMAND</div><h2>Character</h2></div><span class="status-dot"></span></div>
          <label for="characterSelect">Select character</label>
          <select id="characterSelect" disabled><option value="">Load a universe first</option></select>
          <p class="fine-print">Choose an existing character to continue, or create a new character with an extensible Starfinder-inspired starting profile.</p>
          <button type="button" id="newCharacterButton" class="button-secondary" disabled>Create a character</button>
        </section>
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
        <div class="map-toolbar"><div><div class="eyebrow">TACTICAL OVERVIEW</div><h2>Universe map</h2></div><span class="map-legend"><i></i> Star systems</span></div>
        <div class="canvas-wrap"><canvas id="renderCanvas" aria-label="Interactive 3D universe map"></canvas><div id="canvasFallback" class="canvas-fallback hidden">Babylon.js could not initialize. Check network access to the Babylon.js CDN.</div></div>
        <div class="map-footer"><span id="mapStatus">Waiting for universe data</span><span>Drag to orbit · Scroll to zoom · Select a sphere</span></div>
      </section>
    </section>
    <footer>CORE <?= htmlspecialchars($releaseVersion, ENT_QUOTES, 'UTF-8') ?> · Player portal prototype. Account login is an initial implementation; gameplay APIs remain development/private-network restricted.</footer>
  </main>
</body>
</html>
