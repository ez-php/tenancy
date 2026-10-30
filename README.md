# ez-php/tenancy

Single-database multi-tenancy primitives for ez-php: resolve the tenant of each request,
keep it as the active tenant, and scope shared stores to it — per-tenant cache key prefixes
and per-tenant search indexes.

---

## Installation

```bash
composer require ez-php/tenancy
```

---

## Setup

```php
// provider/modules.php
EzPhp\Tenancy\TenancyServiceProvider::class,

// bootstrap: resolve the tenant before anything tenant-aware runs
$app->middleware(EzPhp\Tenancy\Middleware\ResolveTenantMiddleware::class);
```

`config/tenancy.php`:

```php
return [
    'resolver' => 'subdomain',          // subdomain | host | header | path, or 'host,subdomain'
    'base_domain' => 'example.com',     // acme.example.com → acme
    'hosts' => ['shop.acme.com' => 'acme'],
    'header' => 'X-Tenant',
    'path_segment' => 0,                // /acme/orders → acme
    'required' => true,                 // 404 unless a known tenant resolves
    'tenants' => [
        'acme' => ['name' => 'Acme Inc.'],
        'globex' => ['name' => 'Globex'],
    ],
    'cache_prefix' => 'tenant:',
];
```

Tenant ids are DNS labels: lowercase letters, digits and inner hyphens, at most 63
characters. Only tenants the store knows are activated. For a database-backed list, bind
your own `TenantStoreInterface`.

---

## Usage

```php
use EzPhp\Tenancy\TenantContext;

public function __construct(private TenantContext $tenants) {}

$tenant = $this->tenants->current();     // throws when none is active
$tenant->id;                             // 'acme'
$tenant->get('name');                    // 'Acme Inc.'
```

### CLI commands and queue jobs

There is no request, so activate the tenant explicitly. The previous tenant is restored
afterwards, even on exceptions:

```php
$context->run($store->find('acme'), fn () => $importer->import());
```

For queue jobs you can let the queue carry the tenant instead: wrap the bound queue in
`TenantAwareQueue`. A job pushed while a tenant is active is stored with that tenant's id, and
the worker's `pop()` makes the same tenant active again before the job runs (also for retries
and `queue:failed retry`):

```php
use EzPhp\Contracts\QueueInterface;
use EzPhp\Tenancy\Queue\TenantAwareQueue;

// in your provider's register(), after QueueServiceProvider
$this->app->bind(QueueInterface::class, fn ($app) => new TenantAwareQueue(
    $app->make(\EzPhp\Queue\Driver\DatabaseDriver::class), // the real driver
    $app->make(TenantContext::class),
    $app->make(TenantStoreInterface::class),
));
```

Like `ez-php/queue`'s `UniqueQueue`, the decorator hides the driver's failed-job repository, so
bind `FailedJobRepositoryInterface` to the driver yourself if you use `queue:failed`.

### Streamed responses

`ResolveTenantMiddleware` deactivates the tenant when the action returns, but a
`StreamedResponse` (`download()`, `sse()`) writes its body later, while the response is
sent. Capture the tenant in the action and re-activate it inside the stream:

```php
$tenant = $this->tenants->current();

return StreamedResponse::sse(function () use ($tenant): \Generator {
    yield from $this->tenants->run($tenant, fn (): array => $this->events->forCurrentTenant());
});
```

Without that, tenant-scoped services used while streaming throw "No tenant is active".

### Tenant-scoped cache (`ez-php/cache`)

```php
use EzPhp\Tenancy\Cache\TenantScopedCache;

public function __construct(private TenantScopedCache $cache) {}

$this->cache->set('plan', 'pro');        // stored as tenant:acme:plan
```

To make every `CacheInterface` consumer tenant-scoped, bind the interface to the adapter
in your own provider. `flush()` empties the whole shared store, every tenant included. To
clear one tenant's entries, tag them and flush the tag.

### Tenant-scoped search (`ez-php/search`)

`TenantScopedSearchDriver` turns index `articles` into `acme_articles`. Bind
`SearchDriverInterface` to it, or construct `SearchIndex` with it.

Without an active tenant, both adapters throw `TenancyException`. They never fall back to
the shared, unprefixed store.

---

## Security note

Resolving a tenant is not authorizing the user for it. With the header or path resolver,
any signed-in user can name another tenant. Check membership after
`ResolveTenantMiddleware`, in your application or with `ez-php/authorization`.

---

## License

MIT
