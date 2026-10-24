<?php
declare(strict_types=1);

namespace Core\Modules\Characters;

use Core\Modules\Universe\UniverseLifecycleGuard;
use Core\Modules\ContentCatalog;

use Core\Storage\JsonStorage;
use RuntimeException;

/** Deterministic, character-persistent scan results for the character's current system. */
final class CharacterScanService
{
    public function __construct(private JsonStorage $storage) {}

    /** @return array{scan_status:string,scan:array,character:array} */
    public function scanCurrentSystem(string $universeId, string $characterId, int $expectedRevision): array
    {
        $universeId = trim($universeId);
        $characterId = trim($characterId);
        if ($universeId === '' || $characterId === '') throw new RuntimeException('universe_id and character_id are required.');
        if ($expectedRevision < 0) throw new RuntimeException('Expected character revision must be zero or greater.');

        $universe = $this->storage->read('universes', $universeId);
        if ($universe === null || ($universe['id'] ?? null) !== $universeId) throw new RuntimeException('The selected universe does not exist.');
        UniverseLifecycleGuard::assertCanPerformGameplayAction($universe);
        $systems = $universe['baseline']['systems'] ?? null;
        if (!is_array($systems)) throw new RuntimeException('The selected universe has no valid system list.');
        $systemsById = [];
        foreach ($systems as $system) if (is_array($system) && isset($system['id'])) $systemsById[(string)$system['id']] = $system;
        $fingerprint = (string)($universe['baseline']['seed_fingerprint'] ?? $universe['id']);
        $discoveryDocument = ContentCatalog::document('discoveries');
        $signals = $discoveryDocument['entries'];
        $scanRules = $discoveryDocument['scan_rules'] ?? ['minimum_signals' => 1, 'maximum_signals' => 3];
        $minimumSignals = (int)($scanRules['minimum_signals'] ?? 1);
        $maximumSignals = (int)($scanRules['maximum_signals'] ?? 3);
        if ($minimumSignals < 1 || $maximumSignals < $minimumSignals) throw new RuntimeException('Discovery scan_rules in config/content/discoveries.json are invalid.');
        $scanStatus = 'scanned';
        $scanResult = [];

        $character = $this->storage->transact('characters', $characterId, static function (array $character) use ($universeId, $expectedRevision, $systemsById, $fingerprint, $signals, $minimumSignals, $maximumSignals, &$scanStatus, &$scanResult): array {
            if (($character['universe_id'] ?? null) !== $universeId) throw new RuntimeException('Character does not belong to the selected universe.');
            CharacterLifecycleGuard::assertCanPerformGameplayAction($character);
            $actualRevision = (int)($character['revision'] ?? 0);
            if ($actualRevision !== $expectedRevision) throw new RuntimeException('Character revision conflict: expected ' . $expectedRevision . ', current revision is ' . $actualRevision . '.');
            $systemId = (string)($character['current_system_id'] ?? $character['location']['system_id'] ?? '');
            if ($systemId === '' || !isset($systemsById[$systemId])) throw new RuntimeException('Character current system is not valid in the selected universe.');

            $scans = $character['scans'] ?? [];
            if (!is_array($scans)) $scans = [];
            $bySystem = $scans['systems'] ?? [];
            if (!is_array($bySystem)) $bySystem = [];
            if (isset($bySystem[$systemId]) && is_array($bySystem[$systemId])) {
                $scanStatus = 'already_scanned';
                $scanResult = $bySystem[$systemId];
                return $character;
            }

            $system = $systemsById[$systemId];
            $hash = hash('sha256', $fingerprint . ':scan:' . $systemId);
            $signalEntries = [];
            foreach ($signals as $type => $definition) $signalEntries[] = ['type' => $type] + $definition;
            if ($signalEntries === []) throw new RuntimeException('No discovery signal definitions are configured.');
            $effectiveMinimum = min($minimumSignals, count($signalEntries));
            $effectiveMaximum = min($maximumSignals, count($signalEntries));
            $count = $effectiveMinimum + (hexdec(substr($hash, 0, 2)) % ($effectiveMaximum - $effectiveMinimum + 1));
            $discoveries = [];
            $used = [];
            for ($i = 0; $i < $count; $i++) {
                $offset = (hexdec(substr($hash, 2 + ($i * 2), 2)) + $i) % count($signalEntries);
                while (isset($used[$offset])) $offset = ($offset + 1) % count($signalEntries);
                $used[$offset] = true;
                $signal = $signalEntries[$offset];
                $discoveries[] = [
                    'id' => 'dsc_' . substr(hash('sha256', $fingerprint . ':' . $systemId . ':' . $signal['type']), 0, 20),
                    'type' => $signal['type'],
                    'label' => $signal['label'],
                    'description' => $signal['description'],
                    'system_id' => $systemId,
                    'system_name' => (string)($system['name'] ?? $systemId),
                    'classification' => (string)($system['classification'] ?? 'unknown'),
                ];
            }
            $now = gmdate('c');
            $scanResult = [
                'system_id' => $systemId,
                'system_name' => (string)($system['name'] ?? $systemId),
                'scanned_at' => $now,
                'scan_revision' => $actualRevision + 1,
                'discoveries' => $discoveries,
            ];
            $bySystem[$systemId] = $scanResult;
            $scans['systems'] = $bySystem;
            $character['scans'] = $scans;
            $character['revision'] = $actualRevision + 1;
            $scanResult = $bySystem[$systemId];
            return $character;
        });

        return ['scan_status' => $scanStatus, 'scan' => $scanResult, 'character' => $character];
    }
}
