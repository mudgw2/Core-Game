<?php
declare(strict_types=1);

/**
 * Direct PHP entry point for universe share-code creation/rotation.
 * Bypasses extensionless Apache rewrite requirements while retaining the
 * same authenticated application-router handler and CSRF enforcement.
 */
$_SERVER['REQUEST_URI'] = '/api/universe/share';
require dirname(__DIR__) . '/public/index.php';
