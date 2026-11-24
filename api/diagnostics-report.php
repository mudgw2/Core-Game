<?php
declare(strict_types=1);

$root = dirname(__DIR__);
define('CORE_ROOT', $root);
require $root . '/bootstrap.php';

use Core\Core\Logger;
use Core\Http\PlayerApiSecurity;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405); header('Allow: POST');
    echo json_encode(['ok' => false, 'error' => ['code' => 'method_not_allowed']]); exit;
}
PlayerApiSecurity::startSession();
$accountId = PlayerApiSecurity::currentUserId();
if ($accountId === null) {
    http_response_code(401); echo json_encode(['ok' => false, 'error' => ['code' => 'authentication_required']]); exit;
}
$expected = (string)($_SESSION['core_csrf'] ?? '');
$provided = (string)($_SERVER['HTTP_X_CORE_CSRF'] ?? '');
if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
    http_response_code(403); echo json_encode(['ok' => false, 'error' => ['code' => 'csrf_validation_failed']]); exit;
}
$raw = file_get_contents('php://input');
if (!is_string($raw) || strlen($raw) > 262144) {
    http_response_code(413); echo json_encode(['ok' => false, 'error' => ['code' => 'report_too_large', 'message' => 'Diagnostic reports are limited to 256 KB.']]); exit;
}
$report = json_decode($raw, true);
if (!is_array($report) || !is_array($report['diagnostics'] ?? null)) {
    http_response_code(400); echo json_encode(['ok' => false, 'error' => ['code' => 'invalid_report']]); exit;
}
$now = time();
$recent = (int)($_SESSION['core_diag_last_submit'] ?? 0);
if ($recent > 0 && $now - $recent < 10) {
    http_response_code(429); echo json_encode(['ok' => false, 'error' => ['code' => 'rate_limited', 'message' => 'Wait a few seconds before submitting another report.']]); exit;
}
$_SESSION['core_diag_last_submit'] = $now;
$id = preg_match('/^[a-f0-9-]{16,64}$/i', (string)($report['report_id'] ?? '')) ? (string)$report['report_id'] : bin2hex(random_bytes(12));
$version = (string)require $root . '/config/version.php';
$record = [
    'report_id' => $id,
    'received_at' => gmdate('c'),
    'core_version' => $version,
    'account_id' => $accountId,
    'character_id' => substr((string)($report['character_id'] ?? ''), 0, 100),
    'client' => is_array($report['client'] ?? null) ? $report['client'] : [],
    'diagnostics' => $report['diagnostics'],
];
$dir = $root . '/storage/diagnostics';
if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
    http_response_code(500); echo json_encode(['ok' => false, 'error' => ['code' => 'diagnostics_storage_unavailable']]); exit;
}
// Retain a bounded rolling set of reports; never prune the current submission.
$existing = glob($dir . '/*.json') ?: [];
if (count($existing) > 100) {
    usort($existing, static fn(string $a, string $b): int => (filemtime($a) ?: 0) <=> (filemtime($b) ?: 0));
    foreach (array_slice($existing, 0, count($existing) - 99) as $oldReport) @unlink($oldReport);
}
$file = $dir . '/' . gmdate('Y-m') . '-' . $id . '.json';
$encoded = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
if ($encoded === false || @file_put_contents($file, $encoded . PHP_EOL, LOCK_EX) === false) {
    http_response_code(500); echo json_encode(['ok' => false, 'error' => ['code' => 'diagnostics_write_failed']]); exit;
}
Logger::warning('Browser diagnostic report received', ['report_id' => $id, 'character_id' => $record['character_id'], 'file' => basename($file)]);
echo json_encode(['ok' => true, 'report_id' => $id, 'message' => 'Diagnostic report saved on the CORE server.']);
