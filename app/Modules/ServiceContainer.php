<?php
declare(strict_types=1);

namespace Core\Modules;

use RuntimeException;

/** Small explicit service container used for module dependency injection. */
final class ServiceContainer
{
    /** @var array<string,mixed> */
    private array $services = [];

    public function set(string $id, mixed $service): void
    {
        $id = trim($id);
        if ($id === '') {
            throw new RuntimeException('Service ID must not be empty.');
        }
        if (array_key_exists($id, $this->services)) {
            throw new RuntimeException("Service '{$id}' is already registered.");
        }
        $this->services[$id] = $service;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new RuntimeException("Service '{$id}' is not registered.");
        }
        return $this->services[$id];
    }
}
