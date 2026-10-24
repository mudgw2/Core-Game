<?php
declare(strict_types=1);

namespace Core\Modules\Characters;

use Core\Modules\Universe\UniverseLifecycleGuard;
use Core\Modules\ContentCatalog;

use Core\Storage\JsonStorage;
use RuntimeException;

/** Small, revision-checked crafting loop for character-bound discovery resources. */
final class CharacterCraftingService
{
    public function __construct(private JsonStorage $storage) {}

    /** @return array{recipe:array,crafting_status:string,character:array} */
    public function craft(string $universeId, string $characterId, string $recipeId, int $quantity, int $expectedRevision): array
    {
        $universeId = trim($universeId);
        $characterId = trim($characterId);
        $recipeId = trim($recipeId);
        if ($universeId === '' || $characterId === '' || $recipeId === '') throw new RuntimeException('universe_id, character_id, and recipe_id are required.');
        if ($quantity < 1 || $quantity > 10) throw new RuntimeException('Craft quantity must be between 1 and 10.');
        if ($expectedRevision < 0) throw new RuntimeException('Expected character revision must be zero or greater.');
        $recipes = ContentCatalog::entries('recipes');
        $items = ContentCatalog::entries('items');
        if (!isset($recipes[$recipeId])) throw new RuntimeException('Unknown crafting recipe.');
        $universe = $this->storage->read('universes', $universeId);
        if ($universe === null || ($universe['id'] ?? null) !== $universeId) throw new RuntimeException('The selected universe does not exist.');
        UniverseLifecycleGuard::assertCanPerformGameplayAction($universe);

        $definition = $recipes[$recipeId];
        $result = [];
        $character = $this->storage->transact('characters', $characterId, static function (array $character) use ($universeId, $expectedRevision, $quantity, $recipeId, $definition, $items, &$result): array {
            if (($character['universe_id'] ?? null) !== $universeId) throw new RuntimeException('Character does not belong to the selected universe.');
            CharacterLifecycleGuard::assertCanPerformGameplayAction($character);
            $actualRevision = (int)($character['revision'] ?? 0);
            if ($actualRevision !== $expectedRevision) throw new RuntimeException('Character revision conflict: expected ' . $expectedRevision . ', current revision is ' . $actualRevision . '.');
            $inventory = is_array($character['inventory'] ?? null) ? $character['inventory'] : [];
            $resources = is_array($inventory['resources'] ?? null) ? $inventory['resources'] : [];
            foreach ($definition['inputs'] as $resource => $perCraft) {
                $needed = $perCraft * $quantity;
                if ((int)($resources[$resource] ?? 0) < $needed) throw new RuntimeException('Insufficient ' . (string)($items[$resource]['label'] ?? str_replace('_', ' ', $resource)) . ': need ' . $needed . ', have ' . (int)($resources[$resource] ?? 0) . '.');
            }
            $ledger = is_array($inventory['ledger'] ?? null) ? $inventory['ledger'] : [];
            $now = gmdate('c');
            foreach ($definition['inputs'] as $resource => $perCraft) {
                $needed = $perCraft * $quantity;
                $resources[$resource] = (int)$resources[$resource] - $needed;
                $resourceLabel = (string)($items[$resource]['label'] ?? $resource);
                $ledger[] = ['id' => 'craft_' . substr(hash('sha256', $character['id'] . ':' . $recipeId . ':' . $expectedRevision . ':' . $resource), 0, 20), 'type' => 'craft_input', 'recipe_id' => $recipeId, 'resource' => $resource, 'label' => $resourceLabel, 'quantity' => -$needed, 'recorded_at' => $now];
            }
            $outputKey = (string)($definition['output']['resource'] ?? '');
            $perCraftOutput = (int)($definition['output']['quantity'] ?? 0);
            if ($outputKey === '' || $perCraftOutput < 1 || !isset($items[$outputKey])) throw new RuntimeException('Crafting recipe output is invalid or references an unknown item.');
            $outputLabel = (string)($items[$outputKey]['label'] ?? $outputKey);
            $outputQuantity = $perCraftOutput * $quantity;
            $resources[$outputKey] = (int)($resources[$outputKey] ?? 0) + $outputQuantity;
            $ledger[] = ['id' => 'craft_' . substr(hash('sha256', $character['id'] . ':' . $recipeId . ':' . $expectedRevision . ':output'), 0, 20), 'type' => 'craft_output', 'recipe_id' => $recipeId, 'resource' => $outputKey, 'label' => $outputLabel, 'quantity' => $outputQuantity, 'recorded_at' => $now];
            $inventory['resources'] = $resources;
            $inventory['ledger'] = $ledger;
            $character['inventory'] = $inventory;
            $character['revision'] = $actualRevision + 1;
            $result = ['id' => $recipeId, 'label' => (string)($definition['label'] ?? $recipeId), 'inputs' => array_map(static fn(int $amount): int => $amount * $quantity, $definition['inputs']), 'output' => ['resource' => $outputKey, 'label' => $outputLabel, 'quantity' => $outputQuantity], 'quantity' => $quantity];
            return $character;
        });
        return ['crafting_status' => 'crafted', 'recipe' => $result, 'character' => $character];
    }

    /** Public recipe catalog loaded from editable JSON definitions. */
    public static function recipes(): array
    {
        $items = ContentCatalog::entries('items');
        $recipes = [];
        foreach (ContentCatalog::entries('recipes') as $id => $recipe) {
            $outputId = (string)($recipe['output']['resource'] ?? '');
            $recipes[] = [
                'id' => $id,
                'label' => (string)($recipe['label'] ?? $id),
                'description' => (string)($recipe['description'] ?? ''),
                'inputs' => $recipe['inputs'] ?? [],
                'output' => ['resource' => $outputId, 'label' => (string)($items[$outputId]['label'] ?? $outputId), 'quantity' => (int)($recipe['output']['quantity'] ?? 0)],
            ];
        }
        return $recipes;
    }
}
