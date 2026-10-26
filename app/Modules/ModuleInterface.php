<?php
declare(strict_types=1);

namespace Core\Modules;

/** Contract implemented by an independently registered CORE module. */
interface ModuleInterface
{
    /** Stable machine-readable module identifier. */
    public function name(): string;

    /** Module release/version metadata. */
    public function version(): string;

    /** Names of modules that must boot before this module. */
    public function dependencies(): array;

    /** Initialize the module using shared application services. */
    public function boot(ModuleContext $context): void;
}
