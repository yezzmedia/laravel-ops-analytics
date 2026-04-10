<?php

declare(strict_types=1);

use Filament\Panel;
use YezzMedia\OpsAnalytics\Filament\OpsAnalyticsFilamentPlugin;
use YezzMedia\OpsAnalytics\Filament\Pages\OpsAnalyticsPage;
use YezzMedia\OpsAnalytics\Filament\Pages\TrackerDetailsPage;

it('registers the ops analytics filament plugin pages', function (): void {
    $plugin = OpsAnalyticsFilamentPlugin::make();
    $panel = Panel::make();

    $plugin->register($panel);

    expect($plugin->getId())->toBe('ops-analytics')
        ->and($panel->getPages())->toContain(OpsAnalyticsPage::class)
        ->and($panel->getPages())->toContain(TrackerDetailsPage::class);
});
