<?php
declare(strict_types=1);

namespace Core\Modules\Universe;

/**
 * Deterministic, seed-derived world profiles. World records are calculated from
 * the universe seed and stable system ID, so existing universe JSON needs no migration.
 */
final class WorldGenerator
{
    private const HEX = '0123456789ABCDEF';

    /** @return list<array<string, int|string>> */
    public function generateForSystem(string $universeSeed, array $system): array
    {
        $seed = trim($universeSeed);
        $systemId = (string)($system['id'] ?? '');
        if ($seed === '' || $systemId === '') return [];

        $constellationId = trim((string)($system['constellation_id'] ?? ''));
        $constellationSeed = trim((string)($system['constellation_seed'] ?? ''));
        $systemSeed = trim((string)($system['seed'] ?? ''));
        if ($systemSeed === '') {
            // Legacy systems receive a deterministic derived seed without rewriting saved universe data.
            $parentSeed = $constellationSeed !== '' ? $constellationSeed : $seed;
            $systemSeed = 'SYS-' . strtoupper(substr(hash('sha256', "CORE-SYSTEM-SEED-V2\n" . $parentSeed . "\n" . $systemId), 0, 24));
        }
        $systemHash = hash('sha256', "CORE-WORLD-GENERATION-V2\n" . $systemSeed . "\n" . $systemId);
        $worldCount = 1 + $this->roll($systemHash, 0, 2, 0, 8);
        $systemName = trim((string)($system['name'] ?? 'Unknown System')) ?: 'Unknown System';
        $worlds = [];
        $usedUwps = [];

        for ($slot = 1; $slot <= $worldCount; $slot++) {
            $worldSeed = 'WLD-' . strtoupper(substr(hash('sha256', "CORE-WORLD-SEED-V1\n" . $systemSeed . ":world:" . $slot), 0, 24));
            $hash = hash('sha256', "CORE-WORLD-PROFILE-V2\n" . $worldSeed);
            $worldRoll = $this->roll($hash, 0, 2, 0, 255);
            $size = $this->roll($hash, 2, 4, 0, 10);
            $atmosphere = $this->roll($hash, 6, 4, 0, 15);
            $hydrographics = $this->roll($hash, 10, 4, 0, 10);
            $population = $this->roll($hash, 14, 4, 0, 10);
            $government = $population === 0 ? 0 : $this->roll($hash, 18, 4, 0, 15);
            $lawLevel = $population === 0 ? 0 : $this->roll($hash, 22, 4, 0, 15);
            $techLevel = $this->roll($hash, 26, 4, 0, 15);
            $starport = ['A', 'B', 'C', 'D', 'E', 'X'][$this->roll($hash, 30, 2, 0, 5)];

            if ($worldRoll < 16) {
                $type = 'gas_giant';
                $size = 10;
                $atmosphere = 10 + $this->roll($hash, 32, 2, 0, 5);
                $hydrographics = 0;
                $population = 0;
                $government = 0;
                $lawLevel = 0;
                $techLevel = $this->roll($hash, 34, 2, 0, 15);
                $starport = 'X';
            } elseif ($size === 0) {
                $type = 'asteroid';
            } elseif ($atmosphere === 0) {
                $type = 'airless';
            } elseif ($atmosphere >= 10) {
                $type = 'exotic';
            } elseif ($hydrographics >= 7) {
                $type = 'ocean';
            } elseif ($hydrographics <= 1) {
                $type = 'desert';
            } elseif ($size >= 8) {
                $type = 'large_terrestrial';
            } else {
                $type = 'temperate';
            }

            $uwp = $starport
                . self::HEX[$size]
                . self::HEX[$atmosphere]
                . self::HEX[$hydrographics]
                . self::HEX[$population]
                . self::HEX[$government]
                . self::HEX[$lawLevel]
                . self::HEX[$techLevel];
            // UWP is a profile, not an identity. Resolve the rare in-system collision
            // deterministically so each world in one system has its own displayed UWP.
            while (isset($usedUwps[$uwp])) {
                $techLevel = ($techLevel + 1) % 16;
                $uwp = substr($uwp, 0, 7) . self::HEX[$techLevel];
            }
            $usedUwps[$uwp] = true;

            $worldId = 'wld_' . substr($hash, 0, 20);
            $physical = $this->physicalProfile($worldSeed, $type, $size, $atmosphere, $hydrographics);
            $moons = $this->generateMoons($worldSeed, $type, $size);
            // Universe 1337 / Constellation 01 / System 01 is a controlled
            // astronomy test fixture. Add satellites only to its worlds that
            // would otherwise have none, without changing any other seed.
            if ($seed === '1337' && $systemId === 'sys_7c6a9d2f7d9b50a9' && $moons === []) {
                $moons[] = $this->generateTestMoon($worldSeed, $slot, $type);
            }

            $worlds[] = [
                'id' => $worldId,
                'seed' => $worldSeed,
                'system_seed' => $systemSeed,
                'system_id' => $systemId,
                'constellation_id' => $constellationId,
                'constellation_seed' => $constellationSeed,
                'name' => 'World ' . preg_replace('/^System\\s+/i', '', $systemName) . '-' . $slot,
                'orbital_slot' => $slot,
                'type' => $type,
                'uwp' => $uwp,
                'starport' => $starport,
                'size' => $size,
                'atmosphere' => $atmosphere,
                'hydrographics' => $hydrographics,
                'population' => $population,
                'government' => $government,
                'law_level' => $lawLevel,
                'tech_level' => $techLevel,
                'physical' => $physical,
                'moons' => $moons,
            ];
        }

        return $worlds;
    }


    /**
     * Lightweight deterministic estimates for world presentation and simulation.
     * These are explicitly estimates, not measured astrophysical values.
     */
    private function physicalProfile(string $worldSeed, string $type, int $size, int $atmosphere, int $hydrographics): array
    {
        $radiusKm = $size === 0 ? 80 : $size * 800;
        $densityFactor = match ($type) {
            'gas_giant' => 0.24,
            'asteroid' => 0.55,
            default => 0.82 + ($this->roll(hash('sha256', $worldSeed), 36, 2, 0, 35) / 100),
        };
        $radiusEarth = max(0.0125, $radiusKm / 6371);
        $massEarth = round($densityFactor * ($radiusEarth ** 3), 6);
        $gravityEarth = round($densityFactor * $radiusEarth, 4);
        $rotationHours = 8 + $this->roll(hash('sha256', $worldSeed), 38, 4, 0, 6400) / 100;
        $axialTilt = round($this->roll(hash('sha256', $worldSeed), 42, 4, 0, 4500) / 100, 2);
        return [
            'model' => 'seeded_estimate_v1',
            'radius_km' => $radiusKm,
            'mass_earth' => $massEarth,
            'surface_gravity_g' => $gravityEarth,
            'rotation_period_hours' => round($rotationHours, 2),
            'axial_tilt_degrees' => $axialTilt,
            'atmosphere_code' => $atmosphere,
            'hydrographics_code' => $hydrographics,
            'estimated' => true,
        ];
    }

    /** @return list<array<string, int|float|string>> */
    private function generateMoons(string $worldSeed, string $type, int $size): array
    {
        if ($type === 'asteroid' || $size < 2) return [];
        $hash = hash('sha256', "CORE-MOON-SYSTEM-V1\n" . $worldSeed);
        $count = $this->roll($hash, 0, 2, 0, 3);
        if ($type === 'gas_giant') $count = max(1, $count);
        $moons = [];
        for ($index = 0; $index < $count; $index++) {
            $offset = 2 + ($index * 10);
            $diameter = 250 + $this->roll($hash, $offset, 4, 0, 7500);
            $orbitRadius = round(8 + ($index * 5) + $this->roll($hash, $offset + 4, 4, 0, 1200) / 100, 2);
            $period = round(12 + $this->roll($hash, $offset + 8, 4, 0, 9000) / 100, 2);
            $moons[] = [
                'id' => 'moon_' . substr(hash('sha256', $worldSeed . ':moon:' . ($index + 1)), 0, 16),
                'name' => 'Moon ' . ($index + 1),
                'type' => 'moon',
                'diameter_km' => $diameter,
                'orbital_radius_planet_radii' => $orbitRadius,
                'orbital_period_hours' => $period,
                'inclination_degrees' => round($this->roll($hash, $offset + 12, 4, 0, 3000) / 100, 2),
                'phase_degrees' => $this->roll($hash, $offset + 16, 4, 0, 359),
            ];
        }
        return $moons;
    }

    /** @return array<string, int|float|string> */
    private function generateTestMoon(string $worldSeed, int $slot, string $worldType): array
    {
        $hash = hash('sha256', "CORE-1337-SYSTEM-01-MOON-TEST\n" . $worldSeed . "\n" . $slot);
        return [
            'id' => 'moon_test_' . substr($hash, 0, 16),
            'name' => 'Test Moon 1',
            'type' => 'moon',
            'diameter_km' => $worldType === 'asteroid' ? 420 : 2400,
            'orbital_radius_planet_radii' => $worldType === 'asteroid' ? 8.5 : 16.5,
            'orbital_period_hours' => 36 + (hexdec(substr($hash, 0, 4)) % 48),
            'inclination_degrees' => 12 + (hexdec(substr($hash, 4, 2)) % 12),
            'phase_degrees' => hexdec(substr($hash, 6, 4)) % 360,
            'test_fixture' => 'universe-1337-system-01',
        ];
    }

    private function roll(string $hash, int $offset, int $length = 2, int $minimum = 0, int $maximum = 255): int
    {
        $value = hexdec(substr($hash, $offset, $length));
        return $minimum + ($value % ($maximum - $minimum + 1));
    }
}
