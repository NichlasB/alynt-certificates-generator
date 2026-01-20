<?php
/**
 * Admin settings handler placeholder.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi;

use Alynt\CertificateGenerator\Admin\Render\Alynt_Certificate_Generator_Field_Renderer;
use Alynt\CertificateGenerator\Admin\Settings\Alynt_Certificate_Generator_Admin_Settings_Schema;
use Alynt\CertificateGenerator\Admin\Settings\Sanitize\Alynt_Certificate_Generator_Settings_Sanitizer;
use Alynt\CertificateGenerator\AdminUi\Tabs\Alynt_Certificate_Generator_Tab_Email;
use Alynt\CertificateGenerator\AdminUi\Tabs\Alynt_Certificate_Generator_Tab_General;
use Alynt\CertificateGenerator\AdminUi\Tabs\Alynt_Certificate_Generator_Tab_Logs;
use Alynt\CertificateGenerator\AdminUi\Tabs\Alynt_Certificate_Generator_Tab_Webhooks;

class Alynt_Certificate_Generator_Settings {
	/**
	 * Settings schema.
	 *
	 * @var Alynt_Certificate_Generator_Admin_Settings_Schema
	 */
	private $schema;

	/**
	 * Settings sanitizer.
	 *
	 * @var Alynt_Certificate_Generator_Settings_Sanitizer
	 */
	private $sanitizer;

	public function __construct() {
		$this->schema    = new Alynt_Certificate_Generator_Admin_Settings_Schema();
		$this->sanitizer = new Alynt_Certificate_Generator_Settings_Sanitizer( $this->schema );
	}

	/**
	 * Register settings hooks.
	 */
	public function register(): void {
		\register_setting(
			'alynt_certificate_generator_settings_group',
			ALYNT_CERTIFICATE_GENERATOR_SETTINGS_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->schema->get_defaults(),
			)
		);

		foreach ( $this->get_tabs() as $tab ) {
			$section_id = $this->get_section_id( $tab->get_id() );
			$page_slug  = $this->get_page_slug( $tab->get_id() );

			\add_settings_section(
				$section_id,
				$tab->get_title(),
				array( $this, 'render_section' ),
				$page_slug,
				array(
					'tab_id'      => $tab->get_id(),
					'description' => $tab->get_description(),
				)
			);

			foreach ( $this->schema->get_fields_for_tab( $tab->get_id() ) as $key => $field ) {
				\add_settings_field(
					$key,
					$field['label'] ?? '',
					array( $this, 'render_field' ),
					$page_slug,
					$section_id,
					array(
						'key'   => $key,
						'field' => $field,
					)
				);
			}
		}
	}

	/**
	 * Render the settings page.
	 */
	public function render_page(): void {
		if ( ! \current_user_can( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE ) ) {
			\wp_die( esc_html__( 'You do not have permission to access this page.', 'alynt-certificate-generator' ) );
		}

		$tabs       = $this->get_tabs();
		$active_tab = $this->get_active_tab();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Alynt Certificate Generator', 'alynt-certificate-generator' ) . '</h1>';
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $tab ) {
			$is_active = $tab->get_id() === $active_tab;
			$url       = add_query_arg(
				array(
					'page' => 'alynt-certificate-generator',
					'tab'  => $tab->get_id(),
				),
				admin_url( 'admin.php' )
			);
			printf(
				'<a href="%1$s" class="nav-tab %2$s">%3$s</a>',
				esc_url( $url ),
				$is_active ? 'nav-tab-active' : '',
				esc_html( $tab->get_title() )
			);
		}
		echo '</nav>';

		$description = $this->get_tab_description( $active_tab );
		if ( '' !== $description ) {
			echo '<p>' . esc_html( $description ) . '</p>';
		}

		if ( 'email' === $active_tab && ! $this->has_smtp_plugin() ) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__(
				'No SMTP plugin detected. For reliable delivery, install and configure an SMTP plugin.',
				'alynt-certificate-generator'
			);
			echo '</p></div>';
		}

		echo '<form method="post" action="options.php">';
		settings_fields( 'alynt_certificate_generator_settings_group' );
		echo '<input type="hidden" name="alynt_active_tab" value="' . esc_attr( $active_tab ) . '" />';
		\do_settings_sections( $this->get_page_slug( $active_tab ) );
		submit_button();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render section description.
	 *
	 * @param array $args Section args.
	 */
	public function render_section( array $args ): void {
		if ( empty( $args['description'] ) ) {
			return;
		}

		echo '<p>' . esc_html( (string) $args['description'] ) . '</p>';
	}

	/**
	 * Render a field.
	 *
	 * @param array $args Field args.
	 */
	public function render_field( array $args ): void {
		$key   = $args['key'] ?? '';
		$field = $args['field'] ?? array();

		if ( '' === $key ) {
			return;
		}

		$options = $this->get_settings();
		$value   = $options[ $key ] ?? ( $field['default'] ?? '' );

		Alynt_Certificate_Generator_Field_Renderer::render_field(
			ALYNT_CERTIFICATE_GENERATOR_SETTINGS_OPTION,
			$key,
			$field,
			$value
		);
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_settings( array $input ): array {
		$current    = $this->get_settings();
		$active_tab = $this->get_active_tab( true );

		if ( ! $this->schema->is_valid_tab( $active_tab ) ) {
			$active_tab = 'general';
		}

		return $this->sanitizer->sanitize_for_tab( $input, $current, $active_tab );
	}

	/**
	 * Get current settings.
	 *
	 * @return array
	 */
	private function get_settings(): array {
		$settings = \get_option( ALYNT_CERTIFICATE_GENERATOR_SETTINGS_OPTION, array() );
		if ( ! is_array( $settings ) ) {
			return array();
		}

		return $settings;
	}

	/**
	 * Get active tab.
	 *
	 * @param bool $from_post Check POST first.
	 * @return string
	 */
	private function get_active_tab( bool $from_post = false ): string {
		if ( $from_post && isset( $_POST['alynt_active_tab'] ) ) {
			return sanitize_key( wp_unslash( $_POST['alynt_active_tab'] ) );
		}

		if ( isset( $_GET['tab'] ) ) {
			return sanitize_key( wp_unslash( $_GET['tab'] ) );
		}

		return 'general';
	}

	/**
	 * Get tab description.
	 *
	 * @param string $tab_id Tab ID.
	 * @return string
	 */
	private function get_tab_description( string $tab_id ): string {
		foreach ( $this->get_tabs() as $tab ) {
			if ( $tab->get_id() === $tab_id ) {
				return $tab->get_description();
			}
		}

		return '';
	}

	/**
	 * Get tab list.
	 *
	 * @return array
	 */
	private function get_tabs(): array {
		return array(
			new Alynt_Certificate_Generator_Tab_General(),
			new Alynt_Certificate_Generator_Tab_Webhooks(),
			new Alynt_Certificate_Generator_Tab_Email(),
			new Alynt_Certificate_Generator_Tab_Logs(),
		);
	}

	/**
	 * Build section ID for a tab.
	 *
	 * @param string $tab Tab ID.
	 * @return string
	 */
	private function get_section_id( string $tab ): string {
		return 'alynt_certificate_generator_section_' . $tab;
	}

	/**
	 * Build page slug for a tab.
	 *
	 * @param string $tab Tab ID.
	 * @return string
	 */
	private function get_page_slug( string $tab ): string {
		return 'alynt_certificate_generator_settings_' . $tab;
	}

	/**
	 * Detect common SMTP plugins.
	 *
	 * @return bool
	 */
	private function has_smtp_plugin(): bool {
		$known = array(
			'wp-mail-smtp/wp_mail_smtp.php',
			'easy-wp-smtp/easy-wp-smtp.php',
			'post-smtp/postman-smtp.php',
			'wp-smtp/wp-smtp.php',
		);

		$active_plugins = (array) \get_option( 'active_plugins', array() );
		$network_active = (array) \get_site_option( 'active_sitewide_plugins', array() );

		foreach ( $known as $plugin_file ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				return true;
			}
			if ( isset( $network_active[ $plugin_file ] ) ) {
				return true;
			}
		}

		return false;
	}
}
