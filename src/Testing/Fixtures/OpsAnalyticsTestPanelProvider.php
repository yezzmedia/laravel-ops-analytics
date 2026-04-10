<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Testing\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use YezzMedia\OpsAnalytics\Filament\OpsAnalyticsFilamentPlugin;

final class OpsAnalyticsTestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('ops-analytics-test')
            ->path('ops-analytics-test')
            ->authGuard('web')
            ->plugin(OpsAnalyticsFilamentPlugin::make());
    }
}
