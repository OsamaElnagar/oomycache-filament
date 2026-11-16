<?php

declare(strict_types=1);

namespace Oomycache\Filament\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Oomycache\Oomycache\Facades\Oomycache;

/**
 * Reusable, cache-backed navigation badge counter for Filament pages/resources.
 *
 * The count runs once per TTL through OoMyCache's explicit-table raw cache and
 * bypasses the query cache while executing, so the badge cannot cache itself.
 * The caller owns the dependency table list and a stable scope string; the key
 * is tenant/panel-aware because OoMyCache composes keys through its resolver.
 *
 * Usage in a Filament resource or page:
 *
 *     public static function getNavigationBadge(): ?string
 *     {
 *         return app(self::class)->cachedNavigationBadgeCount(
 *             'orders',
 *             'open',
 *             Order::query()->where('status', 'open'),
 *             120,
 *         );
 *     }
 *
 * Zero counts return null so no badge is rendered; counts above zero are
 * returned as strings. Invalidate the entry with
 * `Oomycache::flushTables(['orders'])` after writes the cache cannot observe.
 */
trait HasCachedNavigationBadge
{
    /**
     * @param  EloquentBuilder<Model>|QueryBuilder  $query  Count query; its results are never cached by the Eloquent layer.
     * @param  int  $ttl  Cache lifetime in seconds for the badge count.
     */
    public function cachedNavigationBadgeCount(
        string $table,
        string $scope,
        EloquentBuilder|QueryBuilder $query,
        int $ttl = 300,
    ): ?string {
        $count = (int) Oomycache::rememberQuery(
            [$table],
            'nav-badge:'.$table.':'.$scope,
            $ttl,
            fn (): int => (int) Oomycache::withoutQueryCache(
                fn (): int => (int) $query->count(),
            ),
        );

        return $count > 0 ? (string) $count : null;
    }
}
