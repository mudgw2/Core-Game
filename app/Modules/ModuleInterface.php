<?php
declare(strict_types=1);

namespace Core\Modules;

interface ModuleInterface
{
    public function name(): string;
    public function version(): string;
    public function boot(): void;
}
