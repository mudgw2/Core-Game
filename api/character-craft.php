<?php
declare(strict_types=1);

/** Explicit wrapper for the development-only character crafting API. */
$_SERVER['REQUEST_URI'] = '/api/characters/craft';
require dirname(__DIR__) . '/public/index.php';
