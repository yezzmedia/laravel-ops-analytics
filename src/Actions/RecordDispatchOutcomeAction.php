<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use YezzMedia\OpsAnalytics\Enums\ConsentGateStatus;
use YezzMedia\OpsAnalytics\Enums\DispatchPostureStatus;
use YezzMedia\OpsAnalytics\Events\DispatchOutcomeRecorded;
use YezzMedia\OpsAnalytics\Models\OpsAnalyticsDispatch;
use YezzMedia\OpsAnalytics\Models\OpsAnalyticsDispatchAttempt;
use YezzMedia\OpsAnalytics\Models\OpsAnalyticsTracker;
use YezzMedia\OpsAnalytics\Support\OpsAnalyticsManager;

final class RecordDispatchOutcomeAction
{
    public function __construct(
        private readonly OpsAnalyticsManager $manager,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(string $trackerKey, array $data, string $source = 'manual'): OpsAnalyticsDispatch
    {
        $tracker = OpsAnalyticsTracker::query()->where('tracker_key', $trackerKey)->first();

        if ($tracker === null) {
            throw ValidationException::withMessages([
                'tracker_key' => 'The selected analytics tracker does not exist.',
            ]);
        }

        $validated = Validator::make($data, [
            'dispatch_key' => ['required', 'string', 'max:255'],
            'event_key' => ['required', 'string', 'max:255'],
            'event_name' => ['required', 'string', 'max:255'],
            'event_category' => ['nullable', 'string', 'max:255'],
            'consent_status' => ['nullable', Rule::in(array_map(static fn ($status) => $status->value, ConsentGateStatus::cases()))],
            'delivery_status' => ['required', Rule::in(array_map(static fn ($status) => $status->value, DispatchPostureStatus::cases()))],
            'queued_at' => ['nullable', 'date'],
            'dispatched_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'last_success_at' => ['nullable', 'date'],
            'last_failure_at' => ['nullable', 'date'],
            'attempt_count' => ['required', 'integer', 'min:0'],
            'payload_fingerprint' => ['nullable', 'string', 'max:255'],
            'failure_summary' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'attempt' => ['nullable', 'array'],
            'attempt.attempt_number' => ['nullable', 'integer', 'min:1'],
            'attempt.status' => ['nullable', Rule::in(array_map(static fn ($status) => $status->value, DispatchPostureStatus::cases()))],
            'attempt.occurred_at' => ['nullable', 'date'],
            'attempt.latency_ms' => ['nullable', 'integer', 'min:0'],
            'attempt.response_code' => ['nullable', 'integer', 'min:0'],
            'attempt.failure_summary' => ['nullable', 'string'],
            'attempt.metadata' => ['nullable', 'array'],
        ])->after(function ($validator) use ($data): void {
            if (! (bool) config('ops-analytics.safety.forbid_sensitive_defaults', true)) {
                return;
            }

            foreach (['metadata', 'attempt.metadata'] as $field) {
                $value = data_get($data, $field);

                if (! is_array($value)) {
                    continue;
                }

                foreach (array_keys($this->flatten($value)) as $key) {
                    if ($this->isSensitiveKey($key)) {
                        $validator->errors()->add($field, sprintf('The %s field contains a forbidden sensitive key [%s].', $field, $key));
                    }
                }
            }
        })->validate();

        /** @var OpsAnalyticsDispatch $dispatch */
        $dispatch = OpsAnalyticsDispatch::query()->updateOrCreate(
            ['dispatch_key' => $validated['dispatch_key']],
            [
                'ops_analytics_tracker_id' => $tracker->getKey(),
                'event_key' => $validated['event_key'],
                'event_name' => $validated['event_name'],
                'event_category' => $validated['event_category'] ?? null,
                'consent_status' => $validated['consent_status'] ?? null,
                'delivery_status' => $validated['delivery_status'],
                'queued_at' => $validated['queued_at'] ?? null,
                'dispatched_at' => $validated['dispatched_at'] ?? null,
                'completed_at' => $validated['completed_at'] ?? null,
                'last_success_at' => $validated['last_success_at'] ?? null,
                'last_failure_at' => $validated['last_failure_at'] ?? null,
                'attempt_count' => $validated['attempt_count'],
                'payload_fingerprint' => $validated['payload_fingerprint'] ?? null,
                'failure_summary' => $validated['failure_summary'] ?? null,
                'metadata' => $this->sanitizeArray($validated['metadata'] ?? null),
            ],
        );

        if (is_array($validated['attempt'] ?? null)) {
            OpsAnalyticsDispatchAttempt::query()->updateOrCreate(
                [
                    'ops_analytics_dispatch_id' => $dispatch->getKey(),
                    'attempt_number' => $validated['attempt']['attempt_number'] ?? 1,
                ],
                [
                    'status' => $validated['attempt']['status'] ?? $validated['delivery_status'],
                    'occurred_at' => $validated['attempt']['occurred_at'] ?? $validated['completed_at'] ?? null,
                    'latency_ms' => $validated['attempt']['latency_ms'] ?? null,
                    'response_code' => $validated['attempt']['response_code'] ?? null,
                    'failure_summary' => $validated['attempt']['failure_summary'] ?? $validated['failure_summary'] ?? null,
                    'metadata' => $this->sanitizeArray($validated['attempt']['metadata'] ?? null),
                ],
            );
        }

        $this->manager->refresh();

        $this->events->dispatch(new DispatchOutcomeRecorded(
            trackerKey: $trackerKey,
            dispatchKey: (string) $dispatch->getAttribute('dispatch_key'),
            status: (string) $dispatch->getAttribute('delivery_status'),
            attemptCount: (int) $dispatch->getAttribute('attempt_count'),
            completedAt: $dispatch->getAttribute('completed_at')?->toIso8601String(),
            actorId: Auth::id(),
            source: $source,
        ));

        return $dispatch;
    }

    /**
     * @param  array<string, mixed>|null  $value
     * @return array<string, mixed>|null
     */
    private function sanitizeArray(?array $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $redactKeys = array_map('strtolower', (array) config('ops-analytics.safety.redact_keys', []));

        $sanitize = function (array $payload) use (&$sanitize, $redactKeys): array {
            $sanitized = [];

            foreach ($payload as $key => $item) {
                $stringKey = (string) $key;

                if (in_array(strtolower($stringKey), $redactKeys, true)) {
                    $sanitized[$stringKey] = '[redacted]';

                    continue;
                }

                $sanitized[$stringKey] = is_array($item) ? $sanitize($item) : $item;
            }

            return $sanitized;
        };

        return $sanitize($value);
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function flatten(array $value, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($value as $key => $item) {
            $path = $prefix === '' ? (string) $key : sprintf('%s.%s', $prefix, $key);

            $flattened[$path] = $item;

            if (is_array($item)) {
                $flattened += $this->flatten($item, $path);
            }
        }

        return $flattened;
    }

    private function isSensitiveKey(string $key): bool
    {
        $segments = explode('.', strtolower($key));
        $redactKeys = array_map('strtolower', (array) config('ops-analytics.safety.redact_keys', []));

        foreach ($segments as $segment) {
            if (in_array($segment, $redactKeys, true)) {
                return true;
            }
        }

        return false;
    }
}
