<?php
declare(strict_types=1);

namespace Core\Modules;

use Core\Core\Logger;
use RuntimeException;

/** Loads game rules and definitions from editable JSON content files, not PHP constants. */
final class ContentCatalog
{
    private const FILES = ['items' => 'items.json', 'recipes' => 'recipes.json', 'discoveries' => 'discoveries.json', 'ship_modules' => 'ship-modules.json'];

    public static function document(string $name): array
    {
        if (!isset(self::FILES[$name])) throw new RuntimeException('Unknown content catalog: ' . $name);
        $path = CORE_ROOT . '/config/content/' . self::FILES[$name];
        $raw = @file_get_contents($path);
        if ($raw === false) {
            Logger::error('Game content JSON could not be read', ['catalog' => $name, 'path' => $path]);
            throw new RuntimeException('Game content catalog is unavailable: ' . $name);
        }
        try { $document = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) {
            Logger::error('Game content JSON is malformed', ['catalog' => $name, 'path' => $path, 'message' => $e->getMessage()]);
            throw new RuntimeException('Game content catalog contains invalid JSON: ' . $name, 0, $e);
        }
        if (!is_array($document) || !is_array($document['entries'] ?? null) || !is_int($document['schema_version'] ?? null)) {
            Logger::error('Game content JSON has an invalid structure', ['catalog' => $name, 'path' => $path]);
            throw new RuntimeException('Game content catalog has an invalid structure: ' . $name);
        }
        foreach ($document['entries'] as $id => $entry) {
            if (!is_string($id) || !preg_match('/^[a-z][a-z0-9_]{1,63}$/', $id) || !is_array($entry)) {
                Logger::error('Game content entry failed validation', ['catalog' => $name, 'entry_id' => (string)$id]);
                throw new RuntimeException('Game content catalog contains an invalid entry: ' . $name);
            }
        }
        try { self::validateDefinitions($name, $document['entries']); }
        catch (\Throwable $e) { Logger::error('Game content definitions failed validation', ['catalog' => $name, 'path' => $path, 'message' => $e->getMessage()]); throw $e; }
        if ($name === 'discoveries') {
            $rules = $document['scan_rules'] ?? ['minimum_signals' => 1, 'maximum_signals' => 3];
            if (!is_array($rules) || !is_int($rules['minimum_signals'] ?? null) || !is_int($rules['maximum_signals'] ?? null) || $rules['minimum_signals'] < 1 || $rules['maximum_signals'] < $rules['minimum_signals']) {
                Logger::error('Discovery scan rules are invalid', ['path' => $path, 'scan_rules' => $rules]);
                throw new RuntimeException('Discovery scan rules are invalid in config/content/discoveries.json.');
            }
        }
        $document['catalog'] = $name;
        return $document;
    }

    private static function validateDefinitions(string $name, array $entries): void
    {
        $items = null;
        if ($name !== 'items') {
            $itemDocument = self::document('items');
            $items = $itemDocument['entries'];
        }
        foreach ($entries as $id => $entry) {
            if ($name === 'items') {
                if (!is_string($entry['label'] ?? null) || trim($entry['label']) === '' || !is_string($entry['category'] ?? null)) throw new RuntimeException('Invalid item definition: ' . $id);
            } elseif ($name === 'recipes') {
                if (!is_string($entry['label'] ?? null) || trim($entry['label']) === '' || !is_array($entry['inputs'] ?? null) || $entry['inputs'] === []) throw new RuntimeException('Invalid recipe definition: ' . $id);
                foreach ($entry['inputs'] as $itemId => $quantity) if (!isset($items[$itemId]) || !is_int($quantity) || $quantity < 1) throw new RuntimeException('Invalid recipe input: ' . $id . '/' . $itemId);
                $output = $entry['output'] ?? [];
                if (!is_array($output) || !isset($items[$output['resource'] ?? '']) || !is_int($output['quantity'] ?? null) || $output['quantity'] < 1) throw new RuntimeException('Invalid recipe output: ' . $id);
            } elseif ($name === 'discoveries') {
                $reward = $entry['reward'] ?? [];
                if (!is_string($entry['label'] ?? null) || trim($entry['label']) === '' || !is_string($entry['description'] ?? null) || !is_string($entry['outcome'] ?? null) || !is_array($reward) || !isset($items[$reward['resource'] ?? '']) || !is_int($reward['minimum'] ?? null) || !is_int($reward['maximum'] ?? null) || $reward['minimum'] < 1 || $reward['maximum'] < $reward['minimum']) throw new RuntimeException('Invalid discovery definition: ' . $id);
            } elseif ($name === 'ship_modules') {
                if (!is_string($entry['label'] ?? null) || trim($entry['label']) === '' || !is_string($entry['slot'] ?? null) || trim($entry['slot']) === '' || !is_array($entry['inputs'] ?? null) || $entry['inputs'] === []) throw new RuntimeException('Invalid ship module definition: ' . $id);
                foreach ($entry['inputs'] as $itemId => $quantity) if (!isset($items[$itemId]) || !is_int($quantity) || $quantity < 1) throw new RuntimeException('Invalid ship module input: ' . $id . '/' . $itemId);
            }
        }
        if ($name === 'ship_modules') {
            $slots = [];
            foreach ($entries as $id => $entry) { $slot = $entry['slot']; if (isset($slots[$slot])) throw new RuntimeException('Duplicate ship module slot definition: ' . $slot); $slots[$slot] = $id; }
        }
    }

    /** @return array<string,array> keyed by stable content ID */
    public static function entries(string $name): array
    {
        return self::document($name)['entries'];
    }

    /** @return list<array> records with their stable ID included */
    public static function listEntries(string $name): array
    {
        $entries = [];
        foreach (self::entries($name) as $id => $entry) $entries[] = ['id' => $id] + $entry;
        return $entries;
    }

    public static function allDocuments(): array
    {
        $result = [];
        foreach (array_keys(self::FILES) as $name) $result[$name] = self::document($name);
        return $result;
    }
}
