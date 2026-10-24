<?php
declare(strict_types=1);

/** Explicit wrapper for the development-only discovery-investigation API. */
$_SERVER['REQUEST_URI'] = '/api/characters/discoveries/investigate';
require dirname(__DIR__) . '/public/index.php';
