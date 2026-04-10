<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpsAnalyticsDispatch extends Model
{
    protected $table = 'ops_analytics_dispatches';

    protected $guarded = [];

    /**
     * @return BelongsTo<OpsAnalyticsTracker, $this>
     */
    public function tracker(): BelongsTo
    {
        return $this->belongsTo(OpsAnalyticsTracker::class, 'ops_analytics_tracker_id');
    }

    /**
     * @return HasMany<OpsAnalyticsDispatchAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(OpsAnalyticsDispatchAttempt::class, 'ops_analytics_dispatch_id');
    }

    protected function casts(): array
    {
        return [
            'queued_at' => 'immutable_datetime',
            'dispatched_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'last_success_at' => 'immutable_datetime',
            'last_failure_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }
}
