<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Doctor;

use YezzMedia\Foundation\Data\DoctorResult;
use YezzMedia\Foundation\Doctor\DoctorCheck;
use YezzMedia\OpsAnalytics\Support\OpsAnalyticsManager;

final class TrackersConfiguredCheck implements DoctorCheck
{
    private const KEY = 'trackers_configured';

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
        $trackers = $this->manager->trackers();

        if ($trackers === []) {
            return new DoctorResult(self::KEY, self::PACKAGE, 'warning', 'No analytics trackers have been configured yet.', false);
        }

        return new DoctorResult(self::KEY, self::PACKAGE, 'passed', 'Analytics trackers are configured.', false);
    }
}
