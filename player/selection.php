<?php
declare(strict_types=1);
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/player/selection.php')), '/');
$urlRoot = rtrim(str_replace('\\', '/', dirname($base)), '/');
if (session_status() !== PHP_SESSION_ACTIVE) { $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'); session_set_cookie_params(['httponly'=>true,'secure'=>$secure,'samesite'=>'Lax','path'=>'/']); session_start(); }
if (empty($_SESSION['core_user']['id'])) { header('Location: '.$base.'/account.php'); exit; }
if (empty($_SESSION['core_csrf'])) $_SESSION['core_csrf'] = bin2hex(random_bytes(32));
$root = dirname(__DIR__); define('CORE_ROOT', $root); require $root.'/bootstrap.php';
$config = require $root.'/config/app.php'; $storage = new \Core\Storage\JsonStorage((string)$config['storage']['path']);
$uid=(string)$_SESSION['core_user']['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_character') {
    $token = (string)($_POST['csrf_token'] ?? '');
    $deleteId = trim((string)($_POST['character_id'] ?? ''));
    if ($token === '' || !hash_equals((string)$_SESSION['core_csrf'], $token) || $deleteId === '') {
        http_response_code(400); exit('Invalid character deletion request.');
    }
    try {
        $storage->transact('characters', $deleteId, static function (array $character) use ($uid): array {
            if (($character['owner_account_id'] ?? null) !== $uid) throw new RuntimeException('Character not found for this account.');
            $lifecycle = is_array($character['lifecycle'] ?? null) ? $character['lifecycle'] : [];
            if (($lifecycle['archived'] ?? false) === true) throw new RuntimeException('Character is already deleted.');
            $lifecycle['archived'] = true;
            $lifecycle['enabled'] = false;
            $lifecycle['updated_at'] = gmdate('c');
            $lifecycle['last_action'] = 'player_delete';
            $character['lifecycle'] = $lifecycle;
            $character['revision'] = (int)($character['revision'] ?? 0) + 1;
            return $character;
        });
        header('Location: '.$base.'/selection.php?deleted=1'); exit;
    } catch (Throwable $e) {
        http_response_code(409); exit(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    }
}
$characters=array_values(array_filter($storage->all('characters'), static fn($c)=>is_array($c) && ($c['owner_account_id']??null)===$uid && (($c['lifecycle']['archived']??false)!==true)));
$universes=[]; foreach($storage->all('universes') as $u) if(is_array($u)) $universes[(string)($u['id']??'')]=$u;
$releaseVersion=require $root.'/config/version.php';
$assignedSeed = isset($_SESSION['core_flash_universe_seed']) ? (string)$_SESSION['core_flash_universe_seed'] : '';
$assignedSummary = is_array($_SESSION['core_flash_universe_summary'] ?? null) ? $_SESSION['core_flash_universe_summary'] : [];
unset($_SESSION['core_flash_universe_seed']);
unset($_SESSION['core_flash_universe_summary']);
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Character Selection · CORE</title><link rel="stylesheet" href="<?=$base?>/player.css?v=<?=rawurlencode((string)$releaseVersion)?>"><link rel="stylesheet" href="<?=$base?>/../assets/borderless.css?v=<?=rawurlencode((string)$releaseVersion)?>"></head><body data-csrf-token="<?=h($_SESSION['core_csrf'])?>" data-api-root="<?=h($urlRoot)?>"><header class="topbar"><a class="brand" href="<?=$base?>/index.php"><span class="brand-mark">C</span><span>CORE <small>PLAYER PORTAL</small></span></a><nav class="portal-nav" aria-label="Player navigation"><a href="<?=$base?>/account.php">Account &amp; sign out</a></nav><span class="build-tag"><?=h($releaseVersion)?></span></header><main class="player-flow"><nav class="flow-steps" aria-label="Player setup progress"><span class="flow-step flow-step-complete">1 <small>Account</small></span><span class="flow-step flow-step-current" aria-current="step">2 <small>Characters</small></span><span class="flow-step">3 <small>Universe</small></span><span class="flow-step">4 <small>Play</small></span></nav><?php if($assignedSeed !== ''): ?><section class="notice success seed-result"><strong>Universe ready.</strong> Your universe seed: <code><?=h($assignedSeed)?></code><p>Keep this seed to recreate the same generated universe. <?=h((string)($assignedSummary['system_count']??''))?> constellations generated.</p></section><?php endif; ?><?php if(isset($_GET['deleted'])): ?><section class="notice success">Character deleted from your roster.</section><?php endif; ?><section class="welcome panel"><div class="eyebrow">PLAYER PORTAL · CHARACTER ROSTER</div><h1>Choose your <span>character.</span></h1><p>All characters associated with your account appear here. Characters can exist before they are assigned to a universe.</p><div class="flow-actions"><a class="action-link" href="<?=$base?>/character-create.php">＋ Create character</a></div></section>
<section class="panel shared-universe-panel">
  <div class="eyebrow">SHARED UNIVERSE ACCESS</div>
  <h2>Visit a friend's universe</h2>
  <p>Enter a share code provided by the universe owner. Your character keeps its home universe and can return at any time.</p>
  <form id="sharedUniverseVisitForm" class="creation-form">
    <label for="sharedVisitCharacter">Character</label>
    <select id="sharedVisitCharacter" required><?php foreach($characters as $candidate): if(empty($candidate['universe_id'])) continue; ?><option value="<?=h($candidate['id'])?>"><?=h($candidate['name']??'Unnamed character')?></option><?php endforeach; ?></select>
    <label for="sharedUniverseCode">Universe share code</label>
    <input id="sharedUniverseCode" type="text" maxlength="32" minlength="32" pattern="[A-Fa-f0-9]{32}" autocomplete="off" required placeholder="32-character code">
    <button type="submit">Visit shared universe</button>
    <p id="sharedUniverseVisitStatus" class="fine-print" aria-live="polite"></p>
  </form>
</section>
<?php if(!$characters): ?><section class="panel empty-state"><h2>Your story starts here.</h2><p>You do not have any characters yet. Create your first character, then assign a universe before entering gameplay.</p><a class="action-link" href="<?=$base?>/character-create.php">Create your first character</a></section><?php else: ?><section class="roster-grid"><?php foreach($characters as $c): $uidUniverse=(string)($c['universe_id']??''); $activeUniverseId=(string)($c['active_universe_id']??$uidUniverse); $visiting=$activeUniverseId!==$uidUniverse; $u=$universes[$activeUniverseId]??null; $homeUniverse=$universes[$uidUniverse]??null; $homeSeed=(string)($homeUniverse['seed']??$homeUniverse['baseline']['seed']??''); $universeSeed=(string)($u['seed']??$u['baseline']['seed']??''); $loc=$c['location']['system_id']??$c['current_system_id']??null; $systemName=$loc; $systemPreview=null; if($u && $loc) foreach(($u['baseline']['systems']??[]) as $sys) if(($sys['id']??null)===$loc){$systemPreview=$sys; $systemName=$sys['name']??$loc;break;} if($systemPreview){$previewGenerator=new \Core\Modules\Universe\WorldGenerator();$systemPreview['worlds']=$previewGenerator->generateForSystem($universeSeed,$systemPreview);} ?><article class="panel character-card"><div class="character-card-visuals"><div class="character-portrait"><canvas class="character-portrait-canvas" aria-label="3D character portrait" data-tint="<?=h($c['appearance']['tint']??'#61d8e8')?>" data-saturation="<?=h($c['appearance']['saturation']??100)?>" data-brightness="<?=h($c['appearance']['brightness']??100)?>"></canvas><span>CHARACTER</span></div><div class="character-environment"><canvas class="character-universe-canvas" aria-label="3D view of assigned universe" data-systems="<?=h(json_encode($systemPreview ? [$systemPreview] : [], JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP))?>"></canvas><span>SYSTEM / LOCATION</span></div></div><div class="eyebrow"><?= $visiting ? 'VISITING SHARED UNIVERSE' : ($uidUniverse ? 'HOME UNIVERSE' : 'UNASSIGNED CHARACTER') ?></div><h2><?=h($c['name']??'Unnamed character')?></h2><p><?=h($c['species']??$c['species_id']??'Unknown species')?> · <?=h($c['role']??'No role')?></p><dl class="stats"><div><dt><?= $visiting ? 'Current shared universe seed' : 'Home universe seed' ?></dt><dd><?=h($universeSeed!==''?$universeSeed:($uidUniverse?'Seed unavailable for legacy universe':'Not assigned'))?></dd></div><?php if($visiting): ?><div><dt>Permanent home universe seed</dt><dd><?=h($homeSeed!==''?$homeSeed:'Seed unavailable')?></dd></div><?php endif; ?><div><dt>Last known constellation</dt><dd><?=h($systemName?:'Not recorded')?></dd></div></dl><?php if(!$uidUniverse): ?><a class="action-link" href="<?=$base?>/universe-create.php?character_id=<?=rawurlencode((string)$c['id'])?>">Create / assign universe</a><?php else: ?><a class="action-link" href="<?=$base?>/game.php?character_id=<?=rawurlencode((string)$c['id'])?>">Play</a><?php if($visiting): ?><button type="button" class="button-secondary shared-universe-leave" data-character-id="<?=h($c['id'])?>">Return to home universe</button><?php else: ?><button type="button" class="button-secondary shared-universe-share" data-universe-id="<?=h($uidUniverse)?>">Create / rotate share code</button><output class="shared-universe-code" aria-live="polite"></output><?php endif; ?><?php endif; ?><form method="post" action="../api/character-delete.php" class="character-delete-form" onsubmit="return confirm('Delete this character from your roster? This cannot be undone from the player portal.');"><input type="hidden" name="action" value="delete_character"><input type="hidden" name="character_id" value="<?=h($c['id'])?>"><input type="hidden" name="csrf_token" value="<?=h($_SESSION['core_csrf'])?>"><button type="submit" class="character-delete-button">Delete character</button></form></article><?php endforeach; ?></section><?php endif; ?><footer>CORE <?=h($releaseVersion)?> · Player workflow</footer></main><script src="https://cdn.babylonjs.com/babylon.js"></script><script src="https://cdn.babylonjs.com/loaders/babylonjs.loaders.min.js"></script><script src="<?=$base?>/orbital-mechanics.js?v=<?=rawurlencode((string)$releaseVersion)?>"></script><script src="<?=$base?>/appearance.js?v=<?=rawurlencode((string)$releaseVersion)?>"></script><script src="<?=$base?>/character-roster-preview.js?v=<?=rawurlencode((string)$releaseVersion)?>"></script><script src="<?=$base?>/shared-universe.js?v=<?=rawurlencode((string)$releaseVersion)?>"></script></body></html>
