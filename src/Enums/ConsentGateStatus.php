<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Enums;

enum ConsentGateStatus: string
{
    case Allowed = 'allowed';
    case Blocked = 'blocked';
    case Unsupported = 'unsupported';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Allowed => 'Allowed',
            self::Blocked => 'Blocked',
            self::Unsupported => 'Unsupported',
            self::Unknown => 'Unknown',
        };
    }
}
