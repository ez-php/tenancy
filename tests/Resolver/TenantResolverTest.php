<?php

declare(strict_types=1);

namespace Tests\Resolver;

use EzPhp\Http\Request;
use EzPhp\Tenancy\Resolver\ChainResolver;
use EzPhp\Tenancy\Resolver\HeaderResolver;
use EzPhp\Tenancy\Resolver\HostResolver;
use EzPhp\Tenancy\Resolver\PathSegmentResolver;
use EzPhp\Tenancy\Resolver\RequestHost;
use EzPhp\Tenancy\Resolver\SubdomainResolver;
use EzPhp\Tenancy\TenancyException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Class TenantResolverTest
 *
 * @package Tests\Resolver
 */
#[CoversClass(SubdomainResolver::class)]
#[CoversClass(HostResolver::class)]
#[CoversClass(HeaderResolver::class)]
#[CoversClass(PathSegmentResolver::class)]
#[CoversClass(ChainResolver::class)]
#[CoversClass(RequestHost::class)]
final class TenantResolverTest extends TestCase
{
    /**
     * @return array<string, array{string, string|null}>
     */
    public static function subdomainHosts(): array
    {
        return [
            'tenant subdomain' => ['acme.example.com', 'acme'],
            'case-insensitive with port' => ['ACME.Example.com:8080', 'acme'],
            'trailing dot' => ['acme.example.com.', 'acme'],
            'base domain itself' => ['example.com', null],
            'nested subdomain' => ['a.b.example.com', null],
            'other domain' => ['acme.example.org', null],
            'suffix lookalike' => ['acmeexample.com', null],
        ];
    }

    #[DataProvider('subdomainHosts')]
    public function test_subdomain_resolver(string $host, ?string $expected): void
    {
        self::assertSame($expected, (new SubdomainResolver('example.com'))->resolve(self::request(['host' => $host])));
    }

    public function test_subdomain_resolver_requires_a_base_domain(): void
    {
        $this->expectException(TenancyException::class);

        new SubdomainResolver(' . ');
    }

    public function test_host_falls_back_to_server_and_keeps_ipv6_brackets(): void
    {
        $fromServer = new Request('GET', '/', server: ['HTTP_HOST' => 'Shop.Acme.com']);
        $ipv6 = self::request(['host' => '[::1]:8080']);

        self::assertSame('shop.acme.com', RequestHost::of($fromServer));
        self::assertSame('[::1]', RequestHost::of($ipv6));
        self::assertNull(RequestHost::of(self::request([])));
    }

    public function test_host_resolver_maps_custom_domains(): void
    {
        $resolver = new HostResolver(['Shop.Acme.com' => 'acme']);

        self::assertSame('acme', $resolver->resolve(self::request(['host' => 'shop.acme.com:443'])));
        self::assertNull($resolver->resolve(self::request(['host' => 'shop.globex.com'])));
        self::assertNull($resolver->resolve(self::request([])));
    }

    public function test_header_resolver(): void
    {
        $resolver = new HeaderResolver('X-Tenant');

        self::assertSame('acme', $resolver->resolve(self::request(['x-tenant' => ' acme '])));
        self::assertNull($resolver->resolve(self::request(['x-tenant' => '  '])));
        self::assertNull($resolver->resolve(self::request([])));
    }

    public function test_path_segment_resolver_reads_only_the_path(): void
    {
        self::assertSame('acme', (new PathSegmentResolver())->resolve(new Request('GET', '/acme/orders?tenant=globex')));
        self::assertSame('orders', (new PathSegmentResolver(1))->resolve(new Request('GET', '//acme//orders')));
        self::assertNull((new PathSegmentResolver())->resolve(new Request('GET', '/?x=1')));
        self::assertSame('acme', (new PathSegmentResolver())->resolve(new Request('GET', 'https://example.com/acme/orders')));
        self::assertNull((new PathSegmentResolver())->resolve(new Request('GET', 'http://:80')));
    }

    public function test_chain_resolver_returns_the_first_candidate(): void
    {
        $chain = new ChainResolver([new HostResolver(['shop.acme.com' => 'acme']), new HeaderResolver()]);

        self::assertSame('acme', $chain->resolve(self::request(['host' => 'shop.acme.com', 'x-tenant' => 'globex'])));
        self::assertSame('globex', $chain->resolve(self::request(['x-tenant' => 'globex'])));
        self::assertNull($chain->resolve(self::request([])));
    }

    /**
     * @param array<string, string> $headers
     *
     * @return Request
     */
    private static function request(array $headers): Request
    {
        return new Request('GET', '/', headers: $headers);
    }
}
