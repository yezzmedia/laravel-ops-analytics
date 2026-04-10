<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Data;

use YezzMedia\OpsAnalytics\Enums\DispatchPostureStatus;

final readonly class AnalyticsOperationsSummary
{
    /**
     * @param  array<int, TrackerRecord>  $trackers
     */
    public function __construct(
        public DispatchPostureStatus $overallStatus,
        public array $trackers,
        public int $healthyCount,
        public int $warningCount,
        public int $failingCount,
        public int $unsupportedCount,
        public int $blockedCount,
        public ?string $checkedAt,
    ) {}
}
