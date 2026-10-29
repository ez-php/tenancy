<?php

declare(strict_types=1);

namespace EzPhp\Tenancy\Resolver;

use EzPhp\Http\RequestInterface;

/**
 * Class PathSegmentResolver
 *
 * Reads the tenant id from a path segment: `/acme/orders` → `acme` at position
 * 0. Only the path is read (no query string). The URI is not rewritten — routes
 * still contain the segment, e.g. `/{tenant}/orders`.
 *
 * @package EzPhp\Tenancy\Resolver
 */
final readonly class PathSegmentResolver implements TenantResolverInterface
{
    /**
     * @param int $position Zero-based segment index.
     */
    public function __construct(private int $position = 0)
    {
    }

    /**
     * @param RequestInterface $request
     *
     * @return string|null
     */
    public function resolve(RequestInterface $request): ?string
    {
        $uri = $request->uri();
        // A request target is normally a path; only an absolute URI goes through
        // parse_url(), which would read "//acme/orders" as a host.
        $path = str_contains($uri, '://')
            ? parse_url($uri, PHP_URL_PATH)
            : substr($uri, 0, strcspn($uri, '?#'));

        if (!is_string($path)) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $path), fn (string $segment): bool => $segment !== ''));

        return isset($segments[$this->position]) ? rawurldecode($segments[$this->position]) : null;
    }
}
