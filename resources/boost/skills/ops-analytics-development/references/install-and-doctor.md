# Install And Doctor Rules

## Declared install steps

Keep the approved install surface aligned with `OpsAnalyticsPlatformPackage`:

- `PublishOpsAnalyticsMigrationsInstallStep`
- `EnsureOpsAnalyticsStoreReadyInstallStep`
- `ConfigureOpsAnalyticsAuditInstallStep`
- `ConfigureDefaultRuntimeTrackerInstallStep`

## Declared doctor checks

- `AnalyticsStoreReadyCheck`
- `TrackersConfiguredCheck`
- `DispatchDeliveryHealthyCheck`
- `ConsentIntegrationReadyCheck`

## Audit rules

- Keep audit persistence optional through `ops-analytics.audit.driver`.
- Support `null` and `activitylog` only unless the package plan changes.
- Keep audit listeners translating real analytics events instead of duplicating action logic.

## Store readiness

- Keep store checks explicit through `OpsAnalyticsStoreSetup`.
- Preserve safe behavior when request-event storage or analytics tables are unavailable.
