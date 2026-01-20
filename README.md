# Alynt Certificate Generator

Generate PDF certificates from image templates with dynamic variables, secure downloads, webhook integration, and email notifications.

## Requirements

- WordPress 6.0+
- PHP 7.4+
- GD extension
- Composer (for PHP dependencies)
- Node.js + npm (for admin/frontend assets)

## Installation

1. Upload the plugin to `wp-content/plugins/alynt-certificate-generator`.
2. Run `composer install`.
3. Run `npm install`.
4. Run `npm run build`.
5. Activate the plugin in WordPress.

## Development

- `npm run dev` - watch JS/CSS and rebuild assets
- `npm run build` - production build
- `npm run lint` - run PHPCS
- `npm run lint:fix` - auto-fix PHPCS issues where possible

## Notes

This plugin follows WordPress Coding Standards and uses Composer for server-side dependencies.
