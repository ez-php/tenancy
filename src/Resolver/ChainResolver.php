<?php

declare(strict_types=1);

namespace EzPhp\Tenancy\Resolver;

use EzPhp\Http\RequestInterface;

/**
 * Class ChainResolver
 *
 * Asks each resolver in order and returns the first candidate — e.g. custom
 * domains first, then subdomains.
 *
 * @package EzPhp\Tenancy\Resolver
 */
final readonly class ChainResolver implements TenantResolverInterface
{
    /**
     * @param list<TenantResolverInterface> $resolvers
     */
    public function __construct(private array $resolvers)
    {
    }

    /**
     * @param RequestInterface $request
     *
     * @return string|null
     */
    public function resolve(RequestInterface $request): ?string
    {
        foreach ($this->resolvers as $resolver) {
            $id = $resolver->resolve($request);

            if ($id !== null) {
                return $id;
            }
        }

        return null;
    }
}
