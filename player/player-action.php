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
        $service = new \Core\Modules\Characters\CharacterService($storage);
        $service->create('', $name, $role, $species, $attributes, $owner);
        header('Location: ' . $base . '/selection.php?created=1'); exit;
    }
    if ($action === 'create_universe') {
        $characterId = trim((string)($_POST['character_id'] ?? ''));
        $seed = trim((string)($_POST['seed'] ?? ''));
        $character = $storage->read('characters', $characterId);
        if (!$character || ($character['owner_account_id'] ?? null) !== $owner) { http_response_code(404); exit('Character not found for this account.'); }
        if ($seed === '' || strlen($seed) > 120) { http_response_code(400); exit('Seed must be between 1 and 120 characters.'); }
        $settings = [];
        foreach (['system_density', 'anomaly_rate', 'binary_star_rate', 'nebula_presence'] as $setting) {
            if (isset($_POST[$setting]) && is_scalar($_POST[$setting])) $settings[$setting] = (int)$_POST[$setting];
        }
        $universe = (new \Core\Modules\Universe\UniverseService($storage))->createOrLoad($seed, $settings);
        $storage->transact('characters', (string)$character['id'], static function (array $current) use ($owner, $universe): array {
            if (($current['owner_account_id'] ?? null) !== $owner) throw new \RuntimeException('Character ownership changed; no association was made.');
            if (!empty($current['universe_id']) && $current['universe_id'] !== $universe['id']) throw new \RuntimeException('This character is already assigned to another universe. Safe unassignment is not available.');
            $first = $universe['baseline']['systems'][0] ?? null;
            if (!is_array($first) || !isset($first['id'])) throw new \RuntimeException('Universe has no valid starting system.');
            $current['universe_id'] = $universe['id'];
            $now = gmdate('c');
            $current['current_system_id'] = (string)$first['id'];
            $current['location'] = ['system_id' => (string)$first['id'], 'updated_at' => $now];
            $current['exploration'] = ['explored_systems' => [['system_id' => (string)$first['id'], 'name' => (string)($first['name'] ?? $first['id']), 'first_visited_at' => $now, 'first_visited_revision' => 0]]];
            $current['lifecycle']['last_action'] = 'universe_assigned';
            $current['lifecycle']['updated_at'] = $now;
            return $current;
        });
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
