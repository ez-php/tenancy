<?php

declare(strict_types=1);

namespace EzPhp\Tenancy;

/**
 * Class TenantContext
 *
 * Holds the tenant active for the current unit of work. Set per request by
 * ResolveTenantMiddleware, and by run() for CLI commands and queue jobs, which
 * have no request.
 *
 * One shared instance per process (bound by TenancyServiceProvider): the
 * middleware and every tenant-scoped service must see the same holder, so
 * resolve it on the root container, not on a scope() child. It is not
 * fiber-aware — concurrent fibers (e.g. an ez-php/websocket server) would share
 * one active tenant.
 *
 * @package EzPhp\Tenancy
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    /**
     * The active tenant, or null.
     *
     * @return Tenant|null
     */
    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * The active tenant.
     *
     * @throws TenancyException When no tenant is active.
     *
     * @return Tenant
     */
    public function current(): Tenant
    {
        return $this->tenant ?? throw new TenancyException(
            'No tenant is active. Resolve one with ResolveTenantMiddleware, or wrap CLI/queue code in TenantContext::run().'
        );
    }

    /**
     * Whether a tenant is active.
     *
     * @return bool
     */
    public function check(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Set (or clear, with null) the active tenant.
     *
     * @param Tenant|null $tenant
     *
     * @return void
     */
    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    /**
     * Run $callback with $tenant active, then restore the previous tenant —
     * also when $callback throws.
     *
     * @template T
     *
     * @param Tenant          $tenant
     * @param callable(): T   $callback
     *
     * @return T
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }
}
