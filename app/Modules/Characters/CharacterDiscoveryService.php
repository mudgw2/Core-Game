<?php
declare(strict_types=1);

namespace Core\Modules\Characters;

use Core\Storage\JsonStorage;
use RuntimeException;

/** Persists character investigations of previously scanned signals. */
final class CharacterDiscoveryService
{
    private const OUTCOMES = [
        'mineral_signature' => 'Survey complete: recoverable mineral fragments have been collected and logged in the ship inventory.',
        'derelict_signal' => 'The signal led to a recoverable cache of salvage components. The recovered parts are now in inventory.',
        'spatial_anomaly' => 'Telemetry captured useful anomaly research data. The research sample is now in inventory.',
        'energy_residue' => 'The residue yielded intact energy cells. The recovered cells are now in inventory.',
        'unusual_asteroid' => 'A sample of the unusual asteroid was recovered and catalogued in inventory.',
        'unknown_beacon' => 'The beacon yielded a recoverable navigation data fragment. The intel fragment is now in inventory.',
    ];

    /** Resource key, display name, minimum and maximum quantity per discovery type. */
    private const REWARDS = [
        'mineral_signature' => ['raw_ore', 'Raw ore', 2, 5],
        'derelict_signal' => ['salvage_parts', 'Salvage parts', 1, 3],
        'spatial_anomaly' => ['research_data', 'Research data', 1, 2],
        'energy_residue' => ['energy_cells', 'Energy cells', 1, 3],
        'unusual_asteroid' => ['rare_ore', 'Rare ore', 1, 2],
        'unknown_beacon' => ['intel_fragments', 'Intel fragments', 1, 2],
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
        $resultReward = null;
        $character = $this->storage->transact('characters', $characterId, static function (array $character) use ($universeId, $discoveryId, $expectedRevision, &$investigationStatus, &$resultDiscovery, &$resultReward): array {
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
                    $discovery['outcome'] = self::OUTCOMES[$type] ?? 'The signal was examined and catalogued.';

                    // Reward quantity is stable for this discovery ID, so retries cannot reroll it.
                    $rewardDefinition = self::REWARDS[$type] ?? ['intel_fragments', 'Intel fragments', 1, 1];
                    [$resourceKey, $resourceLabel, $minimum, $maximum] = $rewardDefinition;
                    $roll = hexdec(substr(hash('sha256', $discoveryId . ':reward'), 0, 8));
                    $quantity = $minimum + ($roll % ($maximum - $minimum + 1));
                    $inventory = $character['inventory'] ?? [];
                    if (!is_array($inventory)) $inventory = [];
                    $resources = is_array($inventory['resources'] ?? null) ? $inventory['resources'] : [];
                    $resources[$resourceKey] = (int)($resources[$resourceKey] ?? 0) + $quantity;
                    $inventory['resources'] = $resources;
                    $ledger = is_array($inventory['ledger'] ?? null) ? $inventory['ledger'] : [];
                    $ledger[] = [
                        'id' => 'inv_' . substr(hash('sha256', $discoveryId . ':ledger'), 0, 20),
                        'discovery_id' => $discoveryId,
                        'resource' => $resourceKey,
                        'label' => $resourceLabel,
                        'quantity' => $quantity,
                        'system_id' => $currentSystemId,
                        'recorded_at' => gmdate('c'),
                    ];
                    $inventory['ledger'] = $ledger;
                    $character['inventory'] = $inventory;
                    $discovery['reward'] = ['resource' => $resourceKey, 'label' => $resourceLabel, 'quantity' => $quantity];
                    $resultReward = $discovery['reward'];
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

        if ($resultReward === null && is_array($resultDiscovery['reward'] ?? null)) $resultReward = $resultDiscovery['reward'];
        return ['investigation_status' => $investigationStatus, 'discovery' => $resultDiscovery, 'reward' => $resultReward, 'character' => $character];
    }
}
