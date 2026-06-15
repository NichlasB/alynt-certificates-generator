# Alynt Certificate Generator

Generate PDF certificates from image templates with dynamic variables, secure downloads, webhook integration, and email notifications.

## Features

- Image-based certificate templates with positioned text, date, select, and image variables, including keyboard-operable positioning and row ordering controls.
- Admin single-certificate generation for one-off certificates.
- Bulk CSV generation with background row processing.
- Frontend shortcodes for certificate requests with field-level validation and logged-in user certificate lists.
- Secure download URLs backed by per-certificate tokens.
- Incoming webhook generation with optional HMAC signature verification and rate limiting.
- Outgoing webhook delivery with retry support through Action Scheduler when available.
- Email template support for certificate notifications.
- Global and per-template custom font management for PDF output.
- Certificate and webhook log storage in custom database tables.
- Optional admin-only diagnostics with redacted event storage, export, and clear controls.

## Requirements

- WordPress 6.0+
- PHP 7.4+
- GD extension
- Composer dependencies included at runtime:
  - `tecnickcom/tcpdf`
  - `woocommerce/action-scheduler`
- Node.js + npm for rebuilding admin/frontend assets from source

## Installation

1. Upload the plugin to `wp-content/plugins/alynt-certificates-generator`.
2. Ensure PHP dependencies are present:
   - For development: run `composer install`.
   - For release packages: include the production `vendor/` directory, especially `vendor/autoload.php`.
3. Ensure production assets are present in `assets/dist/`.
   - For development: run `npm install`, then `npm run build`.
4. Activate the plugin in WordPress.
5. Configure plugin settings from the Alynt Certificate Generator admin menu.

## Basic Usage

1. Create a certificate template under the certificate template post type.
2. Upload or select the template background image.
3. Add variables in the template builder and position them on the image.
4. Configure access, fonts, webhook behavior, and email behavior as needed.
5. Generate certificates from the single generator, the bulk CSV generator, the frontend shortcode, or the incoming webhook endpoint.

## Configuration

Main settings are stored in the `alynt_certificate_generator_settings` option and are grouped into General, Webhooks, Email, Logs, Fonts, and Diagnostics admin tabs.

See [docs/SETTINGS.md](docs/SETTINGS.md) for the current settings schema and [docs/HOOKS.md](docs/HOOKS.md) for extension-hook status.

## Shortcodes

- `[alynt_certificate_form template="123"]` renders a public certificate request form for an accessible template.
- `[alynt_my_certificates]` renders the logged-in user's generated certificate list.

## Developer Commands

- `npm run dev` - watch JS/CSS and rebuild assets.
- `npm run build` - build production assets.
- `npm run lint` - run PHPCS with the project ruleset.
- `npm run lint:fix` - auto-fix PHPCS issues where possible.
- `npm test` - run PHPUnit without caching the result.
- `npm run pot` - generate `languages/alynt-certificate-generator.pot`.

## Local Runtime Notes

The `plugin-tester local-only` LocalWP smoke test keeps a reusable local regression fixture:

- `ACG Smoke Test Template`
- `ACG Runtime Smoke Page`
- Generated certificate log rows for admin/frontend PDF download verification

Plugin Check is installed on the smoke-test site, but it is currently blocked there: Novamira reports WP-CLI is not installed or executable, and the wp-admin Plugin Check UI did not return a results panel during the runtime pass.

## Troubleshooting (no wp-admin menu visible)

- **Dependencies missing**: if `vendor/autoload.php` is missing, the plugin will not boot and no menu will be registered.
- **Capability missing**: the admin menu requires the built-in `manage_options` capability (administrators by default).
  - If your account uses a custom role, ensure it has `manage_options`.
- **Multisite**: the menu is registered in the **site dashboard** (not Network Admin).

## FAQ

### Does the plugin require Composer packages at runtime?

Yes. PDF generation depends on TCPDF and background scheduling can use Action Scheduler, so release packages must include production Composer dependencies.

### Where are generated certificates stored?

Generated PDFs are stored under the WordPress uploads directory. The exact subdirectory can be customized with the PDF storage path setting.

### Does the plugin include public extension hooks?

Yes, limited public filters are available for bulk CSV and incoming webhook batch limits. See [docs/HOOKS.md](docs/HOOKS.md) for the current hook inventory.

### How do diagnostics work?

Diagnostics are disabled by default. Administrators can enable them from the Diagnostics settings tab to store redacted support events for database, filesystem, webhook, bulk, REST, cron, and admin-action failures. The tab also provides a health summary, recent event viewer, JSON export, and clear action.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
