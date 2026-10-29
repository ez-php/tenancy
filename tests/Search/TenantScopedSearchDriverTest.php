<?php

declare(strict_types=1);

namespace Tests\Search;

use EzPhp\Search\SearchDriverInterface;
use EzPhp\Search\SearchOptions;
use EzPhp\Search\SearchResult;
use EzPhp\Tenancy\Search\TenantScopedSearchDriver;
use EzPhp\Tenancy\TenancyException;
use EzPhp\Tenancy\Tenant;
use EzPhp\Tenancy\TenantContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Records the index names it is called with.
 */
final class TenantScopedSearchRecordingDriver implements SearchDriverInterface
{
    /**
     * @var list<string>
     */
    public array $calls = [];

    public function index(string $indexName, string|int $id, array $document): void
    {
        $this->calls[] = "index:{$indexName}:{$id}";
    }

    public function remove(string $indexName, string|int $id): void
    {
        $this->calls[] = "remove:{$indexName}:{$id}";
    }

    public function search(string $indexName, string $query, SearchOptions $options): SearchResult
    {
        $this->calls[] = "search:{$indexName}:{$query}";

        return new SearchResult([], 0, 0);
    }

    public function flush(string $indexName): void
    {
        $this->calls[] = "flush:{$indexName}";
    }
}

/**
 * Class TenantScopedSearchDriverTest
 *
 * @package Tests\Search
 */
#[CoversClass(TenantScopedSearchDriver::class)]
#[UsesClass(Tenant::class)]
#[UsesClass(TenantContext::class)]
final class TenantScopedSearchDriverTest extends TestCase
{
    public function test_every_operation_uses_the_active_tenants_index(): void
    {
        $inner = new TenantScopedSearchRecordingDriver();
        $context = new TenantContext();
        $driver = new TenantScopedSearchDriver($inner, $context);

        $context->run(new Tenant('acme'), function () use ($driver): void {
            $driver->index('articles', 1, ['title' => 'Hi']);
            $driver->remove('articles', 1);
            $driver->search('articles', 'hi', new SearchOptions());
            $driver->flush('articles');
        });
        $context->run(new Tenant('globex'), fn () => $driver->flush('articles'));

        self::assertSame([
            'index:acme_articles:1',
            'remove:acme_articles:1',
            'search:acme_articles:hi',
            'flush:acme_articles',
            'flush:globex_articles',
        ], $inner->calls);
    }

    public function test_throws_without_a_tenant(): void
    {
        $driver = new TenantScopedSearchDriver(new TenantScopedSearchRecordingDriver(), new TenantContext());

        $this->expectException(TenancyException::class);

        $driver->search('articles', 'hi', new SearchOptions());
    }
}
