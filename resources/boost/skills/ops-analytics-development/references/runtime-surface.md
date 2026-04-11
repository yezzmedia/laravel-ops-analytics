# Approved V1 Ops Analytics Surface

The conservative analytics package surface includes these functional groups:

## Tracker and dispatch posture

- register tracker metadata and tracker-health posture
- record dispatch outcomes and aggregate delivery posture
- surface consent gating and recent delivery failures
- refresh aggregated analytics posture for operator review

## Request capture surface

- capture technical request metadata through package-owned middleware
- bootstrap the built-in `default-runtime` tracker for server request capture

## Operator-facing UI

- expose analytics pages for overview and tracker detail
- keep visibility technical and operator-facing

## Approved public types behind those functions

- `OpsAnalyticsPlatformPackage`
- `OpsAnalyticsServiceProvider`
- `OpsAnalyticsManager`
- `TrackerRegistry`
- `DefaultRuntimeTracker`
- `CaptureAnalyticsRequest`
- `TrackEventAction`
- `UpsertTrackerAction`
- `RecordDispatchOutcomeAction`
- `RefreshAnalyticsPostureAction`

Keep descriptor declarations, middleware declarations, and Filament pages aligned with this surface.
