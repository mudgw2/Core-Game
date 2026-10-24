<?php
declare(strict_types=1);

namespace Core\Modules\Universe;

use RuntimeException;

/** Blocks gameplay mutations when a universe is disabled or locked. */
final class UniverseLifecycleGuard
{
    public static function assertCanPerformGameplayAction(array $universe): void
    {
        $lifecycle = is_array($universe['lifecycle'] ?? null) ? $universe['lifecycle'] : [];
        if (($lifecycle['enabled'] ?? true) !== true) {
            throw new RuntimeException('Universe is disabled. Enable it before performing gameplay actions.');
        }
        if (($lifecycle['locked'] ?? false) === true) {
            throw new RuntimeException('Universe is locked. Unlock it before performing gameplay actions.');
        }
    }
}
