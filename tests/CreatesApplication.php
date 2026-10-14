<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $this->assertTestDatabaseIsolated($app);

        return $app;
    }

    private function assertTestDatabaseIsolated(Application $app): void
    {
        $config = $app['config'];
        $sqlite = $config->get('database.connections.sqlite', []);

        if (
            $config->get('database.default') !== 'sqlite'
            || ($sqlite['driver'] ?? null) !== 'sqlite'
            || ($sqlite['database'] ?? null) !== ':memory:'
            || ! empty($sqlite['url'])
        ) {
            throw new \RuntimeException(
                'Tests aborted: the effective database configuration is not isolated SQLite memory.'
            );
        }
    }
}
