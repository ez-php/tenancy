<?php

declare(strict_types=1);

namespace EzPhp\Tenancy\Queue;

use EzPhp\Contracts\JobInterface;
use EzPhp\Contracts\QueueInterface;
use EzPhp\Tenancy\TenantContext;
use EzPhp\Tenancy\TenantStoreInterface;
use Throwable;

/**
 * Class TenantAwareQueue
 *
 * QueueInterface decorator that carries the active tenant from the process that
 * pushes a job to the worker that runs it, so jobs no longer wrap their work in
 * TenantContext::run() by hand.
 *
 * - push(): with an active tenant, stores the job in a TenantQueueEnvelope with
 *   the tenant id; without one, the job is pushed unchanged.
 * - pop(): unwraps the envelope, looks the tenant up in the store and makes it the
 *   active tenant (TenantContext::set()) for the job that follows; a job without an
 *   envelope — or an empty queue — clears it. The tenant therefore stays active
 *   while the Worker runs the job and is replaced on the next pop().
 * - failed(): archives the job inside an envelope for the active tenant, so a
 *   retried failed job runs as its tenant again.
 *
 * A tenant id the store no longer knows clears the context: tenant-scoped code in
 * the job then fails with "No tenant is active" and the job takes the normal
 * failure path instead of running for the wrong tenant.
 *
 * Uses only ez-php/contracts' queue interfaces — no ez-php/queue dependency. Wrap
 * the bound QueueInterface in your own provider (see README); like ez-php/queue's
 * UniqueQueue, the decorator hides the driver's FailedJobRepositoryInterface.
 *
 * @package EzPhp\Tenancy\Queue
 */
final readonly class TenantAwareQueue implements QueueInterface
{
    /**
     * @param QueueInterface       $queue   The real queue driver.
     * @param TenantContext        $context Shared holder of the active tenant.
     * @param TenantStoreInterface $store   Resolves the stored tenant id on pop().
     */
    public function __construct(
        private QueueInterface $queue,
        private TenantContext $context,
        private TenantStoreInterface $store,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function push(JobInterface $job): void
    {
        $this->queue->push($this->wrap($job));
    }

    /**
     * {@inheritDoc}
     */
    public function pop(string $queue = 'default'): ?JobInterface
    {
        $job = $this->queue->pop($queue);

        if (!$job instanceof TenantQueueEnvelope) {
            $this->context->set(null);

            return $job;
        }

        $this->context->set($this->store->find($job->tenantId()));

        return $job->job();
    }

    /**
     * {@inheritDoc}
     */
    public function size(string $queue = 'default'): int
    {
        return $this->queue->size($queue);
    }

    /**
     * {@inheritDoc}
     */
    public function failed(JobInterface $job, Throwable $exception): void
    {
        $this->queue->failed($this->wrap($job), $exception);
    }

    /**
     * @param JobInterface $job
     *
     * @return JobInterface
     */
    private function wrap(JobInterface $job): JobInterface
    {
        $tenant = $this->context->get();

        if ($job instanceof TenantQueueEnvelope || $tenant === null) {
            return $job;
        }

        return new TenantQueueEnvelope($job, $tenant->id);
    }
}
