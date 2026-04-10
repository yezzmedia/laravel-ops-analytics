<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Support;

use InvalidArgumentException;
use Spatie\Activitylog\Support\ActivityLogger;
use YezzMedia\OpsAnalytics\Contracts\OpsAnalyticsAuditWriter;
use YezzMedia\OpsAnalytics\Events\AnalyticsPostureRefreshed;
use YezzMedia\OpsAnalytics\Events\DispatchOutcomeRecorded;
use YezzMedia\OpsAnalytics\Events\TrackerConfigurationUpdated;

final class ActivityLogOpsAnalyticsAuditWriter implements OpsAnalyticsAuditWriter
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function record(object $event): void
    {
        if ($event instanceof AnalyticsPostureRefreshed) {
            $this->recordAnalyticsPostureRefreshed($event);

            return;
        }

        if ($event instanceof TrackerConfigurationUpdated) {
            $this->recordTrackerConfigurationUpdated($event);

            return;
        }

        if ($event instanceof DispatchOutcomeRecorded) {
            $this->recordDispatchOutcomeRecorded($event);

            return;
        }

        throw new InvalidArgumentException(sprintf('Unsupported ops analytics audit event [%s].', $event::class));
    }

    private function recordAnalyticsPostureRefreshed(AnalyticsPostureRefreshed $event): void
    {
        $this->activity
            ->useLog(config('ops-analytics.audit.log_name', 'ops-analytics'))
            ->event('refreshed')
            ->withProperties([
                'overall_status' => $event->overallStatus,
                'healthy_count' => $event->healthyCount,
                'warning_count' => $event->warningCount,
                'failing_count' => $event->failingCount,
                'unsupported_count' => $event->unsupportedCount,
                'blocked_count' => $event->blockedCount,
                'actor_id' => $event->actorId,
                'source' => $event->source,
                'completed_at' => $event->completedAt,
            ])
            ->log('Ops analytics posture snapshot was refreshed.');
    }

    private function recordTrackerConfigurationUpdated(TrackerConfigurationUpdated $event): void
    {
        $this->activity
            ->useLog(config('ops-analytics.audit.log_name', 'ops-analytics'))
            ->event('updated')
            ->withProperties([
                'tracker_key' => $event->trackerKey,
                'driver' => $event->driver,
                'lifecycle_status' => $event->lifecycleStatus,
                'is_enabled' => $event->isEnabled,
                'consent_mode' => $event->consentMode,
                'actor_id' => $event->actorId,
                'source' => $event->source,
                'completed_at' => $event->completedAt,
            ])
            ->log('An analytics tracker metadata record was updated.');
    }

    private function recordDispatchOutcomeRecorded(DispatchOutcomeRecorded $event): void
    {
        $this->activity
            ->useLog(config('ops-analytics.audit.log_name', 'ops-analytics'))
            ->event('recorded')
            ->withProperties([
                'tracker_key' => $event->trackerKey,
                'dispatch_key' => $event->dispatchKey,
                'status' => $event->status,
                'attempt_count' => $event->attemptCount,
                'completed_at' => $event->completedAt,
                'actor_id' => $event->actorId,
                'source' => $event->source,
            ])
            ->log('An analytics dispatch outcome was recorded.');
    }
}
