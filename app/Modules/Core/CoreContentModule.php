<?php
declare(strict_types=1);

namespace Core\Modules\Core;

use Core\Modules\ContentManagementService;
use Core\Modules\ItemCharacteristicsManagementService;
use Core\Modules\ModuleContext;
use Core\Modules\ModuleInterface;
use Core\Modules\UniverseBackupService;
use RuntimeException;

/** Owns editable game-content catalogs and their characteristic schema. */
final class CoreContentModule implements ModuleInterface
{
    public function name(): string { return 'core.content'; }
    public function version(): string { return '1.0.0'; }
    public function dependencies(): array { return ['core.runtime', 'core.universe']; }

    public function boot(ModuleContext $context): void
    {
        $services = $context->services();
        $backups = $services->get('universe.backups');
        if (!$backups instanceof UniverseBackupService) throw new RuntimeException('Content module requires universe.backups.');
        $contentPath = CORE_ROOT . '/config/content';
        $backupPath = dirname((string)$services->get('core.config')['storage']['path']) . '/backups';
        $services->set('content.characteristics', new ItemCharacteristicsManagementService($contentPath, $backupPath));
        $services->set('content.management', new ContentManagementService($contentPath, $backupPath));
    }
}
