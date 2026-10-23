<?php
declare(strict_types=1);

use Core\Application;
use Core\Http\Request;
use Core\Http\Response;

define('CORE_ROOT', dirname(__DIR__));

require CORE_ROOT . '/bootstrap.php';

$app = new Application();
$app->registerDefaultRoutes();

$request = Request::fromGlobals();
$response = $app->router()->dispatch($request);
$response->send();
