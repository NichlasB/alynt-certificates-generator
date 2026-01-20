<?php
/**
 * Custom post type registrations.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Cpt;

class Alynt_Certificate_Generator_Cpt_Manager {
	/**
	 * Register CPTs.
	 */
	public function register_post_types(): void {
		$this->register_certificate_template_cpt();
		$this->register_email_template_cpt();
		$this->maybe_migrate_certificate_templates();
		$this->disable_block_editor_for_templates();
	}

	/**
	 * Register meta fields.
	 */
	public function register_meta(): void {
		$this->register_certificate_template_meta();
		$this->register_email_template_meta();
	}

	/**
	 * Register certificate template CPT.
	 */
	private function register_certificate_template_cpt(): void {
		$labels = array(
			'name'               => __( 'Certificate Templates', 'alynt-certificate-generator' ),
			'singular_name'      => __( 'Certificate Template', 'alynt-certificate-generator' ),
			'add_new'            => __( 'Add New', 'alynt-certificate-generator' ),
			'add_new_item'       => __( 'Add New Template', 'alynt-certificate-generator' ),
			'edit_item'          => __( 'Edit Template', 'alynt-certificate-generator' ),
			'new_item'           => __( 'New Template', 'alynt-certificate-generator' ),
			'view_item'          => __( 'View Template', 'alynt-certificate-generator' ),
			'search_items'       => __( 'Search Templates', 'alynt-certificate-generator' ),
			'not_found'          => __( 'No templates found', 'alynt-certificate-generator' ),
			'not_found_in_trash' => __( 'No templates found in trash', 'alynt-certificate-generator' ),
			'menu_name'          => __( 'Certificate Templates', 'alynt-certificate-generator' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'alynt-certificate-generator',
			'capability_type'     => 'post',
			'show_in_rest'        => true,
			'supports'            => array( 'title' ),
			'menu_icon'           => 'dashicons-awards',
		);

		\register_post_type( 'acg_cert_template', $args );
	}

	/**
	 * Migrate legacy certificate template post type slug.
	 */
	private function maybe_migrate_certificate_templates(): void {
		$option_key = 'acg_cert_template_migrated';
		if ( \get_option( $option_key ) ) {
			return;
		}

		global $wpdb;
		$legacy_type = 'acg_certificate_template';
		$new_type    = 'acg_cert_template';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration.
		$wpdb->update(
			$wpdb->posts,
			array( 'post_type' => $new_type ),
			array( 'post_type' => $legacy_type ),
			array( '%s' ),
			array( '%s' )
		);

		\update_option( $option_key, 1 );
	}

	/**
	 * Register email template CPT.
	 */
	private function register_email_template_cpt(): void {
		$labels = array(
			'name'               => __( 'Email Templates', 'alynt-certificate-generator' ),
			'singular_name'      => __( 'Email Template', 'alynt-certificate-generator' ),
			'add_new'            => __( 'Add New', 'alynt-certificate-generator' ),
			'add_new_item'       => __( 'Add New Email Template', 'alynt-certificate-generator' ),
			'edit_item'          => __( 'Edit Email Template', 'alynt-certificate-generator' ),
			'new_item'           => __( 'New Email Template', 'alynt-certificate-generator' ),
			'view_item'          => __( 'View Email Template', 'alynt-certificate-generator' ),
			'search_items'       => __( 'Search Email Templates', 'alynt-certificate-generator' ),
			'not_found'          => __( 'No email templates found', 'alynt-certificate-generator' ),
			'not_found_in_trash' => __( 'No email templates found in trash', 'alynt-certificate-generator' ),
			'menu_name'          => __( 'Email Templates', 'alynt-certificate-generator' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'alynt-certificate-generator',
			'capability_type'     => 'post',
			'show_in_rest'        => true,
			'supports'            => array( 'title' ),
		);

		\register_post_type( 'acg_email_template', $args );
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
			return \wp_json_encode( $value );
		}

		if ( ! is_string( $value ) ) {
			return '';
		}

		$decoded = json_decode( $value, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return '';
		}

		return \wp_json_encode( $decoded );
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

	/**
	 * Disable block editor for certificate and email template CPTs.
	 *
	 * This ensures metabox form data is submitted via traditional POST,
	 * which is required for saving template variables and permissions.
	 */
	private function disable_block_editor_for_templates(): void {
		\add_filter(
			'use_block_editor_for_post_type',
			function ( bool $use, string $post_type ): bool {
				if ( in_array( $post_type, array( 'acg_cert_template', 'acg_email_template' ), true ) ) {
					return false;
				}
				return $use;
			},
			999,
			2
		);

		\add_filter(
			'use_block_editor_for_post',
			function ( bool $use, \WP_Post $post ): bool {
				if ( in_array( $post->post_type, array( 'acg_cert_template', 'acg_email_template' ), true ) ) {
					return false;
				}
				return $use;
			},
			999,
			2
		);
	}
}
