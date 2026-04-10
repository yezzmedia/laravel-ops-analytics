<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Support;

use Carbon\CarbonImmutable;
use YezzMedia\OpsAnalytics\Data\DispatchPostureRecord;
use YezzMedia\OpsAnalytics\Enums\DispatchPostureStatus;
use YezzMedia\OpsAnalytics\Models\OpsAnalyticsDispatch;

final class DispatchPostureResolver
{
    public function __construct(
        private readonly int $warningMinutes,
        private readonly int $failedMinutes,
    ) {}

    /**
     * @return array<int, DispatchPostureRecord>
     */
    public function resolve(): array
    {
        return OpsAnalyticsDispatch::query()
            ->orderByDesc('completed_at')
            ->get()
            ->map(fn (OpsAnalyticsDispatch $dispatch): DispatchPostureRecord => $this->mapDispatch($dispatch))
            ->values()
            ->all();
    }

    /**
     * @return array<int, DispatchPostureRecord>
     */
    public function resolveForTracker(string $trackerKey): array
    {
        return OpsAnalyticsDispatch::query()
            ->whereHas('tracker', fn ($query) => $query->where('tracker_key', $trackerKey))
            ->orderByDesc('completed_at')
            ->get()
            ->map(fn (OpsAnalyticsDispatch $dispatch): DispatchPostureRecord => $this->mapDispatch($dispatch))
            ->values()
            ->all();
    }

    private function mapDispatch(OpsAnalyticsDispatch $dispatch): DispatchPostureRecord
    {
        $status = $this->resolveStatus($dispatch);

        return new DispatchPostureRecord(
            trackerKey: (string) $dispatch->tracker->getAttribute('tracker_key'),
            dispatchKey: (string) $dispatch->getAttribute('dispatch_key'),
            eventName: (string) $dispatch->getAttribute('event_name'),
            status: $status,
            queuedAt: $this->timestamp($dispatch->getAttribute('queued_at')),
            completedAt: $this->timestamp($dispatch->getAttribute('completed_at')),
            attemptCount: (int) $dispatch->getAttribute('attempt_count'),
            summary: $this->summary($dispatch, $status),
            issues: $this->issues($dispatch, $status),
        );
    }

    private function resolveStatus(OpsAnalyticsDispatch $dispatch): DispatchPostureStatus
    {
        $deliveryStatus = (string) $dispatch->getAttribute('delivery_status');

        if ($deliveryStatus === 'failed') {
            return DispatchPostureStatus::Failed;
        }

        if ($deliveryStatus === 'warning') {
            return DispatchPostureStatus::Warning;
        }

        $completedAt = $dispatch->getAttribute('completed_at');

        if ($completedAt === null) {
            return DispatchPostureStatus::Unsupported;
        }

        $minutes = CarbonImmutable::parse((string) $completedAt)->diffInMinutes(now());

        if ($minutes >= $this->failedMinutes) {
            return DispatchPostureStatus::Failed;
        }

        if ($minutes >= $this->warningMinutes) {
            return DispatchPostureStatus::Warning;
        }

        return DispatchPostureStatus::Healthy;
    }

    private function summary(OpsAnalyticsDispatch $dispatch, DispatchPostureStatus $status): string
    {
        return match ($status) {
            DispatchPostureStatus::Healthy => 'Recent dispatch completed within the healthy delivery window.',
            DispatchPostureStatus::Warning => 'Dispatch delivery is stale or warning-level based on configured thresholds.',
            DispatchPostureStatus::Failed => (string) ($dispatch->getAttribute('failure_summary') ?: 'Dispatch delivery is currently failing.'),
            DispatchPostureStatus::Unsupported => 'Dispatch posture cannot be evaluated from the current metadata.',
        };
    }

    /**
     * @return array<int, string>
     */
    private function issues(OpsAnalyticsDispatch $dispatch, DispatchPostureStatus $status): array
    {
        $issues = [];

        if ($status === DispatchPostureStatus::Failed || $status === DispatchPostureStatus::Warning) {
            $issues[] = (string) ($dispatch->getAttribute('failure_summary') ?: 'Dispatch delivery needs operator attention.');
        }

        if ((int) $dispatch->getAttribute('attempt_count') > 1) {
            $issues[] = 'Dispatch required multiple attempts.';
        }

        if ($status === DispatchPostureStatus::Unsupported) {
            $issues[] = 'Dispatch metadata is incomplete.';
        }

        return $issues;
    }

    private function timestamp(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }
}
