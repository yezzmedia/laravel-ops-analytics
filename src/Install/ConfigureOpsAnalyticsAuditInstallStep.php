<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Install;

use RuntimeException;
use YezzMedia\Foundation\Data\InstallContext;
use YezzMedia\Foundation\Install\AuditInstallStep;
use YezzMedia\Foundation\Install\OptionalInstallStep;

final class ConfigureOpsAnalyticsAuditInstallStep implements AuditInstallStep, OptionalInstallStep
{
    private const DRIVER_WITHOUT_DEFAULT = "'driver' => env('OPS_ANALYTICS_AUDIT_DRIVER'),";

    private const DRIVER_WITH_ACTIVITYLOG_DEFAULT = "'driver' => env('OPS_ANALYTICS_AUDIT_DRIVER', 'activitylog'),";

    private const PACKAGE_CONFIG_PATH = __DIR__.'/../../config/ops-analytics.php';

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
        $path = config_path('ops-analytics.php');

        if (! is_file($path)) {
            $this->publishConfig($path);
        }

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Ops analytics config file could not be read for audit configuration.');
        }

        $config = file_get_contents($path);

        if ($config === false) {
            throw new RuntimeException('Ops analytics config file could not be loaded for audit configuration.');
        }

        if (str_contains($config, self::DRIVER_WITH_ACTIVITYLOG_DEFAULT)) {
            return;
        }

        if (! str_contains($config, self::DRIVER_WITHOUT_DEFAULT)) {
            throw new RuntimeException('Ops analytics config file is missing the expected audit driver placeholder.');
        }

        $updated = str_replace(self::DRIVER_WITHOUT_DEFAULT, self::DRIVER_WITH_ACTIVITYLOG_DEFAULT, $config);

        if (file_put_contents($path, $updated) === false) {
            throw new RuntimeException('Ops analytics config file could not be updated for audit configuration.');
        }
    }

    public function isOptional(): bool
    {
        return true;
    }

    private function publishConfig(string $path): void
    {
        if (! is_file(self::PACKAGE_CONFIG_PATH) || ! is_readable(self::PACKAGE_CONFIG_PATH)) {
            throw new RuntimeException('Ops analytics package config file could not be read for audit configuration.');
        }

        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Ops analytics config directory could not be created for audit configuration.');
        }

        $config = file_get_contents(self::PACKAGE_CONFIG_PATH);

        if ($config === false) {
            throw new RuntimeException('Ops analytics package config file could not be loaded for audit configuration.');
        }

        if (file_put_contents($path, $config) === false) {
            throw new RuntimeException('Ops analytics config file could not be published for audit configuration.');
        }
    }
}
