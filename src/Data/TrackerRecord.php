<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Data;

use YezzMedia\OpsAnalytics\Enums\TrackerHealthStatus;

final readonly class TrackerRecord
{
    /**
     * @param  array<int, string>  $issues
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $trackerKey,
        public string $name,
        public string $driver,
        public string $lifecycleStatus,
        public bool $isEnabled,
        public ?string $consentMode,
        public TrackerHealthStatus $healthStatus,
        public ?string $lastDispatchAt,
        public ?string $lastSuccessAt,
        public ?string $lastFailureAt,
        public string $summary,
        public array $issues,
        public array $metadata,
    ) {}
}
