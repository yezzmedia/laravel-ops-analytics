<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Contracts;

use YezzMedia\OpsAnalytics\Data\AnalyticsContext;
use YezzMedia\OpsAnalytics\Data\AnalyticsEvent;

interface Tracker
{
    public function key(): string;

    public function name(): string;

    public function driver(): string;

    public function enabled(): bool;

    public function consentMode(): ?string;

    /**
     * @return array<string, mixed>
     */
    public function configurationSummary(): array;

    public function track(AnalyticsEvent $event, AnalyticsContext $context): void;
}
