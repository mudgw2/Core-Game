<?php
declare(strict_types=1);

/** Explicit endpoint wrapper for reversible character lifecycle controls. */
$_SERVER['REQUEST_URI'] = '/api/characters/lifecycle';
require dirname(__DIR__) . '/public/index.php';
