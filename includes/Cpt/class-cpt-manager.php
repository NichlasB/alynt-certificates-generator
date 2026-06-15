<?php
/**
 * Custom post type registrations.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Cpt;

defined( 'ABSPATH' ) || exit;

/**
 * Registers plugin custom post types and related editor behavior.
 */
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
		$meta_manager = new Alynt_Certificate_Generator_Cpt_Meta_Manager();
		$meta_manager->register_meta();
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
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'alynt-certificate-generator',
			'capability_type' => 'post',
			'show_in_rest'    => true,
			'supports'        => array( 'title' ),
			'menu_icon'       => 'dashicons-awards',
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time CPT slug migration.
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
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'alynt-certificate-generator',
			'capability_type' => 'post',
			'show_in_rest'    => true,
			'supports'        => array( 'title' ),
		);

		\register_post_type( 'acg_email_template', $args );
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
			array( $this, 'disable_block_editor_for_template_post_type' ),
			999,
			2
		);

		\add_filter(
			'use_block_editor_for_post',
			array( $this, 'disable_block_editor_for_template_post' ),
			999,
			2
		);
	}

	/**
	 * Disable block editor for plugin template post types.
	 *
	 * @param bool   $should_use Current editor decision.
	 * @param string $post_type  Post type.
	 * @return bool
	 */
	public function disable_block_editor_for_template_post_type( bool $should_use, string $post_type ): bool {
		if ( in_array( $post_type, array( 'acg_cert_template', 'acg_email_template' ), true ) ) {
			return false;
		}

		return $should_use;
	}

	/**
	 * Disable block editor for plugin template posts.
	 *
	 * @param bool     $should_use Current editor decision.
	 * @param \WP_Post $post       Post object.
	 * @return bool
	 */
	public function disable_block_editor_for_template_post( bool $should_use, \WP_Post $post ): bool {
		return $this->disable_block_editor_for_template_post_type( $should_use, $post->post_type );
	}
}
