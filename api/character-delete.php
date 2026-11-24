<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$playerBase = rtrim(str_replace('\\\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/character-delete.php'))), '/') . '/player';
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params(['httponly' => true, 'secure' => $secure, 'samesite' => 'Lax', 'path' => '/']);
    session_start();
}
if (empty($_SESSION['core_user']['id'])) {
    header('Location: ' . $playerBase . '/account.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}
$token = (string)($_POST['csrf_token'] ?? '');
$characterId = trim((string)($_POST['character_id'] ?? ''));
if ($token === '' || empty($_SESSION['core_csrf']) || !hash_equals((string)$_SESSION['core_csrf'], $token) || $characterId === '') {
    http_response_code(400);
    exit('Invalid character deletion request. Return to Character Selection and try again.');
}
define('CORE_ROOT', $root);
require $root . '/bootstrap.php';
$config = require $root . '/config/app.php';
$storage = new \Core\Storage\JsonStorage((string)$config['storage']['path']);
$accountId = (string)$_SESSION['core_user']['id'];
try {
    $storage->transact('characters', $characterId, static function (array $character) use ($accountId): array {
        if ((string)($character['owner_account_id'] ?? '') !== $accountId) {
            throw new RuntimeException('Character not found for this account.');
        }
        $lifecycle = is_array($character['lifecycle'] ?? null) ? $character['lifecycle'] : [];
        if (($lifecycle['archived'] ?? false) === true) {
            throw new RuntimeException('This character has already been deleted.');
        }
        $lifecycle['archived'] = true;
        $lifecycle['enabled'] = false;
        $lifecycle['updated_at'] = gmdate('c');
        $lifecycle['last_action'] = 'player_delete';
        $character['lifecycle'] = $lifecycle;
        $character['revision'] = (int)($character['revision'] ?? 0) + 1;
        return $character;
    });
    header('Location: ' . $playerBase . '/selection.php?deleted=1', true, 303);
    exit;
} catch (Throwable $error) {
    http_response_code(409);
    header('Content-Type: text/plain; charset=utf-8');
    echo htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}
