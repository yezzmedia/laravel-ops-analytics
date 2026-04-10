<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use UnitEnum;
use YezzMedia\OpsAnalytics\Actions\RefreshAnalyticsPostureAction;
use YezzMedia\OpsAnalytics\Data\DeliveryFailureRecord;
use YezzMedia\OpsAnalytics\Data\DispatchPostureRecord;
use YezzMedia\OpsAnalytics\Data\TrackerRecord;
use YezzMedia\OpsAnalytics\Filament\Widgets\OpsAnalyticsOverviewWidget;
use YezzMedia\OpsAnalytics\Support\OpsAnalyticsManager;

class OpsAnalyticsPage extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string|UnitEnum|null $navigationGroup = 'Analytics';

    protected static ?string $navigationLabel = 'Analytics';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'Analytics Operations';

    protected static ?string $slug = 'ops-analytics';

    public static function canAccess(): bool
    {
        return Gate::check('ops.analytics.view');
    }

    public static function getNavigationBadge(): ?string
    {
        return app(OpsAnalyticsManager::class)->overallStatus()->label();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return app(OpsAnalyticsManager::class)->overallStatus()->color();
    }

    protected function getHeaderWidgets(): array
    {
        return [
            OpsAnalyticsOverviewWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function content(Schema $schema): Schema
    {
        $manager = app(OpsAnalyticsManager::class);
        $summary = $manager->summary();

        return $schema->components([
            $this->overviewSection($summary, $manager->dispatches(), $manager->failures()),
            $this->dispatchesSection($manager->dispatches()),
            $this->trackersSection($summary),
            $this->consentSection($manager),
            $this->failuresSection($manager->failures()),
            $this->actionsSection(),
        ]);
    }

    private function overviewSection($summary, array $dispatches, array $failures): Section
    {
        return Section::make('Overview')
            ->schema([
                Grid::make(6)->schema([
                    ...$this->labeledText('Overall Status', $summary->overallStatus->label(), color: $summary->overallStatus->color(), icon: $summary->overallStatus->icon(), badge: true),
                    ...$this->labeledText('Trackers', (string) count($summary->trackers), color: 'gray', badge: true),
                    ...$this->labeledText('Recent Dispatches', (string) count($dispatches), color: count($dispatches) > 0 ? 'success' : 'gray', badge: true),
                    ...$this->labeledText('Healthy', (string) $summary->healthyCount, color: 'success', badge: true),
                    ...$this->labeledText('Warnings', (string) $summary->warningCount, color: $summary->warningCount > 0 ? 'warning' : 'gray', badge: true),
                    ...$this->labeledText('Failures', (string) $summary->failingCount, color: $summary->failingCount > 0 ? 'danger' : 'gray', badge: true),
                    ...$this->labeledText('Blocked', (string) $summary->blockedCount, color: $summary->blockedCount > 0 ? 'warning' : 'gray', badge: true),
                    ...$this->labeledText('Failure History', (string) count($failures), color: count($failures) > 0 ? 'danger' : 'success', badge: true),
                ]),
                ...$this->labeledText('Last checked', $summary->checkedAt ?? now()->toIso8601String(), color: 'gray'),
            ]);
    }

    private function dispatchesSection(array $dispatches): Section
    {
        if ($dispatches === []) {
            return Section::make('Recent Dispatch Activity')
                ->schema([
                    Text::make('No request dispatches have been recorded yet for the current tracker inventory.')
                        ->color('gray'),
                ]);
        }

        return Section::make('Recent Dispatch Activity')
            ->schema(
                array_merge(...array_map(fn (DispatchPostureRecord $record): array => [
                    Text::make(sprintf('%s | %s', $record->eventName, $record->status->label()))
                        ->badge()
                        ->color($record->status->color())
                        ->icon($record->status->icon()),
                    Text::make(sprintf('Tracker: %s | Attempts: %d | Completed: %s', $record->trackerKey, $record->attemptCount, $record->completedAt ?? 'Pending'))
                        ->color('gray'),
                    Text::make($record->summary)->color('gray'),
                    ...array_map(fn (string $issue): Text => Text::make($issue)->color('warning'), $record->issues),
                ], array_slice($dispatches, 0, 6))),
            );
    }

    private function trackersSection($summary): Section
    {
        if ($summary->trackers === []) {
            return Section::make('Tracker Inventory')
                ->schema([
                    Text::make('No analytics trackers are currently registered.')
                        ->color('gray'),
                ]);
        }

        return Section::make('Tracker Inventory')
            ->schema(
                array_merge(...array_map(function (TrackerRecord $tracker): array {
                    return [
                        Text::make($tracker->name)
                            ->badge()
                            ->color($tracker->healthStatus->color()),
                        Text::make(sprintf('Driver: %s | Consent: %s | Lifecycle: %s', $tracker->driver, $tracker->consentMode ?? 'No consent mode', $tracker->lifecycleStatus))
                            ->color('gray'),
                        Text::make(sprintf('Last dispatch: %s | Last success: %s | Last failure: %s', $tracker->lastDispatchAt ?? 'Never', $tracker->lastSuccessAt ?? 'Never', $tracker->lastFailureAt ?? 'Never'))
                            ->color('gray'),
                        Text::make($tracker->summary)->color('gray'),
                        ...array_map(fn (string $issue): Text => Text::make($issue)->color('warning'), $tracker->issues),
                        ...$this->metadataLines($tracker->metadata),
                    ];
                }, $summary->trackers)),
            );
    }

    private function consentSection(OpsAnalyticsManager $manager): Section
    {
        $records = $manager->consentGates();

        if ($records === []) {
            return Section::make('Consent Gating')
                ->schema([
                    Text::make('No consent-gating metadata is currently available.')
                        ->color('gray'),
                ]);
        }

        return Section::make('Consent Gating')
            ->schema(
                array_merge(...array_map(
                    fn ($record): array => [
                        Text::make($record->trackerKey ?? 'Global')
                            ->badge()
                            ->color(match ($record->status->value) {
                                'allowed' => 'success',
                                'blocked' => 'warning',
                                'unknown' => 'danger',
                                default => 'gray',
                            }),
                        Text::make(sprintf('Source: %s', $record->source ?? 'No explicit source'))
                            ->color('gray'),
                        Text::make($record->summary)->color('gray'),
                        ...array_map(fn (string $issue): Text => Text::make($issue)->color('warning'), $record->issues),
                    ],
                    $records,
                )),
            );
    }

    private function failuresSection(array $failures): Section
    {
        if ($failures === []) {
            return Section::make('Recent Failures')
                ->schema([
                    Text::make('No analytics delivery failures are currently tracked.')
                        ->color('success'),
                ]);
        }

        return Section::make('Recent Failures')
            ->schema(
                array_merge(...array_map(
                    fn (DeliveryFailureRecord $record): array => [
                        Text::make(sprintf('%s | %s', $record->trackerKey, $record->dispatchKey ?? 'Unknown dispatch'))
                            ->badge()
                            ->color('danger'),
                        Text::make(sprintf('Occurred: %s', $record->occurredAt ?? 'Unknown'))
                            ->color('gray'),
                        Text::make($record->summary)->color('danger'),
                        ...$this->metadataLines($record->details),
                    ],
                    array_slice($failures, 0, 5),
                )),
            );
    }

    /**
     * @return array{Text, Text}
     */
    private function labeledText(string $label, string $value, ?string $color = null, ?string $icon = null, bool $badge = false): array
    {
        $valueText = Text::make($value);

        if ($badge) {
            $valueText = $valueText->badge();
        }

        if ($color !== null) {
            $valueText = $valueText->color($color);
        }

        if ($icon !== null) {
            $valueText = $valueText->icon($icon);
        }

        return [
            Text::make($label)->badge()->color('gray'),
            $valueText,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<int, Text>
     */
    private function metadataLines(array $metadata): array
    {
        if ($metadata === []) {
            return [];
        }

        $lines = [];

        foreach (array_slice($metadata, 0, 4, true) as $key => $value) {
            $lines[] = Text::make(sprintf('%s: %s', str_replace('_', ' ', (string) $key), $this->stringify($value)))
                ->color('gray');
        }

        return $lines;
    }

    private function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value) || $value === null) {
            return (string) ($value ?? 'null');
        }

        if (is_array($value)) {
            return implode(', ', array_map(fn (mixed $item): string => $this->stringify($item), array_slice($value, 0, 4)));
        }

        return get_debug_type($value);
    }

    private function actionsSection(): Actions
    {
        return Actions::make([
            Action::make('refresh')
                ->label('Refresh Analytics Posture')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->visible(fn (): bool => Gate::check('ops.analytics.manage'))
                ->action(function (): void {
                    app(RefreshAnalyticsPostureAction::class)->execute('filament');

                    Notification::make()
                        ->success()
                        ->title('Analytics posture refreshed')
                        ->send();
                }),
        ]);
    }
}
