<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Data;

final readonly class DeliveryFailureRecord
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public string $trackerKey,
        public ?string $dispatchKey,
        public ?string $occurredAt,
        public string $summary,
        public array $details,
    ) {}
}
