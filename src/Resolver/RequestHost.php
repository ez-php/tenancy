<?php

declare(strict_types=1);

namespace EzPhp\Tenancy\Resolver;

use EzPhp\Http\RequestInterface;

/**
 * Class RequestHost
 *
 * Reads the request's host — the `Host` header, else `$_SERVER['HTTP_HOST']`
 * — lower-cased and without port. `X-Forwarded-Host` is deliberately ignored:
 * it is client-controlled unless a trusted proxy sets it.
 *
 * @package EzPhp\Tenancy\Resolver
 */
final class RequestHost
{
    /**
     * @param RequestInterface $request
     *
     * @return string|null Null when the request carries no host.
     */
    public static function of(RequestInterface $request): ?string
    {
        $host = $request->header('host') ?? $request->server('HTTP_HOST');

        if (!is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower(trim($host));

        // "[::1]:8080" → "[::1]"; "example.com:8080" → "example.com".
        $host = str_starts_with($host, '[')
            ? substr($host, 0, (int) strpos($host . ']', ']') + 1)
            : explode(':', $host, 2)[0];

        return rtrim($host, '.') ?: null;
    }
}
