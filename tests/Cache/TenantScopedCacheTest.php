<?php

declare(strict_types=1);

namespace Tests\Cache;

use EzPhp\Cache\ArrayDriver;
use EzPhp\Cache\CacheStats;
use EzPhp\Tenancy\Cache\TenantScopedCache;
use EzPhp\Tenancy\TenancyException;
use EzPhp\Tenancy\Tenant;
use EzPhp\Tenancy\TenantContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class TenantScopedCacheTest
 *
 * @package Tests\Cache
 */
#[CoversClass(TenantScopedCache::class)]
#[UsesClass(Tenant::class)]
#[UsesClass(TenantContext::class)]
final class TenantScopedCacheTest extends TestCase
{
    private ArrayDriver $store;

    private TenantContext $context;

    private TenantScopedCache $cache;

    protected function setUp(): void
    {
        $this->store = new ArrayDriver();
        $this->context = new TenantContext();
        $this->cache = new TenantScopedCache($this->store, $this->context);
    }

    public function test_tenants_share_the_store_but_not_their_entries(): void
    {
        $this->context->run(new Tenant('acme'), fn () => $this->cache->set('plan', 'pro'));
        $this->context->run(new Tenant('globex'), fn () => $this->cache->set('plan', 'free'));

        self::assertSame('pro', $this->store->get('tenant:acme:plan'));
        self::assertSame('free', $this->store->get('tenant:globex:plan'));
        self::assertSame('pro', $this->context->run(new Tenant('acme'), fn (): mixed => $this->cache->get('plan')));
        self::assertFalse($this->context->run(new Tenant('acme'), fn (): bool => $this->cache->has('missing')));
    }

    public function test_every_key_based_operation_is_scoped(): void
    {
        $this->context->set(new Tenant('acme'));

        self::assertSame('v', $this->cache->remember('r', 60, fn (): string => 'v'));
        self::assertSame(2, $this->cache->increment('n', 2));
        self::assertSame(1, $this->cache->decrement('n'));
        $this->cache->forget('r');
        $this->cache->tags('users')->set('u1', 'x');

        self::assertSame(1, $this->store->get('tenant:acme:n'));
        self::assertFalse($this->store->has('tenant:acme:r'));
        self::assertTrue($this->store->has('tenant:acme:u1'));
        self::assertTrue($this->cache->lock('job', 10)->acquire());
        self::assertFalse($this->store->lock('tenant:acme:job', 10)->acquire());
    }

    public function test_custom_prefix(): void
    {
        $cache = new TenantScopedCache($this->store, $this->context, 't-');
        $this->context->run(new Tenant('acme'), fn () => $cache->set('k', 1));

        self::assertSame(1, $this->store->get('t-acme:k'));
    }

    public function test_flush_and_stats_act_on_the_whole_shared_store(): void
    {
        $this->store->set('tenant:globex:plan', 'free');
        $this->context->set(new Tenant('acme'));

        $this->cache->flush();

        self::assertFalse($this->store->has('tenant:globex:plan'));
        self::assertInstanceOf(CacheStats::class, $this->cache->stats());
    }

    public function test_throws_instead_of_falling_back_to_the_shared_store_without_a_tenant(): void
    {
        $this->expectException(TenancyException::class);

        $this->cache->get('plan');
    }
}
