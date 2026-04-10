<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Install;

use RuntimeException;
use YezzMedia\Foundation\Data\InstallContext;
use YezzMedia\Foundation\Install\InstallStep;
use YezzMedia\OpsAnalytics\Support\OpsAnalyticsStoreSetup;

final class EnsureOpsAnalyticsStoreReadyInstallStep implements InstallStep
{
    public function __construct(private readonly OpsAnalyticsStoreSetup $storeSetup) {}

    public function key(): string
    {
        return 'ensure_ops_analytics_store_ready';
    }

    public function package(): string
    {
        return 'yezzmedia/laravel-ops-analytics';
    }

    public function priority(): int
    {
        return 220;
    }

    public function shouldRun(InstallContext $context): bool
    {
        return ! $this->storeSetup->storeReady();
    }

    public function handle(InstallContext $context): void
    {
        if ($this->storeSetup->hasPartialTables()) {
            throw new RuntimeException('Ops analytics store has a partial table set. Resolve the partial state before continuing.');
        }

        if (! $context->allowMigrations) {
            throw new RuntimeException('Ops analytics store is not ready and migrations are disabled for this install run.');
        }

        $this->storeSetup->runMigrations();

        if (! $this->storeSetup->storeReady()) {
            throw new RuntimeException('Ops analytics store is still not ready after running package migrations.');
        }
    }
}
