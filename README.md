<p align="center">
  <img src="https://cdn.jsdelivr.net/gh/OsamaElnagar/oomycache-filament@main/art/plugin-image.jpg" alt="OoMyCache Filament Bridge — Query Cache studio, tenant-safe cache keys and O(1) invalidation" width="100%" class="filament-hidden">
</p>

<h1 align="center">OoMyCache Filament Bridge</h1>

<p align="center">
  <strong>Query Cache studio, tenant-safe cache keys, and event-bypass invalidation for Filament 5.</strong><br>
  Optional bridge for the framework-agnostic <a href="https://packagist.org/packages/osamaelnagar/oomycache">osamaelnagar/oomycache</a> package.
</p>

<p align="center">
  <a href="https://packagist.org/packages/osamaelnagar/oomycache-filament"><img src="https://img.shields.io/packagist/v/osamaelnagar/oomycache-filament?style=flat-square&color=6f7dff" alt="Latest version"></a>
  <a href="https://packagist.org/packages/osamaelnagar/oomycache-filament"><img src="https://img.shields.io/packagist/dt/osamaelnagar/oomycache-filament?style=flat-square&color=14c9de" alt="Total downloads"></a>
  <a href="https://packagist.org/packages/osamaelnagar/oomycache-filament"><img src="https://img.shields.io/badge/Filament-v5-b6aaff?style=flat-square" alt="Filament v5"></a>
  <a href="https://packagist.org/packages/osamaelnagar/oomycache-filament"><img src="https://img.shields.io/badge/PHP-8.3%2B-777bb4?style=flat-square" alt="PHP 8.3+"></a>
  <a href="https://github.com/OsamaElnagar/oomycache-filament/stargazers"><img src="https://img.shields.io/github/stars/OsamaElnagar/oomycache-filament?style=flat-square&color=ff9bd6" alt="GitHub stars"></a>
  <a href="https://github.com/OsamaElnagar/oomycache-filament/actions/workflows/tests.yml"><img src="https://github.com/OsamaElnagar/oomycache-filament/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
  <a href="LICENSE.md"><img src="https://img.shields.io/badge/license-MIT-22d3a7?style=flat-square" alt="MIT license"></a>
</p>

Filament 5 bridge for [`osamaelnagar/oomycache`](https://github.com/osamaelnagar/oomycache): a Query
Cache studio for your panel, tenant-safe cache keys, and invalidation for Filament actions that bypass
Eloquent model events.

> OoMyCache core is **framework-agnostic** and works in any Laravel app. This bridge is optional —
> install it only when your app uses Filament.

## Measured impact

**77% fewer SQL statements · 99%+ cache hit rate** across 40 authenticated loads of 10 heavy Filament
pages in a production-like 62-model ERP.

| Surface | SQL before | SQL after | Reduction |
| --- | ---: | ---: | ---: |
| 10 heavy pages (4 loads each) | 791 | **184** | **77%** |
| Journal-entries list, eager lines | 3 / load | **0 / load** | **100%** |
| Audit-log list (12.6k rows) | 2 / load | **0 / load** | **100%** |

Warm renders execute no application queries for cached data, and a UI delete/force-delete/restore writes
exactly one invalidation and one audit row. Filament page wall time is Livewire-render-bound; compare SQL
and DB time rather than total milliseconds. The core [benchmark report](https://github.com/OsamaElnagar/oomycache/blob/main/docs/implementation/benchmark-report.md)
documents the full method and limitations.

## What it adds

- **Tenant/panel-safe cache keys** — binds a Filament-aware `TenantResolver`: the current panel id is
  part of every key; if a panel has tenancy and no tenant can be resolved, caching **fails closed**.
- **Query Cache studio page** — configurable `Query Cache` navigation page with store status, cacheable models and
  their version tokens, live stats counters (when enabled), selective model invalidation and a global
  purge action. Filament-components-only markup, so custom panel themes apply unchanged. Set
  `oomycache.filament.dashboard.navigation_label`, `.navigation_group` and `.navigation_sort` to fit
  the host panel's navigation; the group defaults to the panel's default group.
- **Optional navigation badge** — `oomycache.filament.dashboard.navigation_badge` accepts `off` (default),
  `hit-rate` (`98%`, coloured success at ≥90%, warning at ≥70%, danger below), `invalidations` (stats counter,
  info) and `models` (discovered cacheable models, gray). Badge values reuse the studio's memoised
  status/model discovery and never issue a DB query.
- **Current-panel guard awareness** — studio access and purge authorization use the active Filament panel's
  auth guard and evaluate Gates for that user. The purge gate stays deny-by-default.
- **Event-bypass invalidation** — listens to Filament `ActionCalled` and flushes the affected models
  for associate/dissociate/reorder/import, bulk actions and non-cacheable models, which write through
  query builders / pivot tables without firing Eloquent model events. Bulk-action flushing is
  configurable (safe by default — see [Bulk-action invalidation](#bulk-action-invalidation)). Standard
  per-record attach/detach/delete/force-delete/restore actions on cacheable models defer to core events
  so one logical write produces one invalidation. `FilamentCacheInvalidator::flushForAction()` is an
  explicit escape hatch for relation-manager or raw pivot writes the core cannot observe.

## Screenshots

<img src="https://cdn.jsdelivr.net/gh/OsamaElnagar/oomycache-filament@main/art/screenshots/query-cache-studio-light.jpg" alt="OoMyCache Query Cache studio in light mode: cache status, hit/miss stats and cacheable models" width="100%">

<img src="https://cdn.jsdelivr.net/gh/OsamaElnagar/oomycache-filament@main/art/screenshots/query-cache-studio-dark.jpg" alt="OoMyCache Query Cache studio in dark mode" width="100%">

<img src="https://cdn.jsdelivr.net/gh/OsamaElnagar/oomycache-filament@main/art/screenshots/products-light.jpg" alt="A Filament products table served from the OoMyCache query cache with zero ERP queries on a warm render" width="100%">

## Installation

```bash
composer require osamaelnagar/oomycache osamaelnagar/oomycache-filament
```

Register the plugin on the panel(s) where the cache studio should appear:

```php
use Oomycache\Filament\OomycachePlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(OomycachePlugin::make());
}
```

Then publish the OoMyCache configuration (in the core package) and add the `HasQueryCache` trait to
your models.

### Studio configuration

All keys live under `oomycache.filament.dashboard` in the core package's `config/oomycache.php`:

```php
'dashboard' => [
    'can_view' => null,                 // optional Gate name
    'can_purge' => 'oomycache.purge',   // deny-by-default Gate name
    'navigation_label' => 'Query Cache',
    'navigation_group' => null,
    'navigation_sort' => null,
    'navigation_badge' => 'off',        // off | hit-rate | invalidations | models
    'studio_page' => null,              // optional class-string subclass of QueryCacheStudio
],
```

Set `studio_page` to a `QueryCacheStudio` subclass to add your own navigation methods without
registering a second page with the same slug; invalid values fall back to the packaged page.

### Bulk-action invalidation

Bulk actions may write through query builders (for example `fetchSelectedRecords(false)` with
`getSelectedRecordsQuery()`) without firing Eloquent model events, so the bridge flushes the affected
models after them. This is configurable in the core package's `config/oomycache.php`:

```php
'invalidation' => [
    'flush_all_bulk_actions' => env('OOMYCACHE_FILAMENT_FLUSH_ALL_BULK_ACTIONS', true),
],
```

The safety trade-off:

- **`true` (default) — safest.** Every bulk action flushes, so even custom bulk actions that bypass
  model events invalidate correctly. The cost is possible over-invalidation (extra flushes / cache
  thrash).
- **`false` — fewer flushes.** Only bulk actions whose names are in the bridge's known-write
  allowlist (`attach`, `associate`, `detach`, `dissociate`, `reorder`, `import`, `delete`,
  `force-delete`, `restore`) flush. **Custom bulk actions that write via query builders must call
  `FilamentCacheInvalidator::flushForAction()` explicitly**, or those writes may remain cached.

For badges on other pages/resources, use the included trait:

```php
use Oomycache\Filament\Support\HasCachedNavigationBadge;

public static function getNavigationBadge(): ?string
{
    return app(self::class)->cachedNavigationBadgeCount(
        'orders',                       // dependency table for invalidation
        'open',                         // stable key scope
        Order::query()->where('status', 'open'),
        120,                            // TTL in seconds (default 300)
    );
}
```

It stores the count as `nav-badge:{table}:{scope}` through `Oomycache::rememberQuery()`, bypasses the
query cache while executing, and returns `null` for zero. Invalidate with `Oomycache::flushTables(['orders'])`.

For relation-manager or raw pivot writes the core cannot observe, call the explicit escape hatch:

```php
use Oomycache\Filament\Support\FilamentCacheInvalidator;

app(FilamentCacheInvalidator::class)->flushForAction($action, $record);
```

It flushes the affected cacheable model once, even for `delete`/`attach` actions normally handled by
core events; ordinary action names still do nothing.

## For AI agents (Laravel Boost)

The bridge ships a Laravel Boost skill at
[`resources/boost/skills/oomycache-filament/SKILL.md`](resources/boost/skills/oomycache-filament/SKILL.md)
covering plugin registration, the studio page, the deny-by-default `can_purge` gate, tenant resolver
behaviour, and what Filament actions the invalidator covers.

## Requirements

- PHP 8.3+
- Laravel 12 / 13
- Filament 5
- OoMyCache core (`osamaelnagar/oomycache`)

## Security

See [`SECURITY.md`](SECURITY.md) for supported versions and private vulnerability reporting. Do not
open public issues for security reports.

- Bulk-action flushing is configurable via `oomycache.filament.invalidation.flush_all_bulk_actions`
  (env `OOMYCACHE_FILAMENT_FLUSH_ALL_BULK_ACTIONS`, default `true`). The default is the safe choice: it
  flushes on every bulk action and may over-invalidate. Setting it to `false` reduces cache thrash but
  requires custom bulk actions that write via query builders to call
  `FilamentCacheInvalidator::flushForAction()` explicitly. The deny-by-default `can_purge` gate is
  unchanged.

## Contributing

See [`CONTRIBUTING.md`](CONTRIBUTING.md) for setup, tests, release rules and bridge-specific
constraints.

- The studio page is only accessible to authenticated panel users; set
  `oomycache.filament.dashboard.can_view` to a Gate name to restrict it further.
- **Destructive actions are deny-by-default.** Set `oomycache.filament.dashboard.can_purge` to a
  Gate name and define that gate for the operators who may invalidate/clear caches. Without it the
  buttons are hidden and the action closures refuse to run:

  ```php
  // AppServiceProvider::boot()
  Gate::define('oomycache.purge', fn (User $user): bool => $user->is_admin);
  config(['oomycache.filament.dashboard.can_purge' => 'oomycache.purge']);
  ```

- Purge/invalidate actions use the OoMyCache version-namespace API (O(1) token bumps) and never call
  `Cache::flush()` or Redis `flushdb`.
- Tenancy fail-closed behaviour is inherited from the core `TenantResolver` contract.
- **Multi-tenant queue/console/API entrypoints:** the bridge binds the Filament resolver only when no
  `oomycache.tenant.resolver` is configured. If your app resolves tenants outside panels (jobs, APIs),
  configure that resolver and make it return `TenantContext::failClosed()` when no tenant is known;
  otherwise those entrypoints run without tenant/panel segments.
