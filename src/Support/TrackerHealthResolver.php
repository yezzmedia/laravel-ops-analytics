<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Support;

use YezzMedia\OpsAnalytics\Enums\DispatchPostureStatus;
use YezzMedia\OpsAnalytics\Enums\TrackerHealthStatus;
use YezzMedia\OpsAnalytics\Models\OpsAnalyticsTracker;

final class TrackerHealthResolver
{
    public function resolve(OpsAnalyticsTracker $tracker, DispatchPostureStatus $dispatchStatus): TrackerHealthStatus
    {
        if (! (bool) $tracker->getAttribute('is_enabled')) {
            return TrackerHealthStatus::Unsupported;
        }

        return match ($dispatchStatus) {
            DispatchPostureStatus::Healthy => TrackerHealthStatus::Healthy,
            DispatchPostureStatus::Warning => TrackerHealthStatus::Degraded,
            DispatchPostureStatus::Failed => TrackerHealthStatus::Failed,
            DispatchPostureStatus::Unsupported => TrackerHealthStatus::Unsupported,
        };
    }
}
