<?php
/**
 * Fired during plugin activation.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator;

use Alynt\CertificateGenerator\Database\Alynt_Certificate_Generator_Database;

class Alynt_Certificate_Generator_Activator {
	/**
	 * Run activation tasks.
	 */
	public static function activate(): void {
		self::verify_requirements();
		self::initialize_options();
		Alynt_Certificate_Generator_Database::migrate();
	}

	/**
	 * Ensure server requirements are met.
	 */
	private static function verify_requirements(): void {
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			\wp_die(
				esc_html__( 'Alynt Certificate Generator requires PHP 7.4 or higher.', 'alynt-certificate-generator' ),
				esc_html__( 'Plugin activation failed', 'alynt-certificate-generator' )
			);
		}

		if ( version_compare( \get_bloginfo( 'version' ), '6.0', '<' ) ) {
			\wp_die(
				esc_html__( 'Alynt Certificate Generator requires WordPress 6.0 or higher.', 'alynt-certificate-generator' ),
				esc_html__( 'Plugin activation failed', 'alynt-certificate-generator' )
			);
		}

		if ( ! extension_loaded( 'gd' ) ) {
			\wp_die(
				esc_html__( 'Alynt Certificate Generator requires the GD extension.', 'alynt-certificate-generator' ),
				esc_html__( 'Plugin activation failed', 'alynt-certificate-generator' )
			);
		}
	}

	/**
	 * Initialize plugin options.
	 */
	private static function initialize_options(): void {
		if ( false === \get_option( ALYNT_CERTIFICATE_GENERATOR_SETTINGS_OPTION ) ) {
			\add_option( ALYNT_CERTIFICATE_GENERATOR_SETTINGS_OPTION, array() );
		}
	}
}
