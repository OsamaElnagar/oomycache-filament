# OoMyCache Filament Bridge

Filament 5 bridge for [`osamaelnagar/oomycache`](https://github.com/osamaelnagar/oomycache).

## What it adds

- **Tenant/panel-safe cache keys** — binds a Filament-aware `TenantResolver`: the current panel id is
  part of every key; if a panel has tenancy and no tenant can be resolved, caching **fails closed**.
- **Query Cache studio page** — `Query Cache` navigation page with store status, cacheable models and
  their version tokens, live stats counters (when enabled), selective model invalidation and a global
  purge action. Filament-components-only markup, so custom panel themes apply unchanged.
- **Event-bypass invalidation** — listens to Filament `ActionCalled` and flushes the affected models
  for attach/detach/associate/dissociate/reorder/import and all bulk actions, which write through
  query builders / pivot tables without firing Eloquent model events.

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
