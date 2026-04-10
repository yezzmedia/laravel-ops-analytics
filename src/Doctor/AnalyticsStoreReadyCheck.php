<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Doctor;

use YezzMedia\Foundation\Data\DoctorResult;
use YezzMedia\Foundation\Doctor\DoctorCheck;
use YezzMedia\OpsAnalytics\Support\OpsAnalyticsStoreSetup;

final class AnalyticsStoreReadyCheck implements DoctorCheck
{
    private const KEY = 'analytics_store_ready';

    private const PACKAGE = 'yezzmedia/laravel-ops-analytics';

    public function __construct(private readonly OpsAnalyticsStoreSetup $storeSetup) {}

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
        if ($this->storeSetup->storeReady()) {
            return new DoctorResult(self::KEY, self::PACKAGE, 'passed', 'Ops analytics store is ready.', false);
        }

        return new DoctorResult(
            self::KEY,
            self::PACKAGE,
            'failed',
            'Ops analytics store is missing required tables.',
            false,
            ['missing_tables' => $this->storeSetup->missingTables()],
        );
    }
}
