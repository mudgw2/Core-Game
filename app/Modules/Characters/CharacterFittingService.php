<?php
declare(strict_types=1);

namespace Core\Modules\Characters;

use Core\Modules\Universe\UniverseLifecycleGuard;
use Core\Modules\ContentCatalog;

use Core\Storage\JsonStorage;
use RuntimeException;

/** Revision-checked ship module installation backed by character inventory. */
final class CharacterFittingService
{
    public function __construct(private JsonStorage $storage) {}

    public function install(string $universeId, string $characterId, string $moduleId, int $expectedRevision): array
    {
        $universeId = trim($universeId); $characterId = trim($characterId); $moduleId = trim($moduleId);
        if ($universeId === '' || $characterId === '' || $moduleId === '') throw new RuntimeException('universe_id, character_id, and module_id are required.');
        if ($expectedRevision < 0) throw new RuntimeException('Expected character revision must be zero or greater.');
        $moduleCatalog = ContentCatalog::entries('ship_modules');
        $items = ContentCatalog::entries('items');
        if (!isset($moduleCatalog[$moduleId])) throw new RuntimeException('Unknown ship module.');
        $universe = $this->storage->read('universes', $universeId);
        if ($universe === null || ($universe['id'] ?? null) !== $universeId) throw new RuntimeException('The selected universe does not exist.');
        UniverseLifecycleGuard::assertCanPerformGameplayAction($universe);
        $module = $moduleCatalog[$moduleId];
        $result = [];
        $character = $this->storage->transact('characters', $characterId, static function (array $character) use ($universeId, $expectedRevision, $moduleId, $module, $items, &$result): array {
            if (($character['universe_id'] ?? null) !== $universeId) throw new RuntimeException('Character does not belong to the selected universe.');
            CharacterLifecycleGuard::assertCanPerformGameplayAction($character);
            $revision = (int)($character['revision'] ?? 0);
            if ($revision !== $expectedRevision) throw new RuntimeException('Character revision conflict: expected ' . $expectedRevision . ', current revision is ' . $revision . '.');
            $ship = is_array($character['ship'] ?? null) ? $character['ship'] : [];
            $modules = is_array($ship['modules'] ?? null) ? $ship['modules'] : [];
            if (isset($modules[$module['slot']])) throw new RuntimeException('The ' . $module['slot'] . ' slot is already occupied.');
            $inventory = is_array($character['inventory'] ?? null) ? $character['inventory'] : [];
            $resources = is_array($inventory['resources'] ?? null) ? $inventory['resources'] : [];
            foreach ($module['inputs'] as $resource => $amount) {
                if ((int)($resources[$resource] ?? 0) < $amount) throw new RuntimeException('Insufficient ' . (string)($items[$resource]['label'] ?? str_replace('_', ' ', $resource)) . ': need ' . $amount . ', have ' . (int)($resources[$resource] ?? 0) . '.');
            }
            $now = gmdate('c');
            $ledger = is_array($inventory['ledger'] ?? null) ? $inventory['ledger'] : [];
            foreach ($module['inputs'] as $resource => $amount) {
                $resources[$resource] = (int)$resources[$resource] - $amount;
                $resourceLabel = (string)($items[$resource]['label'] ?? $resource);
                $ledger[] = ['id' => 'fit_' . substr(hash('sha256', $character['id'] . ':' . $moduleId . ':' . $revision . ':' . $resource), 0, 20), 'type' => 'module_install_input', 'module_id' => $moduleId, 'resource' => $resource, 'label' => $resourceLabel, 'quantity' => -$amount, 'recorded_at' => $now];
            }
            $modules[$module['slot']] = ['id' => $moduleId, 'label' => $module['label'], 'description' => $module['description'], 'installed_at' => $now];
            $ship['modules'] = $modules; $ship['updated_at'] = $now;
            $inventory['resources'] = $resources; $inventory['ledger'] = $ledger;
            $character['ship'] = $ship; $character['inventory'] = $inventory; $character['revision'] = $revision + 1;
            $result = ['id' => $moduleId, 'label' => $module['label'], 'slot' => $module['slot'], 'description' => $module['description'], 'inputs' => $module['inputs']];
            return $character;
        });
        return ['fitting_status' => 'installed', 'module' => $result, 'character' => $character];
    }

    public static function catalog(): array
    {
        return ContentCatalog::listEntries('ship_modules');
    }
}
