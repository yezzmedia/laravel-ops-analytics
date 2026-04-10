<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use YezzMedia\OpsAnalytics\Actions\RecordDispatchOutcomeAction;
use YezzMedia\OpsAnalytics\Actions\TrackEventAction;
use YezzMedia\OpsAnalytics\Actions\UpsertTrackerAction;
use YezzMedia\OpsAnalytics\Data\AnalyticsContext;
use YezzMedia\OpsAnalytics\Data\AnalyticsEvent;
use YezzMedia\OpsAnalytics\Enums\ConsentGateStatus;
use YezzMedia\OpsAnalytics\Events\DispatchOutcomeRecorded;
use YezzMedia\OpsAnalytics\Events\TrackerConfigurationUpdated;
use YezzMedia\OpsAnalytics\Http\Middleware\CaptureAnalyticsRequest;
use YezzMedia\OpsAnalytics\Jobs\DispatchAnalyticsEventJob;
use YezzMedia\OpsAnalytics\Models\OpsAnalyticsDispatch;
use YezzMedia\OpsAnalytics\Models\OpsAnalyticsDispatchAttempt;
use YezzMedia\OpsAnalytics\Models\OpsAnalyticsTracker;
use YezzMedia\OpsAnalytics\Support\DefaultRuntimeTracker;
use YezzMedia\OpsAnalytics\Support\TrackerRegistry;

use function Pest\Laravel\get;

it('registers the default runtime tracker in the runtime registry', function (): void {
    $tracker = app(TrackerRegistry::class)->find('default-runtime');

    expect($tracker)->toBeInstanceOf(DefaultRuntimeTracker::class)
        ->and($tracker?->driver())->toBe('default-runtime')
        ->and($tracker?->enabled())->toBeTrue();
});

it('upserts a tracker and dispatches the tracker-updated event', function (): void {
    Event::fake([TrackerConfigurationUpdated::class]);

    $tracker = app(UpsertTrackerAction::class)->execute([
        'tracker_key' => 'plausible',
        'name' => 'Plausible',
        'driver' => 'plausible',
        'lifecycle_status' => 'active',
        'is_enabled' => true,
        'consent_mode' => 'required',
        'configuration_summary' => [
            'workspace' => 'marketing',
            'site' => 'yezzmedia.com',
        ],
        'metadata' => [
            'region' => 'eu',
            'environment' => 'production',
        ],
    ], 'test');

    expect($tracker)->toBeInstanceOf(OpsAnalyticsTracker::class)
        ->and($tracker->getAttribute('tracker_key'))->toBe('plausible')
        ->and($tracker->getAttribute('configuration_summary')['site'])->toBe('yezzmedia.com')
        ->and($tracker->getAttribute('metadata')['environment'])->toBe('production');

    Event::assertDispatched(TrackerConfigurationUpdated::class, fn (TrackerConfigurationUpdated $event): bool => $event->trackerKey === 'plausible' && $event->source === 'test');
});

it('rejects forbidden sensitive keys in tracker metadata by default', function (): void {
    expect(fn () => app(UpsertTrackerAction::class)->execute([
        'tracker_key' => 'bad-tracker',
        'name' => 'Bad Tracker',
        'driver' => 'demo',
        'lifecycle_status' => 'active',
        'is_enabled' => true,
        'metadata' => [
            'nested' => [
                'token' => 'secret',
            ],
        ],
    ]))->toThrow(ValidationException::class);
});

it('rejects forbidden sensitive keys in dispatch metadata by default', function (): void {
    Event::fake([DispatchOutcomeRecorded::class]);

    OpsAnalyticsTracker::query()->create([
        'tracker_key' => 'plausible',
        'name' => 'Plausible',
        'driver' => 'plausible',
        'lifecycle_status' => 'active',
        'is_enabled' => true,
    ]);

    expect(fn () => app(RecordDispatchOutcomeAction::class)->execute('plausible', [
        'dispatch_key' => 'dispatch-001',
        'event_key' => 'page_view',
        'event_name' => 'Page View',
        'delivery_status' => 'healthy',
        'attempt_count' => 2,
        'completed_at' => now()->toIso8601String(),
        'metadata' => [
            'request' => 'raw-payload',
            'safe' => 'value',
        ],
        'attempt' => [
            'attempt_number' => 2,
            'status' => 'healthy',
            'metadata' => [
                'authorization' => 'Bearer secret',
                'safe' => 'ok',
            ],
        ],
    ], 'test'))->toThrow(ValidationException::class);
});

it('records a dispatch outcome with safe metadata', function (): void {
    Event::fake([DispatchOutcomeRecorded::class]);

    OpsAnalyticsTracker::query()->create([
        'tracker_key' => 'plausible',
        'name' => 'Plausible',
        'driver' => 'plausible',
        'lifecycle_status' => 'active',
        'is_enabled' => true,
    ]);

    $dispatch = app(RecordDispatchOutcomeAction::class)->execute('plausible', [
        'dispatch_key' => 'dispatch-002',
        'event_key' => 'signup',
        'event_name' => 'Signup',
        'delivery_status' => 'healthy',
        'attempt_count' => 2,
        'completed_at' => now()->toIso8601String(),
        'metadata' => [
            'channel' => 'web',
            'safe' => 'value',
        ],
        'attempt' => [
            'attempt_number' => 2,
            'status' => 'healthy',
            'metadata' => [
                'transport' => 'queue',
                'safe' => 'ok',
            ],
        ],
    ], 'test');

    $attempt = OpsAnalyticsDispatchAttempt::query()->where('ops_analytics_dispatch_id', $dispatch->getKey())->first();

    expect($dispatch)->toBeInstanceOf(OpsAnalyticsDispatch::class)
        ->and($dispatch->getAttribute('metadata')['channel'])->toBe('web')
        ->and($attempt)->not->toBeNull()
        ->and($attempt?->getAttribute('metadata')['transport'])->toBe('queue');

    Event::assertDispatched(DispatchOutcomeRecorded::class, fn (DispatchOutcomeRecorded $event): bool => $event->dispatchKey === 'dispatch-002' && $event->attemptCount === 2 && $event->source === 'test');
});

it('dispatches analytics jobs synchronously when sync fallback is enabled', function (): void {
    Bus::fake();
    config()->set('ops-analytics.dispatch.sync_fallback', true);

    app(TrackEventAction::class)->execute(
        new AnalyticsEvent(
            eventKey: 'page_view',
            name: 'Page View',
            category: 'navigation',
            occurredAt: now()->toIso8601String(),
            subjectType: 'page',
            subjectKey: 'dashboard',
            properties: [],
        ),
        new AnalyticsContext(
            trackerKey: 'plausible',
            actorType: 'user',
            actorKey: '1',
            sessionKey: 'session-1',
            consentStatus: ConsentGateStatus::Allowed,
            metadata: [],
        ),
    );

    Bus::assertDispatchedSync(DispatchAnalyticsEventJob::class);
});

it('captures request metadata through the analytics middleware for the default tracker', function (): void {
    config()->set('ops-analytics.dispatch.sync_fallback', true);

    app(UpsertTrackerAction::class)->execute([
        'tracker_key' => 'default-runtime',
        'name' => 'Default Runtime Tracker',
        'driver' => 'default-runtime',
        'lifecycle_status' => 'active',
        'is_enabled' => true,
        'consent_mode' => 'optional',
        'configuration_summary' => app(DefaultRuntimeTracker::class)->configurationSummary(),
        'metadata' => [
            'default_tracker' => true,
            'source' => 'test',
        ],
    ], 'test');

    Route::middleware(['web', CaptureAnalyticsRequest::class])->get('/ops-analytics-test', function () {
        return response()->json(['ok' => true]);
    })->name('ops.analytics.test');

    get('/ops-analytics-test?filter=recent', [
        'User-Agent' => 'OpsAnalyticsTest/1.0',
        'Referer' => 'https://example.com/previous',
        'Cookie' => 'analytics_consent=allowed',
    ])
        ->assertOk();

    $dispatch = OpsAnalyticsDispatch::query()
        ->where('event_key', 'http.request.completed')
        ->whereHas('tracker', fn ($query) => $query->where('tracker_key', 'default-runtime'))
        ->latest('completed_at')
        ->first();

    /** @var array<string, mixed>|null $metadata */
    $metadata = $dispatch?->getAttribute('metadata');
    /** @var array<string, mixed> $context */
    $context = is_array($metadata['context'] ?? null) ? $metadata['context'] : [];

    expect($dispatch)->not->toBeNull()
        ->and($dispatch?->getAttribute('delivery_status'))->toBe('healthy')
        ->and($metadata['category'] ?? null)->toBe('runtime')
        ->and($context['request_method'] ?? null)->toBe('GET')
        ->and($context['route_name'] ?? null)->toBe('ops.analytics.test')
        ->and($context['actor_hash'] ?? null)->toBeNull()
        ->and(array_key_exists('session_hash', $context))->toBeTrue()
        ->and($context['ip_hash'] ?? null)->not->toBeNull()
        ->and($metadata['subject_key'] ?? null)->toBe('ops.analytics.test');
});
