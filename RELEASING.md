# Releasing `osamaelnagar/oomycache-filament`

> **Package:** `osamaelnagar/oomycache-filament` · **Target repository:**
> `https://github.com/osamaelnagar/oomycache-filament` · **Automation:**
> [`.github/workflows/release.yml`](.github/workflows/release.yml) · **CI:**
> [`.github/workflows/tests.yml`](.github/workflows/tests.yml)
> **Shared runbook:** [`release-checklist.md`](../OoMyCache/docs/implementation/release-checklist.md)
> — this file only covers the bridge-specific deltas (read the core checklist first).

The bridge is a Filament 5 companion for `osamaelnagar/oomycache`. It currently lives inside the OoMyCache
dogfood app at `packages/OoMyCacheFilament` and is **not its own git repository yet**, so the release
workflow is target-state automation: it starts running once the directory is split into its own
repository and a `v*` tag is pushed. Version the bridge together with the core for the first release
(`v0.1.0-alpha.1`).

---

## 1. Repository split (one time)

The dogfood app is not a git repository yet. If/when it is, preserve per-package history with subtree;
otherwise copy the tree and `git init`.

```bash
# Option A: dogfood app is a git repository (history preserved)
git subtree split -P packages/OoMyCacheFilament -b oomycache-filament-standalone
git clone . ../oomycache-filament && cd ../oomycache-filament
git checkout oomycache-filament-standalone
git remote set-url origin https://github.com/osamaelnagar/oomycache-filament.git
git push -u origin HEAD:main

# Option B: no history to preserve
mkdir -p /tmp/oomycache-filament && rsync -a --exclude vendor --exclude .phpunit.cache --exclude build \
  packages/OoMyCacheFilament/ /tmp/oomycache-filament/
cd /tmp/oomycache-filament && git init -b main && git add . \
  && git commit -m "Extract OoMyCache Filament bridge" \
  && git remote add origin https://github.com/osamaelnagar/oomycache-filament.git \
  && git push -u origin main
```

After the split: submit the repo at <https://packagist.org/packages/submit>, enable the Packagist
webhook, add branch protection on `main`, and copy the core `.github/release.yml` (note categories) +
`update-changelog` workflow into this repository. Composer assumes the core lives at `../OoMyCache` via
a path repository for local development; the release workflow clones
`https://github.com/osamaelnagar/oomycache.git` next to the checkout, so no sibling clone is needed in CI.

---

## 2. Version pinning & release order

| Core release state | bridge `require` for `osamaelnagar/oomycache` |
| --- | --- |
| No core tag yet | `"dev-main \|\| dev-master \|\| ^0.1"` (current) |
| Prerelease core tag (e.g. `v0.1.0-alpha.1`) | keep the `dev-*` pins for the bridge alpha run, or pin the prerelease explicitly: `^0.1.0-alpha.1` — a bare `^0.1` does **not** match prerelease versions |
| First stable core tag (`v0.1.0`) | `"^0.1"` (remove `dev-main`/`dev-master`) |
| Core `v1.0.0` | `"^1.0"` (bump deliberately with the bridge's own major) |

Release order is always **core → wait for Packagist → bridge**. A bridge tag whose core pin cannot
resolve fails the release workflow's validate/test jobs (stable tags are rejected while the pin contains
`dev-*`; prerelease tags only warn). Update the bridge `tests.yml` clone step to the same tag-aware clone
once the `dev-*` pin is removed.

---

## 3. Pre-release gate

```bash
cd packages/OoMyCacheFilament             # or the standalone clone
composer validate --strict
composer test                             # Pint + PHPStan L7 + Pest (Testbench 11 / Filament 5)
```

Also confirm the app-level bridge tests are green in the dogfood app:

```bash
cd D:/Herd/filament-custom-pages
php artisan test tests/Feature/QueryCacheStudioTest.php \
  tests/Feature/QueryCacheTenancyBridgeTest.php \
  tests/Feature/QueryCacheRelationManagerInvalidationTest.php \
  tests/Feature/QueryCacheTenantPanelTest.php --compact
```

Checklist: `CHANGELOG.md` has an accurate `## Unreleased` section (no edit needed when present — the
workflow accepts `## Unreleased` and `## [X.Y.Z]`); `README.md` install instructions match the versions
being tagged; both packages' `LICENSE.md` exist; the workflow YAML parses (see the core checklist §11).

---

## 4. Tag and publish

```bash
git fetch origin && git checkout main && git pull --ff-only
git status --short                        # must be empty

# Rehearse (workflow_dispatch is a dry run by default: validates + tests, publishes nothing):
gh workflow run release.yml -f tag=v0.1.0-alpha.1 -f dry_run=true
gh run watch

# Publish:
git tag -a v0.1.0-alpha.1 -m "oomycache-filament v0.1.0-alpha.1"
git push origin v0.1.0-alpha.1
```

`.github/workflows/release.yml` validates the tag (SemVer, newest tag, cut from `main`), the composer
identity (`osamaelnagar/oomycache-filament`), the core pin, and the changelog; runs `composer test`;
then creates the GitHub Release with the changelog section + auto-generated notes, marked pre-release
for `alpha`/`beta`/`rc` tags. It refuses to overwrite an existing release — fix forward with a new
version instead of re-tagging.

---

## 5. Post-release verification

```bash
# Packagist has indexed the bridge (usually < 1 minute after the tag push):
curl -s https://repo.packagist.org/p2/osamaelnagar/oomycache-filament.json \
  | jq -r '.packages["osamaelnagar/oomycache-filament"][].version' | head -n 5
# Webhook delayed? force an update (needs a Packagist API token):
curl -s -X POST https://packagist.org/api/update-package \
  -d "username=osamaelnagar" -d "apiToken=$PACKAGIST_API_TOKEN" \
  -d '{"repository":"https://github.com/osamaelnagar/oomycache-filament"}' | jq .

# Fresh app smoke test:
composer create-project laravel/laravel bridge-smoke && cd bridge-smoke
composer require filament/filament:^5.0 osamaelnagar/oomycache osamaelnagar/oomycache-filament:^0.1
# ->plugin(OomycachePlugin::make()) on a panel; php artisan filament:make-user; php artisan serve
```

Checklist in the running Filament panel:

- [ ] Query Cache page renders store status, cacheable models + version tokens and stats counters.
- [ ] Selective invalidate and global purge actions work (version-namespace bumps only; no `Cache::flush()`).
- [ ] A tenanted panel shows tenant-scoped keys; a tenanted panel without a resolved tenant fails closed.
- [ ] Relation-manager attach/detach invalidates the affected models (event-bypass `ActionCalled` bridge).
- [ ] `composer require` resolves both packages without any `dev-*` constraints.

---

## 6. Rollback & security

Prefer a follow-up patch release over deleting tags. If a version was consumed, publish the fix and tell
users to upgrade; deleting the GitHub Release/tag and forcing a Packagist refresh is the last resort
(`gh release delete … --yes` + `git push origin :refs/tags/…`, see core checklist §9).

Report vulnerabilities privately through the core security policy
([`../OoMyCache/.github/SECURITY.md`](../OoMyCache/.github/SECURITY.md)); if the issue is bridge-only,
publish a separate GitHub Security Advisory on the bridge repository with its own affected range and
reference the core advisory ID when the root cause is in the core (core checklist §10).
