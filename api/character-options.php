<?php
declare(strict_types=1);

/** Explicit endpoint wrapper for data-driven character creation definitions. */
$_SERVER['REQUEST_URI'] = '/api/character-options';
require dirname(__DIR__) . '/public/index.php';
