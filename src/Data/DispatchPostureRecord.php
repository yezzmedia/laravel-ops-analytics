<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Data;

use YezzMedia\OpsAnalytics\Enums\DispatchPostureStatus;

final readonly class DispatchPostureRecord
{
    /**
     * @param  array<int, string>  $issues
     */
    public function __construct(
        public string $trackerKey,
        public string $dispatchKey,
        public string $eventName,
        public DispatchPostureStatus $status,
        public ?string $queuedAt,
        public ?string $completedAt,
        public int $attemptCount,
        public string $summary,
        public array $issues,
    ) {}
}
