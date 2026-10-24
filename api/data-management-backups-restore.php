<?php
declare(strict_types=1);
/** Development-only checksum-verified universe backup restore endpoint. */
$_SERVER['REQUEST_URI'] = '/api/data-management/backups/restore';
require dirname(__DIR__) . '/public/index.php';
