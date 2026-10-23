<?php
declare(strict_types=1);

namespace Core\Modules\Universe;

final class UniverseGenerator
{
    /**
     * Generates a deterministic preview. This is intentionally a small starter
     * generator; later generator versions must be versioned for save compatibility.
     */
    public function generate(string $seed): array
    {
        $normalizedSeed = trim($seed);
        $hash = hash('sha256', $normalizedSeed);
        $universeId = 'uni_' . substr($hash, 0, 24);
        $systemCount = 8 + (hexdec(substr($hash, 24, 2)) % 9);

        $systems = [];
        for ($i = 0; $i < $systemCount; $i++) {
            $systemHash = hash('sha256', $hash . ':system:' . $i);
            $systems[] = [
                'id' => 'sys_' . substr($systemHash, 0, 16),
                'name' => 'System ' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT),
                'x' => (hexdec(substr($systemHash, 0, 4)) % 20001) - 10000,
                'y' => (hexdec(substr($systemHash, 4, 4)) % 20001) - 10000,
                'classification' => ['ordinary', 'binary', 'nebula-adjacent', 'anomalous'][hexdec(substr($systemHash, 8, 2)) % 4],
            ];
        }

        return [
            'id' => $universeId,
            'generator_version' => 1,
            'seed_fingerprint' => substr($hash, 0, 12),
            'system_count' => count($systems),
            'systems' => $systems,
        ];
    }
}
