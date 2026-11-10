<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Oomycache\Filament\Support\FilamentCacheInvalidator;
use Oomycache\Filament\Support\FilamentTenantResolver;
use Oomycache\Filament\Tests\Fixtures\BridgeCacheableRecord;
use Oomycache\Filament\Tests\Fixtures\BridgeTenant;
use Oomycache\Filament\Tests\TestCase;
use Oomycache\Oomycache\Contracts\VersionStore;

uses(TestCase::class);

function fakeFilamentManager(Panel $panel, ?Model $tenant = null): object
{
    return new class($panel, $tenant)
    {
        public function __construct(
            private readonly Panel $panel,
            private readonly ?Model $tenant,
        ) {}

        public function getCurrentPanel(): Panel
        {
            return $this->panel;
        }

        public function getTenant(): ?Model
        {
            return $this->tenant;
        }

        public function getUserScopedAuthIdentifier(): ?string
        {
            return 'test-user';
        }
    };
}

it('flushes the affected model for an event-bypassing Filament action', function (): void {
    Schema::create('bridge_cacheable_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    $record = BridgeCacheableRecord::query()->create(['name' => 'Record']);

    $versions = app(VersionStore::class);
    $connection = $record->getConnection()->getName();
    $before = $versions->get('bridge_cacheable_records', $connection);

    app(FilamentCacheInvalidator::class)->handle(
        Action::make('attach')->record($record),
    );

    expect($versions->get('bridge_cacheable_records', $connection))->not->toBe($before);
});

it('does not flush for an ordinary action name', function (): void {
    Schema::create('bridge_cacheable_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    $record = BridgeCacheableRecord::query()->create(['name' => 'Record']);

    $versions = app(VersionStore::class);
    $connection = $record->getConnection()->getName();
    $before = $versions->get('bridge_cacheable_records', $connection);

    app(FilamentCacheInvalidator::class)->handle(
        Action::make('view')->record($record),
    );

    expect($versions->get('bridge_cacheable_records', $connection))->toBe($before);
});

it('includes the panel id in tenant segments', function (): void {
    Filament::swap(fakeFilamentManager(Panel::make()->id('admin')->path('admin')));

    $context = app(FilamentTenantResolver::class)->resolve();

    expect($context->failClosed)->toBeFalse()
        ->and($context->segments())->toHaveKey('panel', 'admin');
});

it('fails closed when panel tenancy is active without a tenant', function (): void {
    Filament::swap(fakeFilamentManager(
        Panel::make()->id('admin')->path('admin')->tenant(BridgeTenant::class),
    ));

    $context = app(FilamentTenantResolver::class)->resolve();

    expect($context->failClosed)->toBeTrue()
        ->and($context->reason)->toBe('filament-tenant-unresolved');
});
