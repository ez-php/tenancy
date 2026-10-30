<?php

declare(strict_types=1);

namespace Tests\Queue;

use EzPhp\Contracts\JobInterface;
use EzPhp\Contracts\QueueInterface;
use EzPhp\Tenancy\ArrayTenantStore;
use EzPhp\Tenancy\Queue\TenantAwareQueue;
use EzPhp\Tenancy\Queue\TenantQueueEnvelope;
use EzPhp\Tenancy\Tenant;
use EzPhp\Tenancy\TenantContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * A queued job recording the tenant it ran for.
 */
final class TenancyRecordingJob implements JobInterface
{
    private int $attempts = 0;

    public function __construct(public readonly string $name, private readonly int $delay = 0)
    {
    }

    public function handle(): void
    {
    }

    public function fail(Throwable $exception): void
    {
    }

    public function getQueue(): string
    {
        return 'default';
    }

    public function getDelay(): int
    {
        return $this->delay;
    }

    public function getMaxTries(): int
    {
        return 3;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function incrementAttempts(): void
    {
        $this->attempts++;
    }
}

/**
 * Stores jobs serialized, like a real driver, so the envelope must survive serialize().
 */
final class TenancySerializingQueue implements QueueInterface
{
    /** @var list<string> */
    public array $jobs = [];

    /** @var list<JobInterface> */
    public array $failed = [];

    public function push(JobInterface $job): void
    {
        $this->jobs[] = serialize($job);
    }

    public function pop(string $queue = 'default'): ?JobInterface
    {
        $payload = array_shift($this->jobs);
        $job = $payload === null ? null : unserialize($payload);

        return $job instanceof JobInterface ? $job : null;
    }

    public function size(string $queue = 'default'): int
    {
        return count($this->jobs);
    }

    public function failed(JobInterface $job, Throwable $exception): void
    {
        $this->failed[] = $job;
    }
}

#[CoversClass(TenantAwareQueue::class)]
#[CoversClass(TenantQueueEnvelope::class)]
#[UsesClass(ArrayTenantStore::class)]
#[UsesClass(Tenant::class)]
#[UsesClass(TenantContext::class)]
final class TenantAwareQueueTest extends TestCase
{
    private TenancySerializingQueue $driver;

    private ArrayTenantStore $store;

    protected function setUp(): void
    {
        $this->driver = new TenancySerializingQueue();
        $this->store = new ArrayTenantStore(['acme' => ['name' => 'Acme'], 'globex' => []]);
    }

    private function queue(TenantContext $context): TenantAwareQueue
    {
        return new TenantAwareQueue($this->driver, $context, $this->store);
    }

    public function test_the_pushing_tenant_is_active_in_the_worker(): void
    {
        $web = new TenantContext();
        $web->run(new Tenant('acme'), fn () => $this->queue($web)->push(new TenancyRecordingJob('invoice', 30)));

        $worker = new TenantContext();
        $job = $this->queue($worker)->pop();

        self::assertInstanceOf(TenancyRecordingJob::class, $job);
        self::assertSame('invoice', $job->name);
        self::assertSame('Acme', $worker->current()->get('name'));
        self::assertSame(0, $this->queue($worker)->size());
    }

    public function test_the_envelope_delegates_queue_placement_to_the_job(): void
    {
        $envelope = new TenantQueueEnvelope(new TenancyRecordingJob('x', 30), 'acme');
        $envelope->incrementAttempts();

        self::assertSame(['default', 30, 3, 1], [$envelope->getQueue(), $envelope->getDelay(), $envelope->getMaxTries(), $envelope->getAttempts()]);
    }

    public function test_jobs_without_a_tenant_are_unchanged_and_clear_the_worker_context(): void
    {
        $this->queue(new TenantContext())->push(new TenancyRecordingJob('plain'));

        $worker = new TenantContext();
        $worker->set(new Tenant('globex')); // left over from the previous job
        $job = $this->queue($worker)->pop();

        self::assertInstanceOf(TenancyRecordingJob::class, $job);
        self::assertFalse($worker->check());
    }

    public function test_an_empty_queue_clears_the_context(): void
    {
        $worker = new TenantContext();
        $worker->set(new Tenant('acme'));

        self::assertNull($this->queue($worker)->pop());
        self::assertFalse($worker->check());
    }

    public function test_a_tenant_the_store_no_longer_knows_leaves_no_tenant_active(): void
    {
        $this->driver->push(new TenantQueueEnvelope(new TenancyRecordingJob('orphan'), 'deleted'));

        $worker = new TenantContext();
        $job = $this->queue($worker)->pop();

        self::assertInstanceOf(TenancyRecordingJob::class, $job);
        self::assertFalse($worker->check());
    }

    public function test_failed_jobs_are_archived_with_their_tenant(): void
    {
        $worker = new TenantContext();
        $worker->set(new Tenant('acme'));

        $this->queue($worker)->failed(new TenancyRecordingJob('broken'), new RuntimeException('x'));
        $this->queue($worker)->failed(new TenantQueueEnvelope(new TenancyRecordingJob('wrapped'), 'globex'), new RuntimeException('y'));

        self::assertInstanceOf(TenantQueueEnvelope::class, $this->driver->failed[0]);
        self::assertSame('acme', $this->driver->failed[0]->tenantId());
        self::assertInstanceOf(TenantQueueEnvelope::class, $this->driver->failed[1]);
        self::assertSame('globex', $this->driver->failed[1]->tenantId(), 'an envelope is never wrapped twice');
    }

    public function test_handle_and_fail_delegate_when_popped_without_the_decorator(): void
    {
        $job = new TenancyRecordingJob('x');
        $envelope = new TenantQueueEnvelope($job, 'acme');

        $envelope->handle();
        $envelope->fail(new RuntimeException('z'));

        self::assertSame($job, $envelope->job());
    }
}
