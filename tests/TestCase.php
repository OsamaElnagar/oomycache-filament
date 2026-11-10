<?php

declare(strict_types=1);

namespace Oomycache\Filament\Tests;

use Oomycache\Filament\OomycacheFilamentServiceProvider;
use Oomycache\Oomycache\OomycacheServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

/**
 * Minimal Testbench application for the OoMyCache Filament bridge.
 *
 * The bridge service provider only needs the OoMyCache core provider: its
 * Filament integration points are class-existence guarded, so a full panel
 * boot (routes, assets, DB) is intentionally not required by these tests.
 */
abstract class TestCase extends OrchestraTestCase
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            OomycacheServiceProvider::class,
            OomycacheFilamentServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('oomycache.enabled', true);
        $app['config']->set('oomycache.store', null);
    }
}
