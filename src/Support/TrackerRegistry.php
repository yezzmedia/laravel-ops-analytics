<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Support;

use YezzMedia\OpsAnalytics\Contracts\Tracker;

final class TrackerRegistry
{
    /**
     * @var array<string, Tracker>
     */
    private array $trackers = [];

    public function register(Tracker $tracker): void
    {
        $this->trackers[$tracker->key()] = $tracker;
    }

    /**
     * @return array<int, Tracker>
     */
    public function all(): array
    {
        return array_values($this->trackers);
    }

    public function find(string $trackerKey): ?Tracker
    {
        return $this->trackers[$trackerKey] ?? null;
    }
}
