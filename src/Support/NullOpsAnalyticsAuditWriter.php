<?php

declare(strict_types=1);

namespace YezzMedia\OpsAnalytics\Support;

use YezzMedia\OpsAnalytics\Contracts\OpsAnalyticsAuditWriter;

final class NullOpsAnalyticsAuditWriter implements OpsAnalyticsAuditWriter
{
    public function record(object $event): void {}
}
