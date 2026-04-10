<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Support;

use YezzMedia\OpsAnalytics\Data\DeliveryFailureRecord;
use YezzMedia\OpsAnalytics\Models\OpsAnalyticsDispatchAttempt;

final class DeliveryFailureResolver
{
    /**
     * @return array<int, DeliveryFailureRecord>
     */
    public function resolve(int $limit = 10): array
    {
        return OpsAnalyticsDispatchAttempt::query()
            ->with(['dispatch.tracker'])
            ->where('status', 'failed')
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn (OpsAnalyticsDispatchAttempt $attempt): DeliveryFailureRecord => $this->mapAttempt($attempt))
            ->values()
            ->all();
    }

    /**
     * @return array<int, DeliveryFailureRecord>
     */
    public function resolveForTracker(string $trackerKey, int $limit = 10): array
    {
        return OpsAnalyticsDispatchAttempt::query()
            ->with(['dispatch.tracker'])
            ->where('status', 'failed')
            ->whereHas('dispatch.tracker', fn ($query) => $query->where('tracker_key', $trackerKey))
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn (OpsAnalyticsDispatchAttempt $attempt): DeliveryFailureRecord => $this->mapAttempt($attempt))
            ->values()
            ->all();
    }

    private function mapAttempt(OpsAnalyticsDispatchAttempt $attempt): DeliveryFailureRecord
    {
        return new DeliveryFailureRecord(
            trackerKey: (string) $attempt->dispatch->tracker->getAttribute('tracker_key'),
            dispatchKey: (string) $attempt->dispatch->getAttribute('dispatch_key'),
            occurredAt: $attempt->getAttribute('occurred_at')?->toIso8601String(),
            summary: (string) ($attempt->getAttribute('failure_summary') ?: 'Analytics delivery failed.'),
            details: [
                'attempt_number' => (int) $attempt->getAttribute('attempt_number'),
                'response_code' => $attempt->getAttribute('response_code'),
                'latency_ms' => $attempt->getAttribute('latency_ms'),
            ],
        );
    }
}
