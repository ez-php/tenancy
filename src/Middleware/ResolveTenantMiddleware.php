<?php

declare(strict_types=1);

namespace EzPhp\Tenancy\Middleware;

use EzPhp\Contracts\MiddlewareInterface;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\Response;
use EzPhp\Http\ResponseInterface;
use EzPhp\Tenancy\Resolver\TenantResolverInterface;
use EzPhp\Tenancy\Tenant;
use EzPhp\Tenancy\TenantContext;
use EzPhp\Tenancy\TenantStoreInterface;

/**
 * Class ResolveTenantMiddleware
 *
 * Resolves the request's tenant, checks it against the tenant store and makes
 * it the active tenant in TenantContext for the rest of the pipeline. The
 * previous tenant is restored afterwards — also when the handler throws — so
 * long-running workers and consecutive test requests never inherit a tenant.
 *
 * A missing, malformed or unknown tenant answers 404 when $required (the
 * default); otherwise the request continues with no tenant active.
 *
 * Resolution is not authorization: with the header or path resolver, a
 * signed-in user of one tenant can name another. Membership checks belong
 * after this middleware, in the application or ez-php/authorization.
 *
 * @package EzPhp\Tenancy\Middleware
 */
final readonly class ResolveTenantMiddleware implements MiddlewareInterface
{
    /**
     * @param TenantResolverInterface $resolver
     * @param TenantStoreInterface    $store
     * @param TenantContext           $context
     * @param bool                    $required
     */
    public function __construct(
        private TenantResolverInterface $resolver,
        private TenantStoreInterface $store,
        private TenantContext $context,
        private bool $required = true,
    ) {
    }

    /**
     * @param RequestInterface $request
     * @param callable         $next
     *
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        $id = $this->resolver->resolve($request);
        $tenant = $id !== null && Tenant::isValidId($id) ? $this->store->find($id) : null;

        if ($tenant === null && $this->required) {
            return new Response('Tenant not found.', 404);
        }

        $previous = $this->context->get();
        $this->context->set($tenant);

        try {
            /** @var ResponseInterface */
            return $next($request);
        } finally {
            $this->context->set($previous);
        }
    }
}
