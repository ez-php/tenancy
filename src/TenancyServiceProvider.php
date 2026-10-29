<?php

declare(strict_types=1);

namespace EzPhp\Tenancy;

use EzPhp\Cache\CacheInterface;
use EzPhp\Contracts\ConfigInterface;
use EzPhp\Contracts\ContainerInterface;
use EzPhp\Contracts\ServiceProvider;
use EzPhp\Search\SearchDriverInterface;
use EzPhp\Tenancy\Cache\TenantScopedCache;
use EzPhp\Tenancy\Middleware\ResolveTenantMiddleware;
use EzPhp\Tenancy\Resolver\ChainResolver;
use EzPhp\Tenancy\Resolver\HeaderResolver;
use EzPhp\Tenancy\Resolver\HostResolver;
use EzPhp\Tenancy\Resolver\PathSegmentResolver;
use EzPhp\Tenancy\Resolver\SubdomainResolver;
use EzPhp\Tenancy\Resolver\TenantResolverInterface;
use EzPhp\Tenancy\Search\TenantScopedSearchDriver;

/**
 * Class TenancyServiceProvider
 *
 * Binds the shared TenantContext, the tenant store, the configured resolver and
 * ResolveTenantMiddleware, plus the tenant-scoped cache and search adapters
 * under their own class names. It never rebinds CacheInterface or
 * SearchDriverInterface — an application opts in by injecting
 * TenantScopedCache / TenantScopedSearchDriver, or by binding those interfaces
 * to them in its own provider.
 *
 * Configuration keys (in config/tenancy.php or environment):
 *   - tenancy.resolver      — `subdomain` (default) | `host` | `header` | `path`, or a comma-separated chain
 *   - tenancy.base_domain   — base domain for `subdomain`, e.g. `example.com`
 *   - tenancy.hosts         — host => tenant id map for `host`
 *   - tenancy.header        — header for `header` (default `X-Tenant`)
 *   - tenancy.path_segment  — zero-based segment for `path` (default 0)
 *   - tenancy.required      — 404 when no known tenant is resolved (default true)
 *   - tenancy.tenants       — tenant id => attributes map for ArrayTenantStore
 *   - tenancy.cache_prefix  — TenantScopedCache key prefix (default `tenant:`)
 *
 * @package EzPhp\Tenancy
 */
final class TenancyServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->instance(TenantContext::class, new TenantContext());

        $this->app->bind(TenantStoreInterface::class, function (ContainerInterface $app): TenantStoreInterface {
            $tenants = $app->make(ConfigInterface::class)->get('tenancy.tenants', []);

            return new ArrayTenantStore(is_array($tenants) ? $tenants : []);
        });

        $this->app->bind(TenantResolverInterface::class, function (ContainerInterface $app): TenantResolverInterface {
            $config = $app->make(ConfigInterface::class);
            $names = array_values(array_filter(array_map(
                'trim',
                explode(',', $this->string($config, 'tenancy.resolver', 'subdomain')),
            )));
            $resolvers = array_map(fn (string $name): TenantResolverInterface => $this->resolver($config, $name), $names);

            return count($resolvers) === 1 ? $resolvers[0] : new ChainResolver($resolvers);
        });

        $this->app->bind(ResolveTenantMiddleware::class, function (ContainerInterface $app): ResolveTenantMiddleware {
            $required = $app->make(ConfigInterface::class)->get('tenancy.required', true);

            return new ResolveTenantMiddleware(
                $app->make(TenantResolverInterface::class),
                $app->make(TenantStoreInterface::class),
                $app->make(TenantContext::class),
                filter_var($required, FILTER_VALIDATE_BOOLEAN),
            );
        });

        // ez-php/cache and ez-php/search are suggests: these closures only run —
        // and only autoload the adapters — when an application resolves them.
        $this->app->bind(TenantScopedCache::class, fn (ContainerInterface $app): TenantScopedCache => new TenantScopedCache(
            $app->make(CacheInterface::class),
            $app->make(TenantContext::class),
            $this->string($app->make(ConfigInterface::class), 'tenancy.cache_prefix', 'tenant:'),
        ));

        $this->app->bind(TenantScopedSearchDriver::class, fn (ContainerInterface $app): TenantScopedSearchDriver => new TenantScopedSearchDriver(
            $app->make(SearchDriverInterface::class),
            $app->make(TenantContext::class),
        ));
    }

    /**
     * @param ConfigInterface $config
     * @param string          $name
     *
     * @throws TenancyException When $name is not a known resolver.
     *
     * @return TenantResolverInterface
     */
    private function resolver(ConfigInterface $config, string $name): TenantResolverInterface
    {
        return match ($name) {
            'subdomain' => new SubdomainResolver($this->string($config, 'tenancy.base_domain', '')),
            'host' => new HostResolver($this->hostMap($config->get('tenancy.hosts', []))),
            'header' => new HeaderResolver($this->string($config, 'tenancy.header', 'X-Tenant')),
            'path' => new PathSegmentResolver($this->int($config->get('tenancy.path_segment', 0))),
            default => throw new TenancyException(sprintf(
                'Unknown tenancy resolver "%s" — use subdomain, host, header or path.',
                $name,
            )),
        };
    }

    /**
     * @param ConfigInterface $config
     * @param string          $key
     * @param string          $default
     *
     * @return string
     */
    private function string(ConfigInterface $config, string $key, string $default): string
    {
        $value = $config->get($key, $default);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * @param mixed $value
     *
     * @return int
     */
    private function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @param mixed $hosts
     *
     * @return array<string, string>
     */
    private function hostMap(mixed $hosts): array
    {
        $map = [];

        foreach (is_array($hosts) ? $hosts : [] as $host => $id) {
            if (is_string($host) && is_string($id)) {
                $map[$host] = $id;
            }
        }

        return $map;
    }
}
