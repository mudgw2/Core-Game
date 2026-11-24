<?php
declare(strict_types=1);

namespace Core\Modules\Universe;

use Core\Modules\ModuleContext;
use Core\Modules\ModuleInterface;
use Core\Modules\UniverseBackupService;
use Core\Modules\UniverseDeletionService;
use Core\Storage\JsonStorage;
use RuntimeException;

/** Owns universe generation, lifecycle, navigation, backup, and deletion services. */
final class UniverseModule implements ModuleInterface
{
    public function name(): string { return 'core.universe'; }
    public function version(): string { return '1.0.0'; }
    public function dependencies(): array { return ['core.runtime']; }

    public function boot(ModuleContext $context): void
    {
        $services = $context->services();
        $storage = $services->get('core.storage');
        if (!$storage instanceof JsonStorage) throw new RuntimeException('Universe module requires core.storage.');
        $config = $services->get('core.config');
        $storagePath = (string)($config['storage']['path'] ?? '');
        if ($storagePath === '') throw new RuntimeException('Universe module requires a configured storage path.');

        $backups = new UniverseBackupService($storage, dirname($storagePath) . '/backups');
        $services->set('universe.service', new UniverseService($storage));
        $services->set('universe.lifecycle', new UniverseLifecycleService($storage));
        $services->set('navigation.routes', new NavigationRouteService($storage));
        $services->set('universe.backups', $backups);
        $services->set('universe.deletion', new UniverseDeletionService($storage, $backups));
    }
}
