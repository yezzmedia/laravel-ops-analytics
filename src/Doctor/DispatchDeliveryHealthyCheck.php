<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Doctor;

use YezzMedia\Foundation\Data\DoctorResult;
use YezzMedia\Foundation\Doctor\DoctorCheck;
use YezzMedia\OpsAnalytics\Enums\DispatchPostureStatus;
use YezzMedia\OpsAnalytics\Support\OpsAnalyticsManager;

final class DispatchDeliveryHealthyCheck implements DoctorCheck
{
    private const KEY = 'dispatch_delivery_healthy';

    private const PACKAGE = 'yezzmedia/laravel-ops-analytics';

    public function __construct(private readonly OpsAnalyticsManager $manager) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function package(): string
    {
        return self::PACKAGE;
    }

    public function run(): DoctorResult
    {
        $summary = $this->manager->summary();

        return match ($summary->overallStatus) {
            DispatchPostureStatus::Healthy => new DoctorResult(self::KEY, self::PACKAGE, 'passed', 'Analytics delivery posture is healthy.', false),
            DispatchPostureStatus::Warning => new DoctorResult(self::KEY, self::PACKAGE, 'warning', 'Analytics delivery posture is degraded.', false),
            DispatchPostureStatus::Failed => new DoctorResult(self::KEY, self::PACKAGE, 'failed', 'Analytics delivery posture is failing.', false),
            DispatchPostureStatus::Unsupported => new DoctorResult(self::KEY, self::PACKAGE, 'warning', 'Analytics delivery posture is unsupported in the current environment.', false),
        };
    }
}
