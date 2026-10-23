<?php
declare(strict_types=1);

/** Explicit wrapper for the development-only character location API. */
$_SERVER['REQUEST_URI'] = '/api/characters/location';
require dirname(__DIR__) . '/public/index.php';
