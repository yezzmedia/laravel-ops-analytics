# Ops Analytics Testing Pattern

- Use package tests as the primary proof surface.
- Keep registration expectations in `RegistrationTest`.
- Keep install and store readiness behavior in `StoreAndInstallTest`.
- Keep runtime capture and action flows in `RuntimeActionsTest` and related action tests.
- Keep page and plugin assertions in `OpsAnalyticsPageTest` and `PluginAndDetailsTest`.
- Run `composer test:ops-analytics` from `/home/yezz/Developement/packages/1-dev-test` for targeted verification.
- Run `composer test:all` from `/home/yezz/Developement/packages/1-dev-test` before broad completion.
