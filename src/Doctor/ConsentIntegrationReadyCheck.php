<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Doctor;

use YezzMedia\Foundation\Data\DoctorResult;
use YezzMedia\Foundation\Doctor\DoctorCheck;
use YezzMedia\OpsAnalytics\Enums\ConsentGateStatus;
use YezzMedia\OpsAnalytics\Support\OpsAnalyticsManager;

final class ConsentIntegrationReadyCheck implements DoctorCheck
{
    private const KEY = 'consent_integration_ready';

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
        $records = $this->manager->consentGates();

        if ($records === []) {
            return new DoctorResult(self::KEY, self::PACKAGE, 'warning', 'No consent-gate analytics metadata is available yet.', false);
        }

        $statuses = array_map(fn ($record) => $record->status, $records);

        if (in_array(ConsentGateStatus::Unknown, $statuses, true)) {
            return new DoctorResult(self::KEY, self::PACKAGE, 'warning', 'Consent integration cannot be fully evaluated from the current analytics metadata.', false);
        }

        if (in_array(ConsentGateStatus::Unsupported, $statuses, true)) {
            return new DoctorResult(self::KEY, self::PACKAGE, 'warning', 'Consent integration is unsupported for one or more analytics trackers.', false);
        }

        return new DoctorResult(self::KEY, self::PACKAGE, 'passed', 'Consent-aware analytics gating is available.', false);
    }
}
