<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Install;

use RuntimeException;
use YezzMedia\Foundation\Data\InstallContext;
use YezzMedia\Foundation\Install\AuditInstallStep;
use YezzMedia\Foundation\Install\OptionalInstallStep;

final class ConfigureOpsAnalyticsAuditInstallStep implements AuditInstallStep, OptionalInstallStep
{
    public function key(): string
    {
        return 'configure_ops_analytics_audit';
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
        return $context->shouldConfigureAuditFor('yezzmedia/laravel-ops-analytics');
    }

    public function handle(InstallContext $context): void
    {
        $path = dirname(__DIR__, 2).'/config/ops-analytics.php';

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Ops analytics config file could not be read for audit configuration.');
        }

        $config = file_get_contents($path);

        if ($config === false) {
            throw new RuntimeException('Ops analytics config file could not be loaded for audit configuration.');
        }

        $needle = "'driver' => env('OPS_ANALYTICS_AUDIT_DRIVER'),";

        if (! str_contains($config, $needle)) {
            throw new RuntimeException('Ops analytics config file is missing the expected audit driver placeholder.');
        }

        $updated = str_replace($needle, "'driver' => env('OPS_ANALYTICS_AUDIT_DRIVER', 'activitylog'),", $config);

        if (file_put_contents($path, $updated) === false) {
            throw new RuntimeException('Ops analytics config file could not be updated for audit configuration.');
        }
    }

    public function isOptional(): bool
    {
        return true;
    }
}
