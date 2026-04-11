# Filament Surface

The analytics Filament plugin currently owns these package surfaces:

- `OpsAnalyticsPage`
- `TrackerDetailsPage`

Keep this page pair aligned with the declared ops modules and analytics manager surface.

When changing analytics UI:

- keep overview and tracker-detail responsibilities separated
- keep tracker detail as drill-down surface rather than top-level navigation
- keep page content data-driven from the manager and current resolvers
