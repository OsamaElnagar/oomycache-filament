# OoMyCache Filament Bridge

<img src="https://cdn.jsdelivr.net/gh/OsamaElnagar/oomycache-filament@main/art/plugin-image.jpg" alt="OoMyCache Filament Bridge — Query Cache studio, tenant-safe cache keys and O(1) invalidation" width="100%" class="filament-hidden">

Filament 5 bridge for [`osamaelnagar/oomycache`](https://github.com/osamaelnagar/oomycache): a Query
Cache studio for your panel, tenant-safe cache keys, and invalidation for Filament actions that bypass
Eloquent model events.

## What it adds

- **Tenant/panel-safe cache keys** — binds a Filament-aware `TenantResolver`: the current panel id is
  part of every key; if a panel has tenancy and no tenant can be resolved, caching **fails closed**.
- **Query Cache studio page** — `Query Cache` navigation page with store status, cacheable models and
  their version tokens, live stats counters (when enabled), selective model invalidation and a global
  purge action. Filament-components-only markup, so custom panel themes apply unchanged.
- **Event-bypass invalidation** — listens to Filament `ActionCalled` and flushes the affected models
  for attach/detach/associate/dissociate/reorder/import and all bulk actions, which write through
  query builders / pivot tables without firing Eloquent model events.

## Screenshots

<img src="https://cdn.jsdelivr.net/gh/OsamaElnagar/oomycache-filament@main/art/screenshots/query-cache-studio-light.jpg" alt="OoMyCache Query Cache studio in light mode: cache status, hit/miss stats and cacheable models" width="100%">

<img src="https://cdn.jsdelivr.net/gh/OsamaElnagar/oomycache-filament@main/art/screenshots/query-cache-studio-dark.jpg" alt="OoMyCache Query Cache studio in dark mode" width="100%">

<img src="https://cdn.jsdelivr.net/gh/OsamaElnagar/oomycache-filament@main/art/screenshots/products-light.jpg" alt="A Filament products table served from the OoMyCache query cache with zero ERP queries on a warm render" width="100%">

## Installation

```bash
composer require osamaelnagar/oomycache:^0.1.0-alpha.1 osamaelnagar/oomycache-filament:^0.1.0-alpha.1
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

## Requirements

- PHP 8.3+
- Laravel 12 / 13
- Filament 5
- OoMyCache core (`osamaelnagar/oomycache`)

## Security

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
