<?php

declare(strict_types=1);

namespace Oomycache\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Oomycache\Filament\Pages\QueryCacheStudio;

/**
 * Registers the OoMyCache Filament bridge on a panel.
 *
 *     ->plugin(\Oomycache\Filament\OomycachePlugin::make())
 */
final class OomycachePlugin implements Plugin
{
    public static function make(): static
    {
        // Resolve through the container so apps/tests can swap the plugin
        // implementation, matching Filament's recommended plugin pattern.
        return app(self::class);
    }

    public function getId(): string
    {
        return 'oomycache';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            QueryCacheStudio::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
