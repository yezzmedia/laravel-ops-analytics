<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Auth;
use YezzMedia\OpsAnalytics\Events\AnalyticsPostureRefreshed;
use YezzMedia\OpsAnalytics\Support\OpsAnalyticsManager;

final class RefreshAnalyticsPostureAction
{
    public function __construct(
        private readonly OpsAnalyticsManager $manager,
        private readonly Dispatcher $events,
    ) {}

    public function execute(string $source = 'manual'): void
    {
        $summary = $this->manager->refresh();

        $this->events->dispatch(new AnalyticsPostureRefreshed(
            overallStatus: $summary->overallStatus->value,
            healthyCount: $summary->healthyCount,
            warningCount: $summary->warningCount,
            failingCount: $summary->failingCount,
            unsupportedCount: $summary->unsupportedCount,
            blockedCount: $summary->blockedCount,
            actorId: Auth::id(),
            source: $source,
            completedAt: $summary->checkedAt ?? now()->toIso8601String(),
        ));
    }
}
