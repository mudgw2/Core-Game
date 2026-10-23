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
            $written = @file_put_contents(self::$path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
            if ($written === false) error_log('[CORE LOGGER FAILURE] Could not write log file: ' . self::$path);
        }
        if (self::$environment === 'development' || $level === 'ERROR' || $level === 'CRITICAL') error_log('[CORE] ' . $line);
    }
}
