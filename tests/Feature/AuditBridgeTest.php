<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;
use YezzMedia\OpsAnalytics\Contracts\OpsAnalyticsAuditWriter;
use YezzMedia\OpsAnalytics\Events\AnalyticsPostureRefreshed;
use YezzMedia\OpsAnalytics\Support\ActivityLogOpsAnalyticsAuditWriter;
use YezzMedia\OpsAnalytics\Support\NullOpsAnalyticsAuditWriter;

it('binds the null analytics audit writer by default', function (): void {
    expect(app(OpsAnalyticsAuditWriter::class))->toBeInstanceOf(NullOpsAnalyticsAuditWriter::class);
});

it('ships a null analytics audit driver by default in package config', function (): void {
    $config = require dirname(__DIR__, 2).'/config/ops-analytics.php';

    expect($config['audit']['driver'])->toBeNull();
});

it('null analytics audit writer accepts posture refresh events', function (): void {
    $writer = new NullOpsAnalyticsAuditWriter;

    $writer->record(new AnalyticsPostureRefreshed(
        overallStatus: 'warning',
        healthyCount: 1,
        warningCount: 1,
        failingCount: 0,
        unsupportedCount: 0,
        blockedCount: 1,
        actorId: 7,
        source: 'test',
        completedAt: '2026-04-10T12:00:00+00:00',
    ));

    expect(true)->toBeTrue();
});

it('binds the activitylog analytics audit writer when configured', function (): void {
    if (! class_exists(Activity::class)) {
        $this->markTestSkipped('spatie/laravel-activitylog is not installed in the package environment.');
    }

    if (! Schema::hasTable('activity_log')) {
        Schema::create('activity_log', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->string('event')->nullable();
            $table->json('attribute_changes')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
            $table->index('log_name');
        });
    }

    config()->set('ops-analytics.audit.driver', 'activitylog');
    app()->forgetInstance(OpsAnalyticsAuditWriter::class);

    $writer = app(OpsAnalyticsAuditWriter::class);

    expect($writer)->toBeInstanceOf(ActivityLogOpsAnalyticsAuditWriter::class);

    $writer->record(new AnalyticsPostureRefreshed(
        overallStatus: 'failed',
        healthyCount: 1,
        warningCount: 1,
        failingCount: 1,
        unsupportedCount: 1,
        blockedCount: 1,
        actorId: 7,
        source: 'ops_panel',
        completedAt: '2026-04-10T12:30:00+00:00',
    ));

    $activity = Activity::query()->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity?->log_name)->toBe('ops-analytics')
        ->and($activity?->event)->toBe('refreshed')
        ->and($activity?->description)->toBe('Ops analytics posture snapshot was refreshed.')
        ->and($activity?->getProperty('overall_status'))->toBe('failed')
        ->and($activity?->getProperty('blocked_count'))->toBe(1)
        ->and($activity?->getProperty('source'))->toBe('ops_panel');
});
