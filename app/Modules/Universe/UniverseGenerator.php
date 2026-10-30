<?php
declare(strict_types=1);

namespace Core\Modules\Universe;

use RuntimeException;

final class UniverseGenerator
{
    /** Data-driven starter universe characteristics. Values are clamped/validated at the boundary. */
    public const SETTING_DEFAULTS = [
        'system_density' => 50,
        'anomaly_rate' => 25,
        'binary_star_rate' => 30,
        'nebula_presence' => 35,
    ];

    public function normalizeSettings(array $settings): array
    {
        $normalized = [];
        foreach (self::SETTING_DEFAULTS as $key => $default) {
            $value = array_key_exists($key, $settings) ? $settings[$key] : $default;
            // HTML form values arrive as decimal-free strings; accept those and native
            // integers, but reject floats, decimal strings, exponent notation, and booleans.
            $isInteger = is_int($value) || (is_string($value) && preg_match('/^-?\\d+$/D', $value) === 1);
            if (!$isInteger || (int)$value < 0 || (int)$value > 100) {
                throw new RuntimeException('Universe setting ' . $key . ' must be an integer from 0 to 100.');
            }
            $normalized[$key] = (int)$value;
        }
        foreach ($settings as $key => $_) {
            if (!array_key_exists((string)$key, self::SETTING_DEFAULTS)) {
                throw new RuntimeException('Unknown universe setting: ' . (string)$key);
            }
        }
        return $normalized;
    }

    public function generate(string $seed, array $settings = []): array
    {
        $normalizedSeed = trim($seed);
        if ($normalizedSeed === '' || strlen($normalizedSeed) > 120) throw new RuntimeException('Seed must be between 1 and 120 characters.');
        $normalizedSettings = $this->normalizeSettings($settings);
        $hasSettings = $settings !== [];
        $identityInput = $hasSettings ? $normalizedSeed . "\nCORE-UNIVERSE-SETTINGS-V1\n" . json_encode($normalizedSettings, JSON_THROW_ON_ERROR) : $normalizedSeed;
        $hash = hash('sha256', $identityInput);
        $seedHash = hash('sha256', $normalizedSeed);
        $universeId = 'uni_' . substr($hash, 0, 24);
        $density = $normalizedSettings['system_density'];
        $systemCount = 8 + (hexdec(substr($hash, 24, 2)) % 9) + (int)round($density / 100 * 8);
        $systems = [];
        for ($i = 0; $i < $systemCount; $i++) {
            $systemHash = hash('sha256', $hash . ':system:' . $i);
            $roll = hexdec(substr($systemHash, 8, 2)) % 100;
            if ($roll < $normalizedSettings['anomaly_rate']) $classification = 'anomalous';
            elseif ($roll < $normalizedSettings['anomaly_rate'] + $normalizedSettings['nebula_presence']) $classification = 'nebula-adjacent';
            elseif ($roll < $normalizedSettings['anomaly_rate'] + $normalizedSettings['nebula_presence'] + $normalizedSettings['binary_star_rate']) $classification = 'binary';
            else $classification = 'ordinary';
            $systems[] = [
                'id' => 'sys_' . substr($systemHash, 0, 16),
                'name' => 'System ' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT),
                'x' => (hexdec(substr($systemHash, 0, 4)) % 20001) - 10000,
                'y' => (hexdec(substr($systemHash, 4, 4)) % 20001) - 10000,
                'classification' => $classification,
            ];
        }
        return [
            'id' => $universeId,
            'seed' => $normalizedSeed,
            'settings' => $normalizedSettings,
            'generator_version' => 2,
            'seed_fingerprint' => substr($seedHash, 0, 12),
            'system_count' => count($systems),
            'systems' => $systems,
        ];
    }
}
