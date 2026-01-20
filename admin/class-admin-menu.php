<?php
/**
 * Admin menu registration.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi;

class Alynt_Certificate_Generator_Admin_Menu {
	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	public function __construct( string $plugin_name, string $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the admin menu.
	 */
	public function register_menu(): void {
		\add_menu_page(
			__( 'Alynt Certificates', 'alynt-certificate-generator' ),
			__( 'Certificates', 'alynt-certificate-generator' ),
			ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE,
			'alynt-certificate-generator',
			array( $this, 'render_settings_page' ),
			'dashicons-awards',
			58
		);

		\add_submenu_page(
			'alynt-certificate-generator',
			__( 'Certificate Logs', 'alynt-certificate-generator' ),
			__( 'Logs', 'alynt-certificate-generator' ),
			ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE,
			'alynt-certificate-logs',
			array( $this, 'render_logs_page' )
		);

		\add_submenu_page(
			'alynt-certificate-generator',
			__( 'Bulk Generation', 'alynt-certificate-generator' ),
			__( 'Bulk Generate', 'alynt-certificate-generator' ),
			ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE,
			'alynt-bulk-generator',
			array( $this, 'render_bulk_page' )
		);
	}

	/**
	 * Render the settings page placeholder.
	 */
	public function render_settings_page(): void {
		if ( ! \current_user_can( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE ) ) {
			\wp_die(
				esc_html__( 'You do not have permission to access this page.', 'alynt-certificate-generator' )
			);
		}

		$settings = new Alynt_Certificate_Generator_Settings();
		$settings->render_page();
	}

	/**
	 * Render the certificate logs page.
	 */
	public function render_logs_page(): void {
		$page = new Alynt_Certificate_Generator_Certificate_Log_Page();
		$page->render_page();
	}

	/**
	 * Render the bulk generator page.
	 */
	public function render_bulk_page(): void {
		$page = new Alynt_Certificate_Generator_Bulk_Generator_Page();
		$page->render_page();
	}
}
