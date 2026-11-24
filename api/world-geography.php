<?php
declare(strict_types=1);

/** Explicit geography API endpoint; no Apache rewrite rules required. */
$_SERVER['REQUEST_URI'] = '/api/world/geography';
require dirname(__DIR__) . '/public/index.php';
