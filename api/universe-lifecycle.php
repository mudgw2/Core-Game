<?php
declare(strict_types=1);

/** Explicit endpoint wrapper for reversible universe lifecycle controls. */
$_SERVER['REQUEST_URI'] = '/api/universe/lifecycle';
require dirname(__DIR__) . '/public/index.php';
