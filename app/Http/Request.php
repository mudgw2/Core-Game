<?php
declare(strict_types=1);

namespace Core\Http;

final class Request
{
    public function __construct(
        private string $method,
        private string $path,
        private array $queryParams = [],
        private array $headers = [],
        private string $body = ''
    ) {}

    public static function fromGlobals(): self
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $headers = function_exists('getallheaders') ? getallheaders() : [];

        return new self($method, rtrim($path, '/') ?: '/', $_GET, is_array($headers) ? $headers : [], (string)file_get_contents('php://input'));
    }

    public function method(): string { return $this->method; }
    public function path(): string { return $this->path; }
    public function query(string $key, mixed $default = null): mixed { return $this->queryParams[$key] ?? $default; }
    public function header(string $key): ?string
    {
        foreach ($this->headers as $name => $value) {
            if (strcasecmp((string)$name, $key) === 0) return is_string($value) ? $value : null;
        }
        return null;
    }

    public function jsonBody(): array
    {
        if (trim($this->body) === '') return [];
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : [];
    }
}
