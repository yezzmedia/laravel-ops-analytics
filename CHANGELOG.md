# Changelog

All notable changes to `yezzmedia/laravel-ops-analytics` will be documented in this file.

The format is based on Keep a Changelog and this package follows Semantic Versioning.

## [Unreleased]

## [0.1.2] - 2026-04-13

### Fixed

- configured the published host `config/ops-analytics.php` file instead of the package source config during audit install setup
- accepted the analytics audit driver when it was already configured with the `activitylog` default to avoid failing repeat installs

## [0.1.1] - 2026-04-12

### Fixed

- required `yezzmedia/laravel-foundation:^0.1.1` so the published analytics package can rely on the shipped HTTP middleware declaration contract during bootstrap

### Documentation

- added the initial changelog for the analytics package and recorded the release-compatibility hotfix

## [0.1.0] - 2026-04-12

### Added

- ops-facing analytics delivery posture, tracker health, and consent-aware dispatch package bootstrap
- Foundation-aligned permissions, features, install steps, doctor checks, ops modules, and request-capture middleware declaration surface
