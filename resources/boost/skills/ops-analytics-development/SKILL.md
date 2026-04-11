---
name: ops-analytics-development
description: "Build and maintain yezzmedia/laravel-ops-analytics. Activate when changing tracker registration, dispatch posture, request traffic capture, analytics install or doctor flows, analytics Filament pages, audit integration, or package tests that depend on the approved ops analytics V1 surface."
license: MIT
metadata:
  author: yezzmedia
---

# Ops Analytics Development

## Documentation

Use `search-docs` for Laravel, Filament, Pest, Package Tools, and Boost details. Use the reference files in this skill for the approved analytics runtime surface.

Use the `foundation-package-development` skill when descriptor capability choices or foundation registration behavior change.

## When To Use This Skill

Activate this skill when working inside `yezzmedia/laravel-ops-analytics`, especially when changing one of these areas:

- tracker registration, built-in runtime tracker bootstrap, or tracker inventory state
- dispatch recording, dispatch posture aggregation, consent gating, or delivery-failure projection
- request capture middleware, built-in runtime tracker flows, or dispatch-facing aggregations
- analytics install steps, doctor checks, audit wiring, or package configuration boundaries
- analytics Filament pages or tracker detail workflows
- package tests that prove registration, install, runtime capture, or UI behavior

## Functional Workflow

1. Identify whether the change belongs to tracker metadata, dispatch posture, request traffic, install or doctor flow, or UI surface.
2. Read the matching reference file before editing package-owned public surface.
3. Keep write paths in actions and keep aggregation logic in resolvers or the manager.
4. Keep request capture sanitized and safe-by-default.
5. Verify both descriptor registration and runtime behavior with package tests.

## Core Rules

- Keep `OpsAnalyticsPlatformPackage` declarative and aligned with the real package surface.
- Keep `OpsAnalyticsServiceProvider` bindings in `packageRegistered()` and descriptor registration in `packageBooted()`.
- Keep built-in tracker bootstrap explicit through `DefaultRuntimeTracker` and `ConfigureDefaultRuntimeTrackerInstallStep`.
- Keep request capture middleware append-only and package-owned through `analytics.capture_request`.
- Keep dispatch posture, tracker health, and consent gating as the current operator-facing focus.
- Keep analytics UI operator-facing and technical rather than marketing-oriented.
- Keep audit writing optional and driven by configured package audit driver.
- Keep traffic pages safe when storage is unavailable.

## Testing Pattern

- Cover descriptor, install-step, doctor-check, feature, and ops-module registration in package tests.
- Cover middleware capture, dispatch recording, and built-in runtime tracker behavior in runtime tests.
- Cover Filament page headings, sections, and widgets in page/plugin feature tests.
- Run targeted analytics verification before broad suite runs.

## References

- Use [references/runtime-surface.md](references/runtime-surface.md) for the approved analytics package surface.
- Use [references/install-and-doctor.md](references/install-and-doctor.md) for install-step, doctor-check, and audit-driver rules.
- Use [references/traffic-and-dispatch.md](references/traffic-and-dispatch.md) for request capture, dispatch posture, and tracker runtime boundaries.
- Use [references/filament-surface.md](references/filament-surface.md) for the analytics cluster pages and UI ownership.
- Use [references/testing.md](references/testing.md) for verification expectations.
- Use [references/checklist.md](references/checklist.md) before finalizing analytics changes.

## Common Pitfalls

- mixing request capture concerns with dispatch-posture and tracker-health summaries
- changing tracker bootstrap behavior without keeping `DefaultRuntimeTracker` and install flow aligned
- adding analytics pages without updating ops-module declarations
- recording unsafe request context without using the package sanitization rules
- proving behavior only in the host instead of package tests
