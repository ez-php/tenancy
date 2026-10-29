<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Tenancy\ArrayTenantStore;
use EzPhp\Tenancy\TenancyException;
use EzPhp\Tenancy\Tenant;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Class ArrayTenantStoreTest
 *
 * @package Tests
 */
#[CoversClass(ArrayTenantStore::class)]
#[UsesClass(Tenant::class)]
final class ArrayTenantStoreTest extends TestCase
{
    public function test_finds_configured_tenants(): void
    {
        $store = new ArrayTenantStore(['acme' => ['name' => 'Acme'], '42' => []]);

        self::assertSame('Acme', $store->find('acme')?->get('name'));
        self::assertSame('42', $store->find('42')?->id);
        self::assertNull($store->find('globex'));
    }

    public function test_rejects_invalid_ids_up_front(): void
    {
        $this->expectException(TenancyException::class);

        new ArrayTenantStore(['Not_Valid' => []]);
    }

    public function test_rejects_non_array_attributes(): void
    {
        $this->expectException(TenancyException::class);

        new ArrayTenantStore(['acme' => 'Acme']);
    }
}
