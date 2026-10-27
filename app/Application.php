<?php
declare(strict_types=1);

namespace Core;

use Core\Core\Logger;
use Core\Http\Request;
use Core\Http\Response;
use Core\Http\Router;
use Core\Modules\Universe\UniverseService;
use Core\Modules\Universe\UniverseLifecycleService;
use Core\Modules\Universe\UniverseLifecycleGuard;
use Core\Modules\Characters\CharacterService;
use Core\Modules\ContentCatalog;
use Core\Modules\Characters\CharacterTravelService;
use Core\Modules\Characters\CharacterScanService;
use Core\Modules\Characters\CharacterDiscoveryService;
use Core\Modules\Characters\CharacterCraftingService;
use Core\Modules\Characters\CharacterFittingService;
use Core\Modules\Characters\CharacterLifecycleService;
use Core\Modules\DataManagementService;
use Core\Modules\UniverseBackupService;
use Core\Modules\UniverseDeletionService;
use Core\Modules\Universe\NavigationRouteService;
use Core\Storage\JsonStorage;
use Core\Modules\ModuleContext;
use Core\Modules\ModuleInterface;
use Core\Modules\ModuleRegistry;
use Core\Modules\ServiceContainer;
use Throwable;
use RuntimeException;

final class Application
{
    private Router $router;
    private ServiceContainer $services;
    private ModuleRegistry $modules;

    public function __construct()
    {
        $this->router = new Router();
        $this->services = new ServiceContainer();
        $this->modules = new ModuleRegistry(new ModuleContext($this->services));
    }

    /** Register a gameplay or infrastructure module before module boot. */
    public function registerModule(ModuleInterface $module): void
    {
        $this->modules->register($module);
    }

    /** Boot registered modules and return their resolved boot order. */
    public function bootModules(): array
    {
        return $this->modules->bootAll();
    }

    public function moduleRegistry(): ModuleRegistry
    {
        return $this->modules;
    }

    public function services(): ServiceContainer
    {
        return $this->services;
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
        $universeLifecycleService = new UniverseLifecycleService($storage);
        $characterService = new CharacterService($storage);
        $travelService = new CharacterTravelService($storage);
        $scanService = new CharacterScanService($storage);
        $discoveryService = new CharacterDiscoveryService($storage);
        $craftingService = new CharacterCraftingService($storage);
        $fittingService = new CharacterFittingService($storage);
        $lifecycleService = new CharacterLifecycleService($storage);
        $routeService = new NavigationRouteService($storage);
        $dataManagementService = new DataManagementService($storage);
        $backupService = new UniverseBackupService($storage, dirname((string)$config['storage']['path']) . '/backups');
        $universeDeletionService = new UniverseDeletionService($storage, $backupService);

        $this->router->get('/api/data-management', static function () use ($dataManagementService, $config): Response {
            if (!self::developmentRequestAllowed($config)) {
                Logger::warning('Data management inventory access denied', ['remote_addr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''), 'environment' => (string)($config['app']['environment'] ?? 'production')]);
                return Response::json(['ok' => false, 'error' => ['code' => 'data_management_disabled', 'message' => 'Data management is available only in development mode from a local/private network.']], 403);
            }
            try { return Response::json(['ok' => true, 'inventory' => $dataManagementService->inventory()]); }
            catch (Throwable $e) {
                Logger::error('Data management inventory failed', ['exception' => $e::class, 'message' => $e->getMessage()]);
                return Response::json(['ok' => false, 'error' => ['code' => 'inventory_failed', 'message' => 'Saved-data inventory could not be loaded. Check CORE logs.']], 500);
            }
        });

        $this->router->post('/api/data-management/preview', static function (Request $request) use ($dataManagementService, $config): Response {
            if (!self::developmentRequestAllowed($config)) {
                Logger::warning('Data management preview access denied', ['remote_addr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''), 'environment' => (string)($config['app']['environment'] ?? 'production')]);
                return Response::json(['ok' => false, 'error' => ['code' => 'data_management_disabled', 'message' => 'Data management is available only in development mode from a local/private network.']], 403);
            }
            $body = $request->jsonBody();
            $scope = isset($body['scope']) && is_string($body['scope']) ? trim($body['scope']) : '';
            $id = isset($body['id']) && is_string($body['id']) ? trim($body['id']) : '';
            if (!in_array($scope, ['character', 'universe'], true) || $id === '') {
                Logger::warning('Data management preview validation failed', ['scope' => $scope, 'error_code' => 'invalid_cleanup_preview']);
                return Response::json(['ok' => false, 'error' => ['code' => 'invalid_cleanup_preview', 'message' => 'Provide scope (character or universe) and record ID.']], 400);
            }
            try {
                $preview = $dataManagementService->preview($scope, $id);
                Logger::info('Data management cleanup preview generated', ['scope' => $scope, 'record_id' => $id, 'executable' => $preview['executable']]);
                return Response::json(['ok' => true, 'preview' => $preview]);
            } catch (Throwable $e) {
                $message = $e->getMessage();
                if (str_contains($message, 'does not exist')) return Response::json(['ok' => false, 'error' => ['code' => 'record_not_found', 'message' => $message]], 404);
                if (str_contains($message, 'required') || str_contains($message, 'Scope must')) return Response::json(['ok' => false, 'error' => ['code' => 'invalid_cleanup_preview', 'message' => $message]], 400);
                Logger::error('Data management cleanup preview failed', ['scope' => $scope, 'record_id' => $id, 'exception' => $e::class, 'message' => $message]);
                return Response::json(['ok' => false, 'error' => ['code' => 'cleanup_preview_failed', 'message' => 'Cleanup preview failed. Check CORE logs.']], 500);
            }
        });

        $this->router->get('/api/data-management/backups', static function () use ($backupService, $config): Response {
            if (!self::developmentRequestAllowed($config)) return Response::json(['ok' => false, 'error' => ['code' => 'data_management_disabled', 'message' => 'Backup management is available only in development mode from a local/private network.']], 403);
            try { return Response::json(['ok' => true, 'backups' => $backupService->list()]); }
            catch (Throwable $e) { Logger::error('Backup listing failed', ['exception' => $e::class, 'message' => $e->getMessage()]); return Response::json(['ok' => false, 'error' => ['code' => 'backup_list_failed', 'message' => 'Backups could not be listed. Check CORE logs.']], 500); }
        });

        $this->router->post('/api/data-management/backups', static function (Request $request) use ($backupService, $config): Response {
            if (!self::developmentRequestAllowed($config)) return Response::json(['ok' => false, 'error' => ['code' => 'data_management_disabled', 'message' => 'Backup management is available only in development mode from a local/private network.']], 403);
            $body = $request->jsonBody(); $id = isset($body['universe_id']) && is_string($body['universe_id']) ? trim($body['universe_id']) : '';
            if ($id === '') return Response::json(['ok' => false, 'error' => ['code' => 'universe_id_required', 'message' => 'Provide a universe ID.']], 400);
            try { return Response::json(['ok' => true, 'backup' => $backupService->create($id)], 201); }
            catch (Throwable $e) { $message = $e->getMessage(); if (str_contains($message, 'does not exist')) return Response::json(['ok' => false, 'error' => ['code' => 'universe_not_found', 'message' => $message]], 404); Logger::error('Universe backup request failed', ['universe_id' => $id, 'exception' => $e::class, 'message' => $message]); return Response::json(['ok' => false, 'error' => ['code' => 'backup_failed', 'message' => 'Universe backup failed. Check CORE logs.']], 500); }
        });

        $this->router->post('/api/data-management/backups/restore', static function (Request $request) use ($backupService, $config): Response {
            if (!self::developmentRequestAllowed($config)) return Response::json(['ok' => false, 'error' => ['code' => 'data_management_disabled', 'message' => 'Backup management is available only in development mode from a local/private network.']], 403);
            $body = $request->jsonBody(); $id = isset($body['backup_id']) && is_string($body['backup_id']) ? trim($body['backup_id']) : ''; $confirmation = isset($body['confirmation']) && is_string($body['confirmation']) ? trim($body['confirmation']) : '';
            if ($id === '' || $confirmation !== 'RESTORE') return Response::json(['ok' => false, 'error' => ['code' => 'restore_confirmation_required', 'message' => 'Provide a backup ID and confirmation phrase RESTORE.']], 400);
            try { return Response::json(['ok' => true, 'restore' => $backupService->restore($id)]); }
            catch (Throwable $e) { $message = $e->getMessage(); if (str_contains($message, 'does not exist')) return Response::json(['ok' => false, 'error' => ['code' => 'backup_not_found', 'message' => $message]], 404); if (str_contains($message, 'conflict') || str_contains($message, 'checksum') || str_contains($message, 'invalid') || str_contains($message, 'unsupported')) return Response::json(['ok' => false, 'error' => ['code' => 'restore_rejected', 'message' => $message]], 409); Logger::error('Universe backup restore failed', ['backup_id' => $id, 'exception' => $e::class, 'message' => $message]); return Response::json(['ok' => false, 'error' => ['code' => 'restore_failed', 'message' => 'Backup restore failed. Check CORE logs.']], 500); }
        });

        $this->router->post('/api/data-management/universe-delete', static function (Request $request) use ($universeDeletionService, $config): Response {
            if (!self::developmentRequestAllowed($config)) {
                Logger::warning('Universe deletion access denied', ['remote_addr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''), 'environment' => (string)($config['app']['environment'] ?? 'production')]);
                return Response::json(['ok' => false, 'error' => ['code' => 'data_management_disabled', 'message' => 'Universe deletion is available only in development mode from a local/private network.']], 403);
            }
            $body = $request->jsonBody();
            $id = isset($body['universe_id']) && is_string($body['universe_id']) ? trim($body['universe_id']) : '';
            $confirmation = isset($body['confirmation']) && is_string($body['confirmation']) ? $body['confirmation'] : '';
            $stateRevision = filter_var($body['expected_state_revision'] ?? null, FILTER_VALIDATE_INT);
            $lifecycleRevision = filter_var($body['expected_lifecycle_revision'] ?? null, FILTER_VALIDATE_INT);
            $characterIds = $body['expected_character_ids'] ?? null;
            if ($id === '' || $stateRevision === false || $lifecycleRevision === false || !is_array($characterIds)) {
                Logger::warning('Universe deletion validation failed', ['universe_id' => $id, 'error_code' => 'invalid_universe_deletion_request']);
                return Response::json(['ok' => false, 'error' => ['code' => 'invalid_universe_deletion_request', 'message' => 'Provide universe_id, both expected revisions, expected_character_ids, and the exact confirmation phrase.']], 400);
            }
            try {
                $result = $universeDeletionService->delete($id, $confirmation, (int)$stateRevision, (int)$lifecycleRevision, $characterIds);
                return Response::json(['ok' => true, 'deletion' => $result]);
            } catch (Throwable $e) {
                $message = $e->getMessage();
                if (str_contains($message, 'does not exist')) { Logger::warning('Universe deletion target not found', ['universe_id' => $id, 'message' => $message]); return Response::json(['ok' => false, 'error' => ['code' => 'universe_not_found', 'message' => $message]], 404); }
                if (str_contains($message, 'Confirmation must') || str_contains($message, 'required') || str_contains($message, 'invalid')) { Logger::warning('Universe deletion rejected by confirmation or validation', ['universe_id' => $id, 'message' => $message]); return Response::json(['ok' => false, 'error' => ['code' => 'confirmation_or_validation_failed', 'message' => $message]], 400); }
                if (str_contains($message, 'stale') || str_contains($message, 'changed') || str_contains($message, 'conflict')) { Logger::warning('Universe deletion rejected due to stale preview or concurrent change', ['universe_id' => $id, 'message' => $message]); return Response::json(['ok' => false, 'error' => ['code' => 'universe_deletion_conflict', 'message' => $message]], 409); }
                Logger::error('Universe deletion failed', ['universe_id' => $id, 'exception' => $e::class, 'message' => $message]);
                return Response::json(['ok' => false, 'error' => ['code' => 'universe_deletion_failed', 'message' => $message]], 500);
            }
        });

        $this->router->get('/api/health', static fn(): Response => Response::json([
            'ok' => true,
            'application' => 'CORE',
            'version' => require CORE_ROOT . '/config/version.php',
        ]));

        // Read-only game definitions are externalized to config/content/*.json and exposed through the API.
        $this->router->get('/api/content', static function (): Response {
            try { return Response::json(['ok' => true, 'content' => ContentCatalog::allDocuments()]); }
            catch (Throwable $e) { Logger::error('Game content catalog request failed', ['exception' => $e::class, 'message' => $e->getMessage()]); return Response::json(['ok' => false, 'error' => ['code' => 'content_catalog_failed', 'message' => 'Game content definitions could not be loaded. Check CORE logs.']], 500); }
        });
        foreach (['items' => 'items', 'recipes' => 'recipes', 'discoveries' => 'discoveries', 'ship-modules' => 'ship_modules'] as $pathName => $catalogName) {
            $this->router->get('/api/content/' . $pathName, static function () use ($catalogName): Response {
                try { return Response::json(['ok' => true, 'catalog' => $catalogName, 'document' => ContentCatalog::document($catalogName)]); }
                catch (Throwable $e) { Logger::error('Game content catalog request failed', ['catalog' => $catalogName, 'exception' => $e::class, 'message' => $e->getMessage()]); return Response::json(['ok' => false, 'error' => ['code' => 'content_catalog_failed', 'message' => 'Game content definitions could not be loaded. Check CORE logs.']], 500); }
            });
        }
        $this->router->get('/api/content/item-categories', static function (): Response {
            try { return Response::json(['ok' => true, 'catalog' => 'item_categories', 'document' => ContentCatalog::itemCategoriesDocument()]); }
            catch (Throwable $e) { Logger::error('Item category catalog request failed', ['exception' => $e::class, 'message' => $e->getMessage()]); return Response::json(['ok' => false, 'error' => ['code' => 'content_catalog_failed', 'message' => 'Item category definitions could not be loaded. Check CORE logs.']], 500); }
        });
        $this->router->get('/api/content/item-characteristics', static function (): Response {
            try { return Response::json(['ok' => true, 'catalog' => 'item_characteristics', 'document' => ContentCatalog::itemCharacteristicsDocument()]); }
            catch (Throwable $e) { Logger::error('Item characteristic schema request failed', ['exception' => $e::class, 'message' => $e->getMessage()]); return Response::json(['ok' => false, 'error' => ['code' => 'content_characteristics_failed', 'message' => 'Item characteristic definitions could not be loaded. Check CORE logs.']], 500); }
        });

        $characteristicManager = new \Core\Modules\ItemCharacteristicsManagementService(
            CORE_ROOT . '/config/content',
            dirname((string)$config['storage']['path']) . '/backups'
        );
        $this->router->post('/api/content/item-characteristics/validate', static function (Request $request) use ($characteristicManager, $config): Response {
            if (!self::developmentRequestAllowed($config)) return Response::json(['ok'=>false,'error'=>['code'=>'content_management_disabled','message'=>'Content editing is available only in development mode from a local/private network.']],403);
            $body=$request->jsonBody(); $document=$body['document']??null;
            if (!is_array($document)) return Response::json(['ok'=>false,'error'=>['code'=>'invalid_content_request','message'=>'Provide a characteristic schema document.']],400);
            try { return Response::json(['ok'=>true,'validation'=>$characteristicManager->validate($document)]); }
            catch (Throwable $e) { Logger::warning('Characteristic schema validation failed',['message'=>$e->getMessage()]); return Response::json(['ok'=>false,'error'=>['code'=>'content_validation_failed','message'=>$e->getMessage()]],400); }
        });
        $this->router->post('/api/content/item-characteristics/save', static function (Request $request) use ($characteristicManager, $config): Response {
            if (!self::developmentRequestAllowed($config)) return Response::json(['ok'=>false,'error'=>['code'=>'content_management_disabled','message'=>'Content editing is available only in development mode from a local/private network.']],403);
            $body=$request->jsonBody(); $revision=$body['expected_revision']??null; $document=$body['document']??null;
            if (!is_int($revision)||!is_array($document)) return Response::json(['ok'=>false,'error'=>['code'=>'invalid_content_request','message'=>'Provide expected_revision and a schema document.']],400);
            try { return Response::json(['ok'=>true,'saved'=>$characteristicManager->save($revision,$document)]); }
            catch (Throwable $e) { $message=$e->getMessage(); if (str_contains($message,'revision conflict')) return Response::json(['ok'=>false,'error'=>['code'=>'content_revision_conflict','message'=>$message]],409); Logger::warning('Characteristic schema save failed',['expected_revision'=>$revision,'message'=>$message]); return Response::json(['ok'=>false,'error'=>['code'=>'content_save_rejected','message'=>$message]],400); }
        });

        $contentManager = new \Core\Modules\ContentManagementService(
            CORE_ROOT . '/config/content',
            dirname((string)$config['storage']['path']) . '/backups'
        );
        $this->router->post('/api/content/validate', static function (Request $request) use ($contentManager, $config): Response {
            if (!self::developmentRequestAllowed($config)) return Response::json(['ok' => false, 'error' => ['code' => 'content_management_disabled', 'message' => 'Content editing is available only in development mode from a local/private network.']], 403);
            $body = $request->jsonBody();
            $catalog = isset($body['catalog']) && is_string($body['catalog']) ? trim($body['catalog']) : '';
            $document = $body['document'] ?? null;
            if ($catalog === '' || !is_array($document)) return Response::json(['ok' => false, 'error' => ['code' => 'invalid_content_request', 'message' => 'Provide an allow-listed catalog name and a JSON document object.']], 400);
            try { return Response::json(['ok' => true, 'validation' => $contentManager->validate($catalog, $document)]); }
            catch (Throwable $e) { Logger::warning('Game content draft validation failed', ['catalog' => $catalog, 'message' => $e->getMessage()]); return Response::json(['ok' => false, 'error' => ['code' => 'content_validation_failed', 'message' => $e->getMessage()]], 400); }
        });
        $this->router->post('/api/content/save', static function (Request $request) use ($contentManager, $config): Response {
            if (!self::developmentRequestAllowed($config)) return Response::json(['ok' => false, 'error' => ['code' => 'content_management_disabled', 'message' => 'Content editing is available only in development mode from a local/private network.']], 403);
            $body = $request->jsonBody();
            $catalog = isset($body['catalog']) && is_string($body['catalog']) ? trim($body['catalog']) : '';
            $revision = $body['expected_revision'] ?? null;
            $document = $body['document'] ?? null;
            if ($catalog === '' || !is_int($revision) || !is_array($document)) return Response::json(['ok' => false, 'error' => ['code' => 'invalid_content_request', 'message' => 'Provide catalog, integer expected_revision, and a JSON document object.']], 400);
            try { return Response::json(['ok' => true, 'saved' => $contentManager->save($catalog, $revision, $document)]); }
            catch (Throwable $e) {
                $message = $e->getMessage();
                if (str_contains($message, 'revision conflict')) return Response::json(['ok' => false, 'error' => ['code' => 'content_revision_conflict', 'message' => $message]], 409);
                Logger::warning('Game content save failed', ['catalog' => $catalog, 'expected_revision' => $revision, 'message' => $message]);
                return Response::json(['ok' => false, 'error' => ['code' => 'content_save_rejected', 'message' => $message]], 400);
            }
        });

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

            $settings = [];
            $rawSettings = $request->query('settings', '');
            if (is_string($rawSettings) && $rawSettings !== '') { try { $decoded = json_decode($rawSettings, true, 512, JSON_THROW_ON_ERROR); if (!is_array($decoded)) throw new \RuntimeException('Settings must be a JSON object.'); $settings = $decoded; } catch (\Throwable $e) { return Response::json(['ok' => false, 'error' => ['code' => 'invalid_universe_settings', 'message' => 'Universe settings must be a valid JSON object.']], 400); } }
            try { return Response::json(['ok' => true, 'universe' => $universeService->preview($seed, $settings)]); } catch (\Throwable $e) { return Response::json(['ok' => false, 'error' => ['code' => 'invalid_universe_settings', 'message' => $e->getMessage()]], 400); }
        });

        $this->router->post('/api/universe', static function (Request $request) use ($universeService): Response {
            $body = $request->jsonBody();
            $seed = isset($body['seed']) && is_string($body['seed']) ? trim($body['seed']) : '';
            $settings = is_array($body['settings'] ?? null) ? $body['settings'] : [];

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
                return Response::json(['ok' => true, 'universe' => $universeService->createOrLoad($seed, $settings)], 201);
            } catch (Throwable $e) {
                Logger::error('Universe create/load failed', ['exception' => $e::class, 'message' => $e->getMessage()]);
                return Response::json([
                    'ok' => false,
                    'error' => ['code' => 'internal_error', 'message' => 'The universe could not be saved. Check the application log.'],
                ], 500);
            }
        });

        $this->router->post('/api/universe/lifecycle', static function (Request $request) use ($universeLifecycleService): Response {
            $body = $request->jsonBody();
            $universeId = isset($body['universe_id']) && is_string($body['universe_id']) ? trim($body['universe_id']) : '';
            $action = isset($body['action']) && is_string($body['action']) ? trim($body['action']) : '';
            $expectedRevision = $body['expected_revision'] ?? null;
            if ($universeId === '' || $action === '' || !is_int($expectedRevision)) {
                Logger::warning('Universe lifecycle request validation failed', ['error_code' => 'invalid_universe_lifecycle_request']);
                return Response::json(['ok' => false, 'error' => ['code' => 'invalid_universe_lifecycle_request', 'message' => 'Provide universe_id, action, and integer expected_revision.']], 400);
            }
            try {
                $result = $universeLifecycleService->apply($universeId, $action, $expectedRevision);
                Logger::info('Universe lifecycle changed', ['universe_id' => $universeId, 'action' => $action, 'status' => $result['lifecycle_status'], 'revision' => $result['universe']['lifecycle']['revision'] ?? 0]);
                return Response::json(['ok' => true] + $result);
            } catch (Throwable $e) {
                $message = $e->getMessage();
                if (str_contains($message, 'revision conflict')) {
                    Logger::warning('Universe lifecycle revision conflict', ['universe_id' => $universeId, 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'revision_conflict', 'message' => $message]], 409);
                }
                if (str_contains($message, 'required') || str_contains($message, 'Action must') || str_contains($message, 'Expected universe lifecycle revision')) {
                    Logger::warning('Universe lifecycle action rejected', ['universe_id' => $universeId, 'action' => $action, 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'invalid_universe_lifecycle_action', 'message' => $message]], 400);
                }
                if (str_contains($message, 'does not exist')) return Response::json(['ok' => false, 'error' => ['code' => 'universe_not_found', 'message' => $message]], 404);
                Logger::error('Universe lifecycle update failed', ['universe_id' => $universeId, 'exception' => $e::class, 'message' => $message]);
                return Response::json(['ok' => false, 'error' => ['code' => 'universe_lifecycle_failed', 'message' => 'The universe lifecycle could not be updated. Check CORE logs.']], 500);
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
                if (str_contains($message, 'must ') || str_contains($message, 'cannot be empty') || str_contains($message, 'exceeds') || str_contains($message, 'Universe is disabled') || str_contains($message, 'Universe is locked')) {
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
                return Response::json(['ok' => false, 'error' => ['code' => 'invalid_universe_request', 'message' => $e->getMessage()]], 400);
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

        $this->router->get('/api/characters/record', static function (Request $request) use ($characterService, $config): Response {
            if (!self::developmentRequestAllowed($config)) return Response::json(['ok' => false, 'error' => ['code' => 'character_api_disabled', 'message' => 'Character records are available only in development mode from a local/private network.']], 403);
            $characterId = $request->query('character_id', '');
            if (!is_string($characterId) || trim($characterId) === '') return Response::json(['ok' => false, 'error' => ['code' => 'missing_character_id', 'message' => 'Provide a character_id query parameter.']], 400);
            try { return Response::json(['ok' => true, 'character' => $characterService->getById($characterId)]); }
            catch (Throwable $e) {
                if (str_contains($e->getMessage(), 'does not exist')) return Response::json(['ok' => false, 'error' => ['code' => 'character_not_found', 'message' => $e->getMessage()]], 404);
                Logger::error('Character record read failed', ['character_id' => $characterId, 'exception' => $e::class, 'message' => $e->getMessage()]);
                return Response::json(['ok' => false, 'error' => ['code' => 'character_read_failed', 'message' => 'Character record could not be read. Check CORE logs.']], 500);
            }
        });

        $this->router->get('/api/character-options', static function (): Response {
            $path = CORE_ROOT . '/storage/data/content/character-options.json';
            if (!is_file($path)) return Response::json(['ok' => false, 'error' => ['code' => 'character_options_missing', 'message' => 'Character creation definitions are missing.']], 500);
            try { $options = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); }
            catch (Throwable $e) { Logger::error('Character option definitions are invalid', ['message' => $e->getMessage()]); return Response::json(['ok' => false, 'error' => ['code' => 'character_options_invalid', 'message' => 'Character creation definitions are invalid. Check CORE logs.']], 500); }
            if (!is_array($options) || !is_array($options['roles'] ?? null) || !is_array($options['species'] ?? null) || !is_array($options['attributes'] ?? null)) return Response::json(['ok' => false, 'error' => ['code' => 'character_options_invalid', 'message' => 'Character creation definitions have an invalid structure.']], 500);
            return Response::json(['ok' => true, 'options' => $options]);
        });

        $this->router->get('/api/characters', static function (Request $request) use ($characterService, $config): Response {
            if (!self::developmentRequestAllowed($config)) {
                Logger::warning('Character API access denied', ['remote_addr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''), 'environment' => (string)($config['app']['environment'] ?? 'production')]);
                return Response::json(['ok' => false, 'error' => ['code' => 'character_api_disabled', 'message' => 'Character API is available only in development mode from a local/private network.']], 403);
            }
            $universeId = $request->query('universe_id', '');
            if (!is_string($universeId)) return Response::json(['ok' => false, 'error' => ['code' => 'invalid_universe_id', 'message' => 'universe_id must be a string.']], 400);
            try {
                if (trim($universeId) === '') {
                    $all = (new JsonStorage((string)$config['storage']['path']))->all('characters');
                    return Response::json(['ok' => true, 'characters' => $all]);
                }
                return Response::json(['ok' => true, 'universe_id' => trim($universeId), 'characters' => $characterService->listForUniverse($universeId)]);
            } catch (Throwable $e) {
                if (str_contains($e->getMessage(), 'does not exist')) return Response::json(['ok' => false, 'error' => ['code' => 'universe_not_found', 'message' => $e->getMessage()]], 404);
                Logger::error('Character list failed', ['universe_id' => $universeId, 'exception' => $e::class, 'message' => $e->getMessage()]);
                return Response::json(['ok' => false, 'error' => ['code' => 'character_list_failed', 'message' => 'Characters could not be loaded. Check CORE logs.']], 500);
            }
        });

        $this->router->post('/api/characters/scan', static function (Request $request) use ($scanService, $config): Response {
            if (!self::developmentRequestAllowed($config)) {
                Logger::warning('Character scan API access denied', ['remote_addr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''), 'environment' => (string)($config['app']['environment'] ?? 'production')]);
                return Response::json(['ok' => false, 'error' => ['code' => 'scan_api_disabled', 'message' => 'Character scanning is available only in development mode from a local/private network.']], 403);
            }
            $body = $request->jsonBody();
            $universeId = isset($body['universe_id']) && is_string($body['universe_id']) ? trim($body['universe_id']) : '';
            $characterId = isset($body['character_id']) && is_string($body['character_id']) ? trim($body['character_id']) : '';
            $expectedRevision = $body['expected_revision'] ?? null;
            if ($universeId === '' || $characterId === '' || !is_int($expectedRevision)) {
                Logger::warning('Character scan request validation failed', ['error_code' => 'invalid_character_scan_request']);
                return Response::json(['ok' => false, 'error' => ['code' => 'invalid_character_scan_request', 'message' => 'Provide universe_id, character_id, and integer expected_revision.']], 400);
            }
            try {
                $result = $scanService->scanCurrentSystem($universeId, $characterId, $expectedRevision);
                Logger::info('Character system scan completed', ['scan_status' => $result['scan_status'], 'character_id' => $characterId, 'universe_id' => $universeId, 'system_id' => $result['scan']['system_id'] ?? null, 'discoveries' => count($result['scan']['discoveries'] ?? []), 'revision' => $result['character']['revision'] ?? null]);
                return Response::json(['ok' => true] + $result);
            } catch (Throwable $e) {
                $message = $e->getMessage();
                if (str_contains($message, 'revision conflict')) {
                    Logger::warning('Character scan revision conflict', ['character_id' => $characterId, 'expected_revision' => $expectedRevision, 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'revision_conflict', 'message' => $message]], 409);
                }
                if (str_contains($message, 'does not exist')) return Response::json(['ok' => false, 'error' => ['code' => 'universe_not_found', 'message' => $message]], 404);
                if (str_contains($message, 'do not belong') || str_contains($message, 'not valid') || str_contains($message, 'required') || str_contains($message, 'Expected character revision') || str_contains($message, 'Character is disabled') || str_contains($message, 'Character is locked') || str_contains($message, 'Universe is disabled') || str_contains($message, 'Universe is locked')) {
                    Logger::warning('Character scan rejected', ['character_id' => $characterId, 'universe_id' => $universeId, 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'invalid_scan_request', 'message' => $message]], 400);
                }
                Logger::error('Character system scan failed', ['character_id' => $characterId, 'universe_id' => $universeId, 'exception' => $e::class, 'message' => $message]);
                return Response::json(['ok' => false, 'error' => ['code' => 'character_scan_failed', 'message' => 'System scan could not be saved. Check CORE logs.']], 500);
            }
        });

        $this->router->post('/api/characters/craft', static function (Request $request) use ($craftingService, $config): Response {
            if (!self::developmentRequestAllowed($config)) {
                Logger::warning('Character crafting API access denied', ['remote_addr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''), 'environment' => (string)($config['app']['environment'] ?? 'production')]);
                return Response::json(['ok' => false, 'error' => ['code' => 'crafting_api_disabled', 'message' => 'Crafting is available only in development mode from a local/private network.']], 403);
            }
            $body = $request->jsonBody();
            $universeId = isset($body['universe_id']) && is_string($body['universe_id']) ? trim($body['universe_id']) : '';
            $characterId = isset($body['character_id']) && is_string($body['character_id']) ? trim($body['character_id']) : '';
            $recipeId = isset($body['recipe_id']) && is_string($body['recipe_id']) ? trim($body['recipe_id']) : '';
            $quantity = $body['quantity'] ?? 1;
            $expectedRevision = $body['expected_revision'] ?? null;
            if ($universeId === '' || $characterId === '' || $recipeId === '' || !is_int($quantity) || !is_int($expectedRevision)) {
                Logger::warning('Character crafting request validation failed', ['error_code' => 'invalid_crafting_request']);
                return Response::json(['ok' => false, 'error' => ['code' => 'invalid_crafting_request', 'message' => 'Provide universe_id, character_id, recipe_id, integer quantity, and integer expected_revision.']], 400);
            }
            try {
                $result = $craftingService->craft($universeId, $characterId, $recipeId, $quantity, $expectedRevision);
                Logger::info('Character crafting completed', ['character_id' => $characterId, 'universe_id' => $universeId, 'recipe_id' => $recipeId, 'quantity' => $quantity, 'revision' => $result['character']['revision'] ?? null]);
                return Response::json(['ok' => true] + $result);
            } catch (Throwable $e) {
                $message = $e->getMessage();
                if (str_contains($message, 'revision conflict')) {
                    Logger::warning('Character crafting revision conflict', ['character_id' => $characterId, 'expected_revision' => $expectedRevision, 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'revision_conflict', 'message' => $message]], 409);
                }
                if (str_contains($message, 'does not exist')) {
                    Logger::warning('Character crafting target not found', ['character_id' => $characterId, 'universe_id' => $universeId, 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'crafting_target_not_found', 'message' => $message]], 404);
                }
                if (str_contains($message, 'required') || str_contains($message, 'must be') || str_contains($message, 'Unknown crafting') || str_contains($message, 'Insufficient') || str_contains($message, 'do not belong') || str_contains($message, 'Expected character revision') || str_contains($message, 'Character is disabled') || str_contains($message, 'Character is locked') || str_contains($message, 'Universe is disabled') || str_contains($message, 'Universe is locked')) {
                    Logger::warning('Character crafting rejected', ['character_id' => $characterId, 'recipe_id' => $recipeId, 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'crafting_rejected', 'message' => $message]], 409);
                }
                Logger::error('Character crafting failed', ['character_id' => $characterId, 'universe_id' => $universeId, 'recipe_id' => $recipeId, 'exception' => $e::class, 'message' => $message]);
                return Response::json(['ok' => false, 'error' => ['code' => 'crafting_failed', 'message' => 'Crafting could not be saved. Check CORE logs.']], 500);
            }
        });

        $this->router->post('/api/characters/fit', static function (Request $request) use ($fittingService, $config): Response {
            if (!self::developmentRequestAllowed($config)) {
                Logger::warning('Ship fitting API access denied', ['remote_addr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''), 'environment' => (string)($config['app']['environment'] ?? 'production')]);
                return Response::json(['ok' => false, 'error' => ['code' => 'fitting_api_disabled', 'message' => 'Ship fitting is available only in development mode from a local/private network.']], 403);
            }
            $body = $request->jsonBody();
            $universeId = isset($body['universe_id']) && is_string($body['universe_id']) ? trim($body['universe_id']) : '';
            $characterId = isset($body['character_id']) && is_string($body['character_id']) ? trim($body['character_id']) : '';
            $moduleId = isset($body['module_id']) && is_string($body['module_id']) ? trim($body['module_id']) : '';
            $expectedRevision = $body['expected_revision'] ?? null;
            if ($universeId === '' || $characterId === '' || $moduleId === '' || !is_int($expectedRevision)) {
                Logger::warning('Ship fitting request validation failed', ['error_code' => 'invalid_fitting_request']);
                return Response::json(['ok' => false, 'error' => ['code' => 'invalid_fitting_request', 'message' => 'Provide universe_id, character_id, module_id, and integer expected_revision.']], 400);
            }
            try {
                $result = $fittingService->install($universeId, $characterId, $moduleId, $expectedRevision);
                Logger::info('Ship module installed', ['character_id' => $characterId, 'universe_id' => $universeId, 'module_id' => $moduleId, 'revision' => $result['character']['revision'] ?? null]);
                return Response::json(['ok' => true] + $result);
            } catch (Throwable $e) {
                $message = $e->getMessage();
                if (str_contains($message, 'revision conflict')) return Response::json(['ok' => false, 'error' => ['code' => 'revision_conflict', 'message' => $message]], 409);
                if (str_contains($message, 'does not exist')) return Response::json(['ok' => false, 'error' => ['code' => 'fitting_target_not_found', 'message' => $message]], 404);
                if (str_contains($message, 'required') || str_contains($message, 'must be') || str_contains($message, 'Unknown ship module') || str_contains($message, 'Insufficient') || str_contains($message, 'already occupied') || str_contains($message, 'do not belong')) return Response::json(['ok' => false, 'error' => ['code' => 'fitting_rejected', 'message' => $message]], 409);
                Logger::error('Ship fitting failed', ['character_id' => $characterId, 'universe_id' => $universeId, 'module_id' => $moduleId, 'exception' => $e::class, 'message' => $message]);
                return Response::json(['ok' => false, 'error' => ['code' => 'fitting_failed', 'message' => 'Ship module could not be installed. Check CORE logs.']], 500);
            }
        });

        $this->router->post('/api/characters/discoveries/investigate', static function (Request $request) use ($discoveryService, $config): Response {
            if (!self::developmentRequestAllowed($config)) {
                Logger::warning('Discovery investigation API access denied', ['remote_addr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''), 'environment' => (string)($config['app']['environment'] ?? 'production')]);
                return Response::json(['ok' => false, 'error' => ['code' => 'discovery_api_disabled', 'message' => 'Discovery investigation is available only in development mode from a local/private network.']], 403);
            }
            $body = $request->jsonBody();
            $universeId = isset($body['universe_id']) && is_string($body['universe_id']) ? trim($body['universe_id']) : '';
            $characterId = isset($body['character_id']) && is_string($body['character_id']) ? trim($body['character_id']) : '';
            $discoveryId = isset($body['discovery_id']) && is_string($body['discovery_id']) ? trim($body['discovery_id']) : '';
            $expectedRevision = $body['expected_revision'] ?? null;
            if ($universeId === '' || $characterId === '' || $discoveryId === '' || !is_int($expectedRevision)) {
                Logger::warning('Discovery investigation request validation failed', ['error_code' => 'invalid_discovery_request']);
                return Response::json(['ok' => false, 'error' => ['code' => 'invalid_discovery_request', 'message' => 'Provide universe_id, character_id, discovery_id, and integer expected_revision.']], 400);
            }
            try {
                $result = $discoveryService->investigate($universeId, $characterId, $discoveryId, $expectedRevision);
                Logger::info('Discovery investigation completed', ['investigation_status' => $result['investigation_status'], 'character_id' => $characterId, 'universe_id' => $universeId, 'discovery_id' => $discoveryId, 'revision' => $result['character']['revision'] ?? null]);
                return Response::json(['ok' => true] + $result);
            } catch (Throwable $e) {
                $message = $e->getMessage();
                if (str_contains($message, 'revision conflict')) {
                    Logger::warning('Discovery investigation revision conflict', ['character_id' => $characterId, 'expected_revision' => $expectedRevision, 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'revision_conflict', 'message' => $message]], 409);
                }
                if (str_contains($message, 'does not exist')) {
                    Logger::warning('Discovery investigation target not found', ['character_id' => $characterId, 'discovery_id' => $discoveryId, 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'discovery_not_found', 'message' => $message]], 404);
                }
                if (str_contains($message, 'do not belong') || str_contains($message, 'Travel to') || str_contains($message, 'required') || str_contains($message, 'Expected character revision') || str_contains($message, 'Character is disabled') || str_contains($message, 'Character is locked') || str_contains($message, 'Universe is disabled') || str_contains($message, 'Universe is locked')) {
                    Logger::warning('Discovery investigation rejected', ['character_id' => $characterId, 'discovery_id' => $discoveryId, 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'investigation_rejected', 'message' => $message]], 409);
                }
                Logger::error('Discovery investigation failed', ['character_id' => $characterId, 'universe_id' => $universeId, 'discovery_id' => $discoveryId, 'exception' => $e::class, 'message' => $message]);
                return Response::json(['ok' => false, 'error' => ['code' => 'investigation_failed', 'message' => 'Discovery could not be investigated. Check CORE logs.']], 500);
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
                if (str_contains($message, 'do not belong') || str_contains($message, 'does not belong') || str_contains($message, 'not directly reachable') || str_contains($message, 'Character is disabled') || str_contains($message, 'Character is locked') || str_contains($message, 'Universe is disabled') || str_contains($message, 'Universe is locked')) {
                    Logger::warning('Character location target rejected', ['character_id' => $characterId, 'universe_id' => $universeId, 'system_id' => $systemId, 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'invalid_location', 'message' => $message]], 400);
                }
                if (str_contains($message, 'required') || str_contains($message, 'Expected character revision') || str_contains($message, 'valid system list') || str_contains($message, 'Character is disabled') || str_contains($message, 'Character is locked') || str_contains($message, 'Universe is disabled') || str_contains($message, 'Universe is locked')) {
                    Logger::warning('Character location request rejected', ['character_id' => $characterId, 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'invalid_location', 'message' => $message]], 400);
                }
                Logger::error('Character location update failed', ['character_id' => $characterId, 'universe_id' => $universeId, 'exception' => $e::class, 'message' => $message]);
                return Response::json(['ok' => false, 'error' => ['code' => 'character_location_failed', 'message' => 'Character location could not be updated. Check CORE logs.']], 500);
            }
        });

        $this->router->post('/api/characters/travel', static function (Request $request) use ($travelService, $config): Response {
            if (!self::developmentRequestAllowed($config)) {
                Logger::warning('Character travel API access denied', ['remote_addr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''), 'environment' => (string)($config['app']['environment'] ?? 'production')]);
                return Response::json(['ok' => false, 'error' => ['code' => 'travel_api_disabled', 'message' => 'Route execution is available only in development mode from a local/private network.']], 403);
            }
            $body = $request->jsonBody();
            $action = isset($body['action']) && is_string($body['action']) ? trim($body['action']) : '';
            $universeId = isset($body['universe_id']) && is_string($body['universe_id']) ? trim($body['universe_id']) : '';
            $characterId = isset($body['character_id']) && is_string($body['character_id']) ? trim($body['character_id']) : '';
            $expectedRevision = $body['expected_revision'] ?? null;
            if (!in_array($action, ['start', 'advance'], true) || $universeId === '' || $characterId === '' || !is_int($expectedRevision)) {
                Logger::warning('Character travel request validation failed', ['error_code' => 'invalid_travel_request', 'action' => $action]);
                return Response::json(['ok' => false, 'error' => ['code' => 'invalid_travel_request', 'message' => 'Provide action (start or advance), universe_id, character_id, and integer expected_revision.']], 400);
            }
            try {
                if ($action === 'start') {
                    $destinationId = isset($body['destination_system_id']) && is_string($body['destination_system_id']) ? trim($body['destination_system_id']) : '';
                    if ($destinationId === '') return Response::json(['ok' => false, 'error' => ['code' => 'invalid_travel_request', 'message' => 'Provide destination_system_id when starting a route.']], 400);
                    $result = $travelService->start($universeId, $characterId, $destinationId, $expectedRevision);
                } else {
                    $result = $travelService->advance($universeId, $characterId, $expectedRevision);
                }
                Logger::info('Character travel action completed', ['action' => $action, 'travel_status' => $result['travel_status'], 'character_id' => $characterId, 'universe_id' => $universeId, 'revision' => $result['character']['revision'], 'current_system_id' => $result['character']['current_system_id'] ?? null]);
                return Response::json(['ok' => true] + $result);
            } catch (Throwable $e) {
                $message = $e->getMessage();
                if (str_contains($message, 'revision conflict')) {
                    Logger::warning('Character travel revision conflict', ['action' => $action, 'character_id' => $characterId, 'expected_revision' => $expectedRevision, 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'revision_conflict', 'message' => $message]], 409);
                }
                if (str_contains($message, 'does not exist')) return Response::json(['ok' => false, 'error' => ['code' => 'travel_target_not_found', 'message' => $message]], 404);
                if (str_contains($message, 'does not belong') || str_contains($message, 'invalid path') || str_contains($message, 'not active') || str_contains($message, 'No travel route') || str_contains($message, 'route') || str_contains($message, 'location changed') || str_contains($message, 'Character is disabled') || str_contains($message, 'Character is locked') || str_contains($message, 'Universe is disabled') || str_contains($message, 'Universe is locked')) {
                    Logger::warning('Character travel action rejected', ['action' => $action, 'character_id' => $characterId, 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'travel_rejected', 'message' => $message]], 409);
                }
                if (str_contains($message, 'required') || str_contains($message, 'Expected character revision') || str_contains($message, 'valid system list') || str_contains($message, 'Character is disabled') || str_contains($message, 'Character is locked') || str_contains($message, 'Universe is disabled') || str_contains($message, 'Universe is locked')) {
                    Logger::warning('Character travel request rejected', ['action' => $action, 'character_id' => $characterId, 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'invalid_travel_request', 'message' => $message]], 400);
                }
                Logger::error('Character travel failed', ['action' => $action, 'character_id' => $characterId, 'universe_id' => $universeId, 'exception' => $e::class, 'message' => $message]);
                return Response::json(['ok' => false, 'error' => ['code' => 'character_travel_failed', 'message' => 'Character travel could not be updated. Check CORE logs.']], 500);
            }
        });

        $this->router->post('/api/characters/lifecycle', static function (Request $request) use ($lifecycleService, $config): Response {
            if (!self::developmentRequestAllowed($config)) {
                Logger::warning('Character lifecycle API access denied', ['remote_addr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''), 'environment' => (string)($config['app']['environment'] ?? 'production')]);
                return Response::json(['ok' => false, 'error' => ['code' => 'character_lifecycle_api_disabled', 'message' => 'Character lifecycle management is available only in development mode from a local/private network.']], 403);
            }
            $body = $request->jsonBody();
            $universeId = isset($body['universe_id']) && is_string($body['universe_id']) ? trim($body['universe_id']) : '';
            $characterId = isset($body['character_id']) && is_string($body['character_id']) ? trim($body['character_id']) : '';
            $action = isset($body['action']) && is_string($body['action']) ? trim($body['action']) : '';
            $expectedRevision = $body['expected_revision'] ?? null;
            if ($universeId === '' || $characterId === '' || $action === '' || !is_int($expectedRevision)) {
                Logger::warning('Character lifecycle request validation failed', ['error_code' => 'invalid_character_lifecycle_request']);
                return Response::json(['ok' => false, 'error' => ['code' => 'invalid_character_lifecycle_request', 'message' => 'Provide universe_id, character_id, action, and integer expected_revision.']], 400);
            }
            try {
                $result = $lifecycleService->apply($universeId, $characterId, $action, $expectedRevision);
                Logger::info('Character lifecycle action completed', ['action' => $action, 'lifecycle_status' => $result['lifecycle_status'], 'character_id' => $characterId, 'universe_id' => $universeId, 'revision' => $result['character']['revision'] ?? null]);
                return Response::json(['ok' => true] + $result);
            } catch (Throwable $e) {
                $message = $e->getMessage();
                if (str_contains($message, 'revision conflict')) {
                    Logger::warning('Character lifecycle revision conflict', ['action' => $action, 'character_id' => $characterId, 'expected_revision' => $expectedRevision]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'revision_conflict', 'message' => $message]], 409);
                }
                if (str_contains($message, 'does not exist')) return Response::json(['ok' => false, 'error' => ['code' => 'character_or_universe_not_found', 'message' => $message]], 404);
                if (str_contains($message, 'does not belong')) return Response::json(['ok' => false, 'error' => ['code' => 'character_universe_mismatch', 'message' => $message]], 400);
                if (str_contains($message, 'required') || str_contains($message, 'must be zero') || str_contains($message, 'Action must')) {
                    Logger::warning('Character lifecycle action rejected', ['action' => $action, 'character_id' => $characterId, 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'invalid_character_lifecycle_action', 'message' => $message]], 400);
                }
                Logger::error('Character lifecycle action failed', ['action' => $action, 'character_id' => $characterId, 'universe_id' => $universeId, 'exception' => $e::class, 'message' => $message]);
                return Response::json(['ok' => false, 'error' => ['code' => 'character_lifecycle_failed', 'message' => 'Character lifecycle could not be updated. Check CORE logs.']], 500);
            }
        });

        $this->router->post('/api/characters/delete', static function (Request $request) use ($characterService, $config): Response {
            if (!self::developmentRequestAllowed($config)) {
                Logger::warning('Character deletion API access denied', ['remote_addr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''), 'environment' => (string)($config['app']['environment'] ?? 'production')]);
                return Response::json(['ok' => false, 'error' => ['code' => 'character_delete_api_disabled', 'message' => 'Character deletion is available only in development mode from a local/private network.']], 403);
            }
            $body = $request->jsonBody();
            $universeId = isset($body['universe_id']) && is_string($body['universe_id']) ? trim($body['universe_id']) : '';
            $characterId = isset($body['character_id']) && is_string($body['character_id']) ? trim($body['character_id']) : '';
            $confirmationName = isset($body['confirmation_name']) && is_string($body['confirmation_name']) ? $body['confirmation_name'] : '';
            $expectedRevision = $body['expected_revision'] ?? null;
            if ($characterId === '' || trim($confirmationName) === '' || !is_int($expectedRevision)) {
                Logger::warning('Character deletion request validation failed', ['error_code' => 'invalid_character_delete_request']);
                return Response::json(['ok' => false, 'error' => ['code' => 'invalid_character_delete_request', 'message' => 'Provide character_id, confirmation_name, integer expected_revision, and universe_id when the character is assigned.']], 400);
            }
            try {
                $result = $characterService->deleteCharacter($universeId, $characterId, $confirmationName, $expectedRevision);
                Logger::warning('Character permanently deleted', ['character_id' => $characterId, 'universe_id' => $universeId, 'character_name' => $result['name'], 'last_revision' => $result['revision']]);
                return Response::json(['ok' => true, 'deletion' => $result]);
            } catch (Throwable $e) {
                $message = $e->getMessage();
                if (str_contains($message, 'revision conflict')) {
                    Logger::warning('Character deletion revision conflict', ['character_id' => $characterId, 'expected_revision' => $expectedRevision, 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'revision_conflict', 'message' => $message]], 409);
                }
                if (str_contains($message, 'does not exist')) return Response::json(['ok' => false, 'error' => ['code' => 'character_not_found', 'message' => $message]], 404);
                if (str_contains($message, 'required') || str_contains($message, 'must be zero') || str_contains($message, 'Confirmation name') || str_contains($message, 'leave universe_id empty')) {
                    Logger::warning('Character deletion rejected', ['character_id' => $characterId, 'universe_id' => $universeId, 'message' => $message]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'invalid_character_delete', 'message' => $message]], 400);
                }
                if (str_contains($message, 'does not belong')) {
                    Logger::warning('Cross-universe character deletion rejected', ['character_id' => $characterId, 'universe_id' => $universeId]);
                    return Response::json(['ok' => false, 'error' => ['code' => 'character_universe_mismatch', 'message' => $message]], 400);
                }
                Logger::error('Character deletion failed', ['character_id' => $characterId, 'universe_id' => $universeId, 'exception' => $e::class, 'message' => $message]);
                return Response::json(['ok' => false, 'error' => ['code' => 'character_delete_failed', 'message' => 'Character could not be deleted. Check CORE logs.']], 500);
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
            $role = isset($body['role_id']) && is_string($body['role_id']) ? $body['role_id'] : (isset($body['role']) && is_string($body['role']) ? $body['role'] : 'Explorer');
            $speciesId = isset($body['species_id']) && is_string($body['species_id']) ? $body['species_id'] : 'human';
            $attributes = is_array($body['attributes'] ?? null) ? $body['attributes'] : [];
            if (trim($name) === '') {
                Logger::warning('Character creation validation failed', ['error_code' => 'invalid_character_request']);
                return Response::json(['ok' => false, 'error' => ['code' => 'invalid_character_request', 'message' => 'Provide a non-empty character name.']], 400);
            }
            try {
                $character = $characterService->create($universeId, $name, $role, $speciesId, $attributes);
                Logger::info('Character created', ['character_id' => $character['id'], 'universe_id' => $universeId !== '' ? $universeId : null]);
                return Response::json(['ok' => true, 'character' => $character], 201);
            } catch (Throwable $e) {
                $message = $e->getMessage();
                if (str_contains($message, 'does not exist')) return Response::json(['ok' => false, 'error' => ['code' => 'universe_not_found', 'message' => $message]], 404);
                if (str_contains($message, 'required') || str_contains($message, 'cannot be empty') || str_contains($message, 'must be between') || str_contains($message, 'must be a non-empty') || str_contains($message, 'valid character role') || str_contains($message, 'valid character species') || str_contains($message, 'must be between') || str_contains($message, 'must be a whole number') || str_contains($message, 'Unknown character attribute') || str_contains($message, 'Universe is disabled') || str_contains($message, 'Universe is locked')) {
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
                'GET /api/content (items, recipes, discoveries, and ship modules from JSON definitions)',
                'GET /api/content/items',
                'GET /api/content/recipes',
                'GET /api/content/discoveries',
                'GET /api/content/ship-modules',
                'GET /api/characters/record?character_id=... (development/private network only; full persisted character state)',
                'GET /api/universe/preview?seed=your-seed',
                'POST /api/universe with JSON {"seed":"your-seed"}',
                'GET /api/characters?universe_id=... (development/private network only)',
                'GET /api/navigation?universe_id=... (development/private network only)',
                'GET /api/navigation/route?universe_id=...&from_system_id=...&to_system_id=... (development/private network only)',
                'POST /api/characters/travel with action start or advance (development/private network only)',
                'POST /api/characters/discoveries/investigate with universe_id, character_id, discovery_id, and expected_revision (development/private network only)',
                'POST /api/characters/craft with universe_id, character_id, recipe_id, quantity, and expected_revision (development/private network only)',
                'POST /api/characters/fit with universe_id, character_id, module_id, and expected_revision (development/private network only)',
                'POST /api/characters/lifecycle with universe_id, character_id, action, and expected_revision (development/private network only)',
                'POST /api/characters/delete with universe_id, character_id, confirmation_name, and expected_revision (development/private network only)',
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
