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
- Alpha tags: install with an explicit alpha constraint
- Requires the core package `osamaelnagar/oomycache`

## Install

```bash
composer require osamaelnagar/oomycache:^0.1.0-alpha.1 osamaelnagar/oomycache-filament:^0.1.0-alpha.1
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

- Page access: authenticated panel users; optionally restrict with
  `oomycache.filament.dashboard.can_view` = Gate name.
- Navigation placement is configurable: `oomycache.filament.dashboard.navigation_label`,
  `.navigation_group` and `.navigation_sort`. The group defaults to the panel default when null;
  do not rely on the page forcing a specific group.
- **Destructive actions are deny-by-default.** Set
  `oomycache.filament.dashboard.can_purge` = Gate name and define that gate in the app:

```php
Gate::define('oomycache.purge', fn (User $user): bool => $user->is_admin);
config(['oomycache.filament.dashboard.can_purge' => 'oomycache.purge']);
```

Without the Gate the invalidate/clear buttons are hidden and the action closures refuse to run.

## Invalidation coverage

The bridge listens to Filament `ActionCalled` and flushes affected models for
attach/detach/associate/dissociate/reorder/import plus every bulk action. It resolves the affected
model from the action record, action model or Livewire table model, and never breaks the panel action.

It does **not** cover: raw SQL, `saveQuietly()`, `withoutEvents()`, or non-Filament pivot writes in app
code. `belongsToMany` pivots on cacheable models are covered by the core package's relation wrapper;
`morphToMany` and raw pivot writes still need an explicit flush (see the core skill/guardrails).

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
