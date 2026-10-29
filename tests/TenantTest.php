<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Tenancy\TenancyException;
use EzPhp\Tenancy\Tenant;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Class TenantTest
 *
 * @package Tests
 */
#[CoversClass(Tenant::class)]
#[CoversClass(TenancyException::class)]
final class TenantTest extends TestCase
{
    public function test_exposes_id_and_attributes(): void
    {
        $tenant = new Tenant('acme', ['name' => 'Acme Inc.']);

        self::assertSame('acme', $tenant->id);
        self::assertSame('Acme Inc.', $tenant->get('name'));
        self::assertSame('fallback', $tenant->get('plan', 'fallback'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validIds(): array
    {
        return [
            'single char' => ['a'],
            'digits' => ['42'],
            'inner hyphen' => ['acme-eu'],
            '63 chars' => [str_repeat('a', 63)],
        ];
    }

    #[DataProvider('validIds')]
    public function test_accepts_dns_label_ids(string $id): void
    {
        self::assertTrue(Tenant::isValidId($id));
    }

    /**
     * Ids end up in `tenant:<id>:` cache prefixes and `<id>_<index>` index names;
     * `:` and `_` would let two tenants collide (tenant "a" + key "b:x" vs tenant "a:b" + key "x").
     *
     * @return array<string, array{string}>
     */
    public static function invalidIds(): array
    {
        return [
            'empty' => [''],
            'colon' => ['a:b'],
            'underscore' => ['a_b'],
            'uppercase' => ['Acme'],
            'dot' => ['acme.eu'],
            'leading hyphen' => ['-acme'],
            'trailing hyphen' => ['acme-'],
            '64 chars' => [str_repeat('a', 64)],
        ];
    }

    #[DataProvider('invalidIds')]
    public function test_rejects_ids_that_could_collide_or_break_backends(string $id): void
    {
        self::assertFalse(Tenant::isValidId($id));

        $this->expectException(TenancyException::class);
        new Tenant($id);
    }
}
