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
  <title>Create Character · CORE</title><link rel="stylesheet" href="<?=$base?>/player.css?v=<?=rawurlencode($releaseVersion)?>"><link rel="stylesheet" href="<?=$base?>/../assets/borderless.css?v=<?=rawurlencode($releaseVersion)?>">
  <script src="https://cdn.babylonjs.com/babylon.js"></script>
  <script src="https://cdn.babylonjs.com/loaders/babylonjs.loaders.min.js"></script>
</head>
<body>
<header class="topbar"><a class="brand" href="<?=$base?>/selection.php"><span class="brand-mark">C</span><span>CORE <small>CHARACTER CREATION</small></span></a><span class="build-tag"><?=h($releaseVersion)?></span></header>
<main class="player-flow">
  <section class="panel creation-screen">
    <div class="eyebrow">CHARACTER WORKSPACE · INITIAL DESIGN</div>
    <h1>Create your <span>character.</span></h1>
    <p class="creation-intro">Build the character's identity and starting capabilities. This is an evolving workspace; sections that are not yet available explain their intended purpose rather than presenting unfinished controls.</p>
    <div class="character-creation-layout">
      <aside class="character-preview-panel">
        <div class="eyebrow">3D CHARACTER PREVIEW</div><h2>Character model</h2>
        <p class="creation-intro">A neutral standing preview of the current humanoid model. Appearance controls will expand as character customization develops.</p>
        <div class="character-preview-canvas-wrap"><canvas id="characterPreviewCanvas" aria-label="Interactive 3D character preview"></canvas><div id="characterPreviewStatus" class="preview-status" role="status">Loading standing character preview…</div></div>
        <div class="preview-controls"><small>Drag to orbit · scroll to zoom</small></div>
      </aside>
      <div class="character-form-column">
        <form method="post" action="<?=$base?>/player-action.php" class="creation-form" id="characterCreationForm">
          <input type="hidden" name="csrf" value="<?=h($_SESSION['core_csrf'] ??= bin2hex(random_bytes(32)))?>"><input type="hidden" name="action" value="create_character"><input type="hidden" name="appearance[tint]" id="characterTintValue" value="#61d8e8"><input type="hidden" name="appearance[saturation]" id="characterSaturationValue" value="100"><input type="hidden" name="appearance[brightness]" id="characterBrightnessValue" value="100">
          <section class="creation-identity" aria-labelledby="identityHeading">
            <div><div class="eyebrow">CHARACTER IDENTITY</div><h2 id="identityHeading">Who are you creating?</h2><p>The name identifies this character throughout CORE. It can be changed independently of the character's generated statistics.</p></div>
            <label for="characterName">Character name</label><input id="characterName" name="name" required minlength="2" maxlength="40" autocomplete="off" placeholder="Enter a character name">
          </section>

          <nav class="character-creation-nav" aria-label="Character creation sections" role="tablist">
            <button type="button" role="tab" id="tab-appearance" aria-controls="section-appearance" aria-selected="true" class="is-active" data-creation-tab="appearance">Appearance</button>
            <button type="button" role="tab" id="tab-role" aria-controls="section-role" aria-selected="false" data-creation-tab="role">Role</button>
            <button type="button" role="tab" id="tab-attributes" aria-controls="section-attributes" aria-selected="false" data-creation-tab="attributes">Attributes</button>
            <button type="button" role="tab" id="tab-skills" aria-controls="section-skills" aria-selected="false" data-creation-tab="skills">Skills &amp; Feats</button>
            <button type="button" role="tab" id="tab-traits" aria-controls="section-traits" aria-selected="false" data-creation-tab="traits">Traits &amp; Benefits</button>
            <button type="button" role="tab" id="tab-inventory" aria-controls="section-inventory" aria-selected="false" data-creation-tab="inventory">Inventory</button>
            <button type="button" role="tab" id="tab-background" aria-controls="section-background" aria-selected="false" data-creation-tab="background">Background</button>
            <button type="button" role="tab" id="tab-misc" aria-controls="section-misc" aria-selected="false" data-creation-tab="misc">Misc</button>
          </nav>

          <div class="character-creation-sections">
            <section class="creation-tab-panel" id="section-appearance" role="tabpanel" aria-labelledby="tab-appearance" data-creation-panel="appearance">
              <div class="creation-section-heading"><div class="eyebrow">PHYSICAL IDENTITY</div><h2>Appearance</h2><p>Species establishes the character's biological or constructed form. Species may also grant mechanical adjustments, which are shown in Attributes and Traits rather than being hidden in the visual settings.</p></div>
              <div class="creation-field-block"><label for="characterSpecies">Species</label><select id="characterSpecies" name="species_id" required><?php foreach (($options['species'] ?? []) as $species): ?><option value="<?=h($species['id'])?>"><?=h($species['label'])?></option><?php endforeach; ?></select><p class="field-explanation" id="speciesDescription" aria-live="polite">Choose a species to see its description and known effects.</p><p class="field-explanation" id="speciesAdjustmentDescription"></p></div>
              <section class="appearance-editor" aria-labelledby="appearanceEditorHeading">
                <div class="appearance-editor-heading"><div><h3 id="appearanceEditorHeading">Model appearance</h3><p>These controls affect the whole current model. Clothing and individual body regions are not separately addressable in this GLB yet.</p></div><button type="button" class="appearance-reset" id="resetAppearanceButton">Reset</button></div>
                <div class="appearance-color-row"><label for="previewColor">Overall tint</label><input id="previewColor" type="color" value="#61d8e8"><span id="appearanceColorValue">#61D8E8</span></div>
                <div class="appearance-presets" aria-label="Quick color presets">
                  <button type="button" class="appearance-swatch" data-appearance-preset="#61d8e8" style="--swatch:#61d8e8" aria-label="Cyan preset" title="Cyan"></button>
                  <button type="button" class="appearance-swatch" data-appearance-preset="#d9a47e" style="--swatch:#d9a47e" aria-label="Warm tan preset" title="Warm tan"></button>
                  <button type="button" class="appearance-swatch" data-appearance-preset="#9b78d1" style="--swatch:#9b78d1" aria-label="Violet preset" title="Violet"></button>
                  <button type="button" class="appearance-swatch" data-appearance-preset="#7bbd86" style="--swatch:#7bbd86" aria-label="Green preset" title="Green"></button>
                  <button type="button" class="appearance-swatch" data-appearance-preset="#d47777" style="--swatch:#d47777" aria-label="Red preset" title="Red"></button>
                  <button type="button" class="appearance-swatch" data-appearance-preset="#c7cbd2" style="--swatch:#c7cbd2" aria-label="Silver preset" title="Silver"></button>
                  <button type="button" class="appearance-swatch" data-appearance-preset="#d7c16f" style="--swatch:#d7c16f" aria-label="Gold preset" title="Gold"></button>
                  <button type="button" class="appearance-swatch" data-appearance-preset="#555b68" style="--swatch:#555b68" aria-label="Slate preset" title="Slate"></button>
                </div>
                <div class="appearance-slider"><label for="previewSaturation">Color saturation <output id="saturationOutput" for="previewSaturation">100%</output></label><input id="previewSaturation" type="range" min="0" max="200" step="5" value="100"><small>Controls how vivid or muted the overall tint appears.</small></div>
                <div class="appearance-slider"><label for="previewBrightness">Color brightness <output id="brightnessOutput" for="previewBrightness">100%</output></label><input id="previewBrightness" type="range" min="50" max="150" step="5" value="100"><small>Adjusts the lightness of the overall model tint.</small></div>
                <p class="appearance-limitation">Current model limitation: skin, hair, eyes, and other visible regions share one material, so they change together. We can separate them when the GLB is updated with distinct material regions.</p>
              </section>
            </section>

            <section class="creation-tab-panel" id="section-role" role="tabpanel" aria-labelledby="tab-role" data-creation-panel="role" hidden>
              <div class="creation-section-heading"><div class="eyebrow">PROFESSIONAL DIRECTION</div><h2>Role</h2><p>A role describes the character's intended professional or functional direction. The current choices are broad starting concepts, not yet complete classes, professions, or progression systems.</p></div>
              <div class="creation-field-block"><label for="characterRole">Available roles</label><select id="characterRole" name="role_id" required><?php foreach (($options['roles'] ?? []) as $role): ?><option value="<?=h($role['id'])?>"><?=h($role['label'])?></option><?php endforeach; ?></select><p class="field-explanation" id="roleDescription" aria-live="polite"></p></div>
              <div class="concept-note"><strong>What a role means</strong><p>For this first iteration, selecting a role records the character's direction. Role-specific skills, equipment, benefits, specializations, and advancement rules have not yet been defined.</p></div>
            </section>

            <section class="creation-tab-panel" id="section-attributes" role="tabpanel" aria-labelledby="tab-attributes" data-creation-panel="attributes" hidden>
              <div class="creation-section-heading"><div class="eyebrow">CORE STATISTICS</div><h2>Attributes</h2><p>Attributes represent broad capabilities. Set the base scores using the configured point-buy budget. Species adjustments are applied afterward to produce each final score.</p></div>
              <section class="ability-builder" aria-labelledby="abilityBuilderHeading">
                <div class="ability-builder-heading"><div><h3 id="abilityBuilderHeading">Ability scores</h3><p><?=h($generation['description'] ?? 'Allocate ability points before species adjustments.')?></p></div><div class="ability-points" aria-live="polite"><strong id="abilityPointsRemaining"><?=h($generation['points'] ?? 10)?></strong><span>points remaining</span></div></div>
                <div class="ability-grid">
                  <?php foreach (($options['attributes'] ?? []) as $attribute): $id = (string)$attribute['id']; ?>
                    <div class="ability-card" data-ability-card="<?=h($id)?>">
                      <label for="ability_<?=h($id)?>"><?=h($attribute['label'])?></label>
                      <p class="attribute-description"><?=h($attribute['description'] ?? '')?></p>
                      <select id="ability_<?=h($id)?>" name="attributes[<?=h($id)?>]" data-ability="<?=h($id)?>" required>
                        <?php foreach (array_keys($generation['costs'] ?? []) as $score): ?><option value="<?=h($score)?>" <?=$score === '10' ? 'selected' : ''?>><?=h($score)?></option><?php endforeach; ?>
                      </select>
                      <div class="ability-result"><span>Final score</span><strong data-final-score="<?=h($id)?>">10</strong></div>
                      <small data-ability-adjustment="<?=h($id)?>">No species adjustment</small>
                    </div>
                  <?php endforeach; ?>
                </div>
                <div class="species-bonus-row" id="humanBonusRow"><label for="humanBonusAttribute">Choose where the flexible species bonus applies</label><select id="humanBonusAttribute" name="human_bonus_attribute"><?php foreach (($options['attributes'] ?? []) as $attribute): ?><option value="<?=h($attribute['id'])?>"><?=h($attribute['label'])?></option><?php endforeach; ?></select></div>
                <div class="ability-summary"><div><span>Species adjustments</span><strong id="speciesAdjustmentSummary">Loading…</strong></div><div><span>Points spent</span><strong id="abilityPointsSpent">0 / <?=h($generation['points'] ?? 10)?></strong></div></div>
                <p class="ability-validation" id="abilityValidation" role="status" aria-live="polite">All ability scores are within the point-buy budget.</p>
              </section>
              <div class="concept-note"><strong>Base score vs. final score</strong><p>The selected number is the base score. Species adjustments are added or subtracted to calculate the final score. Derived statistics and the effects of each score will be defined in a later iteration; they are not implied by this preview.</p></div>
            </section>

            <section class="creation-tab-panel future-tab-panel" id="section-skills" role="tabpanel" aria-labelledby="tab-skills" data-creation-panel="skills" hidden><div class="creation-section-heading"><div class="eyebrow">LEARNED CAPABILITIES</div><h2>Skills &amp; Feats</h2><p>Skills will describe trained capabilities such as engineering, medicine, piloting, or investigation. Feats will represent specialized training or techniques with their own acquisition rules and prerequisites.</p></div><div class="concept-note"><strong>Not configured yet</strong><p>No skill list, feat list, ranks, prerequisites, or allocation rules are currently defined. This section is reserved for those systems; no choices are being silently assumed.</p></div></section>

            <section class="creation-tab-panel future-tab-panel" id="section-traits" role="tabpanel" aria-labelledby="tab-traits" data-creation-panel="traits" hidden><div class="creation-section-heading"><div class="eyebrow">INHERENT CHARACTERISTICS</div><h2>Traits &amp; Benefits</h2><p>This section will explain characteristics and benefits granted by sources such as species, role, background, training, or other special circumstances. Each entry should identify its source and effect.</p></div><div class="concept-note"><strong>Not configured yet</strong><p>Species ability adjustments are currently displayed in Attributes. A broader trait and benefit catalog, including descriptions and stacking rules, has not yet been defined.</p></div></section>

            <section class="creation-tab-panel future-tab-panel" id="section-inventory" role="tabpanel" aria-labelledby="tab-inventory" data-creation-panel="inventory" hidden><div class="creation-section-heading"><div class="eyebrow">POSSESSIONS &amp; EQUIPMENT</div><h2>Inventory</h2><p>Inventory will represent what the character owns or carries, potentially including equipment, clothing, weapons, tools, consumables, and personal possessions.</p></div><div class="concept-note"><strong>Not configured yet</strong><p>Starting equipment packages, item selection, equipment slots, and carrying rules are not part of the current character creation form.</p></div></section>

            <section class="creation-tab-panel" id="section-background" role="tabpanel" aria-labelledby="tab-background" data-creation-panel="background" hidden>
              <div class="creation-section-heading"><div class="eyebrow">ORIGINS &amp; HISTORY</div><h2>Background &amp; Lifepath</h2><p>Generate a procedural past for the character. The first generated year begins after the species' maturation point. Review and regenerate the history, advance the character year by year, choose a direction at the profession milestone, then lock the history when satisfied.</p></div>
              <input type="hidden" name="background_snapshot" id="backgroundSnapshot" value="">
              <div class="lifepath-toolbar">
                <div class="lifepath-status" aria-live="polite"><strong id="lifepathAge">Age: —</strong><span id="lifepathSpecies">Species: —</span><span id="lifepathState">History not generated</span></div>
                <div class="lifepath-actions"><button type="button" class="button-secondary" id="lifepathRegenerate">Generate / Regenerate</button><button type="button" class="button-secondary" id="lifepathNext" disabled>Advance one year</button></div>
              </div>
              <p class="field-explanation" id="lifepathExplanation">Generating a new history creates a new procedural result. The seed is retained so the same history can be reproduced.</p>
              <div id="lifepathTimeline" class="lifepath-timeline" aria-live="polite"><p>Preparing the character's history…</p></div>
              <section id="lifepathCareerMilestone" class="lifepath-milestone" hidden>
                <h3>Profession &amp; Education Milestone</h3>
                <p>The character has reached the species' graduation age. Choose what they do next. This records their direction; it does not determine their eventual CORE role.</p>
                <div class="lifepath-path-options">
                  <button type="button" data-lifepath-path="education">Formal Education</button>
                  <button type="button" data-lifepath-path="military">Military Service</button>
                  <button type="button" data-lifepath-path="employment">Employment</button>
                  <button type="button" data-lifepath-path="independent">Remain Independent</button>
                </div>
                <p class="field-explanation">Profession-specific yearly event catalogs are not yet defined; the selected direction is saved as a structured historical fact.</p>
              </section>
              <div class="lifepath-lock-row"><button type="button" id="lifepathLock" disabled>Lock this history</button><span id="lifepathLockStatus" role="status">A generated history must be locked before the character can be saved.</span></div>
              <div class="concept-note"><strong>History is not destiny</strong><p>Locking records the character's pre-game past and current age. It does not freeze future aging or gameplay experiences. Historical facts are stored separately from current Role, and future CORE systems may interpret them without Lifepath creating NPCs, quests, or factions itself.</p></div>
            </section>

            <section class="creation-tab-panel future-tab-panel" id="section-misc" role="tabpanel" aria-labelledby="tab-misc" data-creation-panel="misc" hidden><div class="creation-section-heading"><div class="eyebrow">ADDITIONAL INFORMATION</div><h2>Misc</h2><p>This section is reserved for character information that does not naturally belong in the other categories, such as notes, biography, titles, or other optional details.</p></div><div class="concept-note"><strong>Not configured yet</strong><p>We will add fields here only when their purpose and persistence requirements are clear, rather than turning Misc into a place for unexplained or unrelated values.</p></div></section>
          </div>
          <div class="creation-actions"><a class="button-secondary" href="<?=$base?>/selection.php">Cancel</a><button type="submit" id="createCharacterButton">Save character</button></div>
        </form>
      </div>
    </div>
  </section>
</main>
<script type="application/json" id="characterCreationRules"><?=$rulesJson ?: '{}'?></script>
<script>
window.CORE_LIFEPATH_CONFIG = {
  endpoint: <?=json_encode($base . '/../api/lifepath.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>,
  csrf: <?=json_encode($_SESSION['core_csrf'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>
};
</script>
<script src="<?=$base?>/appearance.js?v=<?=rawurlencode($releaseVersion)?>" defer></script>
<script src="<?=$base?>/character-creation.js?v=<?=rawurlencode($releaseVersion)?>" defer></script>
<script src="<?=$base?>/lifepath-creation.js?v=<?=rawurlencode($releaseVersion)?>" defer></script>
<script src="<?=$base?>/character-preview.js?v=<?=rawurlencode($releaseVersion)?>" defer></script>
</body></html>
