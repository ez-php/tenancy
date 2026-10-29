<?php

declare(strict_types=1);

namespace EzPhp\Tenancy;

/**
 * Interface TenantStoreInterface
 *
 * Looks up known tenants by id. ResolveTenantMiddleware only activates a tenant
 * the store knows, so an arbitrary Host header or path segment never becomes a
 * tenant (and with it a cache prefix or index name). Bind your own
 * implementation (e.g. database-backed) in place of ArrayTenantStore.
 *
 * @package EzPhp\Tenancy
 */
interface TenantStoreInterface
{
    /**
     * @param string $id A syntactically valid tenant id.
     *
     * @return Tenant|null Null when the tenant is unknown.
     */
    public function find(string $id): ?Tenant;
}
