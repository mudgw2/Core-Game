<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Core\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = CORE_ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$configFile = CORE_ROOT . '/config/app.php';
$config = is_file($configFile) ? require $configFile : [];

\Core\Core\Logger::configure(
    (string)($config['logging']['path'] ?? CORE_ROOT . '/storage/logs/core.log'),
    (string)($config['app']['environment'] ?? 'production')
);

date_default_timezone_set((string)($config['app']['timezone'] ?? 'UTC'));


// Central runtime error capture. Respect @-suppression but log unsuppressed PHP warnings/notices.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    \Core\Core\Logger::error('PHP runtime error', [
        'severity' => $severity, 'message' => $message, 'file' => $file, 'line' => $line,
    ]);
    return false; // Preserve PHP's normal handling/display behavior.
});

set_exception_handler(static function (\Throwable $exception): void {
    \Core\Core\Logger::critical('Uncaught exception', [
        'exception' => $exception::class, 'message' => $exception->getMessage(),
        'file' => $exception->getFile(), 'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString(),
    ]);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => false, 'error' => ['code' => 'internal_error', 'message' => 'An unexpected server error occurred. Check CORE logs.']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
});

register_shutdown_function(static function (): void {
    $last = error_get_last();
    if ($last !== null && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        \Core\Core\Logger::critical('Fatal PHP error', [
            'severity' => $last['type'], 'message' => $last['message'],
            'file' => $last['file'], 'line' => $last['line'],
        ]);
    }
});
