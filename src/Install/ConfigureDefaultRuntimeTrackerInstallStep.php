<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Install;

use YezzMedia\Foundation\Data\InstallContext;
use YezzMedia\Foundation\Install\InstallStep;
use YezzMedia\OpsAnalytics\Actions\UpsertTrackerAction;
use YezzMedia\OpsAnalytics\Support\DefaultRuntimeTracker;
use YezzMedia\OpsAnalytics\Support\OpsAnalyticsStoreSetup;

final class ConfigureDefaultRuntimeTrackerInstallStep implements InstallStep
{
    public function __construct(
        private readonly OpsAnalyticsStoreSetup $storeSetup,
        private readonly UpsertTrackerAction $upsertTracker,
        private readonly DefaultRuntimeTracker $tracker,
    ) {}

    public function key(): string
    {
        return 'configure_default_runtime_tracker';
    }

    public function package(): string
    {
        return 'yezzmedia/laravel-ops-analytics';
    }

    public function priority(): int
    {
        return 230;
    }

    public function shouldRun(InstallContext $context): bool
    {
        return $this->tracker->enabled() && $this->storeSetup->storeReady();
    }

    public function handle(InstallContext $context): void
    {
        $this->upsertTracker->execute([
            'tracker_key' => $this->tracker->key(),
            'name' => $this->tracker->name(),
            'driver' => $this->tracker->driver(),
            'lifecycle_status' => 'active',
            'is_enabled' => true,
            'consent_mode' => $this->tracker->consentMode(),
            'configuration_summary' => $this->tracker->configurationSummary(),
            'metadata' => [
                'default_tracker' => true,
                'source' => 'install',
                'technical_only' => true,
            ],
        ], 'install');
    }
}
