<?php
declare(strict_types=1);

/** Explicit persistent terrain/climate endpoint; no Apache rewrite rules required. */
$_SERVER['REQUEST_URI'] = '/api/world/terrain';
require dirname(__DIR__) . '/public/index.php';
