<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Support;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use YezzMedia\OpsAnalytics\Data\AnalyticsOperationsSummary;
use YezzMedia\OpsAnalytics\Data\ConsentGateRecord;
use YezzMedia\OpsAnalytics\Data\DeliveryFailureRecord;
use YezzMedia\OpsAnalytics\Data\DispatchPostureRecord;
use YezzMedia\OpsAnalytics\Data\TrackerRecord;
use YezzMedia\OpsAnalytics\Enums\ConsentGateStatus;
use YezzMedia\OpsAnalytics\Enums\DispatchPostureStatus;
use YezzMedia\OpsAnalytics\Enums\TrackerHealthStatus;

final class OpsAnalyticsManager
{
    private CacheRepository $cache;

    private ?AnalyticsOperationsSummary $summaryMemo = null;

    public function __construct(
        private readonly TrackerInventoryResolver $trackerInventoryResolver,
        private readonly DispatchPostureResolver $dispatchPostureResolver,
        private readonly ConsentGateResolver $consentGateResolver,
        private readonly DeliveryFailureResolver $deliveryFailureResolver,
        private readonly TrackerHealthResolver $trackerHealthResolver,
        CacheFactory $cacheFactory,
        private readonly bool $cacheEnabled,
        private readonly ?string $cacheStore,
        private readonly int $cacheTtl,
        private readonly bool $excludeUnsupportedFromAggregation = false,
    ) {
        $this->cache = $cacheFactory->store($cacheStore);
    }

    public function summary(): AnalyticsOperationsSummary
    {
        if ($this->summaryMemo instanceof AnalyticsOperationsSummary) {
            return $this->summaryMemo;
        }

        if ($this->cacheEnabled) {
            /** @var AnalyticsOperationsSummary|null $cached */
            $cached = $this->cache->get($this->cacheKey());

            if ($cached instanceof AnalyticsOperationsSummary) {
                return $this->summaryMemo = $cached;
            }
        }

        $summary = $this->computeSummary();

        if ($this->cacheEnabled) {
            $this->cache->put($this->cacheKey(), $summary, $this->cacheTtl);
        }

        return $this->summaryMemo = $summary;
    }

    /**
     * @return array<int, TrackerRecord>
     */
    public function trackers(): array
    {
        return $this->summary()->trackers;
    }

    public function tracker(string $trackerKey): ?TrackerRecord
    {
        foreach ($this->trackers() as $tracker) {
            if ($tracker->trackerKey === $trackerKey) {
                return $tracker;
            }
        }

        return null;
    }

    /**
     * @return array<int, DispatchPostureRecord>
     */
    public function dispatches(): array
    {
        return $this->dispatchPostureResolver->resolve();
    }

    /**
     * @return array<int, DispatchPostureRecord>
     */
    public function dispatchesFor(string $trackerKey): array
    {
        return $this->dispatchPostureResolver->resolveForTracker($trackerKey);
    }

    /**
     * @return array<int, ConsentGateRecord>
     */
    public function consentGates(): array
    {
        return $this->consentGateResolver->resolve();
    }

    public function consentGateFor(?string $trackerKey): ConsentGateRecord
    {
        return $this->consentGateResolver->forTracker($trackerKey);
    }

    /**
     * @return array<int, DeliveryFailureRecord>
     */
    public function failures(int $limit = 10): array
    {
        return $this->deliveryFailureResolver->resolve($limit);
    }

    /**
     * @return array<int, DeliveryFailureRecord>
     */
    public function failuresFor(string $trackerKey, int $limit = 10): array
    {
        return $this->deliveryFailureResolver->resolveForTracker($trackerKey, $limit);
    }

    public function overallStatus(): DispatchPostureStatus
    {
        return $this->summary()->overallStatus;
    }

    public function refresh(): AnalyticsOperationsSummary
    {
        $this->summaryMemo = null;
        $this->cache->forget($this->cacheKey());

        return $this->summary();
    }

    private function computeSummary(): AnalyticsOperationsSummary
    {
        $trackers = $this->trackerInventoryResolver->resolve();
        $statuses = array_map(fn (TrackerRecord $tracker): DispatchPostureStatus => $this->statusFromTracker($tracker), $trackers);
        $aggregationStatuses = $this->aggregationStatuses($statuses);
        $blockedCount = count(array_filter(
            array_map(fn (TrackerRecord $tracker): ConsentGateRecord => $this->consentGateFor($tracker->trackerKey), $trackers),
            fn (ConsentGateRecord $record): bool => $record->status === ConsentGateStatus::Blocked,
        ));

        return new AnalyticsOperationsSummary(
            overallStatus: DispatchPostureStatus::worst($aggregationStatuses === [] ? [DispatchPostureStatus::Unsupported] : $aggregationStatuses),
            trackers: $trackers,
            healthyCount: count(array_filter($statuses, fn (DispatchPostureStatus $status): bool => $status === DispatchPostureStatus::Healthy)),
            warningCount: count(array_filter($statuses, fn (DispatchPostureStatus $status): bool => $status === DispatchPostureStatus::Warning)),
            failingCount: count(array_filter($statuses, fn (DispatchPostureStatus $status): bool => $status === DispatchPostureStatus::Failed)),
            unsupportedCount: count(array_filter($statuses, fn (DispatchPostureStatus $status): bool => $status === DispatchPostureStatus::Unsupported)),
            blockedCount: $blockedCount,
            checkedAt: now()->toIso8601String(),
        );
    }

    private function statusFromTracker(TrackerRecord $tracker): DispatchPostureStatus
    {
        return match ($tracker->healthStatus) {
            TrackerHealthStatus::Healthy => DispatchPostureStatus::Healthy,
            TrackerHealthStatus::Degraded => DispatchPostureStatus::Warning,
            TrackerHealthStatus::Failed => DispatchPostureStatus::Failed,
            TrackerHealthStatus::Unsupported => DispatchPostureStatus::Unsupported,
        };
    }

    /**
     * @param  array<int, DispatchPostureStatus>  $statuses
     * @return array<int, DispatchPostureStatus>
     */
    private function aggregationStatuses(array $statuses): array
    {
        if (! $this->excludeUnsupportedFromAggregation) {
            return $statuses;
        }

        return array_values(array_filter($statuses, fn (DispatchPostureStatus $status): bool => $status !== DispatchPostureStatus::Unsupported));
    }

    private function cacheKey(): string
    {
        return 'ops_analytics.summary';
    }
}
