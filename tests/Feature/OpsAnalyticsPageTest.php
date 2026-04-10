<?php

declare(strict_types=1);

use Filament\Schemas\Components\Actions as ActionsComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Widgets\StatsOverviewWidget\Stat;
use YezzMedia\OpsAnalytics\Doctor\AnalyticsStoreReadyCheck;
use YezzMedia\OpsAnalytics\Doctor\ConsentIntegrationReadyCheck;
use YezzMedia\OpsAnalytics\Doctor\DispatchDeliveryHealthyCheck;
use YezzMedia\OpsAnalytics\Doctor\TrackersConfiguredCheck;
use YezzMedia\OpsAnalytics\Filament\Pages\OpsAnalyticsPage;
use YezzMedia\OpsAnalytics\Filament\Pages\TrackerDetailsPage;
use YezzMedia\OpsAnalytics\Filament\Widgets\OpsAnalyticsOverviewWidget;
use YezzMedia\OpsAnalytics\Models\OpsAnalyticsDispatch;
use YezzMedia\OpsAnalytics\Models\OpsAnalyticsDispatchAttempt;
use YezzMedia\OpsAnalytics\Models\OpsAnalyticsTracker;
use YezzMedia\OpsAnalytics\Support\OpsAnalyticsManager;
use YezzMedia\OpsAnalytics\Testing\Fixtures\TestOpsAnalyticsUser;

beforeEach(function (): void {
    auth()->guard('web')->login(TestOpsAnalyticsUser::fixture([
        'ops.analytics.view',
        'ops.analytics.manage',
    ]));

    $alpha = OpsAnalyticsTracker::query()->create([
        'tracker_key' => 'plausible',
        'name' => 'Plausible',
        'driver' => 'plausible',
        'lifecycle_status' => 'active',
        'is_enabled' => true,
        'consent_mode' => 'required',
        'configuration_summary' => ['workspace' => 'marketing'],
        'metadata' => ['region' => 'eu'],
    ]);

    OpsAnalyticsDispatch::query()->create([
        'ops_analytics_tracker_id' => $alpha->getKey(),
        'dispatch_key' => 'dispatch-alpha',
        'event_key' => 'page_view',
        'event_name' => 'Page View',
        'event_category' => 'navigation',
        'consent_status' => 'allowed',
        'delivery_status' => 'healthy',
        'queued_at' => now()->subMinutes(5),
        'dispatched_at' => now()->subMinutes(4),
        'completed_at' => now()->subMinutes(3),
        'last_success_at' => now()->subMinutes(3),
        'attempt_count' => 1,
    ]);

    $beta = OpsAnalyticsTracker::query()->create([
        'tracker_key' => 'gtm',
        'name' => 'Google Tag Manager',
        'driver' => 'gtm',
        'lifecycle_status' => 'active',
        'is_enabled' => true,
        'consent_mode' => 'blocked',
        'configuration_summary' => ['container' => 'GTM-123'],
    ]);

    $failedDispatch = OpsAnalyticsDispatch::query()->create([
        'ops_analytics_tracker_id' => $beta->getKey(),
        'dispatch_key' => 'dispatch-beta',
        'event_key' => 'purchase',
        'event_name' => 'Purchase',
        'event_category' => 'commerce',
        'consent_status' => 'blocked',
        'delivery_status' => 'failed',
        'queued_at' => now()->subMinutes(20),
        'completed_at' => now()->subMinutes(18),
        'last_failure_at' => now()->subMinutes(18),
        'attempt_count' => 2,
        'failure_summary' => 'Tracker provider returned 500.',
    ]);

    OpsAnalyticsDispatchAttempt::query()->create([
        'ops_analytics_dispatch_id' => $failedDispatch->getKey(),
        'attempt_number' => 2,
        'status' => 'failed',
        'occurred_at' => now()->subMinutes(18),
        'response_code' => 500,
        'failure_summary' => 'Tracker provider returned 500.',
    ]);
});

it('builds the ops analytics page schema', function (): void {
    $page = app(OpsAnalyticsPage::class);
    $schema = $page->content(Schema::make($page));
    $components = $schema->getComponents(withActions: false, withHidden: true);

    expect($components)->toHaveCount(6)
        ->and($components[0])->toBeInstanceOf(Section::class)
        ->and($components[0]->getHeading())->toBe('Overview')
        ->and($components[1])->toBeInstanceOf(Section::class)
        ->and($components[1]->getHeading())->toBe('Recent Dispatch Activity')
        ->and($components[2])->toBeInstanceOf(Section::class)
        ->and($components[2]->getHeading())->toBe('Tracker Inventory')
        ->and($components[5])->toBeInstanceOf(ActionsComponent::class)
        ->and(Closure::bind(fn (): array => $this->getHeaderWidgets(), $page, OpsAnalyticsPage::class)())
        ->toBe([OpsAnalyticsOverviewWidget::class]);
});

it('builds analytics overview stats for tracker posture and dispatch activity', function (): void {
    $widget = app(OpsAnalyticsOverviewWidget::class);

    /** @var array<Stat> $stats */
    $stats = Closure::bind(
        fn (): array => $this->getStats(),
        $widget,
        OpsAnalyticsOverviewWidget::class,
    )();

    expect($stats)->toHaveCount(5)
        ->and($stats[0]->getLabel())->toBe('Trackers')
        ->and($stats[0]->getValue())->toBe(2)
        ->and($stats[1]->getLabel())->toBe('Recent dispatches')
        ->and($stats[1]->getValue())->toBe(2)
        ->and($stats[4]->getLabel())->toBe('Consent blocked')
        ->and($stats[4]->getValue())->toBe(1)
        ->and($stats[1]->getChart())->not->toBe([]);
});

it('returns the expected analytics summary and detail records', function (): void {
    $manager = app(OpsAnalyticsManager::class);
    $summary = $manager->summary();
    $tracker = $manager->tracker('plausible');

    expect(count($summary->trackers))->toBe(2)
        ->and($summary->healthyCount)->toBe(1)
        ->and($summary->failingCount)->toBe(1)
        ->and($summary->blockedCount)->toBe(1)
        ->and($tracker)->not->toBeNull()
        ->and($tracker?->name)->toBe('Plausible')
        ->and($tracker?->summary)->toBe('Tracker delivery posture is healthy.')
        ->and($manager->dispatchesFor('plausible'))->toHaveCount(1)
        ->and($manager->consentGateFor('gtm')->status->value)->toBe('blocked')
        ->and($manager->failures())->toHaveCount(1);
});

it('builds the analytics tracker details page schema for a tracked tracker', function (): void {
    $page = app(TrackerDetailsPage::class);
    $page->tracker = 'plausible';

    $schema = $page->content(Schema::make($page));
    $components = $schema->getComponents();

    expect($page->getTitle())->toBe('Analytics Tracker Detail: Plausible')
        ->and($components)->toHaveCount(4)
        ->and($components[0]->getHeading())->toBe('Analytics Tracker Summary')
        ->and($components[3]->getHeading())->toBe('Failure History');
});

it('shows a fallback detail message for an unknown analytics tracker', function (): void {
    $page = app(TrackerDetailsPage::class);
    $page->tracker = 'missing-tracker';

    $schema = $page->content(Schema::make($page));
    $components = $schema->getComponents();

    expect($page->getTitle())->toBe('Analytics Tracker Detail')
        ->and($components)->toHaveCount(1)
        ->and($components[0]->getHeading())->toBe('Analytics Tracker Summary');
});

it('reports doctor results from the seeded analytics state', function (): void {
    $storeCheck = app(AnalyticsStoreReadyCheck::class)->run();
    $trackersCheck = app(TrackersConfiguredCheck::class)->run();
    $deliveryCheck = app(DispatchDeliveryHealthyCheck::class)->run();
    $consentCheck = app(ConsentIntegrationReadyCheck::class)->run();

    expect($storeCheck->status)->toBe('passed')
        ->and($trackersCheck->status)->toBe('passed')
        ->and($deliveryCheck->status)->toBe('failed')
        ->and($consentCheck->status)->toBe('passed');
});
