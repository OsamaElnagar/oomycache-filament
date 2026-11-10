<?php

declare(strict_types=1);

namespace Oomycache\Filament\Support;

use Filament\Facades\Filament;
use Oomycache\Oomycache\Contracts\TenantResolver;
use Oomycache\Oomycache\Tenant\TenantContext;
use Throwable;

/**
 * Filament-aware tenant/panel segment resolver for OoMyCache.
 *
 * - panel id is always part of the key when inside a panel;
 * - when the current panel has tenancy and no tenant is resolved, caching
 *   fails closed (the query runs against the DB and is never cached);
 * - the optional user segment uses Filament's already-hashed scoped auth
 *   identifier rather than a raw user id.
 */
final class FilamentTenantResolver implements TenantResolver
{
    public function resolve(): TenantContext
    {
        if (! class_exists(Filament::class)) {
            return TenantContext::none();
        }

        try {
            $panel = Filament::getCurrentPanel();
        } catch (Throwable) {
            return TenantContext::none();
        }

        if ($panel === null) {
            return TenantContext::none();
        }

        $segments = ['panel' => (string) $panel->getId()];

        if ($panel->hasTenancy()) {
            try {
                $tenant = Filament::getTenant();
            } catch (Throwable) {
                return TenantContext::failClosed('filament-tenant-unresolved');
            }

            if ($tenant === null) {
                return TenantContext::failClosed('filament-tenant-unresolved');
            }

            $segments['tenant'] = $tenant::class.':'.$tenant->getKey();
        }

        if ((bool) config('oomycache.tenant.include_user', false)) {
            $identifier = $this->userIdentifier();

            if ($identifier !== null) {
                $segments['user'] = substr(hash('xxh128', $identifier), 0, 16);
            }
        }

        return TenantContext::for($segments);
    }

    private function userIdentifier(): ?string
    {
        try {
            $root = Filament::getFacadeRoot();
        } catch (Throwable) {
            return null;
        }

        if (! is_object($root) || ! method_exists($root, 'getUserScopedAuthIdentifier')) {
            return null;
        }

        try {
            $identifier = $root->getUserScopedAuthIdentifier();
        } catch (Throwable) {
            return null;
        }

        return is_string($identifier) && $identifier !== '' ? $identifier : null;
    }
}
