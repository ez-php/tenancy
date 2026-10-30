<?php

declare(strict_types=1);

namespace Tests\Middleware;

use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Http\ResponseInterface;
use EzPhp\Http\StreamedResponse;
use EzPhp\Tenancy\ArrayTenantStore;
use EzPhp\Tenancy\Middleware\ResolveTenantMiddleware;
use EzPhp\Tenancy\Resolver\HeaderResolver;
use EzPhp\Tenancy\Tenant;
use EzPhp\Tenancy\TenantContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;
use Tests\TestCase;

/**
 * Class ResolveTenantMiddlewareTest
 *
 * @package Tests\Middleware
 */
#[CoversClass(ResolveTenantMiddleware::class)]
#[UsesClass(ArrayTenantStore::class)]
#[UsesClass(HeaderResolver::class)]
#[UsesClass(Tenant::class)]
#[UsesClass(TenantContext::class)]
final class ResolveTenantMiddlewareTest extends TestCase
{
    private TenantContext $context;

    protected function setUp(): void
    {
        $this->context = new TenantContext();
    }

    public function test_activates_a_known_tenant_for_the_handler_and_clears_it_afterwards(): void
    {
        $seen = null;

        $response = $this->middleware()->handle(self::request('acme'), function () use (&$seen): ResponseInterface {
            $seen = $this->context->current()->get('name');

            return new Response('ok');
        });

        self::assertSame('Acme', $seen);
        self::assertSame(200, $response->status());
        self::assertFalse($this->context->check());
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function unresolvable(): array
    {
        return [
            'no header' => [null],
            'unknown tenant' => ['globex'],
            'malformed id' => ['Acme:Evil'],
        ];
    }

    #[DataProvider('unresolvable')]
    public function test_answers_404_when_required_and_no_known_tenant_resolves(?string $header): void
    {
        $called = false;

        $response = $this->middleware()->handle(self::request($header), function () use (&$called): ResponseInterface {
            $called = true;

            return new Response();
        });

        self::assertSame(404, $response->status());
        self::assertFalse($called);
    }

    public function test_optional_mode_continues_without_a_tenant(): void
    {
        $active = true;

        $response = $this->middleware(required: false)->handle(self::request('globex'), function () use (&$active): ResponseInterface {
            $active = $this->context->check();

            return new Response();
        });

        self::assertSame(200, $response->status());
        self::assertFalse($active);
    }

    public function test_restores_the_previous_tenant_when_the_handler_throws(): void
    {
        $outer = new Tenant('outer');
        $this->context->set($outer);

        try {
            $this->middleware()->handle(self::request('acme'), function (): never {
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
        }

        self::assertSame($outer, $this->context->get());
    }

    /**
     * A streamed body is written after the middleware returned, so the tenant is no
     * longer active there; capturing it and re-activating it with run() is the
     * documented way (README § Streamed responses).
     */
    public function test_a_streamed_body_runs_after_the_tenant_is_restored(): void
    {
        $response = $this->middleware()->handle(self::request('acme'), function (): ResponseInterface {
            $tenant = $this->context->current();

            return new StreamedResponse(function () use ($tenant): \Generator {
                yield $this->context->check() ? 'active' : 'inactive';
                yield $this->context->run($tenant, fn (): string => $this->context->current()->id);
            });
        });

        self::assertInstanceOf(StreamedResponse::class, $response);

        $body = '';
        $response->writeBody(static function (string $chunk) use (&$body): void {
            $body .= $chunk . ';';
        });

        self::assertSame('inactive;acme;', $body);
        self::assertFalse($this->context->check());
    }

    /**
     * @param bool $required
     *
     * @return ResolveTenantMiddleware
     */
    private function middleware(bool $required = true): ResolveTenantMiddleware
    {
        return new ResolveTenantMiddleware(
            new HeaderResolver(),
            new ArrayTenantStore(['acme' => ['name' => 'Acme']]),
            $this->context,
            $required,
        );
    }

    /**
     * @param string|null $tenant
     *
     * @return Request
     */
    private static function request(?string $tenant): Request
    {
        return new Request('GET', '/', headers: $tenant !== null ? ['x-tenant' => $tenant] : []);
    }
}
