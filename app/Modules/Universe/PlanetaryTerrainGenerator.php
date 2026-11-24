<?php
declare(strict_types=1);

namespace Core\Modules\Universe;

/**
 * Creates the persistent, editable procedural terrain definition for one world.
 * The JSON stores generator inputs and authored features, not a baked heightmap.
 */
final class PlanetaryTerrainGenerator
{
    public const GENERATOR_VERSION = 1;

    public function generate(array $world, array $geography): array
    {
        $worldId = trim((string)($world['id'] ?? ''));
        $worldSeed = trim((string)($world['seed'] ?? ''));
        if ($worldId === '' || $worldSeed === '') {
            throw new \InvalidArgumentException('World ID and world seed are required for terrain generation.');
        }
        $radius = PlanetaryGeographyGenerator::physicalRadiusKm($world, $geography);
        if (!is_finite($radius) || $radius <= 0) {
            throw new \InvalidArgumentException('A valid planetary radius is required for terrain generation.');
        }
        $hydrographics = filter_var($world['hydrographics'] ?? 5, FILTER_VALIDATE_INT);
        if ($hydrographics === false || $hydrographics < 0 || $hydrographics > 10) {
            throw new \InvalidArgumentException('World hydrographics must be an integer from 0 to 10.');
        }
        $seed = hash('sha256', "CORE-PLANETARY-TERRAIN-V1\n" . $worldSeed . "\n" . $worldId);

        return [
            'schema_version' => 1,
            'world' => [
                'id' => $worldId,
                'seed' => $worldSeed,
                'terrain_seed' => $seed,
                'radius_km' => $radius,
            ],
            'generator' => [
                'name' => 'CORE Spherical Fractal Terrain',
                'version' => self::GENERATOR_VERSION,
                'model' => 'spherical_domain_warped_fractal_noise',
                'parameters' => [
                    'hydrographics_fraction' => $hydrographics / 10,
                    'elevation_scale_km' => max(0.8, min(48.0, $radius * 0.0075)),
                    'relief_exaggeration' => 2.5,
                    'ocean_depth_scale_km' => max(0.25, min(18.0, $radius * 0.0028)),
                    'continental_frequency' => 1.6,
                    'regional_frequency' => 3.0,
                    'mountain_frequency' => 11.0,
                    'detail_frequency' => 24.0,
                    'domain_warp_strength' => 0.22,
                    'mountain_threshold' => 0.82,
                    'hill_threshold' => 0.58,
                ],
            ],
            // Authored changes are deliberately separate from generated geography.
            // Supported feature records are documented by feature_schema below.
            'modifications' => [],
            'feature_schema' => [
                'supported_types' => ['mountain', 'volcano', 'meteor_crater', 'elevation_stamp'],
                'coordinates' => 'latitude_longitude_degrees',
                'distance_unit' => 'kilometers',
                'height_unit' => 'kilometers',
                'fields' => [
                    'id' => 'stable developer-assigned identifier',
                    'type' => 'one of supported_types',
                    'latitude' => 'center latitude from -90 to 90',
                    'longitude' => 'center longitude from -180 to 180',
                    'radius_km' => 'feature influence radius',
                    'height_km' => 'positive relief or crater rim height',
                    'depth_km' => 'optional crater depression depth',
                ],
            ],
        ];
    }
}
