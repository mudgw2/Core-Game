<?php
declare(strict_types=1);

/**
 * Explicit endpoint for CORE's API.
 * This avoids relying on Apache rewrite rules and normalizes the route path
 * when CORE is installed in a subdirectory such as /core-game.
 */
$_SERVER['REQUEST_URI'] = '/api/health';
require dirname(__DIR__) . '/public/index.php';
