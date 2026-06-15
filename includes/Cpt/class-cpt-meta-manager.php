<?php
/**
 * Custom post type meta registrations.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Cpt;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and sanitizes plugin CPT meta fields.
 */
class Alynt_Certificate_Generator_Cpt_Meta_Manager {
	/**
	 * Register meta fields.
	 */
	public function register_meta(): void {
		$this->register_certificate_template_meta();
		$this->register_email_template_meta();
	}

	/**
	 * Register certificate template meta.
	 */
	private function register_certificate_template_meta(): void {
		\register_post_meta(
			'acg_cert_template',
			'acg_template_image_id',
			array(
				'type'              => 'integer',
				'single'            => true,
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
				'auth_callback'     => array( $this, 'can_manage_templates' ),
			)
		);

		\register_post_meta(
			'acg_cert_template',
			'acg_template_orientation',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( $this, 'sanitize_orientation' ),
				'show_in_rest'      => true,
				'auth_callback'     => array( $this, 'can_manage_templates' ),
			)
		);

		\register_post_meta(
			'acg_cert_template',
			'acg_template_variables',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( $this, 'sanitize_json_string' ),
				'show_in_rest'      => true,
				'auth_callback'     => array( $this, 'can_manage_templates' ),
			)
		);

		\register_post_meta(
			'acg_cert_template',
			'acg_template_permissions',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( $this, 'sanitize_json_string' ),
				'show_in_rest'      => true,
				'auth_callback'     => array( $this, 'can_manage_templates' ),
			)
		);

		\register_post_meta(
			'acg_cert_template',
			'acg_template_webhook_settings',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( $this, 'sanitize_json_string' ),
				'show_in_rest'      => true,
				'auth_callback'     => array( $this, 'can_manage_templates' ),
			)
		);
	}

	/**
	 * Register email template meta.
	 */
	private function register_email_template_meta(): void {
		\register_post_meta(
			'acg_email_template',
			'acg_email_enabled',
			array(
				'type'              => 'boolean',
				'single'            => true,
				'sanitize_callback' => array( $this, 'sanitize_boolean' ),
				'show_in_rest'      => true,
				'auth_callback'     => array( $this, 'can_manage_templates' ),
			)
		);

		\register_post_meta(
			'acg_email_template',
			'acg_email_to',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'auth_callback'     => array( $this, 'can_manage_templates' ),
			)
		);

		\register_post_meta(
			'acg_email_template',
			'acg_email_subject',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'auth_callback'     => array( $this, 'can_manage_templates' ),
			)
		);

		\register_post_meta(
			'acg_email_template',
			'acg_email_body',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => 'wp_kses_post',
				'show_in_rest'      => true,
				'auth_callback'     => array( $this, 'can_manage_templates' ),
			)
		);

		\register_post_meta(
			'acg_email_template',
			'acg_email_attach_pdf',
			array(
				'type'              => 'boolean',
				'single'            => true,
				'sanitize_callback' => array( $this, 'sanitize_boolean' ),
				'show_in_rest'      => true,
				'auth_callback'     => array( $this, 'can_manage_templates' ),
			)
		);

		\register_post_meta(
			'acg_email_template',
			'acg_email_template_id',
			array(
				'type'              => 'integer',
				'single'            => true,
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
				'auth_callback'     => array( $this, 'can_manage_templates' ),
			)
		);
	}

	/**
	 * Sanitize orientation value.
	 *
	 * @param string $value Orientation.
	 * @return string
	 */
	public function sanitize_orientation( string $value ): string {
		$allowed = array( 'landscape', 'portrait' );
		if ( ! in_array( $value, $allowed, true ) ) {
			return 'landscape';
		}

		return $value;
	}

	/**
	 * Sanitize JSON string values.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_json_string( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return (string) \wp_json_encode( $value );
		}

		if ( ! is_string( $value ) ) {
			return '';
		}

		$decoded = json_decode( $value, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return '';
		}

		return (string) \wp_json_encode( $decoded );
	}

	/**
	 * Sanitize boolean values.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public function sanitize_boolean( $value ): bool {
		return (bool) $value;
	}

	/**
	 * Check if the current user can manage templates.
	 *
	 * @return bool
	 */
	public function can_manage_templates(): bool {
		return \current_user_can( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE );
	}
}
