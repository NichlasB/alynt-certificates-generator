=== Alynt Certificate Generator ===
Contributors: alynt
Tags: certificates, pdf, automation, webhooks
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate PDF certificates from image templates with dynamic fields, secure downloads, email delivery, and webhooks.

== Description ==

Alynt Certificate Generator creates PDF certificates from image-based templates. Administrators can define template variables, generate single certificates, process bulk CSV jobs, send email notifications, and connect incoming or outgoing webhook workflows.

Features include tokenized certificate downloads, custom fonts for PDF output, frontend shortcodes with field-level validation, keyboard-operable template-builder controls, template access controls, webhook retry support, certificate/webhook log screens, and optional admin-only diagnostics.

Frontend usage:
* `[alynt_certificate_form template="123"]` renders a public certificate request form for an accessible template.
* `[alynt_my_certificates]` renders the logged-in user's generated certificate list.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/alynt-certificates-generator/`.
2. Ensure bundled Composer dependencies are present, including `vendor/autoload.php`.
3. Build or include production assets from `assets/dist/`.
4. Activate the plugin through the WordPress Plugins screen.
5. Configure certificate settings under the Alynt Certificate Generator admin menu.

== Frequently Asked Questions ==

= Does this plugin require Composer packages at runtime? =

Yes. Release packages must include the production `vendor/` directory because PDF generation and background scheduling use Composer dependencies.

= Where are generated certificates stored? =

Generated files are stored under the WordPress uploads directory in the plugin certificate folder.

= Does this plugin expose public developer hooks? =

Yes. Limited public filters are available for bulk CSV and incoming webhook batch limits. The current hook inventory is documented in `docs/HOOKS.md`.

= How do diagnostics work? =

Diagnostics are disabled by default. Administrators can enable them from the Diagnostics settings tab to store redacted support events, review recent failures, export JSON diagnostics, and clear stored events.

== Changelog ==

= 0.2.3 =
* Production-readiness tooling and release infrastructure updates.
* Security, database lifecycle, uninstall cleanup, generated PDF path, webhook, and REST permission hardening.
* Optional admin-only diagnostics with redacted event storage, health summary, export, clear controls, and bounded retention.
* Runtime fixes for certificate log table rendering and frontend shortcode PDF generation.
* Template-builder keyboard alternatives for marker positioning and variable row reordering.
* Frontend field-level validation with preserved submitted text, inline errors, invalid-state markup, and first-error focus handling.
* Documentation refreshed for settings, hooks, installation, and release notes.

== Upgrade Notice ==

= 0.2.3 =
Validate settings, templates, generated PDFs, and webhook flows on a local or staging WordPress site before production use.
