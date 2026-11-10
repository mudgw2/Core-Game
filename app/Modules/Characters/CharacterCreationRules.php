<?php
declare(strict_types=1);

namespace Core\Modules\Characters;

use RuntimeException;

/** Server-authoritative, data-driven ability score generation and species adjustments. */
final class CharacterCreationRules
{
    public static function definitions(): array
    {
        $path = CORE_ROOT . '/storage/data/content/character-options.json';
        $options = is_file($path) ? json_decode((string)file_get_contents($path), true) : null;
        if (!is_array($options)) throw new RuntimeException('Character creation definitions are missing or invalid.');
        $rulesPath = CORE_ROOT . '/data/character-creation-rules.json';
        $rules = is_file($rulesPath) ? json_decode((string)file_get_contents($rulesPath), true) : null;
        if (!is_array($rules) || !is_array($rules['ability_generation'] ?? null) || !is_array($rules['species_adjustments'] ?? null)) {
            throw new RuntimeException('Character creation rules are missing or invalid.');
        }
        $options['ability_generation'] = $rules['ability_generation'];
        if (!is_array($options['species'] ?? null)) $options['species'] = [];
        foreach ($options['species'] as &$species) {
            if (!is_array($species) || !is_string($species['id'] ?? null)) continue;
            $speciesRule = $rules['species_adjustments'][$species['id']] ?? [];
            if (is_array($speciesRule)) {
                $species['ability_adjustments'] = $speciesRule['adjustments'] ?? [];
                if (isset($speciesRule['default_choice'])) $species['default_choice'] = $speciesRule['default_choice'];
                $species['ability_adjustment_description'] = (string)($speciesRule['description'] ?? '');
            }
        }
        unset($species);
        return $options;
    }

    /**
     * @return array{base_attributes:array<string,int>,attributes:array<string,int>,species_adjustments:array<string,int>,ability_points_spent:int,ability_point_budget:int,human_bonus_attribute:?string}
     */
    public static function validate(array $options, string $speciesId, array $submitted, ?string $humanBonusAttribute): array
    {
        $generation = $options['ability_generation'] ?? null;
        if (!is_array($generation) || ($generation['type'] ?? '') !== 'point_buy') {
            throw new RuntimeException('Ability score generation rules are missing or unsupported.');
        }
        $budget = filter_var($generation['points'] ?? null, FILTER_VALIDATE_INT);
        $minimum = filter_var($generation['minimum_score'] ?? null, FILTER_VALIDATE_INT);
        $maximum = filter_var($generation['maximum_score'] ?? null, FILTER_VALIDATE_INT);
        $costs = $generation['costs'] ?? null;
        if ($budget === false || $budget < 0 || $minimum === false || $maximum === false || $minimum > $maximum || !is_array($costs)) {
            throw new RuntimeException('Ability score generation rules are invalid.');
        }

        $definitions = $options['attributes'] ?? [];
        if (!is_array($definitions) || $definitions === []) throw new RuntimeException('No character abilities are configured.');
        $base = [];
        $spent = 0;
        foreach ($definitions as $definition) {
            if (!is_array($definition) || !is_string($definition['id'] ?? null)) continue;
            $id = $definition['id'];
            $raw = $submitted[$id] ?? 10;
            if (filter_var($raw, FILTER_VALIDATE_INT) === false) throw new RuntimeException('Ability ' . $id . ' must be a whole number.');
            $score = (int)$raw;
            if ($score < $minimum || $score > $maximum) throw new RuntimeException(ucfirst($id) . ' must be between ' . $minimum . ' and ' . $maximum . ' before species adjustments.');
            $cost = $costs[(string)$score] ?? null;
            if (filter_var($cost, FILTER_VALIDATE_INT) === false) throw new RuntimeException('No point-buy cost is configured for ability score ' . $score . '.');
            $base[$id] = $score;
            $spent += (int)$cost;
        }
        foreach ($submitted as $id => $_) {
            if (!array_key_exists((string)$id, $base)) throw new RuntimeException('Unknown character attribute: ' . (string)$id . '.');
        }
        if ($spent > $budget) throw new RuntimeException('Ability scores exceed the ' . $budget . '-point budget by ' . ($spent - $budget) . ' point(s).');

        $species = null;
        foreach (($options['species'] ?? []) as $entry) {
            if (is_array($entry) && ($entry['id'] ?? null) === $speciesId) { $species = $entry; break; }
        }
        if ($species === null) throw new RuntimeException('Select a valid character species from the available options.');
        $configuredAdjustments = $species['ability_adjustments'] ?? [];
        if (!is_array($configuredAdjustments)) throw new RuntimeException('Species ability adjustments are invalid.');
        $adjustments = [];
        if (isset($configuredAdjustments['choice'])) {
            $humanBonusAttribute = $humanBonusAttribute ?? (isset($species['default_choice']) ? (string)$species['default_choice'] : null);
            if ($humanBonusAttribute === null || !array_key_exists($humanBonusAttribute, $base)) {
                throw new RuntimeException('Select a valid ability for the human species bonus.');
            }
            $adjustments[$humanBonusAttribute] = (int)$configuredAdjustments['choice'];
        } else {
            if ($humanBonusAttribute !== null && $humanBonusAttribute !== '') throw new RuntimeException('The selected species does not use a flexible ability bonus.');
            foreach ($configuredAdjustments as $id => $modifier) {
                if (!array_key_exists((string)$id, $base) || filter_var($modifier, FILTER_VALIDATE_INT) === false) {
                    throw new RuntimeException('Species ability adjustments contain an unknown ability or invalid modifier.');
                }
                $adjustments[(string)$id] = (int)$modifier;
            }
        }

        $final = $base;
        foreach ($adjustments as $id => $modifier) $final[$id] += $modifier;
        foreach ($final as $id => $score) {
            if ($score < 1 || $score > 30) throw new RuntimeException('Species adjustments put ' . $id . ' outside the supported final score range.');
        }

        return [
            'base_attributes' => $base,
            'attributes' => $final,
            'species_adjustments' => $adjustments,
            'ability_points_spent' => $spent,
            'ability_point_budget' => (int)$budget,
            'human_bonus_attribute' => isset($configuredAdjustments['choice']) ? $humanBonusAttribute : null,
        ];
    }
}
