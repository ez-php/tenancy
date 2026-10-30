<?php

declare(strict_types=1);

namespace EzPhp\Tenancy\Queue;

use EzPhp\Contracts\JobInterface;
use Throwable;

/**
 * Class TenantQueueEnvelope
 *
 * The stored form of a job pushed through TenantAwareQueue: the job plus the id
 * of the tenant that was active at push(). TenantAwareQueue::pop() unwraps it,
 * so a Worker always runs the original job — with its middleware, backoff and
 * chain intact. The queue-relevant getters delegate to the job, so drivers place
 * and delay the envelope exactly like the job itself.
 *
 * handle()/fail() only run when a queue without the decorator pops the envelope;
 * they then delegate without activating the tenant.
 *
 * @package EzPhp\Tenancy\Queue
 */
final class TenantQueueEnvelope implements JobInterface
{
    /**
     * @param JobInterface $job
     * @param string       $tenantId
     */
    public function __construct(
        private readonly JobInterface $job,
        private readonly string $tenantId,
    ) {
    }

    /**
     * @return JobInterface
     */
    public function job(): JobInterface
    {
        return $this->job;
    }

    /**
     * @return string
     */
    public function tenantId(): string
    {
        return $this->tenantId;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(): void
    {
        $this->job->handle();
    }

    /**
     * {@inheritDoc}
     */
    public function fail(Throwable $exception): void
    {
        $this->job->fail($exception);
    }

    /**
     * {@inheritDoc}
     */
    public function getQueue(): string
    {
        return $this->job->getQueue();
    }

    /**
     * {@inheritDoc}
     */
    public function getDelay(): int
    {
        return $this->job->getDelay();
    }

    /**
     * {@inheritDoc}
     */
    public function getMaxTries(): int
    {
        return $this->job->getMaxTries();
    }

    /**
     * {@inheritDoc}
     */
    public function getAttempts(): int
    {
        return $this->job->getAttempts();
    }

    /**
     * {@inheritDoc}
     */
    public function incrementAttempts(): void
    {
        $this->job->incrementAttempts();
    }
}
