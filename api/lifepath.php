<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params(['httponly' => true, 'secure' => $secure, 'samesite' => 'Lax', 'path' => '/']);
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST is required.']);
    exit;
}
if (empty($_SESSION['core_user']['id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Sign in to use character creation.']);
    exit;
}
$csrf = (string)($_SERVER['HTTP_X_CORE_CSRF'] ?? $_POST['csrf'] ?? '');
if (!hash_equals((string)($_SESSION['core_csrf'] ?? ''), $csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'The character-creation session expired. Reload the page and try again.']);
    exit;
}
define('CORE_ROOT', dirname(__DIR__));
require CORE_ROOT . '/bootstrap.php';
try {
    $action = (string)($_POST['action'] ?? 'generate');
    if (!in_array($action, ['generate', 'advance', 'reload'], true)) {
        throw new RuntimeException('Unsupported Lifepath action.');
    }
    $race = trim((string)($_POST['race'] ?? 'human'));
    $seed = trim((string)($_POST['seed'] ?? ''));
    $age = filter_var($_POST['age'] ?? 0, FILTER_VALIDATE_INT);
    if ($age === false || $age < 0) throw new RuntimeException('Invalid character age.');
    $engine = new \Core\Modules\Characters\Lifepath\LifepathEngine();
    $result = $engine->generateLifepath($race, $seed !== '' ? $seed : null, $action, $age);
    echo json_encode(['ok' => true, 'lifepath' => $result], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
