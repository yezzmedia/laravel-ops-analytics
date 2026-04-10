<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use YezzMedia\OpsAnalytics\Actions\RecordDispatchOutcomeAction;
use YezzMedia\OpsAnalytics\Data\AnalyticsContext;
use YezzMedia\OpsAnalytics\Data\AnalyticsEvent;
use YezzMedia\OpsAnalytics\Support\ConsentAwareTracker;
use YezzMedia\OpsAnalytics\Support\TrackerRegistry;

final class DispatchAnalyticsEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public AnalyticsEvent $event,
        public AnalyticsContext $context,
    ) {}

    public function handle(
        TrackerRegistry $registry,
        ConsentAwareTracker $consentAwareTracker,
        RecordDispatchOutcomeAction $recordDispatchOutcome,
    ): void {
        $trackerKey = $this->context->trackerKey;

        if ($trackerKey === null) {
            return;
        }

        $tracker = $registry->find($trackerKey);

        if ($tracker === null || ! $tracker->enabled()) {
            $recordDispatchOutcome->execute($trackerKey, [
                'dispatch_key' => (string) Str::uuid(),
                'event_key' => $this->event->eventKey,
                'event_name' => $this->event->name,
                'event_category' => $this->event->category,
                'consent_status' => $this->context->consentStatus?->value,
                'delivery_status' => 'failed',
                'queued_at' => now()->toIso8601String(),
                'completed_at' => now()->toIso8601String(),
                'attempt_count' => 1,
                'failure_summary' => 'Tracker is missing or disabled for analytics dispatch.',
                'metadata' => [
                    'subject_type' => $this->event->subjectType,
                    'subject_key' => $this->event->subjectKey,
                    'context' => $this->context->metadata,
                ],
            ], 'job');

            return;
        }

        $allowed = $consentAwareTracker->dispatch($tracker, $this->event, $this->context);

        $recordDispatchOutcome->execute($trackerKey, [
            'dispatch_key' => (string) Str::uuid(),
            'event_key' => $this->event->eventKey,
            'event_name' => $this->event->name,
            'event_category' => $this->event->category,
            'consent_status' => $this->context->consentStatus?->value,
            'delivery_status' => $allowed ? 'healthy' : 'warning',
            'queued_at' => now()->toIso8601String(),
            'dispatched_at' => now()->toIso8601String(),
            'completed_at' => now()->toIso8601String(),
            'last_success_at' => $allowed ? now()->toIso8601String() : null,
            'attempt_count' => 1,
            'failure_summary' => $allowed ? null : 'Consent-aware gating blocked analytics dispatch.',
            'payload_fingerprint' => hash('sha256', json_encode([
                'event' => $this->event->eventKey,
                'subject_type' => $this->event->subjectType,
                'subject_key' => $this->event->subjectKey,
                'tracker' => $this->context->trackerKey,
                'actor_type' => $this->context->actorType,
                'actor_key' => $this->context->actorKey,
            ], JSON_THROW_ON_ERROR)),
            'metadata' => [
                'subject_type' => $this->event->subjectType,
                'subject_key' => $this->event->subjectKey,
                'category' => $this->event->category,
                'context' => $this->context->metadata,
            ],
            'attempt' => [
                'attempt_number' => 1,
                'status' => $allowed ? 'healthy' : 'warning',
                'occurred_at' => now()->toIso8601String(),
            ],
        ], 'job');
    }
}
