<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Events;

final readonly class DispatchOutcomeRecorded
{
    public function __construct(
        public string $trackerKey,
        public string $dispatchKey,
        public string $status,
        public int $attemptCount,
        public ?string $completedAt,
        public ?int $actorId,
        public string $source,
    ) {}
}
