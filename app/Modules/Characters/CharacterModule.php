<?php
declare(strict_types=1);

namespace Core\Modules\Characters;

use Core\Modules\ModuleContext;
use Core\Modules\ModuleInterface;
use Core\Storage\JsonStorage;
use RuntimeException;

/** Owns character creation, lifecycle, travel, scanning, discovery, crafting, and fitting. */
final class CharacterModule implements ModuleInterface
{
    public function name(): string { return 'core.characters'; }
    public function version(): string { return '1.0.0'; }
    public function dependencies(): array { return ['core.runtime', 'core.universe']; }

    public function boot(ModuleContext $context): void
    {
        $services = $context->services();
        $storage = $services->get('core.storage');
        if (!$storage instanceof JsonStorage) throw new RuntimeException('Character module requires core.storage.');
        $services->set('character.service', new CharacterService($storage));
        $services->set('character.universe-assignment', new CharacterUniverseAssignmentService($storage));
        $services->set('character.travel', new CharacterTravelService($storage));
        $services->set('character.scan', new CharacterScanService($storage));
        $services->set('character.discovery', new CharacterDiscoveryService($storage));
        $services->set('character.crafting', new CharacterCraftingService($storage));
        $services->set('character.fitting', new CharacterFittingService($storage));
        $services->set('character.lifecycle', new CharacterLifecycleService($storage));
    }
}
