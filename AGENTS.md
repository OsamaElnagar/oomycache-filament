# OoMyCache Filament Bridge

Optional Filament 5 bridge for the framework-agnostic `osamaelnagar/oomycache` package.

## Boundaries

- Keep all Filament/Livewire code in this package; the core package must stay Filament-free.
- The bridge must remain optional: installing the core alone must never require Filament.
- Preserve the `OomycachePlugin` + service-provider shape and the `ActionCalled` invalidation contract.

## Map

- `src/OomycachePlugin.php` — panel plugin; registers the Query Cache studio page.
- `src/OomycacheFilamentServiceProvider.php` — binds the tenant resolver, registers the invalidator,
  loads the package views.
- `src/Pages/QueryCacheStudio.php` + `resources/views/pages/` — the `/admin/query-cache` page.
- `src/Support/FilamentTenantResolver.php` — panel segment + fail-closed tenancy + optional user segment.
- `src/Support/FilamentCacheInvalidator.php` — Filament `ActionCalled` → `Oomycache::flush`.
- `tests/` — Testbench/Pest package lane; app-level bridge tests live in the dogfood app.

## Commands

```bash
composer test       # Pint + PHPStan + Pest
composer analyse
composer test:unit
```

## Agent skill

`resources/boost/skills/oomycache-filament/SKILL.md` is shipped to Laravel Boost agents. Update it when
public behavior or installation changes.

## Do not

- Add a hard Filament dependency to the core package.
- Break the deny-by-default `can_purge` gate.
- Weaken the tenant resolver fallback (it must fail closed when tenancy is active without a tenant).
