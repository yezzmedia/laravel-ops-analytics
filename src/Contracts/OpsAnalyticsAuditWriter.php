<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Contracts;

interface OpsAnalyticsAuditWriter
{
    public function record(object $event): void;
}
