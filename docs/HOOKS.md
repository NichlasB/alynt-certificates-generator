# Alynt Certificate Generator Hooks

This document tracks public extension hooks exposed by the plugin.

## Current Status

The plugin currently exposes a small set of public `apply_filters()` extension points. No public `do_action()` extension hooks are currently exposed by the plugin source.

## Public Filters

### `alynt_certificate_generator_bulk_max_rows`

Filters the maximum number of CSV data rows accepted when a bulk certificate job starts.

- Type: filter
- Introduced: 0.2.2
- Default: `1000`
- Parameters:
  - `int $max_rows` Maximum number of data rows to process from the uploaded CSV.
- Return: `int`

Example:

```php
add_filter(
	'alynt_certificate_generator_bulk_max_rows',
	static function ( int $max_rows ): int {
		return 500;
	}
);
```

### `alynt_certificate_generator_webhook_max_items`

Filters the maximum number of items accepted in a single incoming webhook payload.

- Type: filter
- Introduced: 0.2.2
- Default: `25`
- Parameters:
  - `int $max_items` Maximum number of payload items.
  - `int $template_id` Certificate template post ID from the webhook route.
- Return: `int`

Example:

```php
add_filter(
	'alynt_certificate_generator_webhook_max_items',
	static function ( int $max_items, int $template_id ): int {
		return 10;
	},
	10,
	2
);
```

## Existing WordPress Integration Points

The plugin registers WordPress hooks internally for admin pages, custom post types, REST routes, shortcodes, activation/deactivation, scheduled bulk rows, and webhook retry processing. These internal hook registrations are implementation details and should not be treated as a stable public API yet.

## Planned Documentation Rule

When public hooks are added, document each hook here with:

- Hook name.
- Action or filter type.
- Version introduced.
- When it fires or what value it filters.
- Parameters and return value, when applicable.
- A short example.
