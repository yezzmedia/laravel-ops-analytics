<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Listeners;

use YezzMedia\OpsAnalytics\Contracts\OpsAnalyticsAuditWriter;
use YezzMedia\OpsAnalytics\Events\AnalyticsPostureRefreshed;
use YezzMedia\OpsAnalytics\Events\DispatchOutcomeRecorded;
use YezzMedia\OpsAnalytics\Events\TrackerConfigurationUpdated;

final class OpsAnalyticsAuditListener
{
    public function __construct(private readonly OpsAnalyticsAuditWriter $writer) {}

    public function handleAnalyticsPostureRefreshed(AnalyticsPostureRefreshed $event): void
    {
        $this->writer->record($event);
    }

    public function handleTrackerConfigurationUpdated(TrackerConfigurationUpdated $event): void
    {
        $this->writer->record($event);
    }

    public function handleDispatchOutcomeRecorded(DispatchOutcomeRecorded $event): void
    {
        $this->writer->record($event);
    }
}
