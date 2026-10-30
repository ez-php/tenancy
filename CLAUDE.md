# Coding Guidelines

Applies to the entire ez-php project — framework core, all modules, and the application template.

---

## Environment

- PHP **8.5**, Composer for dependency management
- All project based commands run **inside Docker** — never directly on the host

```
docker compose exec app <command>
```

Container name: `ez-php-app`, service name: `app`.

---

## Quality Suite

Run after every change:

```
docker compose exec app composer full
```

Executes in order:
1. `sync_guidelines.php --check` — fails if any `CLAUDE.md` has drifted from this file
2. `check_test_classes.php` — fails on a duplicate test class name (all packages share the `Tests\` namespace, so a collision is a fatal error in the aggregated run, not a test failure)
3. `check_module_deps.php` — fails when a package's code imports an ez-php package its `composer.json` does not declare (module `src`: `require`/`suggest`; tests: `require`/`require-dev` and their dependencies), or requires one it never uses
4. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
5. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
   *(Note: `@PHP85Migration` does not exist yet in php-cs-fixer; `@PHP83Migration` is the highest available and is used intentionally even though the project targets PHP 8.5)*
6. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse             # PHPStan only
composer cs                  # CS Fixer only
composer test                # PHPUnit only
composer guidelines:check    # CLAUDE.md drift only
composer test-classes:check  # duplicate test class names only
composer module-deps:check   # undeclared / unused ez-php package dependencies only
```

**PHPStan:** never suppress with `@phpstan-ignore-line` — always fix the root cause.

---

## Coding Standards

- `declare(strict_types=1)` at the top of every PHP file
- Typed properties, parameters, and return values — avoid `mixed`
- PHPDoc on every class and public method
- One responsibility per class — keep classes small and focused
- Constructor injection — no service locator pattern
- No global state unless intentional and documented
- Concrete classes are `final` — extend behavior through composition, not inheritance. Exception-hierarchy base classes (e.g. `EzPhpException`, `HttpException`, `CacheException`) are one carve-out, since they exist specifically to be extended. A documented template-method-style base class (e.g. `Mailable`, meant to be configured via constructor-time subclassing) is the other — the owning module's `CLAUDE.md` must record it under Design Decisions.

**Naming:**

| Thing | Convention |
|---|---|
| Classes / Interfaces | `PascalCase` |
| Methods / variables | `camelCase` |
| Constants | `UPPER_CASE` |
| Files | Match class name exactly |

**Principles:** SOLID · KISS · DRY · YAGNI

---

## Workflow & Behavior

- Write tests **before or alongside** production code (test-first)
- Read and understand the relevant code before making any changes
- Modify the minimal number of files necessary
- Keep implementations small — if it feels big, it likely belongs in a separate module
- No hidden magic — everything must be explicit and traceable
- No large abstractions without clear necessity
- No heavy dependencies — check if PHP stdlib suffices first
- Respect module boundaries — don't reach across packages
- Keep the framework core small — what belongs in a module stays there
- Document architectural reasoning for non-obvious design decisions
- Do not change public APIs unless necessary
- Prefer composition over inheritance — no premature abstractions

---

## New Modules & CLAUDE.md Files

### 1 — Required files

Every module under `modules/<name>/` must have:

| File | Purpose |
|---|---|
| `composer.json` | package definition, deps, autoload |
| `phpstan.neon` | static analysis config, level 9 |
| `phpunit.xml` | test suite config |
| `.php-cs-fixer.php` | code style config |
| `.gitignore` | ignore `vendor/`, `.env`, cache |
| `.env.example` | environment variable defaults (copy to `.env` on first run) |
| `docker-compose.yml` | Docker Compose service definition (always `container_name: ez-php-<name>-app`) |
| `docker/app/Dockerfile` | module Docker image (`FROM au9500/php:8.5`) |
| `docker/app/container-start.sh` | container entrypoint: `composer install` → `sleep infinity` |
| `docker/app/php.ini` | PHP ini overrides (`memory_limit`, `display_errors`, `xdebug.mode`) |
| `.github/workflows/ci.yml` | standalone CI pipeline |
| `README.md` | public documentation |
| `tests/TestCase.php` | base test case for the module |
| `start.sh` | convenience script: copy `.env`, bring up Docker, wait for services, exec shell |
| `CLAUDE.md` | see section 2 below |

### 2 — CLAUDE.md structure

Every module `CLAUDE.md` must follow this exact structure:

1. **Full content of `CODING_GUIDELINES.md`, verbatim** — copy it as-is, do not summarize or shorten
2. A `---` separator
3. `# Package: ez-php/<name>` (or `# Directory: <name>` for non-package directories)
4. Module-specific section covering:
   - Source structure — file tree with one-line description per file
   - Key classes and their responsibilities
   - Design decisions and constraints
   - Testing approach and infrastructure requirements (MySQL, Redis, etc.)
   - What does **not** belong in this module

**Do not edit part 1 by hand.** It is generated from `CODING_GUIDELINES.md` by
`sync_guidelines.php` at the project root:

```
php sync_guidelines.php            # rewrite every out-of-sync CLAUDE.md
php sync_guidelines.php --check    # report drift, exit 1 if any (CI / pre-commit)
```

Edit `CODING_GUIDELINES.md`, then run the script — it replaces everything before the
`# Package:` / `# Directory:` / `# Project:` heading and preserves the hand-written
section below it byte-for-byte. Editing a single copy only creates drift; before this
script existed, all 40 copies had diverged.

### 3 — Scaffolding a new module

`make_module.php` at the project root writes the required-file set and the monorepo
wiring in one step, wrapping `docker-init` for the Docker subset:

```
composer module:make <name> -- --description="..."
php make_module.php <name> --description="..." --services=mysql,redis --extensions=gmp
```

`<name>` is the kebab-case package name; the namespace is derived as
`EzPhp\<PascalCase>` (each `-`-separated word upper-cased) unless `--namespace=`
overrides it. Existing exceptions the guess gets wrong: `asyncapi` → `AsyncApi`, `asyncapi-ui` → `AsyncApiUi`,
`bignum` → `BigNum`, `dataloader` → `DataLoader`, `dotenv` → `Env`, `graphql` → `GraphQL`, `graphql-subscriptions` → `GraphQLSubscriptions`, `oauth` → `OAuth`,
`opcache` → `OPCache`, `openapi` → `OpenApi`, `qrcode` → `QrCode`, `swagger-ui` → `SwaggerUI`, `webauthn` → `WebAuthn` and
`websocket` → `WebSocket`; `websocket-client` → `WebsocketClient`, `websocket-tls` → `WebsocketTls`,
`webauthn-metadata` → `WebauthnMetadata` and `metrics-statsd` → `MetricsStatsd` are
intentional lower-case-word namespaces, and `testing-application` shares `EzPhp\Testing\`
with `testing`).

To bring in a module whose code already lives in its own repository instead of
generating a fresh skeleton, pass `--repo=` with a git URL:

```
php make_module.php <name> --repo=<git-url> [--namespace=Foo]
```

This runs `git submodule add <url> modules/<name>` instead of writing package
files, then applies the same monorepo wiring below. It is mutually exclusive
with `--services`/`--extensions` and `--description` — a submodule brings its own Docker
scaffold (if any) and its own `composer.json` description. A minimal `CLAUDE.md`
stub is written only if the submodule doesn't already ship one, so
`composer guidelines:sync` has a `# Package:` heading to anchor part 1 against.

It writes `modules/<name>/` and registers the module in the four places the monorepo
needs it — root `composer.json` (`autoload.psr-4` **and** the shared
`autoload-dev` `Tests\` directory list), `phpstan.neon`, `phpunit.xml` (test suite
**and** coverage source), and `packages.sh` (alphabetical position) — in both
generated and `--repo` mode.

Two things stay manual on purpose:

- **`CLAUDE.md` part 1** — only the `# Package:` section is generated. Run
  `composer guidelines:sync` afterwards; baking a guidelines copy into the generator
  would recreate the drift the sync script exists to prevent.
- **The host-port table below** (`--services` only) — claim the "next free" row by
  editing the table in `CODING_GUIDELINES.md` (never in a `CLAUDE.md` copy) and run
  `composer guidelines:sync` in the same change. Editing it drifts every `CLAUDE.md`
  until the sync runs, which is why the generator only reminds you instead of doing
  it. Skipping the edit leaves "next free" stale, so the next module collides.

### 4 — Docker scaffold

Run from the new module root (requires `ez-php/docker` in `require-dev` — like every ez-php requirement, constrained to the current release, e.g. `"^2.6.0"`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders (`docker/db/`, the MySQL init script, only with `--services=mysql`). Existing files are never overwritten.

Pass `--services` to merge MySQL/Redis/Meilisearch/Memcached/Mailpit service definitions directly into `docker-compose.yml` and uncomment the matching sections in `.env.example`, instead of adapting them by hand afterward:

```
vendor/bin/docker-init --services=mysql
vendor/bin/docker-init --services=redis
vendor/bin/docker-init --services=meilisearch
vendor/bin/docker-init --services=mysql,redis
vendor/bin/docker-init --services=memcached,mailpit
```

Pass `--extensions` to merge PHP extension install blocks (apt packages plus `docker-php-ext-install`/`pecl` lines) directly into `docker/app/Dockerfile`, instead of hand-editing it afterward — supported extensions: `bcmath`, `gmp`, `gd`, `imagick`, `memcached`, `apcu` (with `apc.enable_cli=1`):

```
vendor/bin/docker-init --extensions=gmp,bcmath
vendor/bin/docker-init --extensions=gd,imagick
vendor/bin/docker-init --extensions=memcached,apcu
```

When run from a module directory inside this monorepo, any requested extension not already present is also merged into the shared root `docker/app/Dockerfile` — the container `composer full` at the root actually runs against, distinct from the module's own standalone image.

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis, Meilisearch) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | Redis host port | `MEILISEARCH_PORT` |
|---|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 (`REDIS_PORT`) | 7700 |
| `ez-php/framework` | 3307 | — | — |
| `ez-php/` (application template) | 3308 | 6383 (`REDIS_PORT`) | 7702 (opt-in service) |
| `ez-php/orm` | 3309 | — | — |
| `ez-php/cache` | — | 6380 (`REDIS_HOST_PORT`) | — |
| `ez-php/queue` | 3310 | 6381 (`REDIS_HOST_PORT`) | — |
| `ez-php/rate-limiter` | — | 6382 (`REDIS_HOST_PORT`) | — |
| `ez-php/search` | — | — | 7701 |
| `ez-php/event-store` | 3311 | — | — |
| `ez-php/broadcast` | — | 6384 (`REDIS_HOST_PORT`) | — |
| `ez-php/feature-flags` | — | 6385 (`REDIS_HOST_PORT`) | — |
| `ez-php/scheduler` | — | 6386 (`REDIS_HOST_PORT`) | — |
| `ez-php/session` | — | 6387 (`REDIS_HOST_PORT`) | — |
| `ez-php/metrics` | — | 6388 (`REDIS_HOST_PORT`) | — |
| `ez-php/event-store-snapshots` | 3312 | — | — |
| **next free** | **3313** | **6389** | **7703** |

Only set a port for services the module actually uses. Modules without external services need no port config.

> The `MEILISEARCH_PORT` column is the **host** port. Inside a Compose network the service is always reachable at `http://meilisearch:7700` regardless of the host mapping — only publish-side ports need to be unique.

> The "Redis host port" column is likewise the **host**-published port. Every module row maps it through a separate `REDIS_HOST_PORT` env var in `docker-compose.yml`, keeping `REDIS_PORT` fixed at `6379` for in-container connections (the app container always reaches Redis at `redis:6379` over the Compose network, regardless of the host mapping) — the root project and the `ez-php/` application template are the two exceptions, since both have no host/container split and use `REDIS_PORT` for both (the template's other in-container Redis settings — `CACHE_REDIS_PORT`, `QUEUE_REDIS_PORT`, `RATE_LIMITER_REDIS_PORT`, `HEALTH_REDIS_PORT` — stay fixed at `6379` regardless, same as every other module).

> This table tracks only MySQL, Redis, and Meilisearch ports — the three services shared across multiple modules where a collision is otherwise easy to introduce. Mailpit is the one other service with published host ports: SMTP `1025` and web UI `8025`. `ez-php/mail` maps them through `MAILPIT_SMTP_HOST_PORT`/`MAILPIT_API_HOST_PORT` in `modules/mail/docker-compose.yml` (mirroring the `*_HOST_PORT` pattern above, documented in `modules/mail/.env.example`); the root project and the `ez-php/` template each run their own Mailpit on the same defaults (`MAIL_PORT`/`MAIL_WEB_PORT`), so **these three stacks cannot run at the same time** without overriding those variables. It isn't a table column because no module beyond those three runs Mailpit — but a new module adding its own single-use service's ports should likewise parameterize them and document the defaults in its own `.env.example` rather than adding a column here. Services reached only over the Compose network publish no host port and need no entry at all: Memcached (`memcached:11211` in the root stack and `ez-php/cache`) and the opt-in Elasticsearch/Typesense backends in `modules/search/docker-compose.ci.yml`.

### 5 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Every multi-package script sources it — `update_all.sh`, `fullcheck.sh`, `bump_version.sh` and the `git_*_all.sh` scripts (`git_push_all.sh`, `git_pull_all.sh`, `git_tag_all.sh`, `git_delete_all_tags.sh`) — so the package list lives in exactly one place.

When adding a new module, add `"$ROOT/modules/<name>"` to the `PACKAGES` array in `packages.sh` in **alphabetical order** among the other `modules/*` entries (before `framework`, `ez-php`, and the root entry at the end).

---

# Package: ez-php/tenancy

Single-database multi-tenancy primitives: resolve the tenant of each request, keep it as
the active tenant, and scope shared stores to it — a tenant-scoped cache (per-tenant key
prefixes) and a tenant-scoped search driver (per-tenant index names).

---

## Source Structure

```
src/
├── Tenant.php                         — immutable tenant value (DNS-label id + attributes); isValidId()
├── TenantContext.php                  — holder of the active tenant; current()/check()/set()/run()
├── TenantStoreInterface.php           — find(id): ?Tenant
├── ArrayTenantStore.php               — store over the `tenancy.tenants` map, ids validated up front
├── TenancyException.php               — invalid id, misconfigured resolver, no active tenant
├── TenancyServiceProvider.php         — binds context, store, resolver, middleware and both adapters from config
├── Resolver/
│   ├── TenantResolverInterface.php    — resolve(RequestInterface): ?string candidate id
│   ├── SubdomainResolver.php          — acme.example.com → acme (one label before the base domain)
│   ├── HostResolver.php               — full host → id map (custom domains)
│   ├── HeaderResolver.php             — X-Tenant (configurable) header
│   ├── PathSegmentResolver.php        — n-th path segment, query string ignored
│   ├── ChainResolver.php              — first candidate of several resolvers
│   └── RequestHost.php                — Host header / HTTP_HOST, lower-cased, port stripped
├── Middleware/
│   └── ResolveTenantMiddleware.php    — resolve → validate → store lookup → activate; 404 or optional; restores previous tenant
├── Cache/
│   └── TenantScopedCache.php          — CacheInterface via a PrefixedCache per active tenant (ez-php/cache)
├── Queue/
│   ├── TenantAwareQueue.php           — QueueInterface decorator: push() stores the active tenant id in an envelope, pop() unwraps and activates it (clears it otherwise), failed() keeps it
│   └── TenantQueueEnvelope.php        — JobInterface envelope {job, tenantId}; getters delegate to the job; handle()/fail() delegate when popped without the decorator
└── Search/
    └── TenantScopedSearchDriver.php   — SearchDriverInterface decorator, `<id>_<index>` (ez-php/search)

tests/
├── TestCase.php                                  — Base PHPUnit test case
├── TenantTest.php                                — id rules (incl. the collision cases), attributes
├── TenantContextTest.php                         — set/clear, run() restore incl. on exception
├── ArrayTenantStoreTest.php                      — lookup, invalid ids/attributes rejected
├── TenancyServiceProviderTest.php                — shared context, resolver per config, optional mode, adapters
├── Tenancy/ApplicationTestCase.php               — bootstrapped app with a temp config/tenancy.php
├── Resolver/TenantResolverTest.php               — every resolver and RequestHost
├── Middleware/ResolveTenantMiddlewareTest.php    — activation, 404 cases, optional mode, restore on exception, streamed body after restore
├── Queue/TenantAwareQueueTest.php                — tenant carried push → pop through serialization, no-tenant jobs untouched, empty/unknown tenant clears, failed() wraps once
├── Cache/TenantScopedCacheTest.php               — isolation, all key-based ops, tags, flush/stats, no-tenant failure
└── Search/TenantScopedSearchDriverTest.php       — index naming for every operation, no-tenant failure
```

---

## Key Classes and Responsibilities

| Class | Responsibility |
|---|---|
| `Tenant` | Immutable id + attributes. The id format is the module's safety boundary (see below). |
| `TenantContext` | The active tenant for the current unit of work. `run(Tenant, callable)` for CLI commands and queue jobs. |
| `TenantStoreInterface` / `ArrayTenantStore` | Which tenants exist. Only known tenants are ever activated. |
| `TenantResolverInterface` + resolvers | Read a candidate id from the request; no validation, no lookup. |
| `ResolveTenantMiddleware` | Ties resolver, store and context together per request. |
| `TenantScopedCache` | Per-tenant view of one shared cache store. |
| `TenantScopedSearchDriver` | Per-tenant indexes on one search backend. |

---

## Design Decisions and Constraints

- **Tenant ids are DNS labels** (`[a-z0-9]`, inner `-`, 1–63 chars). The id is spliced into cache prefixes (`tenant:<id>:`) and index names (`<id>_<index>`); with `:` and `_` excluded, no two tenants can produce the same prefix or index (tenant `a` + key `b:x` vs tenant `a:b` + key `x`), and every search backend (Elasticsearch requires lowercase) accepts the name. It also matches what a subdomain can be.
- **Only known tenants are activated.** Resolvers produce candidates; the middleware validates the id and looks it up in `TenantStoreInterface`. Otherwise any Host header or path segment would become a tenant — and a fresh cache prefix and index.
- **Resolution is not authorization.** With the header or path resolver, a signed-in user of tenant A can name tenant B, which is a known tenant. Checking that the user belongs to the active tenant is the application's job (or `ez-php/authorization`), after this middleware. `subdomain` is the default resolver.
- **No fallback to the shared store.** Without an active tenant, `TenantScopedCache` and `TenantScopedSearchDriver` throw `TenancyException` instead of using unprefixed keys/indexes, where tenants would see each other's data. CLI and queue work wraps itself in `TenantContext::run()`.
- **Queue jobs carry their tenant in an envelope, unwrapped on `pop()`.** `TenantAwareQueue` stores `{job, tenantId}` and, on `pop()`, activates the tenant and returns the *original* job — not a wrapper whose `handle()` runs it — so `ez-php/queue`'s Worker keeps applying the job's middleware, backoff, release and chain (all of which require a `Job` instance). The tenant then stays active until the next `pop()` replaces or clears it; it is not restored in `finally` because the Worker, not the decorator, runs the job. Retries re-pushed by the Worker go through `push()` while that tenant is active, and `failed()` archives an envelope, so both keep the tenant. Only `ez-php/contracts`' `QueueInterface`/`JobInterface` are used, so there is no dependency on `ez-php/queue`.
- **The previous tenant is always restored** — by `run()` and by the middleware, in `finally`. Long-running workers and back-to-back test requests never inherit a tenant.
- **A `StreamedResponse` body runs after the tenant is restored.** The body is written by `Application::send()`, after the middleware returned, and `StreamedResponse::writeBody()` is push-style over a private generator — the middleware cannot re-activate the tenant around it without buffering the stream or a new `ez-php/http` hook. Streaming actions capture `current()` and wrap their work in `TenantContext::run()` (README § Streamed responses); `ResolveTenantMiddlewareTest` pins the behaviour.
- **`TenantContext` is one mutable holder per process.** The provider registers it with `instance()`, so the middleware and adapters share it; resolve it on the root container, not a `scope()` child. It is not fiber-aware: concurrent fibers (an `ez-php/websocket` server) would share one active tenant. `Tenant` itself is the immutable part.
- **`flush()`/`stats()` on `TenantScopedCache` act on the whole shared store** — every tenant — exactly like `PrefixedCache` (from ez-php/cache). Invalidate one tenant's entries with a tag; `tags()` is built on the tenant's `PrefixedCache`, so tag lists are prefixed.
- **Adapters are opt-in.** The provider binds `TenantScopedCache` / `TenantScopedSearchDriver` under their own names and never rebinds `CacheInterface`/`SearchDriverInterface` (a provider must not replace another module's binding). `ez-php/cache` and `ez-php/search` are `suggest`s.
- **No `X-Forwarded-Host`.** `RequestHost` reads only `Host` / `HTTP_HOST`; a forwarded host is client-controlled unless a trusted proxy sets it, and there is no trusted-proxy configuration here.
- **The path resolver does not rewrite the URI.** Routes keep the segment (`/{tenant}/orders`).
- **404 is a plain `Response`**, not an exception, so the module needs no framework dependency.

---

## Testing Approach

- Unit tests for every class with real `Request`/`Response` objects, `ez-php/cache`'s `ArrayDriver` and a recording `SearchDriverInterface` double.
- `TenancyServiceProviderTest` bootstraps an application via `ez-php/testing-application` with a temporary `config/tenancy.php` reading `TENANCY_*` env vars.
- No external services.
- Test classes live in the shared `Tests\` namespace but must be uniquely named
  across the whole monorepo — the root `phpunit.xml` loads every package in one
  process, so a duplicate name is a fatal error, not a test failure. Prefix with
  `Tenancy` when the obvious name is already taken.

---

## What Does NOT Belong Here

| Concern | Where it belongs |
|---|---|
| Checking that a user belongs to the active tenant | Application layer / `ez-php/authorization` |
| Database-per-tenant connection switching | Not supported (single-database design); a future module |
| Tenant provisioning, per-tenant migrations | Application layer |
| Per-tenant queues / queue isolation | Application (queue names per tenant); `TenantAwareQueue` only carries the tenant id |
| ORM global scopes (`WHERE tenant_id = ?`) | Application repositories (a possible future `ez-php/orm` feature) |
| Database-backed tenant store | Application — implement `TenantStoreInterface` |
| Trusted-proxy / `X-Forwarded-Host` handling | Application or reverse-proxy configuration |
