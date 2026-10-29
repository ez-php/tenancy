<?php

declare(strict_types=1);

namespace EzPhp\Tenancy;

/**
 * Class ArrayTenantStore
 *
 * Tenant store over a fixed map `id => attributes` (the `tenancy.tenants`
 * config key). Every id is validated up front, so a typo fails at boot of the
 * store rather than as a silent 404.
 *
 * @package EzPhp\Tenancy
 */
final readonly class ArrayTenantStore implements TenantStoreInterface
{
    /**
     * @var array<string, Tenant>
     */
    private array $tenants;

    /**
     * @param array<array-key, mixed> $tenants Map of tenant id to attribute array.
     *
     * @throws TenancyException When an id is invalid or attributes are not an array.
     */
    public function __construct(array $tenants)
    {
        $built = [];

        foreach ($tenants as $id => $attributes) {
            if (!is_array($attributes)) {
                throw new TenancyException(sprintf('Attributes of tenant "%s" must be an array.', $id));
            }

            /** @var array<string, mixed> $attributes */
            $built[(string) $id] = new Tenant((string) $id, $attributes);
        }

        $this->tenants = $built;
    }

    /**
     * @param string $id
     *
     * @return Tenant|null
     */
    public function find(string $id): ?Tenant
    {
        return $this->tenants[$id] ?? null;
    }
}
