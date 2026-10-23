<?php
declare(strict_types=1);

namespace Core;

use Core\Core\Logger;
use Core\Http\Request;
use Core\Http\Response;
use Core\Http\Router;
use Core\Modules\Universe\UniverseService;
use Core\Storage\JsonStorage;
use Throwable;

final class Application
{
    private Router $router;

    public function __construct()
    {
        $this->router = new Router();
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function registerDefaultRoutes(): void
    {
        $config = require CORE_ROOT . '/config/app.php';
        $storage = new JsonStorage((string)$config['storage']['path']);
        $universeService = new UniverseService($storage);

        $this->router->get('/api/health', static fn(): Response => Response::json([
            'ok' => true,
            'application' => 'CORE',
            'version' => '0.1.10',
        ]));

        $this->router->get('/api/universe/preview', static function (Request $request) use ($universeService): Response {
            $seed = trim((string)$request->query('seed', ''));
            if ($seed === '') {
                Logger::warning('Request validation failed', [
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'error_code' => 'missing_seed',
                    'message' => 'Required query parameter seed was missing or empty.',
                ]);
                return Response::json([
                    'ok' => false,
                    'error' => ['code' => 'missing_seed', 'message' => 'Provide a seed query parameter.'],
                ], 400);
            }

            return Response::json(['ok' => true, 'universe' => $universeService->preview($seed)]);
        });

        $this->router->post('/api/universe', static function (Request $request) use ($universeService): Response {
            $body = $request->jsonBody();
            $seed = isset($body['seed']) && is_string($body['seed']) ? trim($body['seed']) : '';

            if ($seed === '') {
                Logger::warning('Request validation failed', [
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'error_code' => 'missing_seed',
                    'message' => 'JSON body must contain a non-empty string seed.',
                ]);
                return Response::json([
                    'ok' => false,
                    'error' => ['code' => 'missing_seed', 'message' => 'JSON body must contain a non-empty string "seed".'],
                ], 400);
            }

            try {
                return Response::json(['ok' => true, 'universe' => $universeService->createOrLoad($seed)], 201);
            } catch (Throwable $e) {
                Logger::error('Universe create/load failed', ['exception' => $e::class, 'message' => $e->getMessage()]);
                return Response::json([
                    'ok' => false,
                    'error' => ['code' => 'internal_error', 'message' => 'The universe could not be saved. Check the application log.'],
                ], 500);
            }
        });

        $this->router->post('/api/universe/state', static function (Request $request) use ($universeService, $config): Response {
            $environment = (string)($config['app']['environment'] ?? 'production');
            $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
            $isPrivate = $remote === '127.0.0.1' || $remote === '::1'
                || preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $remote) === 1
                || preg_match('/^(fc|fd)[0-9a-f:]*$/i', $remote) === 1;
            if ($environment !== 'development' || !$isPrivate) {
                Logger::warning('Development state API access denied', ['remote_addr' => $remote, 'environment' => $environment]);
                return Response::json(['ok' => false, 'error' => ['code' => 'state_api_disabled', 'message' => 'State updates are available only in development mode from a local/private network.']], 403);
            }

            $body = $request->jsonBody();
            $universeId = isset($body['universe_id']) && is_string($body['universe_id']) ? trim($body['universe_id']) : '';
            $expectedRevision = $body['expected_revision'] ?? null;
            $change = $body['change'] ?? null;
            if ($universeId === '' || !is_int($expectedRevision) || !is_array($change)) {
                Logger::warning('Request validation failed', ['method' => $request->method(), 'path' => $request->path(), 'error_code' => 'invalid_state_request']);
                return Response::json(['ok' => false, 'error' => ['code' => 'invalid_state_request', 'message' => 'Provide universe_id, integer expected_revision, and a change object.']], 400);
            }
            try {
                $universe = $universeService->updateState($universeId, $expectedRevision, $change);
                return Response::json(['ok' => true, 'universe_id' => $universe['id'], 'state' => $universe['state']]);
            } catch (Throwable $e) {
                $message = $e->getMessage();
                if (str_starts_with($message, 'Revision conflict:')) {
                    Logger::warning('Universe state revision conflict', ['universe_id' => $universeId, 'expected_revision' => $expectedRevision, 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'revision_conflict', 'message' => $message]], 409);
                }
                if (str_contains($message, 'must ') || str_contains($message, 'cannot be empty') || str_contains($message, 'exceeds')) {
                    Logger::warning('Universe state change rejected', ['universe_id' => $universeId, 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'invalid_change', 'message' => $message]], 400);
                }
                Logger::error('Universe state update failed', ['universe_id' => $universeId, 'exception' => $e::class, 'message' => $message]);
                return Response::json(['ok' => false, 'error' => ['code' => 'state_update_failed', 'message' => 'The universe state could not be updated. Check CORE logs.']], 500);
            }
        });

        $this->router->get('/', static fn(): Response => Response::json([
            'name' => 'CORE',
            'message' => 'CORE foundation is running.',
            'endpoints' => [
                'GET /api/health',
                'GET /api/universe/preview?seed=your-seed',
                'POST /api/universe with JSON {"seed":"your-seed"}',
                'POST /api/universe/state with JSON {"universe_id":"...","expected_revision":0,"change":{"type":"..."}} (development/private network only)',
            ],
        ]));
    }
}
