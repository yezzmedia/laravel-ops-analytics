<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Events;

final readonly class TrackerConfigurationUpdated
{
    public function __construct(
        public string $trackerKey,
        public string $driver,
        public string $lifecycleStatus,
        public bool $isEnabled,
        public ?string $consentMode,
        public ?int $actorId,
        public string $source,
        public string $completedAt,
    ) {}
}
