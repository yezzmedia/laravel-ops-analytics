<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use YezzMedia\OpsAnalytics\Filament\Pages\OpsAnalyticsPage;
use YezzMedia\OpsAnalytics\Filament\Pages\TrackerDetailsPage;

final class OpsAnalyticsFilamentPlugin implements Plugin
{
    public static function make(): static
    {
        return new self;
    }

    public function getId(): string
    {
        return 'ops-analytics';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            OpsAnalyticsPage::class,
            TrackerDetailsPage::class,
        ]);
    }

    public function boot(Panel $panel): void {}
}
