<?php
declare(strict_types=1);

namespace Core\Modules\Universe;

/** Independent climate baseline; elevation is an input to later climate simulation, not its generator. */
final class PlanetaryClimateGenerator
{
    public function generate(array $world, array $geography): array
    {
        $worldId = trim((string)($world['id'] ?? ''));
        $seed = trim((string)($world['seed'] ?? ''));
        if ($worldId === '' || $seed === '') throw new \InvalidArgumentException('World ID and world seed are required for climate generation.');
        $hash = hash('sha256', "CORE-PLANETARY-CLIMATE-V1\n" . $seed . "\n" . $worldId);
        $radius = PlanetaryGeographyGenerator::physicalRadiusKm($world, $geography);
        $size = (int)($world['size'] ?? 0);
        return [
            'schema_version' => 1,
            'world' => ['id' => $worldId, 'seed' => $seed],
            'generator' => ['name' => 'CORE Planetary Climate Baseline', 'version' => 1],
            'parameters' => [
                'mean_surface_temperature_c' => round(-12 + (hexdec(substr($hash, 0, 4)) / 65535) * 42, 2),
                'equator_to_pole_temperature_drop_c' => 28.0,
                'elevation_lapse_rate_c_per_km' => 6.5,
                'axial_tilt_degrees' => (float)($world['physical']['axial_tilt_degrees'] ?? 0),
                'moisture_seed' => substr($hash, 8, 24),
                'hydrographics_fraction' => max(0, min(10, (int)($world['hydrographics'] ?? 5))) / 10,
                'atmosphere_profile' => (string)($world['atmosphere'] ?? 'uncharacterized'),
                'world_radius_km' => $radius,
                'world_size_code' => $size,
            ],
            'layers' => ['temperature', 'moisture', 'atmospheric_effects'],
        ];
    }
}
