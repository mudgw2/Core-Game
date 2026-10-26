<?php
declare(strict_types=1);

namespace Core\Modules;

/** Shared, intentionally small context passed to module boot methods. */
final class ModuleContext
{
    public function __construct(private readonly ServiceContainer $services) {}

    public function services(): ServiceContainer
    {
        return $this->services;
    }
}
