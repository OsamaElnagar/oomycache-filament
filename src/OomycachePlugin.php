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
            $this->studioPageClass(),
        ]);
    }

    /**
     * Apps may subclass QueryCacheStudio (e.g. to add navigation methods) and
     * register that class via config instead of registering a second page.
     *
     * @return class-string<QueryCacheStudio>
     */
    private function studioPageClass(): string
    {
        $configured = config('oomycache.filament.dashboard.studio_page', QueryCacheStudio::class);

        if (
            is_string($configured)
            && $configured !== ''
            && class_exists($configured)
            && ($configured === QueryCacheStudio::class || is_subclass_of($configured, QueryCacheStudio::class))
        ) {
            /** @var class-string<QueryCacheStudio> $configured */
            return $configured;
        }

        return QueryCacheStudio::class;
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
