<?php
declare(strict_types=1);

namespace Core\Modules\Demo;

use Core\Modules\ModuleContext;
use Core\Modules\ModuleInterface;

/** Minimal example proving a module can publish a service through the framework. */
final class DemoModule implements ModuleInterface
{
    public function name(): string { return 'demo'; }
    public function version(): string { return '1.0.0'; }
    public function dependencies(): array { return []; }

    public function boot(ModuleContext $context): void
    {
        $context->services()->set('demo.greeting', static fn(string $name): string => 'Hello, ' . trim($name) . '!');
    }
}
