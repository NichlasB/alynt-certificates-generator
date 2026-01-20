<?php
/**
 * Plugin Name: Alynt Certificate Generator
 * Description: Generate PDF certificates from image templates with dynamic variables and delivery automation.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Alynt
 * Text Domain: alynt-certificate-generator
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator;

defined( 'ABSPATH' ) || exit;

define( 'ALYNT_CERTIFICATE_GENERATOR_VERSION', '0.1.0' );
define( 'ALYNT_CERTIFICATE_GENERATOR_PLUGIN_FILE', __FILE__ );
define( 'ALYNT_CERTIFICATE_GENERATOR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'ALYNT_CERTIFICATE_GENERATOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ALYNT_CERTIFICATE_GENERATOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ALYNT_CERTIFICATE_GENERATOR_TEXT_DOMAIN', 'alynt-certificate-generator' );
define( 'ALYNT_CERTIFICATE_GENERATOR_SETTINGS_OPTION', 'alynt_certificate_generator_settings' );
define( 'ALYNT_CERTIFICATE_GENERATOR_DB_VERSION_OPTION', 'alynt_certificate_generator_db_version' );
define( 'ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE', 'manage_options' );
define( 'ALYNT_CERTIFICATE_GENERATOR_AS_GROUP', 'alynt_certificate_generator' );

/**
 * Optional debug notice to help diagnose missing menus.
 *
 * Enable by visiting any wp-admin page with `?acg_debug=1` (admins only).
 */
function alynt_certificate_generator_debug_notice(): void {
	if ( ! \is_admin() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Debug-only, read-only.
	if ( ! isset( $_GET['acg_debug'] ) ) {
		return;
	}

	if ( ! \current_user_can( 'manage_options' ) ) {
		return;
	}

	$autoload = ALYNT_CERTIFICATE_GENERATOR_PLUGIN_DIR . 'vendor/autoload.php';
	$has_autoload = file_exists( $autoload );
	$has_gd = extension_loaded( 'gd' );
	$can_manage = \current_user_can( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE );
	$can_manage_options = \current_user_can( 'manage_options' );
	$wp_version = (string) \get_bloginfo( 'version' );
	$php_version = PHP_VERSION;
	$meets_php = version_compare( $php_version, '7.4', '>=' );
	$meets_wp  = version_compare( $wp_version, '6.0', '>=' );
	$meets_requirements = $meets_php && $meets_wp && $has_gd;

	$roles = array();
	$user  = \wp_get_current_user();
	if ( $user && $user->ID ) {
		$roles = (array) $user->roles;
	}

	$menu_has_slug = false;
	$submenu_has_parent = false;
	// `$menu` / `$submenu` are populated after `admin_menu`.
	global $menu, $submenu;
	if ( is_array( $menu ) ) {
		foreach ( $menu as $item ) {
			if ( isset( $item[2] ) && 'alynt-certificate-generator' === (string) $item[2] ) {
				$menu_has_slug = true;
				break;
			}
		}
	}
	if ( isset( $submenu['alynt-certificate-generator'] ) && is_array( $submenu['alynt-certificate-generator'] ) ) {
		$submenu_has_parent = true;
	}

	$plugin_booted = ! empty( $GLOBALS['acg_plugin_booted'] );

	printf(
		'<div class="notice notice-info"><p><strong>%s</strong></p><ul style="margin-left:1.2em; list-style:disc;">' .
		'<li><strong>plugin_dir</strong>: <code>%s</code></li>' .
		'<li><strong>vendor/autoload.php</strong>: %s</li>' .
		'<li><strong>GD extension</strong>: %s</li>' .
		'<li><strong>WP/PHP</strong>: WP <code>%s</code>, PHP <code>%s</code> (requirements: %s)</li>' .
		'<li><strong>current_user</strong>: ID %s, roles: <code>%s</code></li>' .
		'<li><strong>current_user_can(manage_options)</strong>: %s</li>' .
		'<li><strong>current_user_can(%s)</strong>: %s</li>' .
		'<li><strong>plugin_core_booted</strong>: %s</li>' .
		'<li><strong>menu_slug_registered</strong>: %s</li>' .
		'<li><strong>submenu_registered</strong>: %s</li>' .
		'</ul><p><em>Tip: remove <code>?acg_debug=1</code> to hide this notice.</em></p></div>',
		esc_html__( 'Alynt Certificate Generator (debug)', 'alynt-certificate-generator' ),
		esc_html( ALYNT_CERTIFICATE_GENERATOR_PLUGIN_DIR ),
		$has_autoload ? '<span style="color:green;">present</span>' : '<span style="color:red;">missing</span>',
		$has_gd ? '<span style="color:green;">enabled</span>' : '<span style="color:red;">missing</span>',
		esc_html( $wp_version ),
		esc_html( $php_version ),
		$meets_requirements ? '<span style="color:green;">met</span>' : '<span style="color:red;">NOT met</span>',
		esc_html( $user && $user->ID ? (string) $user->ID : '0' ),
		esc_html( implode( ', ', array_map( 'sanitize_key', $roles ) ) ),
		$can_manage_options ? '<span style="color:green;">true</span>' : '<span style="color:red;">false</span>',
		esc_html( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE ),
		$can_manage ? '<span style="color:green;">true</span>' : '<span style="color:red;">false</span>',
		$plugin_booted ? '<span style="color:green;">yes</span>' : '<span style="color:red;">no</span>',
		$menu_has_slug ? '<span style="color:green;">yes</span>' : '<span style="color:red;">no</span>',
		$submenu_has_parent ? '<span style="color:green;">yes</span>' : '<span style="color:red;">no</span>'
	);
}

\add_action( 'admin_notices', __NAMESPACE__ . '\\alynt_certificate_generator_debug_notice' );
\add_action( 'network_admin_notices', __NAMESPACE__ . '\\alynt_certificate_generator_debug_notice' );

if ( ! alynt_certificate_generator_requirements_met() ) {
	return;
}

$autoload = ALYNT_CERTIFICATE_GENERATOR_PLUGIN_DIR . 'vendor/autoload.php';
if ( ! file_exists( $autoload ) ) {
	alynt_certificate_generator_set_admin_notice(
		'Alynt Certificate Generator requires Composer dependencies. Run composer install.'
	);
	return;
}

require_once $autoload;
require_once ALYNT_CERTIFICATE_GENERATOR_PLUGIN_DIR . 'includes/class-loader.php';
require_once ALYNT_CERTIFICATE_GENERATOR_PLUGIN_DIR . 'includes/class-activator.php';
require_once ALYNT_CERTIFICATE_GENERATOR_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once ALYNT_CERTIFICATE_GENERATOR_PLUGIN_DIR . 'includes/class-plugin.php';

\register_activation_hook(
	__FILE__,
	array( __NAMESPACE__ . '\\Alynt_Certificate_Generator_Activator', 'activate' )
);

\register_deactivation_hook(
	__FILE__,
	array( __NAMESPACE__ . '\\Alynt_Certificate_Generator_Deactivator', 'deactivate' )
);

function alynt_certificate_generator_run(): void {
	$plugin = new Alynt_Certificate_Generator_Plugin();
	$plugin->run();
}

alynt_certificate_generator_run();

function alynt_certificate_generator_requirements_met(): bool {
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		alynt_certificate_generator_set_admin_notice(
			'Alynt Certificate Generator requires PHP 7.4 or higher.'
		);
		return false;
	}

	if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
		alynt_certificate_generator_set_admin_notice(
			'Alynt Certificate Generator requires WordPress 6.0 or higher.'
		);
		return false;
	}

	if ( ! extension_loaded( 'gd' ) ) {
		alynt_certificate_generator_set_admin_notice(
			'Alynt Certificate Generator requires the GD extension to be enabled.'
		);
		return false;
	}

	return true;
}

function alynt_certificate_generator_set_admin_notice( string $message ): void {
	$GLOBALS['alynt_certificate_generator_admin_notice'] = $message;

	\add_action( 'admin_notices', __NAMESPACE__ . '\\alynt_certificate_generator_render_admin_notice' );
	\add_action( 'network_admin_notices', __NAMESPACE__ . '\\alynt_certificate_generator_render_admin_notice' );
}

function alynt_certificate_generator_render_admin_notice(): void {
	$message = '';
	if ( isset( $GLOBALS['alynt_certificate_generator_admin_notice'] ) ) {
		$message = (string) $GLOBALS['alynt_certificate_generator_admin_notice'];
	}

	if ( '' === $message ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html( $message )
	);
}
