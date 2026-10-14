<?php

require_once dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$config = $app['config'];
$sqlite = $config->get('database.connections.sqlite', []);

if (
    $config->get('database.default') !== 'sqlite'
    || ($sqlite['driver'] ?? null) !== 'sqlite'
    || ($sqlite['database'] ?? null) !== ':memory:'
    || ! empty($sqlite['url'])
) {
    throw new RuntimeException(
        'PHPUnit aborted: effective database configuration must be isolated SQLite memory.'
    );
}

$config->set('database.connections', ['sqlite' => $sqlite]);

fwrite(STDOUT, "Verified PHPUnit database target: sqlite (:memory:)\n");
