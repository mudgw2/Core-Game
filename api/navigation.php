<?php
declare(strict_types=1);

/** Explicit wrapper for the development-only navigation graph endpoint. */
$_SERVER['REQUEST_URI'] = '/api/navigation';
require dirname(__DIR__) . '/public/index.php';
