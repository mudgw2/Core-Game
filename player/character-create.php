<?php
declare(strict_types=1);
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/player/character-create.php')), '/');
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params(['httponly' => true, 'secure' => $secure, 'samesite' => 'Lax', 'path' => '/']);
    session_start();
}
if (empty($_SESSION['core_user']['id'])) { header('Location: ' . $base . '/account.php'); exit; }
$root = dirname(__DIR__);
define('CORE_ROOT', $root);
require $root . '/bootstrap.php';
try { $options = \Core\Modules\Characters\CharacterCreationRules::definitions(); }
catch (Throwable $e) { http_response_code(500); exit('Character creation definitions are unavailable.'); }
$releaseVersion = require $root . '/config/version.php';
function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$generation = $options['ability_generation'] ?? [];
$attributeIds = array_values(array_filter(array_map(static fn(array $a): string => (string)($a['id'] ?? ''), $options['attributes'] ?? [])));
$rulesJson = json_encode($options, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Create Character · CORE</title><link rel="stylesheet" href="<?=$base?>/player.css">
  <script src="https://cdn.babylonjs.com/babylon.js"></script>
</head>
<body>
<header class="topbar"><a class="brand" href="<?=$base?>/selection.php"><span class="brand-mark">C</span><span>CORE <small>CHARACTER CREATION</small></span></a><span class="build-tag"><?=h($releaseVersion)?></span></header>
<main class="player-flow">
  <section class="panel creation-screen">
    <div class="eyebrow">STEP 1 · CHARACTER IDENTITY</div>
    <h1>Create your <span>character.</span></h1>
    <p>This character is saved independently. You can create and assign a universe in the next step.</p>
    <div class="character-creation-layout">
      <aside class="character-preview-panel">
        <div class="eyebrow">LIVE 3D PREVIEW</div><h2>Character silhouette</h2>
        <p class="creation-intro">A procedural Babylon.js primitive preview. Character abilities and species are saved independently of the visual model.</p>
        <div class="character-preview-canvas-wrap"><canvas id="characterPreviewCanvas" aria-label="Interactive 3D character preview"></canvas><div id="characterPreviewStatus" class="preview-status" role="status">Initializing 3D preview…</div></div>
        <div class="preview-controls"><label for="previewColor">Preview color</label><input id="previewColor" type="color" value="#61d8e8"><small>Drag to orbit · scroll to zoom</small></div>
      </aside>
      <div class="character-form-column">
        <form method="post" action="<?=$base?>/player-action.php" class="creation-form" id="characterCreationForm">
          <input type="hidden" name="csrf" value="<?=h($_SESSION['core_csrf'] ??= bin2hex(random_bytes(32)))?>"><input type="hidden" name="action" value="create_character">
          <label for="characterName">Character name</label><input id="characterName" name="name" required minlength="2" maxlength="40" autocomplete="off">
          <div class="creation-grid">
            <div><label for="characterRole">Role</label><select id="characterRole" name="role_id" required><?php foreach (($options['roles'] ?? []) as $role): ?><option value="<?=h($role['id'])?>"><?=h($role['label'])?></option><?php endforeach; ?></select></div>
            <div><label for="characterSpecies">Species</label><select id="characterSpecies" name="species_id" required><?php foreach (($options['species'] ?? []) as $species): ?><option value="<?=h($species['id'])?>"><?=h($species['label'])?></option><?php endforeach; ?></select></div>
          </div>
          <section class="ability-builder" aria-labelledby="abilityBuilderHeading">
            <div class="ability-builder-heading"><div><h2 id="abilityBuilderHeading">Ability scores</h2><p><?=h($generation['description'] ?? 'Allocate ability points before species adjustments.')?></p></div><div class="ability-points" aria-live="polite"><strong id="abilityPointsRemaining"><?=h($generation['points'] ?? 10)?></strong><span>points remaining</span></div></div>
            <div class="ability-grid">
              <?php foreach (($options['attributes'] ?? []) as $attribute): $id = (string)$attribute['id']; ?>
                <div class="ability-card" data-ability-card="<?=h($id)?>">
                  <label for="ability_<?=h($id)?>"><?=h($attribute['label'])?></label>
                  <select id="ability_<?=h($id)?>" name="attributes[<?=h($id)?>]" data-ability="<?=h($id)?>" required>
                    <?php foreach (array_keys($generation['costs'] ?? []) as $score): ?><option value="<?=h($score)?>" <?=$score === '10' ? 'selected' : ''?>><?=h($score)?></option><?php endforeach; ?>
                  </select>
                  <div class="ability-result"><span>Final score</span><strong data-final-score="<?=h($id)?>">10</strong></div>
                  <small data-ability-adjustment="<?=h($id)?>">No species adjustment</small>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="species-bonus-row" id="humanBonusRow">
              <label for="humanBonusAttribute">Human ability bonus</label>
              <select id="humanBonusAttribute" name="human_bonus_attribute"><?php foreach (($options['attributes'] ?? []) as $attribute): ?><option value="<?=h($attribute['id'])?>"><?=h($attribute['label'])?></option><?php endforeach; ?></select>
            </div>
            <div class="ability-summary"><div><span>Species adjustments</span><strong id="speciesAdjustmentSummary">+2 Strength</strong></div><div><span>Points spent</span><strong id="abilityPointsSpent">0 / <?=h($generation['points'] ?? 10)?></strong></div></div>
            <p class="ability-validation" id="abilityValidation" role="status" aria-live="polite">All ability scores are within the point-buy budget.</p>
          </section>
          <div class="creation-actions"><a class="button-secondary" href="<?=$base?>/selection.php">Cancel</a><button type="submit" id="createCharacterButton">Save character</button></div>
        </form>
      </div>
    </div>
  </section>
</main>
<script type="application/json" id="characterCreationRules"><?=$rulesJson ?: '{}'?></script>
<script src="<?=$base?>/character-creation.js?v=<?=rawurlencode($releaseVersion)?>" defer></script>
<script src="<?=$base?>/character-preview.js?v=<?=rawurlencode($releaseVersion)?>" defer></script>
</body></html>
