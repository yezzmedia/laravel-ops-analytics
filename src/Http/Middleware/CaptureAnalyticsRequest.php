<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use YezzMedia\OpsAnalytics\Actions\TrackEventAction;
use YezzMedia\OpsAnalytics\Data\AnalyticsContext;
use YezzMedia\OpsAnalytics\Data\AnalyticsEvent;
use YezzMedia\OpsAnalytics\Enums\ConsentGateStatus;
use YezzMedia\OpsAnalytics\Models\OpsAnalyticsTracker;
use YezzMedia\OpsAnalytics\Support\DefaultRuntimeTracker;
use YezzMedia\OpsAnalytics\Support\OpsAnalyticsStoreSetup;
use YezzMedia\OpsAnalytics\Support\TrackerRegistry;

final class CaptureAnalyticsRequest
{
    public function __construct(
        private readonly TrackEventAction $trackEvent,
        private readonly TrackerRegistry $trackers,
        private readonly OpsAnalyticsStoreSetup $storeSetup,
        private readonly DefaultRuntimeTracker $defaultTracker,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        try {
            /** @var Response $response */
            $response = $next($request);
        } catch (Throwable $throwable) {
            $this->capture($request, 500, $startedAt, true);

            throw $throwable;
        }

        $this->capture($request, $response->getStatusCode(), $startedAt, false);

        return $response;
    }

    private function capture(Request $request, int $responseStatus, float $startedAt, bool $failed): void
    {
        if (! (bool) config('ops-analytics.request_capture.enabled', true)) {
            return;
        }

        if (! $this->defaultTracker->enabled() || ! $this->storeSetup->storeReady()) {
            return;
        }

        if ($this->trackers->find($this->defaultTracker->key()) === null) {
            return;
        }

        if (! OpsAnalyticsTracker::query()->where('tracker_key', $this->defaultTracker->key())->exists()) {
            return;
        }

        try {
            $this->trackEvent->execute(
                new AnalyticsEvent(
                    eventKey: $failed ? 'http.request.failed' : 'http.request.completed',
                    name: $failed ? 'HTTP Request Failed' : 'HTTP Request Completed',
                    category: 'runtime',
                    occurredAt: now()->toIso8601String(),
                    subjectType: 'http_request',
                    subjectKey: (string) ($request->route()?->getName() ?? '/'.trim($request->path(), '/')),
                    properties: [
                        'request_method' => $request->getMethod(),
                        'request_path' => '/'.trim($request->path(), '/'),
                        'route_name' => $request->route()?->getName(),
                        'route_uri' => $request->route()?->uri(),
                        'response_status' => $responseStatus,
                        'duration_ms' => max(0, (int) round((microtime(true) - $startedAt) * 1000)),
                        'failed' => $failed,
                    ],
                ),
                new AnalyticsContext(
                    trackerKey: $this->defaultTracker->key(),
                    actorType: $request->user() === null ? null : class_basename($request->user()::class),
                    actorKey: $this->hashed($request->user()?->getAuthIdentifier()),
                    sessionKey: $this->hashed($request->hasSession() ? $request->session()->getId() : null),
                    consentStatus: $this->resolveConsentStatus($request),
                    metadata: [
                        'request_method' => $request->getMethod(),
                        'request_path' => '/'.trim($request->path(), '/'),
                        'route_name' => $request->route()?->getName(),
                        'route_uri' => $request->route()?->uri(),
                        'response_status' => $responseStatus,
                        'duration_ms' => max(0, (int) round((microtime(true) - $startedAt) * 1000)),
                        'request_host' => $request->getHost(),
                        'request_scheme' => $request->getScheme(),
                        'request_kind' => $request->header('X-Livewire') !== null ? 'livewire' : ($request->expectsJson() ? 'json' : 'web'),
                        'query_count' => count($request->query()),
                        'actor_hash' => $this->hashed($request->user()?->getAuthIdentifier()),
                        'session_hash' => $this->hashed($request->hasSession() ? $request->session()->getId() : null),
                        'ip_hash' => $this->hashed($request->ip()),
                        'user_agent_hash' => $this->hashed($request->userAgent()),
                        'referrer_hash' => $this->hashed($request->headers->get('referer')),
                    ],
                ),
            );
        } catch (Throwable) {
            return;
        }
    }

    private function hashed(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        return hash_hmac('sha256', $normalized, (string) config('app.key', 'ops-analytics'));
    }

    private function resolveConsentStatus(Request $request): ConsentGateStatus
    {
        $value = $request->headers->get('X-Analytics-Consent') ?? $request->cookies->get('analytics_consent');

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                'allowed', 'allow', 'granted', 'grant', 'yes', 'true' => ConsentGateStatus::Allowed,
                'blocked', 'block', 'denied', 'deny', 'no', 'false' => ConsentGateStatus::Blocked,
                'unsupported' => ConsentGateStatus::Unsupported,
                default => ConsentGateStatus::Unknown,
            };
        }

        if ((bool) config('ops-analytics.consent.integration_required', false)) {
            return ConsentGateStatus::Unknown;
        }

        return ConsentGateStatus::Unsupported;
    }
}
