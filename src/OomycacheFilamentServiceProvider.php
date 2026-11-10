<?php

declare(strict_types=1);

namespace Oomycache\Filament;

use Filament\Facades\Filament;
use Illuminate\Support\ServiceProvider;
use Oomycache\Filament\Support\FilamentCacheInvalidator;
use Oomycache\Filament\Support\FilamentTenantResolver;
use Oomycache\Oomycache\Contracts\TenantResolver;

/**
 * Filament bridge service provider.
 *
 * - binds the Filament-aware tenant/panel resolver into the OoMyCache engine;
 * - registers the ActionCalled invalidation bridge;
 * - registers the package view namespace used by the Query Cache studio page.
 *
 * The plugin itself is opt-in per panel:
 *
 *     ->plugin(\Oomycache\Filament\OomycachePlugin::make())
 */
final class OomycacheFilamentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'oomycache-filament');

        // Multi-tenant apps (queues, console, API) may provide their own
        // resolver that understands tenants outside a Filament panel. Never
        // override an explicitly configured resolver; the core provider binds
        // it instead.
        if (class_exists(Filament::class) && ! $this->hasConfiguredTenantResolver()) {
            $this->app->bind(TenantResolver::class, FilamentTenantResolver::class);
        }
    }

    public function boot(): void
    {
        FilamentCacheInvalidator::register();
    }

    private function hasConfiguredTenantResolver(): bool
    {
        $resolver = config('oomycache.tenant.resolver');

        return is_string($resolver) && $resolver !== '';
    }
}
