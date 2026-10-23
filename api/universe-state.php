<?php
declare(strict_types=1);

/** Explicit endpoint wrapper; Application enforces development/private-network access. */
$_SERVER['REQUEST_URI'] = '/api/universe/state';
require dirname(__DIR__) . '/public/index.php';
