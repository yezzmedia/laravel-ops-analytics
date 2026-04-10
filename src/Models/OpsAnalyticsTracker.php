<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpsAnalyticsTracker extends Model
{
    protected $table = 'ops_analytics_trackers';

    protected $guarded = [];

    /**
     * @return HasMany<OpsAnalyticsDispatch, $this>
     */
    public function dispatches(): HasMany
    {
        return $this->hasMany(OpsAnalyticsDispatch::class, 'ops_analytics_tracker_id');
    }

    protected function casts(): array
    {
        return [
            'is_enabled' => 'bool',
            'configuration_summary' => 'array',
            'metadata' => 'array',
        ];
    }
}
