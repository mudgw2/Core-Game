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
        $entry = ['time' => gmdate('c'), 'level' => $level, 'message' => $message, 'context' => $context];
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
        $written = @file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($written === false) error_log('[CORE LOGGER FAILURE] Could not write log file: ' . $path);
    }
}
