<?php
declare(strict_types=1);

namespace Core;

use Core\Core\Logger;
use Core\Http\Request;
use Core\Http\Response;
use Core\Http\Router;
use Core\Modules\Universe\UniverseService;
use Core\Modules\Characters\CharacterService;
use Core\Modules\Universe\NavigationRouteService;
use Core\Storage\JsonStorage;
use Throwable;
use RuntimeException;

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
        $characterService = new CharacterService($storage);
        $routeService = new NavigationRouteService($storage);

        $this->router->get('/api/health', static fn(): Response => Response::json([
            'ok' => true,
            'application' => 'CORE',
            'version' => '0.1.18',
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

        $this->router->get('/api/navigation', static function (Request $request) use ($characterService, $config): Response {
            if (!self::developmentRequestAllowed($config)) {
                Logger::warning('Navigation API access denied', ['remote_addr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''), 'environment' => (string)($config['app']['environment'] ?? 'production')]);
                return Response::json(['ok' => false, 'error' => ['code' => 'navigation_api_disabled', 'message' => 'Navigation data is available only in development mode from a local/private network.']], 403);
            }
            $universeId = $request->query('universe_id', '');
            if (!is_string($universeId) || trim($universeId) === '') return Response::json(['ok' => false, 'error' => ['code' => 'missing_universe_id', 'message' => 'Provide a universe_id query parameter.']], 400);
            try {
                return Response::json(['ok' => true, 'universe_id' => trim($universeId), 'navigation' => $characterService->navigationForUniverse($universeId)]);
            } catch (Throwable $e) {
                if (str_contains($e->getMessage(), 'does not exist')) return Response::json(['ok' => false, 'error' => ['code' => 'universe_not_found', 'message' => $e->getMessage()]], 404);
                Logger::error('Navigation graph generation failed', ['universe_id' => $universeId, 'exception' => $e::class, 'message' => $e->getMessage()]);
                return Response::json(['ok' => false, 'error' => ['code' => 'navigation_failed', 'message' => 'Navigation data could not be generated. Check CORE logs.']], 500);
            }
        });

        $this->router->get('/api/navigation/route', static function (Request $request) use ($routeService, $config): Response {
            if (!self::developmentRequestAllowed($config)) {
                Logger::warning('Route planning API access denied', ['remote_addr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''), 'environment' => (string)($config['app']['environment'] ?? 'production')]);
                return Response::json(['ok' => false, 'error' => ['code' => 'route_api_disabled', 'message' => 'Route planning is available only in development mode from a local/private network.']], 403);
            }
            $universeId = $request->query('universe_id', '');
            $fromSystemId = $request->query('from_system_id', '');
            $toSystemId = $request->query('to_system_id', '');
            if (!is_string($universeId) || trim($universeId) === '' || !is_string($fromSystemId) || trim($fromSystemId) === '' || !is_string($toSystemId) || trim($toSystemId) === '') {
                Logger::warning('Route planning validation failed', ['error_code' => 'invalid_route_request']);
                return Response::json(['ok' => false, 'error' => ['code' => 'invalid_route_request', 'message' => 'Provide universe_id, from_system_id, and to_system_id.']], 400);
            }
            try {
                return Response::json($routeService->plan($universeId, $fromSystemId, $toSystemId));
            } catch (Throwable $e) {
                $message = $e->getMessage();
                if (str_contains($message, 'does not exist')) {
                    Logger::warning('Route planning rejected: universe not found', ['universe_id' => trim($universeId), 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'universe_not_found', 'message' => $message]], 404);
                }
                if (str_contains($message, 'endpoints') || str_contains($message, 'No route')) {
                    Logger::warning('Route planning rejected', ['universe_id' => trim($universeId), 'from_system_id' => trim($fromSystemId), 'to_system_id' => trim($toSystemId), 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'invalid_route', 'message' => $message]], 400);
                }
                Logger::error('Route planning failed', ['universe_id' => trim($universeId), 'exception' => $e::class, 'message' => $message]);
                return Response::json(['ok' => false, 'error' => ['code' => 'route_planning_failed', 'message' => 'Route could not be planned. Check CORE logs.']], 500);
            }
        });

        $this->router->get('/api/characters', static function (Request $request) use ($characterService, $config): Response {
            if (!self::developmentRequestAllowed($config)) {
                Logger::warning('Character API access denied', ['remote_addr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''), 'environment' => (string)($config['app']['environment'] ?? 'production')]);
                return Response::json(['ok' => false, 'error' => ['code' => 'character_api_disabled', 'message' => 'Character API is available only in development mode from a local/private network.']], 403);
            }
            $universeId = $request->query('universe_id', '');
            if (!is_string($universeId) || trim($universeId) === '') {
                return Response::json(['ok' => false, 'error' => ['code' => 'missing_universe_id', 'message' => 'Provide a universe_id query parameter.']], 400);
            }
            try {
                return Response::json(['ok' => true, 'universe_id' => trim($universeId), 'characters' => $characterService->listForUniverse($universeId)]);
            } catch (Throwable $e) {
                if (str_contains($e->getMessage(), 'does not exist')) return Response::json(['ok' => false, 'error' => ['code' => 'universe_not_found', 'message' => $e->getMessage()]], 404);
                Logger::error('Character list failed', ['universe_id' => $universeId, 'exception' => $e::class, 'message' => $e->getMessage()]);
                return Response::json(['ok' => false, 'error' => ['code' => 'character_list_failed', 'message' => 'Characters could not be loaded. Check CORE logs.']], 500);
            }
        });

        $this->router->post('/api/characters/location', static function (Request $request) use ($characterService, $config): Response {
            if (!self::developmentRequestAllowed($config)) {
                Logger::warning('Character location API access denied', ['remote_addr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''), 'environment' => (string)($config['app']['environment'] ?? 'production')]);
                return Response::json(['ok' => false, 'error' => ['code' => 'character_api_disabled', 'message' => 'Character location updates are available only in development mode from a local/private network.']], 403);
            }
            $body = $request->jsonBody();
            $universeId = isset($body['universe_id']) && is_string($body['universe_id']) ? trim($body['universe_id']) : '';
            $characterId = isset($body['character_id']) && is_string($body['character_id']) ? trim($body['character_id']) : '';
            $systemId = isset($body['system_id']) && is_string($body['system_id']) ? trim($body['system_id']) : '';
            $expectedRevision = $body['expected_revision'] ?? null;
            if ($universeId === '' || $characterId === '' || $systemId === '' || !is_int($expectedRevision)) {
                Logger::warning('Character location validation failed', ['error_code' => 'invalid_character_location_request']);
                return Response::json(['ok' => false, 'error' => ['code' => 'invalid_character_location_request', 'message' => 'Provide universe_id, character_id, system_id, and integer expected_revision.']], 400);
            }
            try {
                $character = $characterService->move($universeId, $characterId, $systemId, $expectedRevision);
                $movementStatus = (string)($character['_movement_status'] ?? 'moved');
                unset($character['_movement_status']);
                if ($movementStatus === 'already_at_destination') {
                    Logger::info('Character already at destination', ['character_id' => $characterId, 'universe_id' => $universeId, 'system_id' => $systemId, 'revision' => $character['revision']]);
                } else {
                    Logger::info('Character location updated', ['character_id' => $characterId, 'universe_id' => $universeId, 'system_id' => $systemId, 'revision' => $character['revision']]);
                }
                return Response::json(['ok' => true, 'movement_status' => $movementStatus, 'character' => $character]);
            } catch (Throwable $e) {
                $message = $e->getMessage();
                if (str_contains($message, 'revision conflict')) {
                    Logger::warning('Character location revision conflict', ['character_id' => $characterId, 'expected_revision' => $expectedRevision, 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'revision_conflict', 'message' => $message]], 409);
                }
                if (str_contains($message, 'does not exist')) return Response::json(['ok' => false, 'error' => ['code' => 'universe_not_found', 'message' => $message]], 404);
                if (str_contains($message, 'do not belong') || str_contains($message, 'does not belong') || str_contains($message, 'not directly reachable')) {
                    Logger::warning('Character location target rejected', ['character_id' => $characterId, 'universe_id' => $universeId, 'system_id' => $systemId, 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'invalid_location', 'message' => $message]], 400);
                }
                if (str_contains($message, 'required') || str_contains($message, 'Expected character revision') || str_contains($message, 'valid system list')) {
                    Logger::warning('Character location request rejected', ['character_id' => $characterId, 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'invalid_location', 'message' => $message]], 400);
                }
                Logger::error('Character location update failed', ['character_id' => $characterId, 'universe_id' => $universeId, 'exception' => $e::class, 'message' => $message]);
                return Response::json(['ok' => false, 'error' => ['code' => 'character_location_failed', 'message' => 'Character location could not be updated. Check CORE logs.']], 500);
            }
        });

        $this->router->post('/api/characters', static function (Request $request) use ($characterService, $config): Response {
            if (!self::developmentRequestAllowed($config)) {
                Logger::warning('Character API access denied', ['remote_addr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''), 'environment' => (string)($config['app']['environment'] ?? 'production')]);
                return Response::json(['ok' => false, 'error' => ['code' => 'character_api_disabled', 'message' => 'Character API is available only in development mode from a local/private network.']], 403);
            }
            $body = $request->jsonBody();
            $universeId = isset($body['universe_id']) && is_string($body['universe_id']) ? trim($body['universe_id']) : '';
            $name = isset($body['name']) && is_string($body['name']) ? $body['name'] : '';
            $role = isset($body['role']) && is_string($body['role']) ? $body['role'] : 'Explorer';
            if ($universeId === '' || trim($name) === '') {
                Logger::warning('Character creation validation failed', ['error_code' => 'invalid_character_request']);
                return Response::json(['ok' => false, 'error' => ['code' => 'invalid_character_request', 'message' => 'Provide universe_id and a non-empty character name.']], 400);
            }
            try {
                $character = $characterService->create($universeId, $name, $role);
                Logger::info('Character created', ['character_id' => $character['id'], 'universe_id' => $universeId]);
                return Response::json(['ok' => true, 'character' => $character], 201);
            } catch (Throwable $e) {
                $message = $e->getMessage();
                if (str_contains($message, 'does not exist')) return Response::json(['ok' => false, 'error' => ['code' => 'universe_not_found', 'message' => $message]], 404);
                if (str_contains($message, 'required') || str_contains($message, 'cannot be empty') || str_contains($message, 'must be between') || str_contains($message, 'must be a non-empty')) {
                    Logger::warning('Character creation rejected', ['universe_id' => $universeId, 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'invalid_character', 'message' => $message]], 400);
                }
                Logger::error('Character creation failed', ['universe_id' => $universeId, 'exception' => $e::class, 'message' => $message]);
                return Response::json(['ok' => false, 'error' => ['code' => 'character_create_failed', 'message' => 'Character could not be saved. Check CORE logs.']], 500);
            }
        });

        $this->router->get('/', static fn(): Response => Response::json([
            'name' => 'CORE',
            'message' => 'CORE foundation is running.',
            'endpoints' => [
                'GET /api/health',
                'GET /api/universe/preview?seed=your-seed',
                'POST /api/universe with JSON {"seed":"your-seed"}',
                'GET /api/characters?universe_id=... (development/private network only)',
                'GET /api/navigation?universe_id=... (development/private network only)',
                'GET /api/navigation/route?universe_id=...&from_system_id=...&to_system_id=... (development/private network only)',
                'POST /api/characters with JSON {"universe_id":"...","name":"...","role":"Explorer"} (development/private network only)',
                'POST /api/universe/state with JSON {"universe_id":"...","expected_revision":0,"change":{"type":"..."}} (development/private network only)',
            ],
        ]));
    }

    private static function developmentRequestAllowed(array $config): bool
    {
        $environment = (string)($config['app']['environment'] ?? 'production');
        $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $isPrivate = $remote === '127.0.0.1' || $remote === '::1'
            || preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $remote) === 1
            || preg_match('/^(fc|fd)[0-9a-f:]*$/i', $remote) === 1;
        return $environment === 'development' && $isPrivate;
    }
}
