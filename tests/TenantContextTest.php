<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Tenancy\TenancyException;
use EzPhp\Tenancy\Tenant;
use EzPhp\Tenancy\TenantContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;

/**
 * Class TenantContextTest
 *
 * @package Tests
 */
#[CoversClass(TenantContext::class)]
#[UsesClass(Tenant::class)]
final class TenantContextTest extends TestCase
{
    public function test_starts_without_a_tenant(): void
    {
        $context = new TenantContext();

        self::assertFalse($context->check());
        self::assertNull($context->get());

        $this->expectException(TenancyException::class);
        $context->current();
    }

    public function test_set_and_clear(): void
    {
        $context = new TenantContext();
        $tenant = new Tenant('acme');

        $context->set($tenant);
        self::assertTrue($context->check());
        self::assertSame($tenant, $context->current());

        $context->set(null);
        self::assertFalse($context->check());
    }

    public function test_run_activates_the_tenant_and_restores_the_previous_one(): void
    {
        $context = new TenantContext();
        $outer = new Tenant('outer');
        $context->set($outer);

        $seen = $context->run(new Tenant('inner'), fn (): string => $context->current()->id);

        self::assertSame('inner', $seen);
        self::assertSame($outer, $context->current());
    }

    public function test_run_restores_the_previous_tenant_when_the_callback_throws(): void
    {
        $context = new TenantContext();

        try {
            $context->run(new Tenant('acme'), function (): never {
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
        }

        self::assertFalse($context->check());
    }
}
