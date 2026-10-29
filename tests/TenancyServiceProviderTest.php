<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Application\Application;
use EzPhp\Cache\ArrayDriver;
use EzPhp\Cache\CacheInterface;
use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Http\ResponseInterface;
use EzPhp\Search\Drivers\NullDriver;
use EzPhp\Search\SearchDriverInterface;
use EzPhp\Tenancy\ArrayTenantStore;
use EzPhp\Tenancy\Cache\TenantScopedCache;
use EzPhp\Tenancy\Middleware\ResolveTenantMiddleware;
use EzPhp\Tenancy\Resolver\ChainResolver;
use EzPhp\Tenancy\Resolver\HeaderResolver;
use EzPhp\Tenancy\Resolver\HostResolver;
use EzPhp\Tenancy\Resolver\PathSegmentResolver;
use EzPhp\Tenancy\Resolver\RequestHost;
use EzPhp\Tenancy\Resolver\SubdomainResolver;
use EzPhp\Tenancy\Resolver\TenantResolverInterface;
use EzPhp\Tenancy\Search\TenantScopedSearchDriver;
use EzPhp\Tenancy\TenancyException;
use EzPhp\Tenancy\TenancyServiceProvider;
use EzPhp\Tenancy\Tenant;
use EzPhp\Tenancy\TenantContext;
use EzPhp\Tenancy\TenantStoreInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Tenancy\ApplicationTestCase;

/**
 * Class TenancyServiceProviderTest
 *
 * @package Tests
 */
#[CoversClass(TenancyServiceProvider::class)]
#[UsesClass(ArrayTenantStore::class)]
#[UsesClass(ChainResolver::class)]
#[UsesClass(HeaderResolver::class)]
#[UsesClass(HostResolver::class)]
#[UsesClass(PathSegmentResolver::class)]
#[UsesClass(RequestHost::class)]
#[UsesClass(SubdomainResolver::class)]
#[UsesClass(ResolveTenantMiddleware::class)]
#[UsesClass(TenantScopedCache::class)]
#[UsesClass(TenantScopedSearchDriver::class)]
#[UsesClass(Tenant::class)]
#[UsesClass(TenantContext::class)]
final class TenancyServiceProviderTest extends ApplicationTestCase
{
    private const array ENV_VARS = ['TENANCY_RESOLVER', 'TENANCY_BASE_DOMAIN', 'TENANCY_HEADER', 'TENANCY_PATH_SEGMENT', 'TENANCY_REQUIRED', 'TENANCY_CACHE_PREFIX'];

    protected function setUp(): void
    {
        $this->clearEnv();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->clearEnv();

        parent::tearDown();
    }

    /**
     * @param Application $app
     *
     * @return void
     */
    protected function configureApplication(Application $app): void
    {
        $app->register(TenancyServiceProvider::class);
    }

    public function test_middleware_store_and_adapters_share_one_context(): void
    {
        $this->app()->instance(CacheInterface::class, $store = new ArrayDriver());
        $cache = $this->app()->make(TenantScopedCache::class);

        $response = $this->app()->make(ResolveTenantMiddleware::class)->handle(
            new Request('GET', '/', headers: ['host' => 'acme.example.com']),
            function () use ($cache): ResponseInterface {
                $cache->set('k', 'v');

                return new Response();
            },
        );

        self::assertSame(200, $response->status());
        self::assertSame('v', $store->get('tenant:acme:k'));
        self::assertSame($this->app()->make(TenantContext::class), $this->app()->make(TenantContext::class));
        self::assertSame('Acme', $this->app()->make(TenantStoreInterface::class)->find('acme')?->get('name'));
    }

    /**
     * @return array<string, array{string, class-string<TenantResolverInterface>}>
     */
    public static function resolvers(): array
    {
        return [
            'subdomain' => ['subdomain', SubdomainResolver::class],
            'host' => ['host', HostResolver::class],
            'header' => ['header', HeaderResolver::class],
            'path' => ['path', PathSegmentResolver::class],
            'chain' => ['host, subdomain', ChainResolver::class],
        ];
    }

    /**
     * @param string                                $name
     * @param class-string<TenantResolverInterface> $class
     */
    #[DataProvider('resolvers')]
    public function test_resolver_is_built_from_config(string $name, string $class): void
    {
        putenv('TENANCY_RESOLVER=' . $name);

        self::assertInstanceOf($class, $this->app()->make(TenantResolverInterface::class));
    }

    public function test_unknown_resolver_fails(): void
    {
        putenv('TENANCY_RESOLVER=cookie');

        $this->expectException(TenancyException::class);

        $this->app()->make(TenantResolverInterface::class);
    }

    public function test_optional_mode_and_custom_cache_prefix(): void
    {
        putenv('TENANCY_REQUIRED=false');
        putenv('TENANCY_CACHE_PREFIX=t-');
        $this->app()->instance(CacheInterface::class, $store = new ArrayDriver());

        $response = $this->app()->make(ResolveTenantMiddleware::class)->handle(
            new Request('GET', '/', headers: ['host' => 'unknown.example.com']),
            fn (): ResponseInterface => new Response(),
        );
        $this->app()->make(TenantContext::class)->run(
            new Tenant('globex'),
            fn () => $this->app()->make(TenantScopedCache::class)->set('k', 1),
        );

        self::assertSame(200, $response->status());
        self::assertSame(1, $store->get('t-globex:k'));
    }

    public function test_search_adapter_wraps_the_bound_driver(): void
    {
        $this->app()->instance(SearchDriverInterface::class, new NullDriver());

        self::assertInstanceOf(TenantScopedSearchDriver::class, $this->app()->make(TenantScopedSearchDriver::class));
    }

    /**
     * @return void
     */
    private function clearEnv(): void
    {
        foreach (self::ENV_VARS as $var) {
            putenv($var);
        }
    }
}
