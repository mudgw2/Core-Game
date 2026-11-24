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

        // Remove the application's deployment directory from the URL path.
        // CORE_ROOT is defined by public/index.php before the application loads.
        $applicationRoot = defined('CORE_ROOT') ? realpath(CORE_ROOT) : false;
        $documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));

        $basePath = '';
        if ($applicationRoot !== false && $documentRoot !== false) {
            $applicationRoot = rtrim(str_replace('\\', '/', $applicationRoot), '/');
            $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
            if (str_starts_with($applicationRoot, $documentRoot . '/')) {
                $basePath = substr($applicationRoot, strlen($documentRoot));
            }
        }

        // When Apache's DOCUMENT_ROOT points directly at CORE_ROOT, the URI
        // can still carry the mounted URL prefix. Derive that prefix from the
        // rewritten front-controller path rather than relying on filesystem
        // nesting alone (e.g. /core-game/public/index.php => /core-game).
        if ($basePath === '') {
            $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
            if ($scriptName !== '' && str_ends_with($scriptName, '/public/index.php')) {
                $basePath = substr($scriptName, 0, -strlen('/public/index.php'));
            } elseif ($scriptName !== '' && str_ends_with($scriptName, '/index.php')) {
                $basePath = dirname($scriptName);
                if ($basePath === '.' || $basePath === '/') $basePath = '';
            }
        }

        $basePath = rtrim($basePath, '/');
        if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
            $path = substr($path, strlen($basePath)) ?: '/';
        }

        $headers = function_exists('getallheaders') ? getallheaders() : [];
        if (!is_array($headers)) $headers = [];
        if (isset($_SERVER['HTTP_X_CORE_CSRF']) && !isset($headers['X-CORE-CSRF'])) $headers['X-CORE-CSRF'] = (string)$_SERVER['HTTP_X_CORE_CSRF'];

        return new self($method, rtrim($path, '/') ?: '/', $_GET, $headers, (string)file_get_contents('php://input'));
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
