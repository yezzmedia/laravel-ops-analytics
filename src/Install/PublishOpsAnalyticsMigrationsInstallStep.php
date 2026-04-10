<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Install;

use YezzMedia\Foundation\Data\InstallContext;
use YezzMedia\Foundation\Install\InstallStep;
use YezzMedia\OpsAnalytics\Support\OpsAnalyticsStoreSetup;

final class PublishOpsAnalyticsMigrationsInstallStep implements InstallStep
{
    public function __construct(private readonly OpsAnalyticsStoreSetup $storeSetup) {}

    public function key(): string
    {
        return 'publish_ops_analytics_migrations';
    }

    public function package(): string
    {
        return 'yezzmedia/laravel-ops-analytics';
    }

    public function priority(): int
    {
        return 210;
    }

    public function shouldRun(InstallContext $context): bool
    {
        return ! $this->storeSetup->storeReady();
    }

    public function handle(InstallContext $context): void
    {
        // Migrations are registered through package tools during provider boot.
    }
}
