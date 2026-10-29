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
