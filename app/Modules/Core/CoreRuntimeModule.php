<?php
declare(strict_types=1);

namespace Core\Modules\Core;

use Core\Modules\ModuleContext;
use Core\Modules\ModuleInterface;
use Core\Storage\JsonStorage;

/** Provides the shared configuration and persistence foundation for CORE modules. */
final class CoreRuntimeModule implements ModuleInterface
{
    public function __construct(private readonly array $config) {}

    public function name(): string { return 'core.runtime'; }
    public function version(): string { return '1.0.0'; }
    public function dependencies(): array { return []; }

    public function boot(ModuleContext $context): void
    {
        $storagePath = (string)($this->config['storage']['path'] ?? '');
        if ($storagePath === '') {
            throw new \RuntimeException('CORE storage path is not configured.');
        }

        $services = $context->services();
        $services->set('core.config', $this->config);
        $services->set('core.storage', new JsonStorage($storagePath));
    }
}
