<?php
declare(strict_types=1);

namespace Core\Modules\Characters;

/** Resolves the universe a character currently inhabits without changing its home assignment. */
final class CharacterUniverseContext
{
    public static function activeUniverseId(array $character): string
    {
        $active = trim((string)($character['active_universe_id'] ?? ''));
        return $active !== '' ? $active : trim((string)($character['universe_id'] ?? ''));
    }

    public static function activeSystemId(array $character): string
    {
        $current = trim((string)($character['current_system_id'] ?? ''));
        if ($current !== '') {
            return $current;
        }

        return trim((string)($character['location']['system_id'] ?? ''));
    }

    public static function isVisiting(array $character): bool
    {
        return self::activeUniverseId($character) !== trim((string)($character['universe_id'] ?? ''));
    }
}
