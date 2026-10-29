<?php

declare(strict_types=1);

namespace EzPhp\Tenancy\Cache;

use Closure;
use EzPhp\Cache\CacheInterface;
use EzPhp\Cache\CacheStats;
use EzPhp\Cache\LockInterface;
use EzPhp\Cache\PrefixedCache;
use EzPhp\Cache\TaggedCache;
use EzPhp\Tenancy\TenantContext;

/**
 * Class TenantScopedCache
 *
 * CacheInterface that routes every call through a PrefixedCache for the
 * tenant active at call time (`tenant:<id>:` by default), so one shared store
 * holds every tenant's entries apart. Keys, locks and tag lists are prefixed
 * (tags() is built on the tenant's PrefixedCache).
 *
 * With no tenant active every call throws TenancyException — it never falls
 * back to the unprefixed store, where one tenant could read another's entries.
 *
 * flush() and stats() are not key-based and act on the whole shared store,
 * every tenant included — exactly as PrefixedCache documents. Invalidate one
 * tenant's entries through a tag instead.
 *
 * @package EzPhp\Tenancy\Cache
 */
final class TenantScopedCache implements CacheInterface
{
    /**
     * One PrefixedCache per tenant id seen by this instance.
     *
     * @var array<string, PrefixedCache>
     */
    private array $scoped = [];

    /**
     * @param CacheInterface $store   The shared store.
     * @param TenantContext  $context
     * @param string         $prefix  Prepended to the tenant id; the result ends in `:`.
     */
    public function __construct(
        private readonly CacheInterface $store,
        private readonly TenantContext $context,
        private readonly string $prefix = 'tenant:',
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function lock(string $key, int $ttl = 0): LockInterface
    {
        return $this->scoped()->lock($key, $ttl);
    }

    /**
     * {@inheritDoc}
     */
    public function tags(string|array $tags): TaggedCache
    {
        return $this->scoped()->tags($tags);
    }

    /**
     * {@inheritDoc}
     */
    public function stats(): CacheStats
    {
        return $this->store->stats();
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->scoped()->get($key, $default);
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $this->scoped()->set($key, $value, $ttl);
    }

    /**
     * {@inheritDoc}
     */
    public function forget(string $key): void
    {
        $this->scoped()->forget($key);
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        return $this->scoped()->has($key);
    }

    /**
     * {@inheritDoc}
     */
    public function remember(string $key, int $ttl, Closure $callback): mixed
    {
        return $this->scoped()->remember($key, $ttl, $callback);
    }

    /**
     * Empties the whole shared store — every tenant, not only the active one.
     *
     * @return void
     */
    public function flush(): void
    {
        $this->store->flush();
    }

    /**
     * {@inheritDoc}
     */
    public function increment(string $key, int $amount = 1): int
    {
        return $this->scoped()->increment($key, $amount);
    }

    /**
     * {@inheritDoc}
     */
    public function decrement(string $key, int $amount = 1): int
    {
        return $this->scoped()->decrement($key, $amount);
    }

    /**
     * The PrefixedCache of the active tenant.
     *
     * @return PrefixedCache
     */
    private function scoped(): PrefixedCache
    {
        $id = $this->context->current()->id;

        return $this->scoped[$id] ??= new PrefixedCache($this->store, $this->prefix . $id . ':');
    }
}
