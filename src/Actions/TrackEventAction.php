<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Bus\Dispatcher;
use YezzMedia\OpsAnalytics\Data\AnalyticsContext;
use YezzMedia\OpsAnalytics\Data\AnalyticsEvent;
use YezzMedia\OpsAnalytics\Jobs\DispatchAnalyticsEventJob;

final class TrackEventAction
{
    use Queueable;

    public function __construct(private readonly Dispatcher $dispatcher) {}

    public function execute(AnalyticsEvent $event, AnalyticsContext $context): void
    {
        $job = new DispatchAnalyticsEventJob($event, $context);

        $queueConnection = config('ops-analytics.dispatch.queue_connection');
        $queueName = config('ops-analytics.dispatch.queue_name');

        if (is_string($queueConnection) && $queueConnection !== '') {
            $job->onConnection($queueConnection);
        }

        if (is_string($queueName) && $queueName !== '') {
            $job->onQueue($queueName);
        }

        if ((bool) config('ops-analytics.dispatch.sync_fallback', true)) {
            $this->dispatcher->dispatchSync($job);

            return;
        }

        $this->dispatcher->dispatch($job);
    }
}
