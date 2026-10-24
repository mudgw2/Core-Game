<?php
declare(strict_types=1);
/** Development-only guarded universe deletion with automatic pre-deletion backup. */
$_SERVER['REQUEST_URI'] = '/api/data-management/universe-delete';
require dirname(__DIR__) . '/public/index.php';
