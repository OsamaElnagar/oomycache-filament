<?php

declare(strict_types=1);

use Filament\Actions\Events\ActionCalled;
use Filament\Panel;
use Illuminate\Support\Facades\Event;
use Oomycache\Filament\OomycachePlugin;
use Oomycache\Filament\Pages\QueryCacheStudio;
use Oomycache\Filament\Support\FilamentCacheInvalidator;
use Oomycache\Filament\Support\FilamentTenantResolver;
use Oomycache\Filament\Tests\TestCase;
use Oomycache\Oomycache\Contracts\TenantResolver;

uses(TestCase::class);

test('the plugin exposes the oomycache identifier', function (): void {
    expect(OomycachePlugin::make()->getId())->toBe('oomycache');
});

test('make() resolves the plugin through the container', function (): void {
    $instance = new OomycachePlugin;

    app()->instance(OomycachePlugin::class, $instance);

    expect(OomycachePlugin::make())->toBe($instance);
});

test('the service provider binds the Filament-aware tenant resolver when Filament is present', function (): void {
    expect(app(TenantResolver::class))->toBeInstanceOf(FilamentTenantResolver::class);
});

test('the plugin registers the query cache studio page on a panel', function (): void {
    $panel = app(Panel::class);

    $panel->plugin(OomycachePlugin::make());

    expect($panel->hasPlugin('oomycache'))->toBeTrue()
        ->and($panel->getPages())->toContain(QueryCacheStudio::class);
});

test('the invalidator registration is idempotent and registers a listener', function (): void {
    $before = count(Event::getListeners(ActionCalled::class));

    FilamentCacheInvalidator::register();
    FilamentCacheInvalidator::register();

    $after = count(Event::getListeners(ActionCalled::class));

    expect(app()->bound('oomycache.filament-invalidator.registered'))->toBeTrue()
        ->and($after)->toBe($before)
        ->and($after)->toBeGreaterThan(0);
});

test('the bridge view namespace is registered', function (): void {
    expect(view()->exists('oomycache-filament::pages.query-cache-studio'))->toBeTrue();
});

test('the tenant resolver fails open outside a Filament panel', function (): void {
    $resolver = app(FilamentTenantResolver::class);

    expect($resolver->resolve()->segments())->toBeArray();
});
