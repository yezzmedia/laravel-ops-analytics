<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics;

use YezzMedia\Foundation\Contracts\DefinesAuditEvents;
use YezzMedia\Foundation\Contracts\DefinesHttpMiddleware;
use YezzMedia\Foundation\Contracts\DefinesInstallSteps;
use YezzMedia\Foundation\Contracts\DefinesPermissions;
use YezzMedia\Foundation\Contracts\PlatformPackage;
use YezzMedia\Foundation\Contracts\ProvidesDoctorChecks;
use YezzMedia\Foundation\Contracts\ProvidesOpsModules;
use YezzMedia\Foundation\Contracts\RegistersFeatures;
use YezzMedia\Foundation\Data\AuditEventDefinition;
use YezzMedia\Foundation\Data\FeatureDefinition;
use YezzMedia\Foundation\Data\HttpMiddlewareDefinition;
use YezzMedia\Foundation\Data\OpsModuleDefinition;
use YezzMedia\Foundation\Data\PackageMetadata;
use YezzMedia\Foundation\Data\PermissionDefinition;
use YezzMedia\Foundation\Doctor\DoctorCheck;
use YezzMedia\Foundation\Install\InstallStep;
use YezzMedia\OpsAnalytics\Doctor\AnalyticsStoreReadyCheck;
use YezzMedia\OpsAnalytics\Doctor\ConsentIntegrationReadyCheck;
use YezzMedia\OpsAnalytics\Doctor\DispatchDeliveryHealthyCheck;
use YezzMedia\OpsAnalytics\Doctor\TrackersConfiguredCheck;
use YezzMedia\OpsAnalytics\Http\Middleware\CaptureAnalyticsRequest;
use YezzMedia\OpsAnalytics\Install\ConfigureDefaultRuntimeTrackerInstallStep;
use YezzMedia\OpsAnalytics\Install\ConfigureOpsAnalyticsAuditInstallStep;
use YezzMedia\OpsAnalytics\Install\EnsureOpsAnalyticsStoreReadyInstallStep;
use YezzMedia\OpsAnalytics\Install\PublishOpsAnalyticsMigrationsInstallStep;

final class OpsAnalyticsPlatformPackage implements DefinesAuditEvents, DefinesHttpMiddleware, DefinesInstallSteps, DefinesPermissions, PlatformPackage, ProvidesDoctorChecks, ProvidesOpsModules, RegistersFeatures
{
    public function metadata(): PackageMetadata
    {
        return new PackageMetadata(
            name: 'yezzmedia/laravel-ops-analytics',
            vendor: 'yezzmedia',
            description: 'Ops-facing analytics delivery posture, tracker health, and consent-aware dispatch package for the Yezz Media Laravel platform.',
            packageClass: self::class,
        );
    }

    /**
     * @return array<int, PermissionDefinition>
     */
    public function permissionDefinitions(): array
    {
        return [
            new PermissionDefinition(
                name: 'ops.analytics.view',
                package: 'yezzmedia/laravel-ops-analytics',
                label: 'View ops analytics',
                description: 'Allows viewing tracker inventory, delivery posture, consent-aware dispatch posture, and technical analytics failures.',
                defaultRoleHints: ['super-admin'],
            ),
            new PermissionDefinition(
                name: 'ops.analytics.manage',
                package: 'yezzmedia/laravel-ops-analytics',
                label: 'Manage ops analytics',
                description: 'Allows refreshing analytics posture and running package-owned technical analytics metadata actions.',
                defaultRoleHints: ['super-admin'],
            ),
        ];
    }

    /**
     * @return array<int, FeatureDefinition>
     */
    public function featureDefinitions(): array
    {
        return [
            new FeatureDefinition('analytics.dispatch', 'yezzmedia/laravel-ops-analytics', 'Analytics dispatch', 'Provides technical dispatch orchestration and package-owned dispatch metadata recording.'),
            new FeatureDefinition('analytics.delivery_posture', 'yezzmedia/laravel-ops-analytics', 'Delivery posture', 'Surfaces technical analytics delivery posture and delivery failures.'),
            new FeatureDefinition('analytics.tracker_health', 'yezzmedia/laravel-ops-analytics', 'Tracker health', 'Reports tracker configuration posture and technical tracker health states.'),
            new FeatureDefinition('analytics.consent_gating', 'yezzmedia/laravel-ops-analytics', 'Consent gating', 'Surfaces consent-aware dispatch gating state for technical analytics delivery.'),
        ];
    }

    /**
     * @return array<int, AuditEventDefinition>
     */
    public function auditEventDefinitions(): array
    {
        return [
            new AuditEventDefinition(
                key: 'ops.analytics.posture_refreshed',
                package: 'yezzmedia/laravel-ops-analytics',
                action: 'refreshed',
                subjectType: 'analytics_posture_snapshot',
                description: 'Ops analytics posture snapshot was refreshed.',
                severity: 'info',
                contextKeys: ['overall_status', 'healthy_count', 'warning_count', 'failing_count', 'unsupported_count', 'blocked_count', 'actor_id', 'source', 'completed_at'],
            ),
            new AuditEventDefinition(
                key: 'ops.analytics.tracker_updated',
                package: 'yezzmedia/laravel-ops-analytics',
                action: 'updated',
                subjectType: 'analytics_tracker',
                description: 'An analytics tracker metadata record was updated.',
                severity: 'info',
                contextKeys: ['tracker_key', 'driver', 'lifecycle_status', 'is_enabled', 'consent_mode', 'actor_id', 'source'],
            ),
            new AuditEventDefinition(
                key: 'ops.analytics.dispatch_recorded',
                package: 'yezzmedia/laravel-ops-analytics',
                action: 'recorded',
                subjectType: 'analytics_dispatch',
                description: 'A technical analytics dispatch outcome was recorded.',
                severity: 'info',
                contextKeys: ['tracker_key', 'dispatch_key', 'event_key', 'delivery_status', 'attempt_count', 'completed_at', 'actor_id', 'source'],
            ),
        ];
    }

    /**
     * @return array<int, InstallStep>
     */
    public function installSteps(): array
    {
        return [
            app(PublishOpsAnalyticsMigrationsInstallStep::class),
            app(EnsureOpsAnalyticsStoreReadyInstallStep::class),
            app(ConfigureOpsAnalyticsAuditInstallStep::class),
            app(ConfigureDefaultRuntimeTrackerInstallStep::class),
        ];
    }

    /**
     * @return array<int, HttpMiddlewareDefinition>
     */
    public function httpMiddlewareDefinitions(): array
    {
        return [
            new HttpMiddlewareDefinition(
                key: 'analytics.capture_request',
                package: 'yezzmedia/laravel-ops-analytics',
                middleware: CaptureAnalyticsRequest::class,
                kind: 'web_append',
                description: 'Captures technical request metadata for the built-in default runtime analytics tracker.',
            ),
        ];
    }

    /**
     * @return array<int, DoctorCheck>
     */
    public function doctorChecks(): array
    {
        return [
            app(AnalyticsStoreReadyCheck::class),
            app(TrackersConfiguredCheck::class),
            app(DispatchDeliveryHealthyCheck::class),
            app(ConsentIntegrationReadyCheck::class),
        ];
    }

    /**
     * @return array<int, OpsModuleDefinition>
     */
    public function opsModuleDefinitions(): array
    {
        return [
            new OpsModuleDefinition(
                key: 'diagnostics.analytics.overview',
                package: 'yezzmedia/laravel-ops-analytics',
                label: 'Analytics Overview',
                type: 'page',
                permissionHint: 'ops.analytics.view',
            ),
            new OpsModuleDefinition(
                key: 'diagnostics.analytics.detail',
                package: 'yezzmedia/laravel-ops-analytics',
                label: 'Analytics Tracker Detail',
                type: 'page',
                permissionHint: 'ops.analytics.view',
            ),
        ];
    }
}
