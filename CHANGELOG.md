# Release Notes

## Unreleased

- Extracted from the OoMyCache dogfood app into a standalone Filament 5 bridge package.
- `OomycachePlugin` (panel-scoped registration: `->plugin(OomycachePlugin::make())`).
- `Query Cache` studio page: store status, cacheable models + version tokens, live stats counters
  (when enabled), selective model invalidation and a global purge action.
- Filament-aware `TenantResolver`: panel id in every cache key; panel tenancy without a resolved
  tenant fails closed.
- `FilamentCacheInvalidator`: `ActionCalled` invalidation for attach/detach/associate/dissociate/
  reorder/import and all bulk actions (query-builder/pivot writes that bypass Eloquent events).
