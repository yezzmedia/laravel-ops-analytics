<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Support;

use YezzMedia\OpsAnalytics\Contracts\Tracker;
use YezzMedia\OpsAnalytics\Data\AnalyticsContext;
use YezzMedia\OpsAnalytics\Data\AnalyticsEvent;

final class DefaultRuntimeTracker implements Tracker
{
    public function key(): string
    {
        return (string) config('ops-analytics.default_tracker.key', 'default-runtime');
    }

    public function name(): string
    {
        return (string) config('ops-analytics.default_tracker.name', 'Default Runtime Tracker');
    }

    public function driver(): string
    {
        return (string) config('ops-analytics.default_tracker.driver', 'default-runtime');
    }

    public function enabled(): bool
    {
        return (bool) config('ops-analytics.default_tracker.enabled', true);
    }

    public function consentMode(): ?string
    {
        return config('ops-analytics.default_tracker.consent_mode', 'optional');
    }

    public function configurationSummary(): array
    {
        return [
            'capture' => 'server_request',
            'source' => 'foundation_web_middleware',
            'request_capture_enabled' => (bool) config('ops-analytics.request_capture.enabled', true),
            'tracked_fields' => [
                'method',
                'path',
                'route_name',
                'route_uri',
                'response_status',
                'duration_ms',
                'host',
                'scheme',
                'request_kind',
            ],
            'hashed_fields' => [
                'actor',
                'session',
                'ip',
                'user_agent',
                'referrer',
            ],
        ];
    }

    public function track(AnalyticsEvent $event, AnalyticsContext $context): void {}
}
