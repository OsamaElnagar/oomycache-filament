# Release Notes

## 0.1.2 - 2026-11-11

### Added

- `SECURITY.md` and `CONTRIBUTING.md`, plus README/AGENTS policy links.

## 0.1.1 - 2026-11-11

### Fixed

- Stable releases now pin the core package to the tagged constraint `^0.1` instead of also allowing
  `dev-main`/`dev-master`. This is required for a resolvable standalone stable bridge release.
- Local path-repository development declares the core package version as `0.1.0` so CI and the
  release test gate resolve the same pinned constraint.

## 0.1.0 - 2026-11-11

First stable release of the OoMyCache Filament 5 bridge.

### Added

- `OomycachePlugin` for panel-scoped registration.
- Query Cache studio page (`{panel-path}/query-cache`) with store status, cacheable models and
  version tokens, live hit/miss/bypass/invalidation/clear counters (when stats are enabled),
  selective model invalidation and a global purge.
- Configurable studio navigation label/group/sort; no hardcoded navigation group.
- Filament-aware tenant resolver: panel id in every key; active tenancy without a resolved tenant
  fails closed.
- `FilamentCacheInvalidator` for attach/detach/associate/dissociate/reorder/import and bulk actions
  that bypass Eloquent model events.
- Deny-by-default `oomycache.purge` Gate for destructive studio actions, plus optional
  `oomycache.view` page Gate.
- Companion Boost skill and agent guidance for installing the bridge safely.

### Security

- Destructive actions are hidden and refuse to run unless the operator Gate is configured.
- Tenant/panel keys are composed by the core resolver; unresolved panel tenancy is never cached.
- Bridge tests cover plugin registration, navigation config, invalidator behavior, tenant segments
  and fail-closed tenancy.

### Requirements

- PHP 8.3+, Laravel 12/13, Filament 5, Livewire 4.
- `osamaelnagar/oomycache` `^0.1`.
