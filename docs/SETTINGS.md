# Alynt Certificate Generator Settings

This document defines the current settings schema and persistent storage used by Alynt Certificate Generator.

## Storage

- Main option: `alynt_certificate_generator_settings`
- Database schema version option: `alynt_certificate_generator_db_version`
- Global custom fonts option: `acg_custom_fonts`
- Per-template custom fonts post meta: `acg_template_fonts`
- Diagnostics event option: `acg_diagnostics_events`
- Certificate template post type: `acg_cert_template`
- Email template post type: `acg_email_template`

## Main Settings Schema

| Option key | Type | Default | Sanitization | Tab | Description |
| --- | --- | --- | --- | --- | --- |
| `pdf_storage_path` | string | `''` | `sanitize_text_field()` | General | Custom uploads subdirectory for generated PDFs. Blank uses the plugin default. |
| `default_date_format` | select | `Y-m-d` | Allowed option check | General | Format used for date variables and generation dates. |
| `certificate_id_prefix` | string | `ACG-` | `sanitize_text_field()` | General | Prefix applied to generated certificate IDs. |
| `certificate_id_format` | string | `{prefix}{id}` | `sanitize_text_field()` | General | Certificate ID pattern. Supports `{prefix}` and `{id}` placeholders. |
| `delete_data_on_uninstall` | boolean | `false` | Boolean cast | General | Removes templates and logs when the plugin is uninstalled. |
| `delete_files_on_uninstall` | boolean | `false` | Boolean cast | General | Removes generated PDFs when the plugin is uninstalled. |
| `webhook_rate_limit_per_minute` | integer | `100` | Integer cast, minimum `1` | Webhooks | Maximum incoming webhook requests per minute. |
| `webhook_retry_schedule` | string | `60,300,1800,7200` | `sanitize_text_field()` | Webhooks | Comma-separated outgoing webhook retry delays in seconds. |
| `webhook_signature_secret` | string | `''` | `sanitize_text_field()` | Webhooks | Secret used for HMAC verification. Blank disables signature checks. |
| `email_from_name` | string | Site name | `sanitize_text_field()` | Email | Default sender name for certificate emails. |
| `email_from_address` | email | Site admin email | `sanitize_email()` with fallback to default | Email | Default sender email address for certificate emails. |
| `email_footer` | textarea | `''` | `sanitize_textarea_field()` | Email | Footer content appended to certificate emails. |
| `log_retention_days` | integer | `365` | Integer cast, minimum `1` | Logs | Number of days to retain logs before cleanup. |
| `enable_csv_export` | boolean | `true` | Boolean cast | Logs | Enables CSV export from the logs screen. |
| `enable_bulk_cleanup` | boolean | `false` | Boolean cast | Logs | Enables bulk log cleanup tools. |
| `diagnostics_enabled` | boolean | `false` | Boolean cast | Diagnostics | Enables redacted diagnostic event storage for troubleshooting. |
| `diagnostics_min_level` | select | `warning` | Allowed option check | Diagnostics | Minimum severity stored in diagnostics. |
| `diagnostics_retention_days` | integer | `14` | Integer cast, minimum `1` | Diagnostics | Number of days to retain diagnostic events. |
| `diagnostics_max_events` | integer | `200` | Integer cast, minimum `10` | Diagnostics | Maximum diagnostic events kept in the option-backed ring buffer. |

## General

- PDF storage path and certificate ID formatting.
- Date formatting for automatic and generated date values.
- Uninstall cleanup toggles.

## Webhooks

- Incoming webhook rate limit.
- Outgoing webhook retry schedule.
- Optional HMAC signature secret.

## Email

- Default sender name and email address.
- Email footer content.

## Logs

- Log retention.
- CSV export toggle.
- Bulk cleanup toggle.

## Diagnostics

- Diagnostics are disabled by default.
- When enabled, the plugin stores redacted support events in the `acg_diagnostics_events` option.
- Stored events include timestamp, severity, category, event code, summary, redacted context, and a request identifier.
- The Diagnostics tab shows environment health, recent events, JSON export, and a nonce-protected clear action.
- Sensitive fields such as passwords, secrets, API keys, tokens, authorization headers, cookies, nonces, signatures, payload bodies, download tokens, and certificate variables are redacted before storage/export.
- Diagnostic cleanup is scheduled daily with the `alynt_certificate_generator_cleanup_diagnostics` hook and is also removed during uninstall.

## Fonts

The Fonts tab uses custom UI rather than schema fields in `alynt_certificate_generator_settings`.

| Storage key | Type | Scope | Description |
| --- | --- | --- | --- |
| `acg_custom_fonts` | array | Global option | Stores uploaded global custom font families and weight files. |
| `acg_template_fonts` | JSON string | Template post meta | Stores uploaded per-template custom font families and weight files. |
