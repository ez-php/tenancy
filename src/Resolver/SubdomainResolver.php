<?php

declare(strict_types=1);

namespace EzPhp\Tenancy\Resolver;

use EzPhp\Http\RequestInterface;
use EzPhp\Tenancy\TenancyException;

/**
 * Class SubdomainResolver
 *
 * `acme.example.com` with base domain `example.com` → `acme`. Exactly one label
 * in front of the base domain counts; the base domain itself and deeper hosts
 * (`a.b.example.com`) name no tenant.
 *
 * @package EzPhp\Tenancy\Resolver
 */
final readonly class SubdomainResolver implements TenantResolverInterface
{
    private string $suffix;

    /**
     * @param string $baseDomain e.g. `example.com`.
     *
     * @throws TenancyException When $baseDomain is empty.
     */
    public function __construct(string $baseDomain)
    {
        $baseDomain = strtolower(trim($baseDomain, ". \t\n\r\0\x0B"));

        if ($baseDomain === '') {
            throw new TenancyException('SubdomainResolver needs a base domain (tenancy.base_domain).');
        }

        $this->suffix = '.' . $baseDomain;
    }

    /**
     * @param RequestInterface $request
     *
     * @return string|null
     */
    public function resolve(RequestInterface $request): ?string
    {
        $host = RequestHost::of($request);

        if ($host === null || !str_ends_with($host, $this->suffix)) {
            return null;
        }

        $label = substr($host, 0, -strlen($this->suffix));

        return $label !== '' && !str_contains($label, '.') ? $label : null;
    }
}
