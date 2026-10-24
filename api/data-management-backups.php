<?php
declare(strict_types=1);
/** Development-only universe backup inventory/create endpoint. */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') $_SERVER['REQUEST_URI'] = '/api/data-management/backups'; else $_SERVER['REQUEST_URI'] = '/api/data-management/backups';
require dirname(__DIR__) . '/public/index.php';
