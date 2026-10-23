<?php
declare(strict_types=1);

return [
    'app' => [
        'name' => 'CORE',
        'environment' => 'development', // Change to production when deployed publicly.
        'timezone' => 'UTC',
    ],
    'storage' => [
        'path' => dirname(__DIR__) . '/storage/data',
    ],
    'qa' => [
        'enabled' => true, // Development-only browser QA endpoint; disable before public deployment.
    ],
    'logging' => [
        'path' => dirname(__DIR__) . '/storage/logs/core.log',
    ],
];
