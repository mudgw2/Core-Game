<?php
declare(strict_types=1);
/** Development-only cleanup-impact preview endpoint. */
$_SERVER['REQUEST_URI'] = '/api/data-management/preview';
require dirname(__DIR__) . '/public/index.php';
