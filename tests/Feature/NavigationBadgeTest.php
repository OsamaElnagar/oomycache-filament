<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Oomycache\Filament\Pages\QueryCacheStudio;
use Oomycache\Filament\Tests\Fixtures\BadgeCounter;
use Oomycache\Filament\Tests\Fixtures\BadgeStudio;
use Oomycache\Filament\Tests\Fixtures\BridgeCacheableRecord;
use Oomycache\Filament\Tests\TestCase;
use Oomycache\Oomycache\Facades\Oomycache;

uses(TestCase::class);

function statsFixture(int $hits, int $misses, int $invalidations = 0): array
{
    return [
        'enabled' => true,
        'window_seconds' => 3600,
        'hits' => $hits,
        'misses' => $misses,
        'invalidations' => $invalidations,
        'bypasses' => 0,
        'empty_bypasses' => 0,
        'clears' => 0,
    ];
}

test('the studio navigation badge is off by default', function (): void {
    expect(QueryCacheStudio::getNavigationBadge())->toBeNull()
        ->and(QueryCacheStudio::getNavigationBadgeColor())->toBeNull()
        ->and(QueryCacheStudio::getNavigationBadgeTooltip())->toBeNull();
});

test('an unknown badge mode renders nothing', function (): void {
    config(['oomycache.filament.dashboard.navigation_badge' => 'nonsense']);

    expect(QueryCacheStudio::getNavigationBadge())->toBeNull()
        ->and(QueryCacheStudio::getNavigationBadgeColor())->toBeNull();
});

dataset('hit rates', [
    'success' => [98, 2, '98%', 'success'],
    'warning' => [75, 25, '75%', 'warning'],
    'danger' => [60, 40, '60%', 'danger'],
]);

test('the hit-rate badge formats and colours the ratio', function (int $hits, int $misses, string $badge, string $color): void {
    config(['oomycache.filament.dashboard.navigation_badge' => 'hit-rate']);
    BadgeStudio::$status = ['stats' => statsFixture($hits, $misses)];

    expect(BadgeStudio::getNavigationBadge())->toBe($badge)
        ->and(BadgeStudio::getNavigationBadgeColor())->toBe($color)
        ->and(BadgeStudio::getNavigationBadgeTooltip())->toContain($hits.' hits / '.$misses.' misses');
})->with('hit rates');

test('the hit-rate badge has no value without samples', function (): void {
    config(['oomycache.filament.dashboard.navigation_badge' => 'hit-rate']);
    BadgeStudio::$status = ['stats' => statsFixture(0, 0)];

    expect(BadgeStudio::getNavigationBadge())->toBeNull()
        ->and(BadgeStudio::getNavigationBadgeColor())->toBeNull()
        ->and(BadgeStudio::getNavigationBadgeTooltip())->toContain('no samples');
});

test('the invalidations badge shows the stats counter with info colour', function (): void {
    config(['oomycache.filament.dashboard.navigation_badge' => 'invalidations']);
    BadgeStudio::$status = ['stats' => statsFixture(10, 2, 42)];

    expect(BadgeStudio::getNavigationBadge())->toBe('42')
        ->and(BadgeStudio::getNavigationBadgeColor())->toBe('info')
        ->and(BadgeStudio::getNavigationBadgeTooltip())->toContain('invalidations');
});

test('the models badge shows the discovered cacheable model count', function (): void {
    config(['oomycache.filament.dashboard.navigation_badge' => 'models']);
    BadgeStudio::$models = [
        'App\\Models\\First' => ['table' => 'firsts', 'token' => 't1', 'ttl' => 60, 'enabled' => true],
        'App\\Models\\Second' => ['table' => 'seconds', 'token' => 't2', 'ttl' => 60, 'enabled' => true],
    ];

    expect(BadgeStudio::getNavigationBadge())->toBe('2')
        ->and(BadgeStudio::getNavigationBadgeColor())->toBe('gray')
        ->and(BadgeStudio::getNavigationBadgeTooltip())->toContain('cacheable models');
});

test('flushing the badge memo refreshes it within a request', function (): void {
    config(['oomycache.filament.dashboard.navigation_badge' => 'invalidations']);
    BadgeStudio::$status = ['stats' => statsFixture(10, 2, 3)];

    expect(BadgeStudio::getNavigationBadge())->toBe('3');

    BadgeStudio::$status = ['stats' => statsFixture(10, 2, 9)];

    expect(BadgeStudio::getNavigationBadge())->toBe('3');

    BadgeStudio::flushNavigationBadgeMemo();

    expect(BadgeStudio::getNavigationBadge())->toBe('9');
});

test('studio badge computation never queries the database', function (): void {
    Schema::create('bridge_cacheable_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });
    BridgeCacheableRecord::query()->create(['name' => 'Cached']);
    BadgeStudio::$status = ['stats' => statsFixture(90, 10)];
    BadgeStudio::$models = [];

    config(['oomycache.filament.dashboard.navigation_badge' => 'hit-rate']);
    DB::flushQueryLog();
    DB::enableQueryLog();

    BadgeStudio::getNavigationBadge();
    BadgeStudio::getNavigationBadgeColor();
    BadgeStudio::getNavigationBadgeTooltip();

    BadgeStudio::flushNavigationBadgeMemo();
    config(['oomycache.filament.dashboard.navigation_badge' => 'models']);

    BadgeStudio::getNavigationBadge();

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBe([]);
});

test('the badge trait caches a raw count and returns null for zero', function (): void {
    Schema::create('badge_widgets', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    $counter = new BadgeCounter;
    $count = fn (): ?string => $counter->cachedNavigationBadgeCount(
        'badge_widgets',
        'all',
        DB::table('badge_widgets'),
        120,
    );

    expect($count())->toBeNull();

    DB::table('badge_widgets')->insert(['name' => 'first']);

    expect($count())->toBeNull();

    Oomycache::flushTables(['badge_widgets']);

    expect($count())->toBe('1');
});

test('the badge trait accepts an eloquent builder and scopes cache keys', function (): void {
    Schema::create('bridge_cacheable_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });
    BridgeCacheableRecord::query()->create(['name' => 'One']);

    $counter = new BadgeCounter;

    expect($counter->cachedNavigationBadgeCount('bridge_cacheable_records', 'all', BridgeCacheableRecord::query(), 120))->toBe('1')
        ->and($counter->cachedNavigationBadgeCount('bridge_cacheable_records', 'other', BridgeCacheableRecord::query()->where('name', 'missing')))->toBeNull();
});
