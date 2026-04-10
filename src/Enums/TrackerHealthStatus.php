<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Enums;

enum TrackerHealthStatus: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Failed = 'failed';
    case Unsupported = 'unsupported';

    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'Healthy',
            self::Degraded => 'Degraded',
            self::Failed => 'Failed',
            self::Unsupported => 'Unsupported',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Healthy => 'success',
            self::Degraded => 'warning',
            self::Failed => 'danger',
            self::Unsupported => 'gray',
        };
    }
}
