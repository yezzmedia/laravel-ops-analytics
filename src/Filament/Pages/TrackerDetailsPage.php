<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Filament\Pages;

use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use YezzMedia\OpsAnalytics\Support\OpsAnalyticsManager;

class TrackerDetailsPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'ops-analytics/detail';

    #[Url]
    public string $tracker = '';

    public static function canAccess(): bool
    {
        return Gate::check('ops.analytics.view');
    }

    public function getTitle(): string
    {
        $tracker = app(OpsAnalyticsManager::class)->tracker($this->tracker);

        return $tracker === null
            ? 'Analytics Tracker Detail'
            : sprintf('Analytics Tracker Detail: %s', $tracker->name);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to Analytics')
                ->icon('heroicon-o-arrow-left')
                ->url(OpsAnalyticsPage::getUrl()),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $manager = app(OpsAnalyticsManager::class);
        $tracker = $manager->tracker($this->tracker);

        if ($tracker === null) {
            return $schema->components([
                Section::make('Analytics Tracker Summary')
                    ->schema([
                        Text::make('The requested analytics tracker could not be found.')
                            ->color('gray'),
                    ]),
            ]);
        }

        $dispatches = $manager->dispatchesFor($tracker->trackerKey);
        $consent = $manager->consentGateFor($tracker->trackerKey);
        $failures = $manager->failuresFor($tracker->trackerKey);

        return $schema->components([
            Section::make('Analytics Tracker Summary')
                ->schema([
                    Text::make($tracker->summary)->color('gray'),
                    ...array_map(fn (string $issue): Text => Text::make($issue)->color('gray'), $tracker->issues),
                ]),
            Section::make('Recent Dispatches')
                ->schema($dispatches === []
                    ? [Text::make('No dispatch metadata is available for this tracker yet.')->color('gray')]
                    : array_merge(...array_map(fn ($record): array => [
                        Text::make(sprintf('%s (%s)', $record->eventName, $record->status->label()))->color('gray'),
                        Text::make($record->summary)->color('gray'),
                    ], $dispatches))),
            Section::make('Consent Gating')
                ->schema([
                    Text::make($consent->summary)->color('gray'),
                    ...array_map(fn (string $issue): Text => Text::make($issue)->color('gray'), $consent->issues),
                ]),
            Section::make('Failure History')
                ->schema($failures === []
                    ? [Text::make('No analytics delivery failures are currently tracked for this tracker.')->color('success')]
                    : array_merge(...array_map(fn ($record): array => [
                        Text::make($record->dispatchKey ?? 'Unknown dispatch')->color('gray'),
                        Text::make($record->summary)->color('danger'),
                    ], $failures))),
        ]);
    }
}
