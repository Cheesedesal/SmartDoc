<?php

// Reference configuration. Laravel does not load this file automatically.
// Copy the legacy connection entry into config/database.php under "connections".
return [
    'legacy' => [
        'driver' => 'mysql',
        'host' => env('DB_LEGACY_HOST', env('DB_HOST', '127.0.0.1')),
        'port' => env('DB_LEGACY_PORT', env('DB_PORT', '3306')),
        'database' => env('DB_LEGACY_DATABASE', 'smartdoc_legacy'),
        'username' => env('DB_LEGACY_USERNAME', env('DB_USERNAME', 'root')),
        'password' => env('DB_LEGACY_PASSWORD', env('DB_PASSWORD', '')),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => false,
        'engine' => null,
    ],
];
