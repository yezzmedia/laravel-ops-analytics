<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Data;

use YezzMedia\OpsAnalytics\Enums\ConsentGateStatus;

final readonly class ConsentGateRecord
{
    /**
     * @param  array<int, string>  $issues
     */
    public function __construct(
        public ?string $trackerKey,
        public ConsentGateStatus $status,
        public ?string $source,
        public string $summary,
        public array $issues,
    ) {}
}
