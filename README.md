<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/yezzmedia/.github/main/profile/yezzmedia-dark.svg">
    <img src="https://raw.githubusercontent.com/yezzmedia/.github/main/profile/yezzmedia-light.svg" alt="Yezz Media" height="40">
  </picture>
</p>

<p align="center">
  <a href="https://packagist.org/packages/yezzmedia/laravel-ops-analytics"><img src="https://img.shields.io/packagist/v/yezzmedia/laravel-ops-analytics?style=flat-square" alt="Latest Version"></a>
  <a href="https://packagist.org/packages/yezzmedia/laravel-ops-analytics"><img src="https://img.shields.io/packagist/php-v/yezzmedia/laravel-ops-analytics?style=flat-square" alt="PHP Version"></a>
  <a href="https://packagist.org/packages/yezzmedia/laravel-ops-analytics"><img src="https://img.shields.io/packagist/l/yezzmedia/laravel-ops-analytics?style=flat-square" alt="License"></a>
</p>

---

# Laravel Ops &middot; Analytics

`yezzmedia/laravel-ops-analytics` provides analytics delivery posture monitoring, tracker health visibility, and consent-aware dispatch gating for the Yezz Media ops panel.

It registers runtime trackers, captures request traffic, reports dispatch failures, and integrates with `laravel-user-consent` for consent-aware event gating.

## Version

Current release: `0.2.0`

## Requirements

- PHP `^8.5`
- Laravel `^13.0` components
- `spatie/laravel-package-tools ^1.93`
- `yezzmedia/laravel-foundation ^0.2`
- `yezzmedia/laravel-ops ^0.2`

Optional:

- `yezzmedia/laravel-user-consent` — consent-aware dispatch gating

## Installation

```bash
composer require yezzmedia/laravel-ops-analytics
```

## What The Package Provides

### Tracker Registration

`TrackerRegistry` allows downstream packages to register analytics trackers. Registered trackers participate in dispatch posture monitoring and health visibility.

### Dispatch Posture

The package monitors analytics dispatch health:

- Tracks dispatch successes and failures per tracker
- Reports unresolved failures as actionable posture warnings
- Integrates with the ops panel diagnostics overview

### Consent-Aware Gating

When `laravel-user-consent` is installed, the package gates tracker dispatch on active consent decisions, respecting user privacy preferences.

### Install and Doctor

Foundation-aligned install steps and doctor checks ensure:

- Analytics migrations are published and applied
- Default runtime tracker is configured
- Dispatch health can be verified during diagnostics

### Ops Panel Integration

Provides a Filament plugin that surfaces:

- Analytics delivery posture in the ops panel summary
- Tracker health overview
- Consent-aware dispatch status

## Development

```bash
composer test
composer analyse
composer format
```

## License

MIT
