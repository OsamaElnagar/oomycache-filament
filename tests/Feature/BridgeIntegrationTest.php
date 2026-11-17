<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Oomycache\Filament\Pages\QueryCacheStudio;
use Oomycache\Filament\Support\FilamentCacheInvalidator;
use Oomycache\Filament\Support\FilamentTenantResolver;
use Oomycache\Filament\Tests\Fixtures\BridgeCacheableRecord;
use Oomycache\Filament\Tests\Fixtures\BridgeTenant;
use Oomycache\Filament\Tests\TestCase;
use Oomycache\Oomycache\Contracts\VersionStore;
use Oomycache\Oomycache\Events\QueryCacheInvalidated;

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

        public function getCurrentOrDefaultPanel(): Panel
        {
            return $this->panel;
        }

        public function getAuthGuard(): string
        {
            return $this->panel->getAuthGuard();
        }

        public function auth(): Guard
        {
            return auth()->guard($this->panel->getAuthGuard());
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

    // reorder is not handled by core model events; the bridge must flush it.
    app(FilamentCacheInvalidator::class)->handle(
        Action::make('reorder')->record($record),
    );

    expect($versions->get('bridge_cacheable_records', $connection))->not->toBe($before);
});

it('does not duplicate a core-handled delete for a cacheable model', function (): void {
    Schema::create('bridge_cacheable_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    $record = BridgeCacheableRecord::query()->create(['name' => 'Delete record']);

    $versions = app(VersionStore::class);
    $connection = $record->getConnection()->getName();
    $before = $versions->get('bridge_cacheable_records', $connection);

    app(FilamentCacheInvalidator::class)->handle(
        Action::make('delete')->record($record),
    );

    expect($versions->get('bridge_cacheable_records', $connection))->toBe($before);
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

it('recognizes a user authenticated on the current panel guard, not the default guard', function (): void {
    config([
        'auth.guards.admin' => ['driver' => 'session', 'provider' => 'users'],
        'oomycache.filament.dashboard.can_view' => 'oomycache.view',
    ]);
    Gate::define('oomycache.view', fn (Authenticatable $user): bool => $user->getAuthIdentifier() === 'panel-admin');

    auth()->guard('web')->setUser(new GenericUser(['id' => 'default-user']));
    auth()->guard('admin')->setUser(new GenericUser(['id' => 'panel-admin']));
    Filament::swap(fakeFilamentManager(Panel::make()->id('admin')->path('admin')->authGuard('admin')));

    expect(QueryCacheStudio::canAccess())->toBeTrue();

    auth()->guard('admin')->forgetUser();

    expect(QueryCacheStudio::canAccess())->toBeFalse();
});

it('authorizes purge through the panel guard while staying deny-by-default', function (): void {
    config([
        'auth.guards.admin' => ['driver' => 'session', 'provider' => 'users'],
        'oomycache.filament.dashboard.can_purge' => 'oomycache.purge',
    ]);
    Gate::define('oomycache.purge', fn (Authenticatable $user): bool => $user->getAuthIdentifier() === 'panel-admin');

    auth()->guard('web')->setUser(new GenericUser(['id' => 'default-user']));
    auth()->guard('admin')->setUser(new GenericUser(['id' => 'panel-admin']));
    Filament::swap(fakeFilamentManager(Panel::make()->id('admin')->path('admin')->authGuard('admin')));

    expect(QueryCacheStudio::canPurge())->toBeTrue();

    auth()->guard('admin')->forgetUser();

    expect(QueryCacheStudio::canPurge())->toBeFalse();

    auth()->guard('admin')->setUser(new GenericUser(['id' => 'panel-admin']));
    config(['oomycache.filament.dashboard.can_purge' => null]);

    expect(QueryCacheStudio::canPurge())->toBeFalse();
});

it('bumps the token exactly once for an explicit flush of a core-handled action', function (): void {
    Schema::create('bridge_cacheable_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    $record = BridgeCacheableRecord::query()->create(['name' => 'Explicit flush']);
    $versions = app(VersionStore::class);
    $connection = $record->getConnection()->getName();
    $before = $versions->get('bridge_cacheable_records', $connection);

    Event::fake([QueryCacheInvalidated::class]);
    $invalidator = app(FilamentCacheInvalidator::class);

    // The default path still defers to core events for cacheable models.
    $invalidator->handle(Action::make('delete')->record($record));

    expect($versions->get('bridge_cacheable_records', $connection))->toBe($before);

    // The explicit escape hatch bypasses coreHandles() exactly once.
    $invalidator->flushForAction(Action::make('delete')->record($record), $record);

    expect($versions->get('bridge_cacheable_records', $connection))->not->toBe($before);

    Event::assertDispatchedTimes(QueryCacheInvalidated::class, 1);
});

it('still requires a flush-worthy action name for an explicit flush', function (): void {
    Schema::create('bridge_cacheable_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    $record = BridgeCacheableRecord::query()->create(['name' => 'View only']);
    $versions = app(VersionStore::class);
    $connection = $record->getConnection()->getName();
    $before = $versions->get('bridge_cacheable_records', $connection);

    app(FilamentCacheInvalidator::class)->flushForAction(Action::make('view')->record($record), $record);

    expect($versions->get('bridge_cacheable_records', $connection))->toBe($before);
});

it('flushes a generic bulk action when flush_all_bulk_actions is enabled', function (): void {
    config(['oomycache.filament.invalidation.flush_all_bulk_actions' => true]);

    Schema::create('bridge_cacheable_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    $record = BridgeCacheableRecord::query()->create(['name' => 'Bulk enabled']);
    $versions = app(VersionStore::class);
    $connection = $record->getConnection()->getName();
    $before = $versions->get('bridge_cacheable_records', $connection);

    // A custom, non-allowlisted bulk name still flushes under the safe default.
    app(FilamentCacheInvalidator::class)->handle(
        BulkAction::make('exportSelected')->record($record),
    );

    expect($versions->get('bridge_cacheable_records', $connection))->not->toBe($before);
});

it('does not flush a generic bulk action when flush_all_bulk_actions is disabled', function (): void {
    config(['oomycache.filament.invalidation.flush_all_bulk_actions' => false]);

    Schema::create('bridge_cacheable_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    $record = BridgeCacheableRecord::query()->create(['name' => 'Bulk disabled']);
    $versions = app(VersionStore::class);
    $connection = $record->getConnection()->getName();
    $before = $versions->get('bridge_cacheable_records', $connection);

    app(FilamentCacheInvalidator::class)->handle(
        BulkAction::make('exportSelected')->record($record),
    );

    expect($versions->get('bridge_cacheable_records', $connection))->toBe($before);
});

it('still flushes an allowlisted bulk action when flush_all_bulk_actions is disabled', function (): void {
    config(['oomycache.filament.invalidation.flush_all_bulk_actions' => false]);

    Schema::create('bridge_cacheable_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    $record = BridgeCacheableRecord::query()->create(['name' => 'Bulk reorder']);
    $versions = app(VersionStore::class);
    $connection = $record->getConnection()->getName();
    $before = $versions->get('bridge_cacheable_records', $connection);

    // reorder is a known write action in FLUSH_AFTER_ACTION_NAMES and is not
    // deduped by coreHandles(), so the real listener path flushes it.
    app(FilamentCacheInvalidator::class)->handle(
        BulkAction::make('reorder')->record($record),
    );

    expect($versions->get('bridge_cacheable_records', $connection))->not->toBe($before);
});
