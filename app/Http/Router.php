<?php
declare(strict_types=1);

namespace Core\Http;

use Core\Core\Logger;
use Throwable;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function get(string $path, callable $handler): void { $this->add('GET', $path, $handler); }
    public function post(string $path, callable $handler): void { $this->add('POST', $path, $handler); }

    private function add(string $method, string $path, callable $handler): void
    {
        $this->routes[$method][$path] = $handler;
    }

    public function dispatch(Request $request): Response
    {
        $handler = $this->routes[$request->method()][$request->path()] ?? null;
        if ($handler === null) {
            Logger::warning('Route not found', ['method' => $request->method(), 'path' => $request->path()]);
            return Response::json(['ok' => false, 'error' => [
                'code' => 'not_found', 'message' => 'No route matches this request.',
            ]], 404);
        }

        try {
            $response = $handler($request);
            Logger::info('Request handled', ['method' => $request->method(), 'path' => $request->path()]);
            return $response instanceof Response
                ? $response
                : Response::json(['ok' => false, 'error' => ['code' => 'invalid_handler_response']], 500);
        } catch (Throwable $e) {
            Logger::error('Unhandled request exception', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'path' => $request->path(),
            ]);
            return Response::json(['ok' => false, 'error' => [
                'code' => 'internal_error', 'message' => 'An unexpected error occurred.',
            ]], 500);
        }
    }
}
