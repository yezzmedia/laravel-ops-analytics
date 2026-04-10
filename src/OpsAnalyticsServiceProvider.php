<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Spatie\Activitylog\Support\ActivityLogger;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use YezzMedia\Foundation\Support\PlatformPackageRegistrar;
use YezzMedia\OpsAnalytics\Actions\RecordDispatchOutcomeAction;
use YezzMedia\OpsAnalytics\Actions\RefreshAnalyticsPostureAction;
use YezzMedia\OpsAnalytics\Actions\TrackEventAction;
use YezzMedia\OpsAnalytics\Actions\UpsertTrackerAction;
use YezzMedia\OpsAnalytics\Contracts\OpsAnalyticsAuditWriter;
use YezzMedia\OpsAnalytics\Doctor\AnalyticsStoreReadyCheck;
use YezzMedia\OpsAnalytics\Doctor\ConsentIntegrationReadyCheck;
use YezzMedia\OpsAnalytics\Doctor\DispatchDeliveryHealthyCheck;
use YezzMedia\OpsAnalytics\Doctor\TrackersConfiguredCheck;
use YezzMedia\OpsAnalytics\Events\AnalyticsPostureRefreshed;
use YezzMedia\OpsAnalytics\Events\DispatchOutcomeRecorded;
use YezzMedia\OpsAnalytics\Events\TrackerConfigurationUpdated;
use YezzMedia\OpsAnalytics\Http\Middleware\CaptureAnalyticsRequest;
use YezzMedia\OpsAnalytics\Install\ConfigureDefaultRuntimeTrackerInstallStep;
use YezzMedia\OpsAnalytics\Install\ConfigureOpsAnalyticsAuditInstallStep;
use YezzMedia\OpsAnalytics\Install\EnsureOpsAnalyticsStoreReadyInstallStep;
use YezzMedia\OpsAnalytics\Install\PublishOpsAnalyticsMigrationsInstallStep;
use YezzMedia\OpsAnalytics\Listeners\OpsAnalyticsAuditListener;
use YezzMedia\OpsAnalytics\Support\ActivityLogOpsAnalyticsAuditWriter;
use YezzMedia\OpsAnalytics\Support\ConsentAwareTracker;
use YezzMedia\OpsAnalytics\Support\ConsentGateResolver;
use YezzMedia\OpsAnalytics\Support\DefaultRuntimeTracker;
use YezzMedia\OpsAnalytics\Support\DeliveryFailureResolver;
use YezzMedia\OpsAnalytics\Support\DispatchPostureResolver;
use YezzMedia\OpsAnalytics\Support\NullOpsAnalyticsAuditWriter;
use YezzMedia\OpsAnalytics\Support\OpsAnalyticsManager;
use YezzMedia\OpsAnalytics\Support\OpsAnalyticsStoreSetup;
use YezzMedia\OpsAnalytics\Support\TrackerHealthResolver;
use YezzMedia\OpsAnalytics\Support\TrackerInventoryResolver;
use YezzMedia\OpsAnalytics\Support\TrackerRegistry;

class OpsAnalyticsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-ops-analytics')
            ->hasConfigFile('ops-analytics')
            ->hasMigrations(['0001_create_ops_analytics_tables']);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(OpsAnalyticsAuditWriter::class, fn (): OpsAnalyticsAuditWriter => $this->makeAuditWriter());

        $this->app->singleton(OpsAnalyticsStoreSetup::class);
        $this->app->singleton(TrackerRegistry::class);
        $this->app->singleton(ConsentAwareTracker::class);
        $this->app->singleton(DefaultRuntimeTracker::class);
        $this->app->singleton(TrackerInventoryResolver::class, fn (): TrackerInventoryResolver => new TrackerInventoryResolver(
            $this->app->make(TrackerHealthResolver::class),
        ));
        $this->app->singleton(DispatchPostureResolver::class, fn (): DispatchPostureResolver => new DispatchPostureResolver(
            warningMinutes: (int) config('ops-analytics.delivery.warning_minutes', 15),
            failedMinutes: (int) config('ops-analytics.delivery.failed_minutes', 60),
        ));
        $this->app->singleton(ConsentGateResolver::class, fn (): ConsentGateResolver => new ConsentGateResolver(
            integrationRequired: (bool) config('ops-analytics.consent.integration_required', false),
            defaultSource: (string) config('ops-analytics.consent.default_source', 'config'),
        ));
        $this->app->singleton(DeliveryFailureResolver::class);
        $this->app->singleton(TrackerHealthResolver::class);

        $this->app->singleton(TrackEventAction::class);
        $this->app->singleton(UpsertTrackerAction::class);
        $this->app->singleton(RecordDispatchOutcomeAction::class);
        $this->app->singleton(RefreshAnalyticsPostureAction::class);
        $this->app->singleton(CaptureAnalyticsRequest::class, fn (): CaptureAnalyticsRequest => new CaptureAnalyticsRequest(
            $this->app->make(TrackEventAction::class),
            $this->app->make(TrackerRegistry::class),
            $this->app->make(OpsAnalyticsStoreSetup::class),
            $this->app->make(DefaultRuntimeTracker::class),
        ));

        $this->app->singleton(PublishOpsAnalyticsMigrationsInstallStep::class, fn (): PublishOpsAnalyticsMigrationsInstallStep => new PublishOpsAnalyticsMigrationsInstallStep($this->app->make(OpsAnalyticsStoreSetup::class)));
        $this->app->singleton(EnsureOpsAnalyticsStoreReadyInstallStep::class, fn (): EnsureOpsAnalyticsStoreReadyInstallStep => new EnsureOpsAnalyticsStoreReadyInstallStep($this->app->make(OpsAnalyticsStoreSetup::class)));
        $this->app->singleton(ConfigureOpsAnalyticsAuditInstallStep::class);
        $this->app->singleton(ConfigureDefaultRuntimeTrackerInstallStep::class, fn (): ConfigureDefaultRuntimeTrackerInstallStep => new ConfigureDefaultRuntimeTrackerInstallStep(
            $this->app->make(OpsAnalyticsStoreSetup::class),
            $this->app->make(UpsertTrackerAction::class),
            $this->app->make(DefaultRuntimeTracker::class),
        ));

        $this->app->singleton(OpsAnalyticsManager::class, function (): OpsAnalyticsManager {
            return new OpsAnalyticsManager(
                trackerInventoryResolver: $this->app->make(TrackerInventoryResolver::class),
                dispatchPostureResolver: $this->app->make(DispatchPostureResolver::class),
                consentGateResolver: $this->app->make(ConsentGateResolver::class),
                deliveryFailureResolver: $this->app->make(DeliveryFailureResolver::class),
                trackerHealthResolver: $this->app->make(TrackerHealthResolver::class),
                cacheFactory: $this->app->make(CacheFactory::class),
                cacheEnabled: (bool) config('ops-analytics.cache.enabled', true),
                cacheStore: config('ops-analytics.cache.store'),
                cacheTtl: (int) config('ops-analytics.cache.ttl', 300),
                excludeUnsupportedFromAggregation: (bool) config('ops-analytics.unsupported.exclude_from_aggregation', false),
            );
        });

        $this->app->singleton(AnalyticsStoreReadyCheck::class, fn (): AnalyticsStoreReadyCheck => new AnalyticsStoreReadyCheck($this->app->make(OpsAnalyticsStoreSetup::class)));
        $this->app->singleton(TrackersConfiguredCheck::class, fn (): TrackersConfiguredCheck => new TrackersConfiguredCheck($this->app->make(OpsAnalyticsManager::class)));
        $this->app->singleton(DispatchDeliveryHealthyCheck::class, fn (): DispatchDeliveryHealthyCheck => new DispatchDeliveryHealthyCheck($this->app->make(OpsAnalyticsManager::class)));
        $this->app->singleton(ConsentIntegrationReadyCheck::class, fn (): ConsentIntegrationReadyCheck => new ConsentIntegrationReadyCheck($this->app->make(OpsAnalyticsManager::class)));
    }

    public function packageBooted(): void
    {
        $this->app->make(TrackerRegistry::class)->register($this->app->make(DefaultRuntimeTracker::class));
        $this->app->make(PlatformPackageRegistrar::class)->register(new OpsAnalyticsPlatformPackage);

        $this->registerAuditListeners();
    }

    private function registerAuditListeners(): void
    {
        Event::listen(AnalyticsPostureRefreshed::class, [OpsAnalyticsAuditListener::class, 'handleAnalyticsPostureRefreshed']);
        Event::listen(TrackerConfigurationUpdated::class, [OpsAnalyticsAuditListener::class, 'handleTrackerConfigurationUpdated']);
        Event::listen(DispatchOutcomeRecorded::class, [OpsAnalyticsAuditListener::class, 'handleDispatchOutcomeRecorded']);
    }

    private function makeAuditWriter(): OpsAnalyticsAuditWriter
    {
        $driver = config('ops-analytics.audit.driver');

        if ($driver === null) {
            return new NullOpsAnalyticsAuditWriter;
        }

        if ($driver !== 'activitylog') {
            throw new InvalidArgumentException(sprintf('Unsupported ops analytics audit driver [%s].', $driver));
        }

        if (! class_exists('Spatie\\Activitylog\\ActivitylogServiceProvider') || ! class_exists(ActivityLogger::class)) {
            throw new InvalidArgumentException('Ops analytics audit driver [activitylog] requires spatie/laravel-activitylog.');
        }

        return new ActivityLogOpsAnalyticsAuditWriter($this->app->make(ActivityLogger::class));
    }
}
