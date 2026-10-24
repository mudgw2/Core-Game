<?php
declare(strict_types=1);

namespace Core\Modules\Characters;

use RuntimeException;

/** Shared guard preventing gameplay mutations on disabled or locked characters. */
final class CharacterLifecycleGuard
{
    public static function assertCanPerformGameplayAction(array $character): void
    {
        $lifecycle = is_array($character['lifecycle'] ?? null) ? $character['lifecycle'] : [];
        if (($lifecycle['enabled'] ?? true) !== true) {
            throw new RuntimeException('Character is disabled. Enable it before performing gameplay actions.');
        }
        if (($lifecycle['locked'] ?? false) === true) {
            throw new RuntimeException('Character is locked. Unlock it before performing gameplay actions.');
        }
    }
}
