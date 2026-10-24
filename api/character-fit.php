<?php
declare(strict_types=1);

/** Explicit wrapper for the development-only ship fitting API. */
$_SERVER['REQUEST_URI'] = '/api/characters/fit';
require dirname(__DIR__) . '/public/index.php';
