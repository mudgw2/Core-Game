<?php
declare(strict_types=1);

namespace Core\Modules;

use RuntimeException;
use Throwable;

/** Registers modules, validates dependencies, and boots them in dependency order. */
final class ModuleRegistry
{
    /** @var array<string,ModuleInterface> */
    private array $modules = [];
    /** @var list<string> */
    private array $bootOrder = [];
    private bool $booted = false;

    public function __construct(private readonly ModuleContext $context) {}

    public function register(ModuleInterface $module): void
    {
        if ($this->booted) {
            throw new RuntimeException('Modules cannot be registered after module boot has started.');
        }
        $name = trim($module->name());
        $version = trim($module->version());
        if ($name === '' || !preg_match('/^[a-z][a-z0-9_.-]*$/', $name)) {
            throw new RuntimeException('Module name must be a non-empty lowercase identifier.');
        }
        if ($version === '') {
            throw new RuntimeException("Module '{$name}' must declare a version.");
        }
        if (isset($this->modules[$name])) {
            throw new RuntimeException("Module '{$name}' is already registered.");
        }
        $dependencies = $module->dependencies();
        if (!array_is_list($dependencies)) {
            throw new RuntimeException("Module '{$name}' dependencies must be a list of module names.");
        }
        foreach ($dependencies as $dependency) {
            if (!is_string($dependency) || trim($dependency) === '' || $dependency === $name) {
                throw new RuntimeException("Module '{$name}' has an invalid dependency declaration.");
            }
        }
        if (count(array_unique($dependencies)) !== count($dependencies)) {
            throw new RuntimeException("Module '{$name}' declares a duplicate dependency.");
        }
        $this->modules[$name] = $module;
    }

    /** Boot every registered module exactly once, respecting dependencies. */
    public function bootAll(): array
    {
        if ($this->booted) {
            return $this->bootOrder;
        }
        $order = $this->resolveBootOrder();
        $this->booted = true;
        try {
            foreach ($order as $name) {
                $this->modules[$name]->boot($this->context);
                $this->bootOrder[] = $name;
            }
        } catch (Throwable $e) {
            // Do not retry a partially booted registry: module boot may have side effects.
            throw new RuntimeException('Module boot failed; inspect the original exception for details.', 0, $e);
        }
        return $this->bootOrder;
    }

    public function modules(): array
    {
        $result = [];
        foreach ($this->modules as $name => $module) {
            $result[] = [
                'name' => $name,
                'version' => $module->version(),
                'dependencies' => array_values($module->dependencies()),
                'booted' => in_array($name, $this->bootOrder, true),
            ];
        }
        return $result;
    }

    private function resolveBootOrder(): array
    {
        $state = [];
        $order = [];
        $visit = function (string $name) use (&$visit, &$state, &$order): void {
            if (($state[$name] ?? 0) === 2) return;
            if (($state[$name] ?? 0) === 1) {
                throw new RuntimeException("Module dependency cycle detected at '{$name}'.");
            }
            $module = $this->modules[$name] ?? null;
            if ($module === null) {
                throw new RuntimeException("Required module '{$name}' is not registered.");
            }
            $state[$name] = 1;
            foreach ($module->dependencies() as $dependency) {
                if (!isset($this->modules[$dependency])) {
                    throw new RuntimeException("Module '{$name}' requires missing module '{$dependency}'.");
                }
                $visit($dependency);
            }
            $state[$name] = 2;
            $order[] = $name;
        };
        foreach (array_keys($this->modules) as $name) $visit($name);
        return $order;
    }
}
