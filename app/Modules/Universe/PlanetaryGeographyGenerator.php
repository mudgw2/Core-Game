<?php
declare(strict_types=1);

namespace Core\Modules\Universe;

/**
 * Deterministic planetary-scale geographic baseline.
 *
 * Geography is generated from a world's stable seed and UWP. Rendering systems
 * consume these records; they do not make independent geographic decisions.
 */
final class PlanetaryGeographyGenerator
{
    public const GENERATOR_VERSION = 2;
    public const HEX_SCALE_KM = 50;
    public const DEFAULT_COLUMNS = 20;
    public const DEFAULT_ROWS = 16;

    private const HEX = '0123456789ABCDEF';

    /**
     * Resolve the authoritative physical radius while remaining compatible
     * with geography records saved before schema_version 2 introduced planet.
     * Legacy records are not rewritten; the radius is derived from the stable
     * world's size code using the same rule as current geography generation.
     */
    public static function physicalRadiusKm(array $world, array $geography): float
    {
        $stored = $geography['planet']['radius_km'] ?? null;
        if (is_numeric($stored) && is_finite((float)$stored) && (float)$stored > 0) {
            return (float)$stored;
        }

        $size = filter_var($world['size'] ?? null, FILTER_VALIDATE_INT);
        if ($size === false || $size < 0 || $size > 10) {
            return 0.0;
        }

        return $size === 0 ? 80.0 : (float)($size * 800);
    }

    public function generate(array $world, int $columns = self::DEFAULT_COLUMNS, int $rows = self::DEFAULT_ROWS): array
    {
        if ($columns < 4 || $columns > 64 || $rows < 4 || $rows > 64) {
            throw new \InvalidArgumentException('Geography dimensions must be between 4 and 64 hexes.');
        }
        $worldId = trim((string)($world['id'] ?? ''));
        $seed = trim((string)($world['seed'] ?? ''));
        if ($worldId === '' || $seed === '') throw new \InvalidArgumentException('World ID and world seed are required.');

        $size = $this->digit($world, 'size', 0, 10);
        $hydro = $this->digit($world, 'hydrographics', 0, 10);
        $population = $this->digit($world, 'population', 0, 10);
        $starport = strtoupper((string)($world['starport'] ?? 'X'));
        $uwp = strtoupper((string)($world['uwp'] ?? ''));
        $rootSeed = hash('sha256', "CORE-PLANETARY-GEOGRAPHY-V2\n" . $seed . "\n" . $worldId . "\n" . $uwp);
        // Traveller-style size codes describe approximate planetary diameter in 1,600 km increments.
        // CORE uses a minimum physical body radius for size-zero/asteroid test worlds.
        $radiusKm = $size === 0 ? 80 : $size * 800;
        $total = $columns * $rows;
        $waterTarget = (int)round($total * ($hydro / 10));
        $cells = [];

        // Rank deterministic cell scores to meet the world hydrographics ratio
        // while using neighbor influence to form larger, coherent water bodies.
        $waterRanks = [];
        for ($r = 0; $r < $rows; $r++) {
            for ($q = 0; $q < $columns; $q++) {
                $key = $q . ':' . $r;
                $waterRanks[$key] = $this->field($rootSeed, $q, $r, 'water');
            }
        }
        asort($waterRanks, SORT_NUMERIC);
        $waterKeys = array_fill_keys(array_slice(array_keys($waterRanks), 0, $waterTarget), true);

        for ($r = 0; $r < $rows; $r++) {
            for ($q = 0; $q < $columns; $q++) {
                $key = $q . ':' . $r;
                $isWater = isset($waterKeys[$key]);
                $mountainScore = $this->field($rootSeed, $q, $r, 'mountain');
                $hillScore = $this->field($rootSeed, $q, $r, 'hill');
                $terrain = $isWater ? 'water' : ($mountainScore >= 59 ? 'mountains' : ($hillScore >= 49 ? 'hills' : 'plains'));
                $features = [];
                if ($isWater) $features[] = 'water';
                elseif ($terrain === 'mountains') $features[] = 'mountains';
                elseif ($terrain === 'hills') $features[] = 'hills';
                else $features[] = 'plains';

                $elevation = match ($terrain) {
                    'water' => -0.15,
                    'mountains' => 0.65 + ($mountainScore / 100) * 0.35,
                    'hills' => 0.20 + ($hillScore / 100) * 0.25,
                    default => 0.05 + ($hillScore / 100) * 0.08,
                };
                $cells[$key] = [
                    'id' => sprintf('hex_%02d%02d', $q + 1, $r + 1),
                    'coordinates' => sprintf('%02d%02d', $q + 1, $r + 1),
                    'q' => $q,
                    'r' => $r,
                    'terrain' => $terrain,
                    'features' => $features,
                    'water' => $isWater,
                    'elevation_index' => round($elevation, 3),
                    'seed_fingerprint' => substr(hash('sha256', $rootSeed . ':hex:' . $key), 0, 12),
                ];
            }
        }

        // Deterministic population sites prefer dry, low-relief land.
        $populationCenters = [];
        $centerCount = $population === 0 ? 0 : min(12, max(1, (int)ceil($population / 2)));
        $candidates = array_values(array_filter($cells, static fn(array $cell): bool => !$cell['water']));
        usort($candidates, function (array $a, array $b) use ($rootSeed): int {
            $scoreA = $this->siteScore($rootSeed, $a, 'population');
            $scoreB = $this->siteScore($rootSeed, $b, 'population');
            return $scoreB <=> $scoreA;
        });
        foreach (array_slice($candidates, 0, $centerCount) as $index => $cell) {
            $populationCenters[] = [
                'id' => 'pop_' . substr(hash('sha256', $rootSeed . ':population:' . $cell['id']), 0, 16),
                'hex_id' => $cell['id'],
                'coordinates' => $cell['coordinates'],
                'type' => $index === 0 ? 'primary' : 'secondary',
                'population_rating' => $population,
            ];
            $cells[$cell['q'] . ':' . $cell['r']]['features'][] = 'population_center';
        }

        $spaceports = [];
        if (in_array($starport, ['A', 'B', 'C', 'D', 'E'], true) && $candidates !== []) {
            $preferred = $candidates[0];
            $spaceports[] = [
                'id' => 'port_' . substr(hash('sha256', $rootSeed . ':spaceport'), 0, 16),
                'hex_id' => $preferred['id'],
                'coordinates' => $preferred['coordinates'],
                'classification' => $starport,
            ];
            $cells[$preferred['q'] . ':' . $preferred['r']]['features'][] = 'spaceport';
        }

        return [
            'schema_version' => 2,
            'planet' => [
                'body_type' => (string)($world['type'] ?? 'terrestrial'),
                'radius_km' => $radiusKm,
                'diameter_km' => $radiusKm * 2,
                'circumference_km' => round(2 * M_PI * $radiusKm, 2),
                'render_scale' => 'compressed_visual_scale',
                'coordinate_system' => 'spherical_latitude_longitude',
            ],
            'global_surface' => [
                'model' => 'seeded_spherical_field',
                'generator_version' => self::GENERATOR_VERSION,
                'hydrographics_fraction' => $hydro / 10,
                'legacy_hex_grid' => true,
            ],
            'generator' => ['name' => 'CORE Planetary Geography', 'version' => self::GENERATOR_VERSION],
            'world' => [
                'id' => $worldId, 'seed' => $seed, 'uwp' => $uwp,
                'size' => $size, 'hydrographics' => $hydro, 'population' => $population,
                'starport' => $starport,
            ],
            'map' => [
                'coordinate_system' => 'axial_hex_grid',
                'columns' => $columns, 'rows' => $rows,
                'hex_scale_km' => self::HEX_SCALE_KM,
                'hex_count' => $total,
                'water_hex_count' => $waterTarget,
                'hexes' => array_values($cells),
            ],
            'locations' => ['spaceports' => $spaceports, 'population_centers' => $populationCenters],
            'generation_fingerprint' => substr(hash('sha256', json_encode(array_values($cells), JSON_THROW_ON_ERROR)), 0, 24),
        ];
    }

    private function digit(array $world, string $key, int $min, int $max): int
    {
        $value = filter_var($world[$key] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value < $min || $value > $max) {
            throw new \InvalidArgumentException('World ' . $key . ' must be an integer from ' . $min . ' to ' . $max . '.');
        }
        return $value;
    }

    private function roll(string $seed, string $label): int
    {
        return hexdec(substr(hash('sha256', $seed . ':' . $label), 0, 8)) % 100;
    }

    private function field(string $seed, int $q, int $r, string $kind): int
    {
        $sum = $this->roll($seed, $kind . ':' . $q . ':' . $r) * 2;
        $sum += $this->roll($seed, $kind . ':' . ($q - 1) . ':' . $r);
        $sum += $this->roll($seed, $kind . ':' . ($q + 1) . ':' . $r);
        $sum += $this->roll($seed, $kind . ':' . $q . ':' . ($r - 1));
        $sum += $this->roll($seed, $kind . ':' . $q . ':' . ($r + 1));
        return (int)round($sum / 6);
    }

    private function neighborCount(array $waterKeys, int $q, int $r, bool $water): int
    {
        $count = 0;
        foreach ([[$q - 1,$r],[$q + 1,$r],[$q,$r - 1],[$q,$r + 1],[$q - 1,$r + 1],[$q + 1,$r - 1]] as [$nq,$nr]) {
            if (isset($waterKeys[$nq . ':' . $nr]) === $water) $count++;
        }
        return $count;
    }

    private function siteScore(string $seed, array $cell, string $kind): int
    {
        $relief = $cell['terrain'] === 'plains' ? 30 : ($cell['terrain'] === 'hills' ? 15 : 0);
        return $relief + $this->roll($seed, $kind . ':' . $cell['id']);
    }
}
