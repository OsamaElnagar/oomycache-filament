---
name: oomycache-filament
description: >
  Install and configure the OoMyCache Filament 5 bridge: the Query Cache studio page,
  tenant/panel-safe cache keys, and invalidation for Filament actions that bypass Eloquent events.
license: MIT
metadata:
  author: Osama Mohammed Elnagar
---

# OoMyCache Filament bridge — agent integration guide

Use this skill when a **Filament 5** app already uses (or should use) `osamaelnagar/oomycache` and needs
the panel integration. The bridge is optional; the core package works without Filament.

## Compatibility

- PHP 8.3+, Laravel 12/13, Filament 5, Livewire 4
- Stable releases: v0.1.0+; the core and bridge are installed together
- Requires the core package `osamaelnagar/oomycache`

## Install

```bash
composer require osamaelnagar/oomycache osamaelnagar/oomycache-filament
```

Register the plugin on the panel(s) where the Query Cache studio should appear:

```php
use Oomycache\Filament\OomycachePlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugin(OomycachePlugin::make());
}
```

Then add `HasQueryCache` to the Eloquent models you want cached (in the app, not the bridge).

## Studio page

`{panel-path}/query-cache` (e.g. `/admin/query-cache`, or `/query-cache` when the panel path is `''`) shows store status, cacheable models + version tokens, hit/miss/invalidation
counters (when stats are enabled), selective invalidation and a global purge.

- Page access: authenticated panel users on the **current Filament panel's auth guard** (not
  Laravel's default guard); Gates are evaluated for that panel user. Optionally restrict with
  `oomycache.filament.dashboard.can_view` = Gate name.
- Navigation placement is configurable: `oomycache.filament.dashboard.navigation_label`,
  `.navigation_group` and `.navigation_sort`. The group defaults to the panel default when null;
  do not rely on the page forcing a specific group.
- Optional navigation badge: `oomycache.filament.dashboard.navigation_badge` = `off` (default),
  `hit-rate` (hits/(hits+misses) as `98%`; success ≥90%, warning ≥70%, danger below),
  `invalidations` (stats counter, info) or `models` (count of cacheable models, gray). Values reuse
  the studio's memoized status/model discovery, so no badge queries the DB.
- A subclass can be registered through `oomycache.filament.dashboard.studio_page` (must extend
  `QueryCacheStudio`); invalid values fall back to the packaged page. This avoids a duplicate page
  slug when adding custom navigation methods.
- **Destructive actions are deny-by-default.** Set
  `oomycache.filament.dashboard.can_purge` = Gate name and define that gate in the app:

```php
Gate::define('oomycache.purge', fn (User $user): bool => $user->is_admin);
config(['oomycache.filament.dashboard.can_purge' => 'oomycache.purge']);
```

Without the Gate the invalidate/clear buttons are hidden and the action closures refuse to run.

## Cached navigation badges on other pages

Use `Oomycache\Filament\Support\HasCachedNavigationBadge` for resource/page badges that need a row
count. It caches the count through `Oomycache::rememberQuery()` and bypasses the query cache while
counting, so it cannot cache itself:

```php
use Oomycache\Filament\Support\HasCachedNavigationBadge;

public static function getNavigationBadge(): ?string
{
    return app(self::class)->cachedNavigationBadgeCount(
        'orders', 'open', Order::query()->where('status', 'open'), 120,
    );
}
```

The key is `nav-badge:{table}:{scope}` (tenant/panel-aware via the core resolver); zero returns
`null`. Invalidate with `Oomycache::flushTables(['orders'])` after writes the cache cannot see.

## Invalidation coverage

The bridge listens to Filament `ActionCalled` and flushes affected models for
associate/dissociate/reorder/import, bulk actions and event-bypassing actions on non-cacheable models.
Standard per-record attach/detach/delete/force-delete/restore actions on cacheable models defer to
the core events/pivot relations, so one logical write produces one audit/stats invalidation.

It does **not** cover: raw SQL, `saveQuietly()`, `withoutEvents()`, or non-Filament raw pivot writes in
app code. When a model uses `HasQueryCache`, the core wraps **both** `belongsToMany` and
`morphToMany` (plus `morphedByMany`) relations, so relation-level attach/detach/sync/toggle writes
invalidate the related tables. Raw pivot writes, query-builder writes and relation-manager actions
whose table the core cannot infer still need an explicit flush.

For those cases call the escape hatch (it bypasses the core-handled dedupe but still requires a
flush-worthy action name, and performs exactly one flush):

```php
use Oomycache\Filament\Support\FilamentCacheInvalidator;

app(FilamentCacheInvalidator::class)->flushForAction($action, $record);
// or flush the exact tables: Oomycache::flushTables(['order_tag']);
```

## Tenancy

The bridge binds a Filament-aware `TenantResolver`: the panel id is always part of the key; when a panel
has tenancy and no tenant can be resolved, caching **fails closed**. It only binds when
`oomycache.tenant.resolver` is empty — multi-tenant queue/console/API entrypoints must configure their
own resolver that fails closed outside a panel.

## Verify

```bash
cd packages/OoMyCacheFilament && composer test   # bridge package lane
php artisan oomycache:doctor
```

App-level dogfood tests live in the OoMyCache repository's dogfood app, not in this package; run them
there when validating end-to-end Filament behaviour (studio page, relation-manager pivot actions,
tenant panel isolation).

## Anti-patterns

- Enabling the bridge in apps that are not Filament apps.
- Granting `can_purge` to every panel user in production.
- Assuming Filament actions always fire model events — the bridge exists because some don't.
- Making the core package depend on Filament; keep the dependency in this bridge only.

## References

- Core skill: `resources/boost/skills/oomycache-development/SKILL.md` in the core package
- Bridge README, `RELEASING.md`
- Core docs: `docs/implementation/operational-guardrails.md`, `security-review.md`, `config-reference.md`
