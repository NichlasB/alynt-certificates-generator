<?php
/**
 * Plugin Name: Alynt Certificate Generator
 * Description: Generate PDF certificates from image templates with dynamic variables and delivery automation.
 * Version: 0.2.3
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Alynt
 * Text Domain: alynt-certificate-generator
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * GitHub Plugin URI: NichlasB/alynt-certificates-generator
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator;

defined( 'ABSPATH' ) || exit;

define( 'ALYNT_CERTIFICATE_GENERATOR_VERSION', '0.2.3' );
define( 'ALYNT_CERTIFICATE_GENERATOR_PLUGIN_FILE', __FILE__ );
define( 'ALYNT_CERTIFICATE_GENERATOR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'ALYNT_CERTIFICATE_GENERATOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ALYNT_CERTIFICATE_GENERATOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ALYNT_CERTIFICATE_GENERATOR_TEXT_DOMAIN', 'alynt-certificate-generator' );
define( 'ALYNT_CERTIFICATE_GENERATOR_SETTINGS_OPTION', 'alynt_certificate_generator_settings' );
define( 'ALYNT_CERTIFICATE_GENERATOR_DB_VERSION_OPTION', 'alynt_certificate_generator_db_version' );
define( 'ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE', 'manage_options' );
define( 'ALYNT_CERTIFICATE_GENERATOR_AS_GROUP', 'alynt_certificate_generator' );

if ( ! alynt_certificate_generator_requirements_met() ) {
	return;
}

$autoload = ALYNT_CERTIFICATE_GENERATOR_PLUGIN_DIR . 'vendor/autoload.php';
if ( ! file_exists( $autoload ) ) {
	alynt_certificate_generator_set_admin_notice(
		\__( 'Alynt Certificate Generator requires Composer dependencies. Run composer install.', 'alynt-certificate-generator' )
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
/**
 * Bootstrap the plugin runtime.
 *
 * @since 0.2.2
 *
 * @return void
 */
function alynt_certificate_generator_run(): void {

	$plugin = new Alynt_Certificate_Generator_Plugin();
	$plugin->run();
}

alynt_certificate_generator_run();
/**
 * Check the minimum runtime requirements before loading the plugin.
 *
 * @since 0.2.2
 *
 * @return bool True when the current WordPress/PHP environment can run the plugin.
 */
function alynt_certificate_generator_requirements_met(): bool {

	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		alynt_certificate_generator_set_admin_notice(
			\__( 'Alynt Certificate Generator requires PHP 7.4 or higher.', 'alynt-certificate-generator' )
		);
		return false;
	}

	if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
		alynt_certificate_generator_set_admin_notice(
			\__( 'Alynt Certificate Generator requires WordPress 6.0 or higher.', 'alynt-certificate-generator' )
		);
		return false;
	}

	if ( ! extension_loaded( 'gd' ) ) {
		alynt_certificate_generator_set_admin_notice(
			\__( 'Alynt Certificate Generator requires the GD extension to be enabled.', 'alynt-certificate-generator' )
		);
		return false;
	}

	return true;
}

/**
 * Store and register an admin notice for bootstrap-time failures.
 *
 * @since 0.2.2
 *
 * @param string $message Notice message.
 * @return void
 */
function alynt_certificate_generator_set_admin_notice( string $message ): void {

	$GLOBALS['alynt_certificate_generator_admin_notice'] = $message;

	\add_action( 'admin_notices', __NAMESPACE__ . '\\alynt_certificate_generator_render_admin_notice' );
	\add_action( 'network_admin_notices', __NAMESPACE__ . '\\alynt_certificate_generator_render_admin_notice' );
}

/**
 * Render the stored bootstrap admin notice.
 *
 * @since 0.2.2
 *
 * @return void
 */
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
