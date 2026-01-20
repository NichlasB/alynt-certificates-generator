<?php
/**
 * Admin assets loader.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi;

class Alynt_Certificate_Generator_Admin_Assets {
	/**
	 * Enqueue admin assets on plugin pages.
	 *
	 * @param string $hook Current admin hook.
	 */
	public function enqueue_assets( string $hook ): void {
		$screen = \get_current_screen();
		if ( null === $screen ) {
			return;
		}

		$is_plugin_page = isset( $_GET['page'] ) && in_array(
			sanitize_key( wp_unslash( $_GET['page'] ) ),
			array( 'alynt-certificate-generator', 'alynt-certificate-logs', 'alynt-bulk-generator' ),
			true
		);

		if ( ! $is_plugin_page ) {
			return;
		}

		$script_path = ALYNT_CERTIFICATE_GENERATOR_PLUGIN_DIR . 'assets/dist/admin/index.js';
		$style_path  = ALYNT_CERTIFICATE_GENERATOR_PLUGIN_DIR . 'assets/dist/admin/index.css';

		if ( file_exists( $script_path ) ) {
			\wp_enqueue_script(
				'alynt-certificate-generator-admin',
				ALYNT_CERTIFICATE_GENERATOR_PLUGIN_URL . 'assets/dist/admin/index.js',
				array(),
				(string) filemtime( $script_path ),
				true
			);

			\wp_localize_script(
				'alynt-certificate-generator-admin',
				'acgAdmin',
				array(
					'restUrl'   => esc_url_raw( rest_url( 'acg/v1' ) ),
					'restNonce' => wp_create_nonce( 'wp_rest' ),
				)
			);
		}

		if ( file_exists( $style_path ) ) {
			\wp_enqueue_style(
				'alynt-certificate-generator-admin',
				ALYNT_CERTIFICATE_GENERATOR_PLUGIN_URL . 'assets/dist/admin/index.css',
				array(),
				(string) filemtime( $style_path )
			);
		}
	}
}
