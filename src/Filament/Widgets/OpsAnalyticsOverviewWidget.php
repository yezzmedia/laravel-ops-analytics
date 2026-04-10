<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Filament\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use YezzMedia\OpsAnalytics\Support\OpsAnalyticsManager;

final class OpsAnalyticsOverviewWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Analytics Overview';

    protected ?string $description = 'Live posture for the built-in runtime tracker and its recent request deliveries.';

    protected ?string $pollingInterval = '30s';

    /**
     * @var int|string|array<string, int|null>
     */
    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $manager = app(OpsAnalyticsManager::class);
        $summary = $manager->summary();
        $dispatches = $manager->dispatches();
        $failures = $manager->failures();

        $healthyDispatches = count(array_filter($dispatches, fn ($dispatch): bool => $dispatch->status->value === 'healthy'));
        $warningDispatches = count(array_filter($dispatches, fn ($dispatch): bool => $dispatch->status->value === 'warning'));
        $failedDispatches = count(array_filter($dispatches, fn ($dispatch): bool => $dispatch->status->value === 'failed'));

        $deliveryChart = [
            $healthyDispatches,
            $warningDispatches,
            $failedDispatches,
        ];

        return [
            Stat::make('Trackers', count($summary->trackers))
                ->description('Persisted analytics trackers visible in the current posture snapshot.')
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color(count($summary->trackers) > 0 ? 'success' : 'gray')
                ->chart([
                    count($summary->trackers),
                    $summary->healthyCount,
                    $summary->warningCount,
                    $summary->failingCount,
                ]),
            Stat::make('Recent dispatches', count($dispatches))
                ->description('Recent request deliveries currently visible through dispatch posture records.')
                ->descriptionIcon(count($dispatches) > 0 ? Heroicon::OutlinedArrowTrendingUp : Heroicon::OutlinedClock)
                ->color(count($dispatches) > 0 ? 'success' : 'gray')
                ->chart($deliveryChart),
            Stat::make('Healthy deliveries', $healthyDispatches)
                ->description($healthyDispatches > 0 ? 'Successful request deliveries are being recorded.' : 'No healthy deliveries are currently visible.')
                ->descriptionIcon($healthyDispatches > 0 ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedClock)
                ->color($healthyDispatches > 0 ? 'success' : 'gray')
                ->chart($deliveryChart),
            Stat::make('Delivery issues', $summary->warningCount + $summary->failingCount)
                ->description($summary->failingCount > 0 ? 'One or more trackers currently report failed deliveries.' : 'Warnings or failed deliveries will surface here.')
                ->descriptionIcon($summary->failingCount > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
                ->color($summary->failingCount > 0 ? 'danger' : ($summary->warningCount > 0 ? 'warning' : 'success'))
                ->chart($deliveryChart),
            Stat::make('Consent blocked', $summary->blockedCount)
                ->description($summary->blockedCount > 0 ? 'Consent gating blocked one or more tracker deliveries.' : 'No tracker is currently blocked by consent gating.')
                ->descriptionIcon($summary->blockedCount > 0 ? Heroicon::OutlinedShieldExclamation : Heroicon::OutlinedCheckCircle)
                ->color($summary->blockedCount > 0 ? 'warning' : 'success')
                ->chart([
                    $summary->blockedCount,
                    count($failures),
                    $healthyDispatches,
                ]),
        ];
    }
}
