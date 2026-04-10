<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Support;

use YezzMedia\OpsAnalytics\Data\TrackerRecord;
use YezzMedia\OpsAnalytics\Enums\DispatchPostureStatus;
use YezzMedia\OpsAnalytics\Enums\TrackerHealthStatus;
use YezzMedia\OpsAnalytics\Models\OpsAnalyticsDispatch;
use YezzMedia\OpsAnalytics\Models\OpsAnalyticsTracker;

final class TrackerInventoryResolver
{
    public function __construct(private readonly TrackerHealthResolver $trackerHealthResolver) {}

    /**
     * @return array<int, TrackerRecord>
     */
    public function resolve(): array
    {
        return OpsAnalyticsTracker::query()
            ->with(['dispatches' => fn ($query) => $query->latest('completed_at')])
            ->orderBy('name')
            ->get()
            ->map(fn (OpsAnalyticsTracker $tracker): TrackerRecord => $this->mapTracker($tracker))
            ->values()
            ->all();
    }

    private function mapTracker(OpsAnalyticsTracker $tracker): TrackerRecord
    {
        /** @var OpsAnalyticsDispatch|null $latestDispatch */
        $latestDispatch = $tracker->dispatches->sortByDesc('completed_at')->first();
        /** @var OpsAnalyticsDispatch|null $lastSuccess */
        $lastSuccess = $tracker->dispatches->firstWhere('delivery_status', 'healthy');
        /** @var OpsAnalyticsDispatch|null $lastFailure */
        $lastFailure = $tracker->dispatches->firstWhere('delivery_status', 'failed');
        $dispatchStatus = $this->dispatchStatus($latestDispatch, $lastFailure);
        $healthStatus = $this->trackerHealthResolver->resolve($tracker, $dispatchStatus);

        return new TrackerRecord(
            trackerKey: (string) $tracker->getAttribute('tracker_key'),
            name: (string) $tracker->getAttribute('name'),
            driver: (string) $tracker->getAttribute('driver'),
            lifecycleStatus: (string) $tracker->getAttribute('lifecycle_status'),
            isEnabled: (bool) $tracker->getAttribute('is_enabled'),
            consentMode: $tracker->getAttribute('consent_mode'),
            healthStatus: $healthStatus,
            lastDispatchAt: $this->timestamp($latestDispatch?->getAttribute('completed_at') ?? $latestDispatch?->getAttribute('queued_at')),
            lastSuccessAt: $this->timestamp($lastSuccess?->getAttribute('last_success_at') ?? $lastSuccess?->getAttribute('completed_at')),
            lastFailureAt: $this->timestamp($lastFailure?->getAttribute('last_failure_at') ?? $lastFailure?->getAttribute('completed_at')),
            summary: $this->summary($tracker, $healthStatus, $latestDispatch),
            issues: $this->issues($tracker, $healthStatus, $latestDispatch),
            metadata: $this->safeMetadata($tracker->getAttribute('metadata')),
        );
    }

    private function dispatchStatus(?OpsAnalyticsDispatch $latestDispatch, ?OpsAnalyticsDispatch $lastFailure): DispatchPostureStatus
    {
        if ($lastFailure !== null) {
            return DispatchPostureStatus::Failed;
        }

        if ($latestDispatch === null) {
            return DispatchPostureStatus::Warning;
        }

        if ((string) $latestDispatch->getAttribute('delivery_status') === 'warning') {
            return DispatchPostureStatus::Warning;
        }

        return DispatchPostureStatus::Healthy;
    }

    private function summary(OpsAnalyticsTracker $tracker, TrackerHealthStatus $healthStatus, ?OpsAnalyticsDispatch $latestDispatch): string
    {
        if (! (bool) $tracker->getAttribute('is_enabled')) {
            return 'Tracker is disabled and currently excluded from active delivery posture.';
        }

        return match ($healthStatus) {
            TrackerHealthStatus::Healthy => 'Tracker delivery posture is healthy.',
            TrackerHealthStatus::Degraded => $latestDispatch === null
                ? 'Tracker is configured, but no dispatch metadata is available yet.'
                : 'Tracker delivery posture is degraded and needs operator attention.',
            TrackerHealthStatus::Failed => (string) ($latestDispatch?->getAttribute('failure_summary') ?: 'Tracker delivery posture is failing.'),
            TrackerHealthStatus::Unsupported => 'Tracker health cannot be evaluated from the current metadata.',
        };
    }

    /**
     * @return array<int, string>
     */
    private function issues(OpsAnalyticsTracker $tracker, TrackerHealthStatus $healthStatus, ?OpsAnalyticsDispatch $latestDispatch): array
    {
        $issues = [];

        if (! (bool) $tracker->getAttribute('is_enabled')) {
            $issues[] = 'Tracker is disabled.';
        }

        if ($latestDispatch === null) {
            $issues[] = 'No dispatch metadata is available yet.';
        }

        if ($healthStatus === TrackerHealthStatus::Failed) {
            $issues[] = 'Recent dispatch metadata indicates failed delivery.';
        }

        if ($healthStatus === TrackerHealthStatus::Degraded) {
            $issues[] = 'Recent dispatch metadata indicates degraded delivery.';
        }

        return $issues;
    }

    /**
     * @return array<string, mixed>
     */
    private function safeMetadata(mixed $metadata): array
    {
        return is_array($metadata) ? $metadata : [];
    }

    private function timestamp(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }
}
