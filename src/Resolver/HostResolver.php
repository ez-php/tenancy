<?php

declare(strict_types=1);

namespace EzPhp\Tenancy\Resolver;

use EzPhp\Http\RequestInterface;

/**
 * Class HostResolver
 *
 * Maps full hosts to tenant ids — for tenants on their own (custom) domains:
 * `['shop.acme.com' => 'acme']`. Host matching is case-insensitive.
 *
 * @package EzPhp\Tenancy\Resolver
 */
final readonly class HostResolver implements TenantResolverInterface
{
    /**
     * @var array<string, string>
     */
    private array $hosts;

    /**
     * @param array<string, string> $hosts Host => tenant id.
     */
    public function __construct(array $hosts)
    {
        $this->hosts = array_change_key_case($hosts, CASE_LOWER);
    }

    /**
     * @param RequestInterface $request
     *
     * @return string|null
     */
    public function resolve(RequestInterface $request): ?string
    {
        $host = RequestHost::of($request);

        return $host !== null ? ($this->hosts[$host] ?? null) : null;
    }
}
