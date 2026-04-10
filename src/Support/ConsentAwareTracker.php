<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Support;

use YezzMedia\OpsAnalytics\Contracts\Tracker;
use YezzMedia\OpsAnalytics\Data\AnalyticsContext;
use YezzMedia\OpsAnalytics\Data\AnalyticsEvent;
use YezzMedia\OpsAnalytics\Enums\ConsentGateStatus;

final class ConsentAwareTracker
{
    public function dispatch(Tracker $tracker, AnalyticsEvent $event, AnalyticsContext $context): bool
    {
        if ($context->consentStatus === ConsentGateStatus::Blocked) {
            return false;
        }

        $tracker->track($event, $context);

        return true;
    }
}
