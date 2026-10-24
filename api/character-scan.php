<?php
declare(strict_types=1);

/** Explicit wrapper for the development-only character system-scan API. */
$_SERVER['REQUEST_URI'] = '/api/characters/scan';
require dirname(__DIR__) . '/public/index.php';
