# Security policy

## Supported versions

| Version | Supported |
| --- | --- |
| `0.1.x` (current stable) | Yes |
| `0.1.0-alpha.*` | No — upgrade to stable |
| Earlier | No |

## Reporting a vulnerability

Do not open a public issue. Use GitHub private vulnerability reporting:

https://github.com/OsamaElnagar/oomycache-filament/security/advisories/new

Include the bridge/core versions, Filament/Laravel/PHP versions, whether panel tenancy is enabled,
and the impact (unauthorized purge, tenant/panel cache leakage, stale data, UI authorization bypass).
You can also email `osamaelngar98@gmail.com` with the subject `OoMyCache Filament security`.

## Scope

In scope:

- bypass of the studio page Gate or the deny-by-default purge Gate,
- tenant/panel cache-key leakage through the bridge resolver,
- invalidation failures for Filament actions the bridge claims to cover,
- plugin registration or asset handling that could execute unintended code.

Out of scope:

- raw SQL/quiet writes outside Filament actions, which are documented as requiring an explicit core
  flush,
- misconfiguration where an operator grants purge access to every panel user,
- the core package's documented file-store maintenance limitations.

## Guidance

- Keep the core and bridge on the latest stable release.
- Restrict `oomycache.view` and `oomycache.purge` to operator roles.
- Audit every purge/invalidation in the application's audit trail.
- Configure a core tenant resolver for queue/console/API entrypoints outside panels.
- Follow the core [production runbook](https://github.com/OsamaElnagar/oomycache/blob/main/docs/implementation/production-runbook.md).
