<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Data;

use YezzMedia\OpsAnalytics\Enums\ConsentGateStatus;

final readonly class AnalyticsContext
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ?string $trackerKey,
        public ?string $actorType,
        public ?string $actorKey,
        public ?string $sessionKey,
        public ?ConsentGateStatus $consentStatus,
        public array $metadata,
    ) {}
}
