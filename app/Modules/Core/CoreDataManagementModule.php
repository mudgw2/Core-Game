<?php
declare(strict_types=1);

namespace Core\Modules\Core;

use Core\Modules\DataManagementService;
use Core\Modules\ModuleContext;
use Core\Modules\ModuleInterface;
use Core\Storage\JsonStorage;
use RuntimeException;

/** Exposes cross-domain saved-data inventory and cleanup preview services. */
final class CoreDataManagementModule implements ModuleInterface
{
    public function name(): string { return 'core.data-management'; }
    public function version(): string { return '1.0.0'; }
    public function dependencies(): array { return ['core.runtime']; }

    public function boot(ModuleContext $context): void
    {
        $storage = $context->services()->get('core.storage');
        if (!$storage instanceof JsonStorage) throw new RuntimeException('Data management module requires core.storage.');
        $context->services()->set('data.management', new DataManagementService($storage));
    }
}
