<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use YezzMedia\Foundation\Data\InstallContext;
use YezzMedia\OpsAnalytics\Install\ConfigureDefaultRuntimeTrackerInstallStep;
use YezzMedia\OpsAnalytics\Install\ConfigureOpsAnalyticsAuditInstallStep;
use YezzMedia\OpsAnalytics\Install\EnsureOpsAnalyticsStoreReadyInstallStep;
use YezzMedia\OpsAnalytics\Models\OpsAnalyticsTracker;
use YezzMedia\OpsAnalytics\Support\DefaultRuntimeTracker;
use YezzMedia\OpsAnalytics\Support\OpsAnalyticsStoreSetup;

it('exposes the package migration path', function (): void {
    $store = app(OpsAnalyticsStoreSetup::class);

    expect($store->migrationPath())->toBe('/home/yezz/Developement/packages/laravel-ops-analytics/database/migrations');
});

it('detects a partial analytics store', function (): void {
    Schema::dropIfExists('ops_analytics_dispatch_attempts');

    $store = app(OpsAnalyticsStoreSetup::class);

    expect($store->hasPartialTables())->toBeTrue()
        ->and($store->storeReady())->toBeFalse()
        ->and($store->missingTables())->toBe(['ops_analytics_dispatch_attempts']);
});

it('fails the ensure-store step when migrations are disabled on a partial store', function (): void {
    config()->set('ops-analytics.migrations.enabled', false);
    Schema::dropIfExists('ops_analytics_dispatch_attempts');

    $step = app(EnsureOpsAnalyticsStoreReadyInstallStep::class);

    expect(fn () => $step->handle(new InstallContext(allowMigrations: false)))->toThrow(
        RuntimeException::class,
        'Ops analytics store has a partial table set. Resolve the partial state before continuing.',
    );
});

it('configures the default runtime tracker through the install step when the store is ready', function (): void {
    $step = app(ConfigureDefaultRuntimeTrackerInstallStep::class);

    expect($step->shouldRun(new InstallContext))->toBeTrue();

    $step->handle(new InstallContext);

    $tracker = OpsAnalyticsTracker::query()->where('tracker_key', 'default-runtime')->first();

    expect($tracker)->not->toBeNull()
        ->and($tracker?->getAttribute('driver'))->toBe('default-runtime')
        ->and($tracker?->getAttribute('is_enabled'))->toBeTrue()
        ->and($tracker?->getAttribute('metadata')['default_tracker'])->toBeTrue()
        ->and($tracker?->getAttribute('configuration_summary'))->toBe(app(DefaultRuntimeTracker::class)->configurationSummary());
});

it('accepts an already configured analytics audit driver in the published host config', function (): void {
    $path = config_path('ops-analytics.php');

    File::ensureDirectoryExists(dirname($path));

    File::put($path, <<<'PHP'
<?php

return [
    'audit' => [
        'driver' => env('OPS_ANALYTICS_AUDIT_DRIVER', 'activitylog'),
    ],
];
PHP);

    $step = app(ConfigureOpsAnalyticsAuditInstallStep::class);

    $step->handle(new InstallContext(auditPackages: ['yezzmedia/laravel-ops-analytics']));

    expect(File::get($path))
        ->toContain("'driver' => env('OPS_ANALYTICS_AUDIT_DRIVER', 'activitylog'),");
});

it('publishes the host config before configuring the analytics audit driver', function (): void {
    $path = config_path('ops-analytics.php');

    File::delete($path);
    File::ensureDirectoryExists(dirname($path));

    $step = app(ConfigureOpsAnalyticsAuditInstallStep::class);

    $step->handle(new InstallContext(auditPackages: ['yezzmedia/laravel-ops-analytics']));

    expect($path)
        ->toBeFile()
        ->and(File::get($path))
        ->toContain("'driver' => env('OPS_ANALYTICS_AUDIT_DRIVER', 'activitylog'),");
});
