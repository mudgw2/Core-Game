<?php
declare(strict_types=1);

namespace Core\Modules\Characters;

use Core\Storage\JsonStorage;
use RuntimeException;

/** Persists character investigations of previously scanned signals. */
final class CharacterDiscoveryService
{
    private const OUTCOMES = [
        'mineral_signature' => 'Survey complete: the signal confirms a mineral-rich deposit. Detailed extraction data is not yet available.',
        'derelict_signal' => 'Signal traced to an abandoned automated vessel. Its transmission is archived for later salvage gameplay.',
        'spatial_anomaly' => 'Telemetry confirms a localized spatial distortion. Readings have been recorded for further analysis.',
        'energy_residue' => 'The residue is consistent with a recent high-output energy event. The spectral profile has been archived.',
        'unusual_asteroid' => 'Spectral analysis confirms an unusual asteroid composition. A survey record has been added.',
        'unknown_beacon' => 'The beacon yields a partial identifier and a repeating navigation pulse. The signal has been catalogued.',
    ];

    public function __construct(private JsonStorage $storage) {}

    /** @return array{investigation_status:string,discovery:array,character:array} */
    public function investigate(string $universeId, string $characterId, string $discoveryId, int $expectedRevision): array
    {
        $universeId = trim($universeId);
        $characterId = trim($characterId);
        $discoveryId = trim($discoveryId);
        if ($universeId === '' || $characterId === '' || $discoveryId === '') {
            throw new RuntimeException('universe_id, character_id, and discovery_id are required.');
        }
        if ($expectedRevision < 0) throw new RuntimeException('Expected character revision must be zero or greater.');

        $universe = $this->storage->read('universes', $universeId);
        if ($universe === null || ($universe['id'] ?? null) !== $universeId) {
            throw new RuntimeException('The selected universe does not exist.');
        }

        $investigationStatus = 'investigated';
        $resultDiscovery = [];
        $character = $this->storage->transact('characters', $characterId, static function (array $character) use ($universeId, $discoveryId, $expectedRevision, &$investigationStatus, &$resultDiscovery): array {
            if (($character['universe_id'] ?? null) !== $universeId) throw new RuntimeException('Character does not belong to the selected universe.');
            $actualRevision = (int)($character['revision'] ?? 0);
            if ($actualRevision !== $expectedRevision) throw new RuntimeException('Character revision conflict: expected ' . $expectedRevision . ', current revision is ' . $actualRevision . '.');

            $currentSystemId = (string)($character['current_system_id'] ?? $character['location']['system_id'] ?? '');
            $scans = $character['scans'] ?? [];
            $bySystem = is_array($scans) && is_array($scans['systems'] ?? null) ? $scans['systems'] : [];
            $found = false;
            foreach ($bySystem as $systemId => &$scan) {
                if (!is_array($scan) || !is_array($scan['discoveries'] ?? null)) continue;
                foreach ($scan['discoveries'] as &$discovery) {
                    if (!is_array($discovery) || ($discovery['id'] ?? null) !== $discoveryId) continue;
                    $found = true;
                    if ((string)$systemId !== $currentSystemId || (string)($discovery['system_id'] ?? '') !== $currentSystemId) {
                        throw new RuntimeException("Travel to the discovery system before investigating it.");
                    }
                    if (($discovery['status'] ?? 'uninvestigated') === 'investigated') {
                        $investigationStatus = 'already_investigated';
                        $resultDiscovery = $discovery;
                        unset($discovery, $scan);
                        return $character;
                    }
                    $type = (string)($discovery['type'] ?? '');
                    $discovery['status'] = 'investigated';
                    $discovery['investigated_at'] = gmdate('c');
                    $discovery['outcome'] = self::OUTCOMES[$type] ?? 'The signal was examined and catalogued. Further analysis may become available in a future system update.';
                    $resultDiscovery = $discovery;
                    unset($discovery, $scan);
                    $scans['systems'] = $bySystem;
                    $character['scans'] = $scans;
                    $character['revision'] = $actualRevision + 1;
                    return $character;
                }
                unset($discovery);
            }
            unset($scan);
            if (!$found) throw new RuntimeException("Discovery does not exist in this character scan records.");
            return $character;
        });

        return ['investigation_status' => $investigationStatus, 'discovery' => $resultDiscovery, 'character' => $character];
    }
}
