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
			'capability_type'     => 'acg_certificate_template',
			'capabilities'        => $this->get_capabilities(),
			'map_meta_cap'        => true,
			'show_in_rest'        => true,
			'supports'            => array( 'title' ),
			'menu_icon'           => 'dashicons-awards',
		);

		\register_post_type( 'acg_certificate_template', $args );
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
			'capability_type'     => 'acg_email_template',
			'capabilities'        => $this->get_capabilities(),
			'map_meta_cap'        => true,
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
			'acg_certificate_template',
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
			'acg_certificate_template',
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
			'acg_certificate_template',
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
			'acg_certificate_template',
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
			'acg_certificate_template',
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
	 * Build the capabilities map.
	 *
	 * @return array<string, string>
	 */
	private function get_capabilities(): array {
		$cap = ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE;

		return array(
			'edit_post'          => $cap,
			'read_post'          => $cap,
			'delete_post'        => $cap,
			'edit_posts'         => $cap,
			'edit_others_posts'  => $cap,
			'publish_posts'      => $cap,
			'read_private_posts' => $cap,
			'delete_posts'       => $cap,
			'delete_private_posts' => $cap,
			'delete_published_posts' => $cap,
			'delete_others_posts' => $cap,
			'edit_private_posts' => $cap,
			'edit_published_posts' => $cap,
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
}
