<?php
declare(strict_types=1);

namespace Core\Modules\Characters;

use Core\Storage\JsonStorage;
use RuntimeException;

/** Small, revision-checked crafting loop for character-bound discovery resources. */
final class CharacterCraftingService
{
    private const RECIPES = [
        'refine_ore' => [
            'label' => 'Refine raw ore',
            'inputs' => ['raw_ore' => 3],
            'output' => ['refined_alloy', 'Refined alloy', 1],
        ],
        'assemble_components' => [
            'label' => 'Assemble ship components',
            'inputs' => ['salvage_parts' => 2],
            'output' => ['ship_components', 'Ship components', 1],
        ],
        'analyze_research' => [
            'label' => 'Analyze research data',
            'inputs' => ['research_data' => 2],
            'output' => ['tech_insights', 'Tech insights', 1],
        ],
    ];

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
        if (!isset(self::RECIPES[$recipeId])) throw new RuntimeException('Unknown crafting recipe.');
        $universe = $this->storage->read('universes', $universeId);
        if ($universe === null || ($universe['id'] ?? null) !== $universeId) throw new RuntimeException('The selected universe does not exist.');

        $definition = self::RECIPES[$recipeId];
        $result = [];
        $character = $this->storage->transact('characters', $characterId, static function (array $character) use ($universeId, $expectedRevision, $quantity, $recipeId, $definition, &$result): array {
            if (($character['universe_id'] ?? null) !== $universeId) throw new RuntimeException('Character does not belong to the selected universe.');
            $actualRevision = (int)($character['revision'] ?? 0);
            if ($actualRevision !== $expectedRevision) throw new RuntimeException('Character revision conflict: expected ' . $expectedRevision . ', current revision is ' . $actualRevision . '.');
            $inventory = is_array($character['inventory'] ?? null) ? $character['inventory'] : [];
            $resources = is_array($inventory['resources'] ?? null) ? $inventory['resources'] : [];
            foreach ($definition['inputs'] as $resource => $perCraft) {
                $needed = $perCraft * $quantity;
                if ((int)($resources[$resource] ?? 0) < $needed) throw new RuntimeException('Insufficient ' . str_replace('_', ' ', $resource) . ': need ' . $needed . ', have ' . (int)($resources[$resource] ?? 0) . '.');
            }
            $ledger = is_array($inventory['ledger'] ?? null) ? $inventory['ledger'] : [];
            $now = gmdate('c');
            foreach ($definition['inputs'] as $resource => $perCraft) {
                $needed = $perCraft * $quantity;
                $resources[$resource] = (int)$resources[$resource] - $needed;
                $ledger[] = ['id' => 'craft_' . substr(hash('sha256', $character['id'] . ':' . $recipeId . ':' . $expectedRevision . ':' . $resource), 0, 20), 'type' => 'craft_input', 'recipe_id' => $recipeId, 'resource' => $resource, 'label' => ucwords(str_replace('_', ' ', $resource)), 'quantity' => -$needed, 'recorded_at' => $now];
            }
            [$outputKey, $outputLabel, $perCraftOutput] = $definition['output'];
            $outputQuantity = $perCraftOutput * $quantity;
            $resources[$outputKey] = (int)($resources[$outputKey] ?? 0) + $outputQuantity;
            $ledger[] = ['id' => 'craft_' . substr(hash('sha256', $character['id'] . ':' . $recipeId . ':' . $expectedRevision . ':output'), 0, 20), 'type' => 'craft_output', 'recipe_id' => $recipeId, 'resource' => $outputKey, 'label' => $outputLabel, 'quantity' => $outputQuantity, 'recorded_at' => $now];
            $inventory['resources'] = $resources;
            $inventory['ledger'] = $ledger;
            $character['inventory'] = $inventory;
            $character['revision'] = $actualRevision + 1;
            $result = ['id' => $recipeId, 'label' => $definition['label'], 'inputs' => array_map(static fn(int $amount): int => $amount * $quantity, $definition['inputs']), 'output' => ['resource' => $outputKey, 'label' => $outputLabel, 'quantity' => $outputQuantity], 'quantity' => $quantity];
            return $character;
        });
        return ['crafting_status' => 'crafted', 'recipe' => $result, 'character' => $character];
    }

    /** Public recipe catalog for the Workbench. */
    public static function recipes(): array
    {
        $recipes = [];
        foreach (self::RECIPES as $id => $recipe) {
            [$resource, $label, $outputQuantity] = $recipe['output'];
            $recipes[] = ['id' => $id, 'label' => $recipe['label'], 'inputs' => $recipe['inputs'], 'output' => ['resource' => $resource, 'label' => $label, 'quantity' => $outputQuantity]];
        }
        return $recipes;
    }
}
