<?php
declare(strict_types=1);

namespace Core\Simulation;

use RuntimeException;

/**
 * Persistent, server-authoritative simulation clock.
 * A separate lock file serializes read/modify/write operations; JSON replacement
 * is atomic so browser refreshes and concurrent clients cannot reset or tear time.
 */
final class SimulationClock
{
    public static function update(string $storageRoot, string $universeId, string $worldId, float $adjustSeconds = 0): array
    {
        if ($universeId === '' || $worldId === '') {
            throw new RuntimeException('A universe and world are required for the simulation clock.');
        }
        $directory = rtrim($storageRoot, '/') . '/simulation_clocks';
        if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not initialize simulation clock storage.');
        }
        if (!is_writable($directory)) {
            throw new RuntimeException('Simulation clock storage is not writable.');
        }
        $id = hash('sha256', $universeId . "\0" . $worldId);
        $path = $directory . '/' . $id . '.json';
        $lock = @fopen($path . '.clock-lock', 'c');
        if ($lock === false) throw new RuntimeException('Could not open simulation clock lock.');
        try {
            if (!flock($lock, LOCK_EX)) throw new RuntimeException('Could not lock simulation clock.');
            $now = microtime(true);
            $record = null;
            if (is_file($path)) {
                $raw = @file_get_contents($path);
                $decoded = is_string($raw) ? json_decode($raw, true) : null;
                if (!is_array($decoded) || !is_numeric($decoded['elapsed_seconds'] ?? null) || !is_numeric($decoded['updated_at'] ?? null)) {
                    throw new RuntimeException('Stored simulation clock data is invalid; refusing to reset it.');
                }
                $record = $decoded;
            }
            $elapsed = $record === null ? 0.0
                : max(0.0, (float)$record['elapsed_seconds'] + max(0.0, $now - (float)$record['updated_at']));
            $elapsed = max(0.0, $elapsed + $adjustSeconds);
            $next = [
                'schema_version' => 1,
                'universe_id' => $universeId,
                'world_id' => $worldId,
                'elapsed_seconds' => $elapsed,
                'updated_at' => $now,
            ];
            $json = json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            $temporary = $path . '.tmp-' . bin2hex(random_bytes(8));
            if (@file_put_contents($temporary, $json, LOCK_EX) === false || !@rename($temporary, $path)) {
                @unlink($temporary);
                throw new RuntimeException('Could not persist simulation clock state.');
            }
            @chmod($path, 0660);
            return [
                'elapsedSeconds' => $elapsed,
                'serverTime' => $now,
                'dayLengthSeconds' => null,
            ];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
