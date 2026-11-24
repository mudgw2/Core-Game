<?php
declare(strict_types=1);

namespace Core\Core;

final class Logger
{
    private static string $path = '';
    private static string $environment = 'production';

    public static function configure(string $path, string $environment): void
    {
        self::$path = $path;
        self::$environment = $environment;
        $directory = dirname($path);
        if (!is_dir($directory)) {
            @mkdir($directory, 0770, true);
        }
    }

    public static function info(string $message, array $context = []): void { self::write('INFO', $message, $context); }
    public static function warning(string $message, array $context = []): void { self::write('WARNING', $message, $context); }
    public static function error(string $message, array $context = []): void { self::write('ERROR', $message, $context); }
    public static function critical(string $message, array $context = []): void { self::write('CRITICAL', $message, $context); }

    private static function write(string $level, string $message, array $context): void
    {
        $version = 'unknown';
        if (defined('CORE_ROOT') && is_file(CORE_ROOT . '/config/version.php')) {
            try { $version = (string)require CORE_ROOT . '/config/version.php'; } catch (\Throwable) {}
        }
        $sessionId = session_status() === PHP_SESSION_ACTIVE ? session_id() : '';
        $requestContext = [
            'core_version' => $version,
            'request_id' => defined('CORE_REQUEST_ID') ? CORE_REQUEST_ID : null,
            'request_method' => (string)($_SERVER['REQUEST_METHOD'] ?? 'CLI'),
            'request_path' => (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: ''),
            'session_fingerprint' => $sessionId !== '' ? substr(hash('sha256', $sessionId), 0, 16) : null,
        ];
        $entry = ['time' => gmdate('c'), 'level' => $level, 'message' => $message, 'context' => $requestContext + $context];
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($line === false) $line = '{"time":"' . gmdate('c') . '","level":"ERROR","message":"Failed to encode log entry"}';

        if (self::$path !== '') {
            self::append(self::$path, $line);
            $directory = dirname(self::$path);
            foreach (self::channels($level, $message, $context) as $channel) {
                $channelPath = $directory . '/' . $channel . '.log';
                if ($channelPath !== self::$path) self::append($channelPath, $line);
            }
        }
        if (self::$environment === 'development' || $level === 'ERROR' || $level === 'CRITICAL') error_log('[CORE] ' . $line);
    }

    /** Every event stays in core.log and is additionally routed to one or more focused logs. */
    private static function channels(string $level, string $message, array $context): array
    {
        $lower = strtolower($message);
        $channels = [];
        if ($message === 'Request handled') $channels[] = 'api';
        if (str_contains($lower, 'storage') || str_contains($lower, 'stored json') || str_contains($lower, 'atomic storage') || str_contains($lower, 'temporary storage')) $channels[] = 'storage';
        if (str_contains($lower, 'universe deletion') || str_contains($lower, 'universe and associated characters permanently deleted')) $channels[] = 'universe-deletion';
        if (str_contains($lower, 'universe backup') || str_contains($lower, 'backup restored') || str_contains($lower, 'backup rollback') || (isset($context['backup_id']) && (array_key_exists('rollback_failure', $context) || str_contains($lower, 'rollback')))) $channels[] = 'backups';
        if (str_contains($lower, 'unsafe') || str_contains($lower, 'rejected') || str_contains($lower, 'confirmation')) $channels[] = 'security';
        if (str_contains($lower, 'php runtime error') || str_contains($lower, 'uncaught exception') || str_contains($lower, 'fatal php error') || $level === 'CRITICAL') $channels[] = 'php-errors';
        if ($channels === [] && in_array($level, ['ERROR', 'CRITICAL'], true)) $channels[] = 'errors';
        return array_values(array_unique($channels));
    }

    private static function append(string $path, string $line): void
    {
        // Keep persistent logs bounded: rotate at 5 MB, retaining three generations.
        $maxBytes = 5 * 1024 * 1024;
        clearstatcache(true, $path);
        if (is_file($path) && (int)@filesize($path) >= $maxBytes) {
            @unlink($path . '.3');
            if (is_file($path . '.2')) @rename($path . '.2', $path . '.3');
            if (is_file($path . '.1')) @rename($path . '.1', $path . '.2');
            @rename($path, $path . '.1');
        }
        $written = @file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($written === false) error_log('[CORE LOGGER FAILURE] Could not write log file: ' . $path);
    }
}
