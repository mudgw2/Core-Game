<?php
declare(strict_types=1);
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/player/player-action.php')), '/');
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params(['httponly' => true, 'secure' => $secure, 'samesite' => 'Lax', 'path' => '/']);
    session_start();
}
if (empty($_SESSION['core_user']['id'])) { header('Location: ' . $base . '/account.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals((string)($_SESSION['core_csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
    http_response_code(400); exit('Invalid form session. Return to the portal and try again.');
}
$root = dirname(__DIR__);
define('CORE_ROOT', $root);
require $root . '/bootstrap.php';
$config = require $root . '/config/app.php';
$storage = new \Core\Storage\JsonStorage((string)$config['storage']['path']);
$owner = (string)$_SESSION['core_user']['id'];
$action = (string)($_POST['action'] ?? '');
try {
    if ($action === 'create_character') {
        $name = trim((string)($_POST['name'] ?? ''));
        $role = trim((string)($_POST['role_id'] ?? ''));
        $species = trim((string)($_POST['species_id'] ?? 'human'));
        $attributes = is_array($_POST['attributes'] ?? null) ? $_POST['attributes'] : [];
        $humanBonusAttribute = trim((string)($_POST['human_bonus_attribute'] ?? ''));
        $service = new \Core\Modules\Characters\CharacterService($storage);
        $service->create('', $name, $role, $species, $attributes, $owner, $humanBonusAttribute !== '' ? $humanBonusAttribute : null);
        header('Location: ' . $base . '/selection.php?created=1'); exit;
    }
    if ($action === 'create_universe') {
        $characterId = trim((string)($_POST['character_id'] ?? ''));
        $seed = trim((string)($_POST['seed'] ?? ''));
        $character = $storage->read('characters', $characterId);
        if (!$character || ($character['owner_account_id'] ?? null) !== $owner) { http_response_code(404); exit('Character not found for this account.'); }
        if ($seed === '' || strlen($seed) > 120) { http_response_code(400); exit('Seed must be between 1 and 120 characters.'); }
        if (!empty($character['universe_id'])) {
            http_response_code(409);
            exit('This character is already assigned to a universe. Select the character from the roster to continue.');
        }
        \Core\Modules\Characters\CharacterLifecycleGuard::assertCanPerformGameplayAction($character);
        $settings = [];
        foreach (['system_density', 'anomaly_rate', 'binary_star_rate', 'nebula_presence'] as $setting) {
            if (isset($_POST[$setting]) && is_scalar($_POST[$setting])) $settings[$setting] = (int)$_POST[$setting];
        }
        $universe = (new \Core\Modules\Universe\UniverseService($storage))->createOrLoad($seed, $settings);
        $assignedCharacter = (new \Core\Modules\Characters\CharacterUniverseAssignmentService($storage))
            ->assign((string)$character['id'], $owner, (string)$universe['id']);
        // Keep the player-facing seed as a one-time flash message; universe IDs remain internal.
        $_SESSION['core_flash_universe_seed'] = $seed;
        $_SESSION['core_flash_universe_summary'] = ['system_count' => count($universe['baseline']['systems'] ?? []), 'settings' => $universe['settings'] ?? ($universe['baseline']['settings'] ?? [])];
        header('Location: ' . $base . '/selection.php?assigned=1'); exit;
    }
    http_response_code(400); exit('Unknown player action.');
} catch (Throwable $e) {
    http_response_code(400);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Action failed · CORE</title><body><h1>Could not complete that action</h1><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p><p><a href="' . htmlspecialchars($base . '/selection.php', ENT_QUOTES, 'UTF-8') . '">Return to character selection</a></p></body></html>';
}
