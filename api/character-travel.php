<?php
declare(strict_types=1);

/** Explicit wrapper for development-only route execution. */
$_SERVER['REQUEST_URI'] = '/api/characters/travel';
require dirname(__DIR__) . '/public/index.php';
