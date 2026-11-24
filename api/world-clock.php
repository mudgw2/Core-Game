<?php
declare(strict_types=1);

/** Persistent simulation clock API; no Apache rewrite rules required. */
$_SERVER['REQUEST_URI'] = '/api/world/clock';
require dirname(__DIR__) . '/public/index.php';
