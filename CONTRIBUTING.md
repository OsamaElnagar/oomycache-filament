# Contributing

Thanks for improving the OoMyCache Filament bridge.

## Setup

```bash
composer install
composer test
```

The bridge test lane uses Testbench. It does not need a full Filament panel boot; keep it that way
unless a real panel is necessary for the behavior under test.

## Pull requests

- Keep the bridge optional: the core package must never gain a Filament/Livewire dependency.
- Preserve the `OomycachePlugin` shape, the studio Gates and the `ActionCalled` invalidator contract.
- Add a Pest test for plugin registration, navigation config, tenant behavior or invalidator changes.
- Update `README.md`, `CHANGELOG.md` and the bundled Boost skill when installation or behavior changes.
- Do not weaken the deny-by-default `can_purge` gate or the tenant fail-closed fallback.
- Do not commit `vendor/`, `build/` or test cache directories.

## Releases

Tags are cut from `main` after the bridge and core stable versions are compatible. See
`RELEASING.md` and the release workflow. Never move a published tag; cut a new patch.

## Security

Report vulnerabilities privately using [SECURITY.md](SECURITY.md), not a public issue.
