<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Events;

final readonly class AnalyticsPostureRefreshed
{
    public function __construct(
        public string $overallStatus,
        public int $healthyCount,
        public int $warningCount,
        public int $failingCount,
        public int $unsupportedCount,
        public int $blockedCount,
        public ?int $actorId,
        public string $source,
        public string $completedAt,
    ) {}
}
