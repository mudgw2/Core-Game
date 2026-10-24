<?php
declare(strict_types=1);

/** Explicit endpoint wrapper for the development-only character deletion API. */
$_SERVER['REQUEST_URI'] = '/api/characters/delete';
require dirname(__DIR__) . '/public/index.php';
