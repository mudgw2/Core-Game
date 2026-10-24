<?php
declare(strict_types=1);

/** Explicit wrapper for development-only shortest-route planning. */
$_SERVER['REQUEST_URI'] = '/api/navigation/route';
require dirname(__DIR__) . '/public/index.php';
