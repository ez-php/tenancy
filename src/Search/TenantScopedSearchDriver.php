<?php

declare(strict_types=1);

namespace EzPhp\Tenancy\Search;

use EzPhp\Search\SearchDriverInterface;
use EzPhp\Search\SearchOptions;
use EzPhp\Search\SearchResult;
use EzPhp\Tenancy\TenantContext;

/**
 * Class TenantScopedSearchDriver
 *
 * SearchDriverInterface decorator that gives every tenant its own indexes:
 * `articles` becomes `<tenant id>_articles` for the tenant active at call time.
 * Tenant ids contain no `_`, so the first `_` always ends the tenant part and
 * two tenants never share an index name.
 *
 * With no tenant active every call throws TenancyException — run
 * `search:import` and other CLI work inside TenantContext::run().
 *
 * @package EzPhp\Tenancy\Search
 */
final readonly class TenantScopedSearchDriver implements SearchDriverInterface
{
    /**
     * @param SearchDriverInterface $driver
     * @param TenantContext         $context
     */
    public function __construct(
        private SearchDriverInterface $driver,
        private TenantContext $context,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function index(string $indexName, string|int $id, array $document): void
    {
        $this->driver->index($this->scoped($indexName), $id, $document);
    }

    /**
     * {@inheritDoc}
     */
    public function remove(string $indexName, string|int $id): void
    {
        $this->driver->remove($this->scoped($indexName), $id);
    }

    /**
     * {@inheritDoc}
     */
    public function search(string $indexName, string $query, SearchOptions $options): SearchResult
    {
        return $this->driver->search($this->scoped($indexName), $query, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function flush(string $indexName): void
    {
        $this->driver->flush($this->scoped($indexName));
    }

    /**
     * @param string $indexName
     *
     * @return string
     */
    private function scoped(string $indexName): string
    {
        return $this->context->current()->id . '_' . $indexName;
    }
}
