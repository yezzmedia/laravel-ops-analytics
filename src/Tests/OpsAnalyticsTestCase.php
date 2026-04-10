<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Tests;

use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use YezzMedia\Foundation\Testing\FoundationTestCase;
use YezzMedia\OpsAnalytics\OpsAnalyticsServiceProvider;
use YezzMedia\OpsAnalytics\Testing\Fixtures\OpsAnalyticsTestPanelProvider;
use YezzMedia\OpsAnalytics\Testing\Fixtures\TestOpsAnalyticsUser;

abstract class OpsAnalyticsTestCase extends FoundationTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            LivewireServiceProvider::class,
            FilamentServiceProvider::class,
            OpsAnalyticsServiceProvider::class,
            OpsAnalyticsTestPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        Config::set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        Config::set('database.default', 'testing');
        Config::set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        Config::set('auth.guards.web', [
            'driver' => 'session',
            'provider' => 'users',
        ]);
        Config::set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => TestOpsAnalyticsUser::class,
        ]);
        Config::set('ops-analytics.cache.enabled', false);
        Config::set('ops-analytics.audit.driver', null);

        $app->booted(function (): void {
            foreach (['ops.analytics.view', 'ops.analytics.manage'] as $ability) {
                Gate::define($ability, static fn (TestOpsAnalyticsUser $user): bool => $user->allows($ability));
            }
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTablesExist();

        Filament::setCurrentPanel('ops-analytics-test');
    }

    private function ensureTablesExist(): void
    {
        if (! Schema::hasTable('migrations')) {
            Schema::create('migrations', function (Blueprint $table): void {
                $table->id();
                $table->string('migration');
                $table->integer('batch');
            });
        }

        if (! Schema::hasTable('ops_analytics_trackers')) {
            Schema::create('ops_analytics_trackers', function (Blueprint $table): void {
                $table->id();
                $table->string('tracker_key')->unique();
                $table->string('name');
                $table->string('driver');
                $table->string('lifecycle_status')->default('unknown');
                $table->boolean('is_enabled')->default(true);
                $table->string('consent_mode')->nullable();
                $table->json('configuration_summary')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ops_analytics_dispatches')) {
            Schema::create('ops_analytics_dispatches', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('ops_analytics_tracker_id')->constrained('ops_analytics_trackers')->cascadeOnDelete();
                $table->string('dispatch_key')->unique();
                $table->string('event_key');
                $table->string('event_name');
                $table->string('event_category')->nullable();
                $table->string('consent_status')->nullable();
                $table->string('delivery_status')->default('unsupported');
                $table->timestamp('queued_at')->nullable();
                $table->timestamp('dispatched_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('last_success_at')->nullable();
                $table->timestamp('last_failure_at')->nullable();
                $table->unsignedInteger('attempt_count')->default(0);
                $table->string('payload_fingerprint')->nullable();
                $table->text('failure_summary')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ops_analytics_dispatch_attempts')) {
            Schema::create('ops_analytics_dispatch_attempts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('ops_analytics_dispatch_id')->constrained('ops_analytics_dispatches')->cascadeOnDelete();
                $table->unsignedInteger('attempt_number');
                $table->string('status')->default('unsupported');
                $table->timestamp('occurred_at')->nullable();
                $table->unsignedInteger('latency_ms')->nullable();
                $table->unsignedInteger('response_code')->nullable();
                $table->text('failure_summary')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }
}
