<?php

declare(strict_types=1);

namespace EzPhp\Tenancy\Resolver;

use EzPhp\Http\RequestInterface;

/**
 * Class HeaderResolver
 *
 * Reads the tenant id from a request header (default `X-Tenant`) — for APIs
 * whose clients name the tenant explicitly. The client chooses the value, so
 * whether the caller may act for that tenant is an authorization question the
 * application must still answer.
 *
 * @package EzPhp\Tenancy\Resolver
 */
final readonly class HeaderResolver implements TenantResolverInterface
{
    /**
     * @param string $header
     */
    public function __construct(private string $header = 'X-Tenant')
    {
    }

    /**
     * @param RequestInterface $request
     *
     * @return string|null
     */
    public function resolve(RequestInterface $request): ?string
    {
        $value = $request->header($this->header);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
