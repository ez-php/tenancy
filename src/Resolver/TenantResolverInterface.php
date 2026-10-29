<?php

declare(strict_types=1);

namespace EzPhp\Tenancy\Resolver;

use EzPhp\Http\RequestInterface;

/**
 * Interface TenantResolverInterface
 *
 * Extracts a candidate tenant id from a request. A resolver only reads the
 * request — ResolveTenantMiddleware validates the candidate and looks it up in
 * the tenant store.
 *
 * @package EzPhp\Tenancy\Resolver
 */
interface TenantResolverInterface
{
    /**
     * @param RequestInterface $request
     *
     * @return string|null The candidate id, or null when the request names no tenant.
     */
    public function resolve(RequestInterface $request): ?string;
}
