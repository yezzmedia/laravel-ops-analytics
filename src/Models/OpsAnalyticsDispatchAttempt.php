<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpsAnalyticsDispatchAttempt extends Model
{
    protected $table = 'ops_analytics_dispatch_attempts';

    protected $guarded = [];

    /**
     * @return BelongsTo<OpsAnalyticsDispatch, $this>
     */
    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(OpsAnalyticsDispatch::class, 'ops_analytics_dispatch_id');
    }

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }
}
