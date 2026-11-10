<?php
declare(strict_types=1);

namespace Core\Http;

use Core\Storage\JsonStorage;

/** Session, CSRF, and record-ownership checks for player-facing API routes. */
final class PlayerApiSecurity
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params(['httponly' => true, 'secure' => $secure, 'samesite' => 'Lax', 'path' => '/']);
        session_start();
    }

    public static function currentUserId(): ?string
    {
        self::startSession();
        $id = $_SESSION['core_user']['id'] ?? null;
        return is_string($id) && trim($id) !== '' ? trim($id) : null;
    }

    public static function isDevelopmentWorkbenchRequest(array $config): bool
    {
        $environment = (string)($config['app']['environment'] ?? 'production');
        $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $private = $remote === '127.0.0.1' || $remote === '::1'
            || preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $remote) === 1
            || preg_match('/^(fc|fd)[0-9a-f:]*$/i', $remote) === 1;
        return $environment === 'development' && $private;
    }

    public static function ownsCharacter(JsonStorage $storage, string $characterId, string $accountId): bool
    {
        if ($characterId === '' || $accountId === '') return false;
        $character = $storage->read('characters', $characterId);
        return is_array($character)
            && ($character['id'] ?? null) === $characterId
            && (string)($character['owner_account_id'] ?? '') === $accountId;
    }

    public static function ownsUniverse(JsonStorage $storage, string $universeId, string $accountId): bool
    {
        if ($universeId === '' || $accountId === '') return false;
        foreach ($storage->all('characters') as $character) {
            if (is_array($character)
                && (string)($character['owner_account_id'] ?? '') === $accountId
                && (string)($character['universe_id'] ?? '') === $universeId) return true;
        }
        return false;
    }

    /** Returns a denial response, or null when the request is authorized. */
    public static function guard(Request $request, JsonStorage $storage, array $config): ?Response
    {
        $path = $request->path();
        $method = $request->method();
        $policy = self::policyFor($method, $path);
        if ($policy === null) return null;

        // Preserve the existing trusted-LAN Workbench as an administrative workflow.
        if (self::isDevelopmentWorkbenchRequest($config)) return null;

        self::startSession();
        $accountId = self::currentUserId();
        if ($accountId === null) {
            return Response::json(['ok' => false, 'error' => ['code' => 'authentication_required', 'message' => 'Sign in to access this player API.']], 401);
        }

        if ($method !== 'GET') {
            $expected = (string)($_SESSION['core_csrf'] ?? '');
            $provided = (string)($request->header('X-CORE-CSRF') ?? '');
            if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
                return Response::json(['ok' => false, 'error' => ['code' => 'csrf_validation_failed', 'message' => 'The request security token is missing or expired. Refresh the page and try again.']], 403);
            }
        }

        $body = $request->jsonBody();
        if ($policy === 'create') {
            if ($path === '/api/characters' && is_string($body['universe_id'] ?? null) && trim($body['universe_id']) !== ''
                && !self::ownsUniverse($storage, trim($body['universe_id']), $accountId)) {
                return Response::json(['ok' => false, 'error' => ['code' => 'record_not_found', 'message' => 'The requested universe was not found for this account.']], 404);
            }
            return null;
        }

        $recordId = $policy === 'character'
            ? ($method === 'GET' ? $request->query('character_id', '') : ($body['character_id'] ?? ''))
            : ($method === 'GET' ? $request->query('universe_id', '') : ($body['universe_id'] ?? ''));
        if (!is_string($recordId) || trim($recordId) === '') {
            // The character collection endpoint is intentionally account-scoped by its handler.
            if ($path === '/api/characters' && $method === 'GET') return null;
            return Response::json(['ok' => false, 'error' => ['code' => 'record_id_required', 'message' => $policy === 'character' ? 'Provide a character_id.' : 'Provide a universe_id.']], 400);
        }
        $recordId = trim($recordId);
        $allowed = $policy === 'character'
            ? self::ownsCharacter($storage, $recordId, $accountId)
            : self::ownsUniverse($storage, $recordId, $accountId);
        if (!$allowed) {
            // Do not disclose whether another account's record exists.
            return Response::json(['ok' => false, 'error' => ['code' => 'record_not_found', 'message' => 'The requested record was not found for this account.']], 404);
        }
        return null;
    }

    private static function policyFor(string $method, string $path): ?string
    {
        if ($method === 'POST' && $path === '/api/characters') return 'create';
        if ($method === 'POST' && $path === '/api/universe') return 'create';
        if ($method === 'GET' && $path === '/api/characters') return 'list';

        $characterRoutes = [
            '/api/characters/record', '/api/characters/scan', '/api/characters/craft',
            '/api/characters/fit', '/api/characters/discoveries/investigate',
            '/api/characters/location', '/api/characters/travel',
            '/api/characters/lifecycle',
        ];
        if (in_array($path, $characterRoutes, true)) return 'character';

        $universeRoutes = ['/api/navigation', '/api/navigation/route', '/api/universe/lifecycle'];
        if (in_array($path, $universeRoutes, true)) return 'universe';
        return null;
    }
}
