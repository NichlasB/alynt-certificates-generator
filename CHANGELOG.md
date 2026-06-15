# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

## [0.2.3] - 2026-06-15

### Changed

- Added keyboard alternatives in the template builder for marker positioning and variable row reordering.
- Added frontend field-level validation with preserved scalar values, inline errors, invalid-state markup, and focus handling after failed submissions.
- Completed production-readiness cleanup for database lifecycle handling, uninstall cleanup, release validation tooling, dependency audits, and project PHPCS rules.
- Documented the plugin's current public filters for bulk CSV and incoming webhook batch limits.
- Added an admin-only Diagnostics settings tab with redacted support events, health summary, JSON export, clear controls, and bounded retention.

### Fixed

- Removed duplicate REST orchestration and reduced oversized source files through extracted helper classes.
- Fixed the certificate log admin table so generated certificate rows render with their expected columns.
- Fixed frontend shortcode certificate generation by loading WordPress file helpers before creating temporary PDF template images outside wp-admin.
- Hardened generated PDF storage, download, deletion, and retention cleanup so stored PDF paths remain inside the WordPress uploads directory.
- Added lifecycle cleanup for expired logs, deleted templates, scheduled hooks, plugin-owned options, custom tables, transients, uploaded fonts, and generated certificate files.
- Added bounds for bulk CSV rows, webhook batch items, image variable uploads, template variables, and generated PDF image dimensions.

### Security

- Enforced HTTPS-only outgoing webhook URLs.
- Tightened REST permissions for bulk status and font/template management routes.
- Resolved PHPCS security/database blockers and refreshed npm and Composer dependencies so dependency audits pass cleanly.

## [0.2.2] - 2026-06-15

### Added

- Certificate template builder with variable positioning and custom font support.
- Single, bulk CSV, frontend shortcode, and incoming webhook certificate generation flows.
- Secure certificate download URLs with token validation.
- Email template and outgoing webhook delivery support.
- Certificate and webhook log tables with admin log views and CSV export.

### Changed

- Updated npm, Composer, PHPCS, PHPUnit, and release-support tooling for production-readiness validation.

### Security

- Added tokenized download flow and webhook signature support.
