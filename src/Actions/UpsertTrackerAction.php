<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use YezzMedia\OpsAnalytics\Events\TrackerConfigurationUpdated;
use YezzMedia\OpsAnalytics\Models\OpsAnalyticsTracker;
use YezzMedia\OpsAnalytics\Support\OpsAnalyticsManager;

final class UpsertTrackerAction
{
    public function __construct(
        private readonly OpsAnalyticsManager $manager,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, string $source = 'manual'): OpsAnalyticsTracker
    {
        $validated = Validator::make($data, [
            'tracker_key' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', 'string', 'max:255'],
            'lifecycle_status' => ['required', 'string', 'max:255'],
            'is_enabled' => ['required', 'boolean'],
            'consent_mode' => ['nullable', Rule::in(['required', 'optional', 'blocked'])],
            'configuration_summary' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ])->after(function ($validator) use ($data): void {
            if (! (bool) config('ops-analytics.safety.forbid_sensitive_defaults', true)) {
                return;
            }

            $forbiddenKeys = array_map('strtolower', (array) config('ops-analytics.safety.redact_keys', []));

            foreach (['configuration_summary', 'metadata'] as $field) {
                $value = $data[$field] ?? null;

                if (! is_array($value)) {
                    continue;
                }

                foreach (array_keys($this->flatten($value)) as $key) {
                    if ($this->isSensitiveKey($key, $forbiddenKeys)) {
                        $validator->errors()->add($field, sprintf('The %s field contains a forbidden sensitive key [%s].', $field, $key));
                    }
                }
            }
        })->validate();

        /** @var OpsAnalyticsTracker $tracker */
        $tracker = OpsAnalyticsTracker::query()->updateOrCreate(
            ['tracker_key' => $validated['tracker_key']],
            [
                ...$validated,
                'configuration_summary' => $this->sanitizeArray($validated['configuration_summary'] ?? null),
                'metadata' => $this->sanitizeArray($validated['metadata'] ?? null),
            ],
        );

        $this->manager->refresh();

        $this->events->dispatch(new TrackerConfigurationUpdated(
            trackerKey: (string) $tracker->getAttribute('tracker_key'),
            driver: (string) $tracker->getAttribute('driver'),
            lifecycleStatus: (string) $tracker->getAttribute('lifecycle_status'),
            isEnabled: (bool) $tracker->getAttribute('is_enabled'),
            consentMode: $tracker->getAttribute('consent_mode'),
            actorId: Auth::id(),
            source: $source,
            completedAt: now()->toIso8601String(),
        ));

        return $tracker;
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

    /**
     * @param  array<int, string>  $forbiddenKeys
     */
    private function isSensitiveKey(string $key, array $forbiddenKeys): bool
    {
        $segments = explode('.', strtolower($key));

        foreach ($segments as $segment) {
            if (in_array($segment, $forbiddenKeys, true)) {
                return true;
            }
        }

        return false;
    }
}
