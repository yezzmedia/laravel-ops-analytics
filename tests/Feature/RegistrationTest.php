<?php

declare(strict_types=1);

use YezzMedia\Foundation\Registry\FeatureRegistry;
use YezzMedia\Foundation\Registry\HttpMiddlewareRegistry;
use YezzMedia\Foundation\Registry\OpsModuleRegistry;
use YezzMedia\Foundation\Registry\PackageRegistry;
use YezzMedia\Foundation\Registry\PermissionRegistry;
use YezzMedia\OpsAnalytics\OpsAnalyticsPlatformPackage;

it('registers the ops analytics package surface', function (): void {
    expect(app(PackageRegistry::class)->has('yezzmedia/laravel-ops-analytics'))->toBeTrue()
        ->and(app(PermissionRegistry::class)->forPackage('yezzmedia/laravel-ops-analytics')->pluck('name')->all())->toBe([
            'ops.analytics.view',
            'ops.analytics.manage',
        ])
        ->and(app(FeatureRegistry::class)->forPackage('yezzmedia/laravel-ops-analytics')->pluck('name')->all())->toBe([
            'analytics.dispatch',
            'analytics.delivery_posture',
            'analytics.tracker_health',
            'analytics.consent_gating',
        ])
        ->and(app(OpsModuleRegistry::class)->forPackage('yezzmedia/laravel-ops-analytics')->pluck('key')->all())->toBe([
            'diagnostics.analytics.overview',
            'diagnostics.analytics.detail',
        ])
        ->and(app(HttpMiddlewareRegistry::class)->forPackage('yezzmedia/laravel-ops-analytics')->pluck('key')->all())->toBe([
            'analytics.capture_request',
        ]);
});

it('describes the approved ops analytics package surface', function (): void {
    $package = new OpsAnalyticsPlatformPackage;

    expect($package->metadata()->name)->toBe('yezzmedia/laravel-ops-analytics')
        ->and($package->permissionDefinitions())->toHaveCount(2)
        ->and($package->featureDefinitions())->toHaveCount(4)
        ->and($package->auditEventDefinitions())->toHaveCount(3)
        ->and($package->installSteps())->toHaveCount(4)
        ->and($package->httpMiddlewareDefinitions())->toHaveCount(1)
        ->and($package->doctorChecks())->toHaveCount(4)
        ->and($package->opsModuleDefinitions())->toHaveCount(2);
});
