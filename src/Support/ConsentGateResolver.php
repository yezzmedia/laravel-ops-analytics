<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Support;

use YezzMedia\OpsAnalytics\Data\ConsentGateRecord;
use YezzMedia\OpsAnalytics\Enums\ConsentGateStatus;
use YezzMedia\OpsAnalytics\Models\OpsAnalyticsTracker;

final class ConsentGateResolver
{
    public function __construct(
        private readonly bool $integrationRequired,
        private readonly string $defaultSource,
    ) {}

    /**
     * @return array<int, ConsentGateRecord>
     */
    public function resolve(): array
    {
        return OpsAnalyticsTracker::query()
            ->orderBy('name')
            ->get()
            ->map(fn (OpsAnalyticsTracker $tracker): ConsentGateRecord => $this->forTracker((string) $tracker->getAttribute('tracker_key')))
            ->values()
            ->all();
    }

    public function forTracker(?string $trackerKey): ConsentGateRecord
    {
        $tracker = $trackerKey === null
            ? null
            : OpsAnalyticsTracker::query()->where('tracker_key', $trackerKey)->first();

        $status = $this->resolveStatus($tracker);

        return new ConsentGateRecord(
            trackerKey: $trackerKey,
            status: $status,
            source: $this->source($tracker),
            summary: $this->summary($status),
            issues: $this->issues($status),
        );
    }

    private function resolveStatus(?OpsAnalyticsTracker $tracker): ConsentGateStatus
    {
        if ($tracker === null) {
            return $this->integrationRequired ? ConsentGateStatus::Unknown : ConsentGateStatus::Unsupported;
        }

        $mode = $tracker->getAttribute('consent_mode');

        if ($mode === null || $mode === '') {
            return $this->integrationRequired ? ConsentGateStatus::Unknown : ConsentGateStatus::Unsupported;
        }

        if ($mode === 'blocked') {
            return ConsentGateStatus::Blocked;
        }

        return ConsentGateStatus::Allowed;
    }

    private function source(?OpsAnalyticsTracker $tracker): ?string
    {
        return $tracker?->getAttribute('consent_mode') !== null ? 'tracker' : $this->defaultSource;
    }

    private function summary(ConsentGateStatus $status): string
    {
        return match ($status) {
            ConsentGateStatus::Allowed => 'Consent-aware gating allows technical analytics dispatch.',
            ConsentGateStatus::Blocked => 'Consent-aware gating currently blocks technical analytics dispatch.',
            ConsentGateStatus::Unsupported => 'Consent-aware gating is not supported in the current environment.',
            ConsentGateStatus::Unknown => 'Consent-aware gating cannot be evaluated from the current metadata.',
        };
    }

    /**
     * @return array<int, string>
     */
    private function issues(ConsentGateStatus $status): array
    {
        return match ($status) {
            ConsentGateStatus::Allowed => [],
            ConsentGateStatus::Blocked => ['Consent-aware dispatch is blocked.'],
            ConsentGateStatus::Unsupported => ['Consent integration is unavailable.'],
            ConsentGateStatus::Unknown => ['Consent state is incomplete or unknown.'],
        };
    }
}
