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

        $systemHash = hash('sha256', "CORE-WORLD-GENERATION-V1\n" . $seed . "\n" . $systemId);
        $worldCount = 1 + $this->roll($systemHash, 0, 2, 0, 8);
        $systemName = trim((string)($system['name'] ?? 'Unknown System')) ?: 'Unknown System';
        $worlds = [];

        for ($slot = 1; $slot <= $worldCount; $slot++) {
            $hash = hash('sha256', $systemHash . ':world:' . $slot);
            $worldRoll = $this->roll($hash, 0, 100);
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
                $atmosphere = 0;
                $hydrographics = 0;
                $population = 0;
                $government = 0;
                $lawLevel = 0;
                $techLevel = 0;
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

            $worlds[] = [
                'id' => 'wld_' . substr($hash, 0, 20),
                'name' => $systemName . '-' . $slot,
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
            ];
        }

        return $worlds;
    }

    private function roll(string $hash, int $offset, int $length = 2, int $minimum = 0, int $maximum = 255): int
    {
        $value = hexdec(substr($hash, $offset, $length));
        return $minimum + ($value % ($maximum - $minimum + 1));
    }
}
