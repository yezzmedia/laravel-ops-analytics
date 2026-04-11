# Traffic And Dispatch Boundaries

## Request capture

- `analytics.capture_request` is the approved middleware declaration key.
- `CaptureAnalyticsRequest` owns package-side request capture.
- Keep request traffic sanitized, hashed, and config-driven.
- Keep excluded paths, statuses, and hashes driven by package config instead of hard-coded lists.

## Persistence boundaries

- Keep tracker and dispatch posture in the package analytics tables.
- Keep request capture routed through `TrackEventAction` and the existing dispatch recording flow on this branch.
- Avoid introducing a second request-event storage path unless the package surface is intentionally expanded.

## Built-in tracker

- Keep `DefaultRuntimeTracker` as the package-owned server-request tracker.
- Keep install flow and middleware capture aligned with that tracker.

## Feature and ops-module alignment

Current approved declarations include:

- features:
  - `analytics.dispatch`
  - `analytics.delivery_posture`
  - `analytics.tracker_health`
  - `analytics.consent_gating`
- ops modules:
  - `diagnostics.analytics.overview`
  - `diagnostics.analytics.detail`
