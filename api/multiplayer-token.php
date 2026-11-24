<?php
declare(strict_types=1);

/**
 * Direct PHP entry point for the multiplayer authorization grant.
 *
 * This endpoint intentionally bypasses Apache rewrite requirements. The
 * browser can POST to /api/multiplayer-token.php even when AllowOverride is
 * disabled or a VirtualHost rewrite does not cover extensionless API paths.
 * The front controller still performs the same authentication, CSRF, grant,
 * and authorization checks as the canonical router endpoint.
 */
$_SERVER['REQUEST_URI'] = '/api/multiplayer/token';
require dirname(__DIR__) . '/public/index.php';
