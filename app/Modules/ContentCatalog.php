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

    private static function validateDefinitions(string $name, array $entries, ?array $itemDefinitions = null): void
    {
        $items = null;
        if ($name !== 'items') {
            $items = $itemDefinitions ?? self::document('items')['entries'];
        }
        foreach ($entries as $id => $entry) {
            if ($name === 'items') {
                if (!is_string($entry['label'] ?? null) || trim($entry['label']) === '' || strlen($entry['label']) > 120 || !is_string($entry['category'] ?? null) || !is_string($entry['description'] ?? '') || strlen((string)($entry['description'] ?? '')) > 2000 || !is_bool($entry['stackable'] ?? null)) throw new RuntimeException('Invalid item definition: ' . $id);
                $categories = self::itemCategoriesDocument()['entries'];
                if (!isset($categories[$entry['category']])) throw new RuntimeException('Invalid item category for ' . $id . ': ' . (string)$entry['category'] . '. Choose a category defined in config/content/item-categories.json.');
            } elseif ($name === 'recipes') {
                if (!is_string($entry['label'] ?? null) || trim($entry['label']) === '' || strlen($entry['label']) > 120 || !is_string($entry['description'] ?? '') || strlen((string)($entry['description'] ?? '')) > 2000 || !is_array($entry['inputs'] ?? null) || $entry['inputs'] === []) throw new RuntimeException('Invalid recipe definition: ' . $id);
                foreach ($entry['inputs'] as $itemId => $quantity) if (!isset($items[$itemId]) || !is_int($quantity) || $quantity < 1 || $quantity > 1000000) throw new RuntimeException('Invalid recipe input: ' . $id . '/' . $itemId);
                $output = $entry['output'] ?? [];
                if (!is_array($output) || !isset($items[$output['resource'] ?? '']) || !is_int($output['quantity'] ?? null) || $output['quantity'] < 1 || $output['quantity'] > 1000000) throw new RuntimeException('Invalid recipe output: ' . $id);
            } elseif ($name === 'discoveries') {
                $reward = $entry['reward'] ?? [];
                if (!is_string($entry['label'] ?? null) || trim($entry['label']) === '' || !is_string($entry['description'] ?? null) || !is_string($entry['outcome'] ?? null) || strlen($entry['label']) > 120 || strlen($entry['description']) > 2000 || strlen($entry['outcome']) > 2000 || !is_array($reward) || !isset($items[$reward['resource'] ?? '']) || !is_int($reward['minimum'] ?? null) || !is_int($reward['maximum'] ?? null) || $reward['minimum'] < 1 || $reward['maximum'] < $reward['minimum'] || $reward['maximum'] > 1000000) throw new RuntimeException('Invalid discovery definition: ' . $id);
            } elseif ($name === 'ship_modules') {
                if (!is_string($entry['label'] ?? null) || trim($entry['label']) === '' || !is_string($entry['slot'] ?? null) || trim($entry['slot']) === '' || strlen($entry['label']) > 120 || strlen($entry['slot']) > 80 || !is_string($entry['description'] ?? '') || strlen((string)($entry['description'] ?? '')) > 2000 || !is_array($entry['inputs'] ?? null) || $entry['inputs'] === []) throw new RuntimeException('Invalid ship module definition: ' . $id);
                foreach ($entry['inputs'] as $itemId => $quantity) if (!isset($items[$itemId]) || !is_int($quantity) || $quantity < 1 || $quantity > 1000000) throw new RuntimeException('Invalid ship module input: ' . $id . '/' . $itemId);
            }
        }
    }


    /** Validate a complete candidate set, including cross-catalog references. */
    public static function validateDocuments(array $documents): void
    {
        $required = array_keys(self::FILES);
        foreach ($required as $name) {
            $document = $documents[$name] ?? null;
            if (!is_array($document) || !is_int($document['schema_version'] ?? null) || $document['schema_version'] !== 1 || !is_array($document['entries'] ?? null)) {
                throw new RuntimeException('Invalid schema or entries for content catalog: ' . $name);
            }
            if (isset($document['description']) && (!is_string($document['description']) || strlen($document['description']) > 2000)) {
                throw new RuntimeException('Catalog description must be a string no longer than 2000 characters: ' . $name);
            }
            if (count($document['entries']) > 5000) throw new RuntimeException('Catalog contains more than 5000 entries: ' . $name);
            if (isset($document['revision']) && (!is_int($document['revision']) || $document['revision'] < 1)) throw new RuntimeException('Catalog revision must be a positive integer: ' . $name);
            if (isset($document['content_version']) && (!is_string($document['content_version']) || strlen($document['content_version']) > 80)) throw new RuntimeException('Catalog content_version must be a string of at most 80 characters: ' . $name);
            foreach ($document['entries'] as $id => $entry) {
                if (!is_string($id) || !preg_match('/^[a-z][a-z0-9_]{1,63}$/', $id) || !is_array($entry)) {
                    throw new RuntimeException('Invalid content entry ID or definition in catalog: ' . $name);
                }
            }
        }
        $items = $documents['items']['entries'];
        foreach ($required as $name) {
            self::validateDefinitions($name, $documents[$name]['entries'], $items);
        }
        $rules = $documents['discoveries']['scan_rules'] ?? ['minimum_signals' => 1, 'maximum_signals' => 3];
        if (!is_array($rules) || !is_int($rules['minimum_signals'] ?? null) || !is_int($rules['maximum_signals'] ?? null) || $rules['minimum_signals'] < 1 || $rules['maximum_signals'] < $rules['minimum_signals'] || $rules['maximum_signals'] > 100) {
            throw new RuntimeException('Discovery scan rules must have integer bounds from 1 to 100, with maximum >= minimum.');
        }
    }

    /** Return the allow-listed public catalog name to on-disk filename mapping. */
    public static function catalogNames(): array
    {
        return array_keys(self::FILES);
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

    /** Load the JSON-backed category definitions used by the item editor and item validation. */
    public static function itemCategoriesDocument(): array
    {
        $path = CORE_ROOT . '/config/content/item-categories.json';
        $raw = @file_get_contents($path);
        if ($raw === false) {
            Logger::error('Item category JSON could not be read', ['path' => $path]);
            throw new RuntimeException('Item category definitions are unavailable.');
        }
        try { $document = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) {
            Logger::error('Item category JSON is malformed', ['path' => $path, 'message' => $e->getMessage()]);
            throw new RuntimeException('Item category definitions contain invalid JSON.', 0, $e);
        }
        if (!is_array($document) || ($document['schema_version'] ?? null) !== 1 || !is_array($document['entries'] ?? null) || $document['entries'] === []) {
            Logger::error('Item category JSON has an invalid structure', ['path' => $path]);
            throw new RuntimeException('Item category definitions have an invalid structure.');
        }
        foreach ($document['entries'] as $id => $entry) {
            if (!is_string($id) || !preg_match('/^[a-z][a-z0-9_]{1,63}$/', $id) || !is_array($entry) || !is_string($entry['label'] ?? null) || trim($entry['label']) === '' || strlen($entry['label']) > 80 || !is_string($entry['description'] ?? '') || strlen((string)($entry['description'] ?? '')) > 500) {
                Logger::error('Item category definition failed validation', ['path' => $path, 'category_id' => (string)$id]);
                throw new RuntimeException('Invalid item category definition: ' . (string)$id);
            }
        }
        $document['catalog'] = 'item_categories';
        return $document;
    }

    public static function allDocuments(): array
    {
        $result = [];
        foreach (array_keys(self::FILES) as $name) $result[$name] = self::document($name);
        $result['item_categories'] = self::itemCategoriesDocument();
        return $result;
    }
}
