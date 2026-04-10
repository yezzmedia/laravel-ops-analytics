<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

            $table->index(['driver', 'is_enabled']);
        });

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

            $table->index(['delivery_status', 'completed_at']);
            $table->index(['event_key', 'event_name']);
        });

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

            $table->unique(['ops_analytics_dispatch_id', 'attempt_number']);
            $table->index(['status', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_analytics_dispatch_attempts');
        Schema::dropIfExists('ops_analytics_dispatches');
        Schema::dropIfExists('ops_analytics_trackers');
    }
};
