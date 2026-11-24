<?php
declare(strict_types=1);

namespace Core\Modules\Universe;

use RuntimeException;

final class UniverseGenerator
{
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
            $isInteger = is_int($value) || (is_string($value) && preg_match('/^-?\d+$/D', $value) === 1);
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

        // Preserve approximately the prior system population while introducing
        // deterministic regional groupings above individual star systems.
        $systemCount = 8 + (hexdec(substr($hash, 24, 2)) % 9) + (int)round($density / 100 * 8);
        $constellationCount = max(3, min(7, (int)ceil($systemCount / 4)));
        $constellations = [];
        $systems = [];
        $systemOrdinal = 0;
        $remaining = $systemCount;

        for ($region = 0; $region < $constellationCount; $region++) {
            $constellationHash = hash('sha256', $hash . ':constellation:' . $region);
            $regionsLeft = $constellationCount - $region;
            $minimumForRest = $regionsLeft - 1;
            $regionSystemCount = $region === $constellationCount - 1
                ? $remaining
                : max(1, min(5, (int)ceil($remaining / $regionsLeft)));
            $regionSystemCount = min($regionSystemCount, $remaining - $minimumForRest);
            $remaining -= $regionSystemCount;

            $constellationId = 'con_' . substr($constellationHash, 0, 16);
            $constellationSeed = 'CON-' . strtoupper(substr(hash('sha256', "CORE-CONSTELLATION-SEED-V1\n" . $normalizedSeed . "\n" . $constellationId), 0, 24));
            $constellationSystems = [];

            for ($local = 0; $local < $regionSystemCount; $local++) {
                $systemHash = hash('sha256', $constellationHash . ':system:' . $local);
                $roll = hexdec(substr($systemHash, 8, 2)) % 100;
                if ($roll < $normalizedSettings['anomaly_rate']) $classification = 'anomalous';
                elseif ($roll < $normalizedSettings['anomaly_rate'] + $normalizedSettings['nebula_presence']) $classification = 'nebula-adjacent';
                elseif ($roll < $normalizedSettings['anomaly_rate'] + $normalizedSettings['nebula_presence'] + $normalizedSettings['binary_star_rate']) $classification = 'binary';
                else $classification = 'ordinary';

                $systemId = 'sys_' . substr($systemHash, 0, 16);
                $systemSeed = 'SYS-' . strtoupper(substr(hash('sha256', "CORE-SYSTEM-SEED-V2\n" . $constellationSeed . "\n" . $systemId), 0, 24));
                $starClass = ['O', 'B', 'A', 'F', 'G', 'K', 'M'][hexdec(substr($systemHash, 12, 2)) % 7];
                $system = [
                    'id' => $systemId,
                    'seed' => $systemSeed,
                    'name' => 'System ' . str_pad((string)($systemOrdinal + 1), 2, '0', STR_PAD_LEFT),
                    'constellation_id' => $constellationId,
                    'constellation_seed' => $constellationSeed,
                    'x' => (hexdec(substr($systemHash, 0, 4)) % 20001) - 10000,
                    'y' => (hexdec(substr($systemHash, 4, 4)) % 20001) - 10000,
                    'classification' => $classification,
                    'star' => [
                        'id' => 'star_' . substr(hash('sha256', $systemId . ':primary-star'), 0, 16),
                        'name' => 'Primary ' . $starClass . '-' . strtoupper(substr($systemHash, 14, 2)),
                        'spectral_class' => $starClass,
                        'is_binary' => $classification === 'binary',
                    ],
                ];
                $constellationSystems[] = $system;
                $systems[] = $system;
                $systemOrdinal++;
            }

            $constellations[] = [
                'id' => $constellationId,
                'seed' => $constellationSeed,
                'name' => 'Constellation ' . str_pad((string)($region + 1), 2, '0', STR_PAD_LEFT),
                'ordinal' => $region + 1,
                'x' => (hexdec(substr($constellationHash, 0, 4)) % 20001) - 10000,
                'y' => (hexdec(substr($constellationHash, 4, 4)) % 20001) - 10000,
                'system_count' => count($constellationSystems),
                'systems' => $constellationSystems,
            ];
        }

        return [
            'id' => $universeId,
            'seed' => $normalizedSeed,
            'settings' => $normalizedSettings,
            'generator_version' => 3,
            'hierarchy_version' => 1,
            'seed_fingerprint' => substr($seedHash, 0, 12),
            'constellation_count' => count($constellations),
            'system_count' => count($systems),
            'constellations' => $constellations,
            // Flat index retained for existing services and API consumers. These
            // are actual star systems, not constellation records.
            'systems' => $systems,
        ];
    }
}
