<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Data;

final readonly class AnalyticsEvent
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function __construct(
        public string $eventKey,
        public string $name,
        public ?string $category,
        public string $occurredAt,
        public ?string $subjectType,
        public ?string $subjectKey,
        public array $properties,
    ) {}
}
