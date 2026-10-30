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
 * failure path instead of running for the wrong tenant. The id stays attached until
 * the next pop(), so the retry or failed-job record keeps it for when the tenant is
 * restored.
 *
 * A store that throws on pop() puts the envelope back and rethrows: the driver has
 * already removed the job, so swallowing or just propagating the failure would lose
 * it. The Worker stops, as it does when the driver itself is unavailable.
 *
 * Uses only ez-php/contracts' queue interfaces — no ez-php/queue dependency. Wrap
 * the bound QueueInterface in your own provider (see README); like ez-php/queue's
 * UniqueQueue, the decorator hides the driver's FailedJobRepositoryInterface.
 *
 * @package EzPhp\Tenancy\Queue
 */
final class TenantAwareQueue implements QueueInterface
{
    /**
     * Tenant id of the envelope popped last; null for a job without one.
     */
    private ?string $poppedTenantId = null;

    /**
     * @param QueueInterface       $queue   The real queue driver.
     * @param TenantContext        $context Shared holder of the active tenant.
     * @param TenantStoreInterface $store   Resolves the stored tenant id on pop().
     */
    public function __construct(
        private readonly QueueInterface $queue,
        private readonly TenantContext $context,
        private readonly TenantStoreInterface $store,
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

        $this->context->set(null);
        $this->poppedTenantId = null;

        if (!$job instanceof TenantQueueEnvelope) {
            return $job;
        }

        try {
            $tenant = $this->store->find($job->tenantId());
        } catch (Throwable $e) {
            $this->queue->push($job);

            throw $e;
        }

        $this->context->set($tenant);
        $this->poppedTenantId = $job->tenantId();

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
        $tenantId = $this->context->get()->id ?? $this->poppedTenantId;

        if ($job instanceof TenantQueueEnvelope || $tenantId === null) {
            return $job;
        }

        return new TenantQueueEnvelope($job, $tenantId);
    }
}
