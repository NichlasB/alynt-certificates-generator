<?php
/**
 * Single certificate generation admin page.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi;

defined( 'ABSPATH' ) || exit;

use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Certificate_Service;
use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Image_Upload_Service;

class Alynt_Certificate_Generator_Single_Generator_Page {
	/**
	 * Page renderer.
	 *
	 * @var Alynt_Certificate_Generator_Single_Generator_Page_Renderer
	 */
	private $renderer;

	/**
	 * Image upload service.
	 *
	 * @var Alynt_Certificate_Generator_Image_Upload_Service
	 */
	private $image_upload_service;

	public function __construct( ?Alynt_Certificate_Generator_Image_Upload_Service $image_upload_service = null ) {
		$this->renderer             = new Alynt_Certificate_Generator_Single_Generator_Page_Renderer();
		$this->image_upload_service = null !== $image_upload_service ? $image_upload_service : new Alynt_Certificate_Generator_Image_Upload_Service();
	}

	/**
	 * Register admin-post handlers.
	 */
	public function register_actions(): void {
		\add_action( 'admin_post_acg_single_generate', array( $this, 'handle_generate' ) );
		\add_action( 'wp_ajax_acg_get_template_variables', array( $this, 'ajax_get_template_variables' ) );
	}

	/**
	 * Render single generation page.
	 */
	public function render_page(): void {
		if ( ! \current_user_can( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE ) ) {
			\wp_die( esc_html__( 'You do not have permission to access this page.', 'alynt-certificate-generator' ) );
		}

		$templates = \get_posts(
			array(
				'post_type'              => 'acg_cert_template',
				'post_status'            => 'any',
				'numberposts'            => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'suppress_filters'       => true,
			)
		);

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin page routing and notices.
		$selected_template = isset( $_GET['template_id'] ) ? absint( wp_unslash( $_GET['template_id'] ) ) : 0;
		$variables         = $selected_template > 0 ? $this->get_template_variables( $selected_template ) : array();
		$success_message   = '';
		$error_message     = '';

		// Check for success/error messages from redirect.
		if ( isset( $_GET['acg_generated'] ) && isset( $_GET['acg_certificate_id'] ) && isset( $_GET['acg_download_url'] ) ) {
			$certificate_id  = sanitize_text_field( wp_unslash( $_GET['acg_certificate_id'] ) );
			$download_url    = esc_url_raw( wp_unslash( $_GET['acg_download_url'] ) );
			$success_message = sprintf(
				/* translators: %s: certificate ID */
				__( 'Certificate %s generated successfully.', 'alynt-certificate-generator' ),
				'<strong>' . esc_html( $certificate_id ) . '</strong>'
			);
			$success_message .= ' <a href="' . esc_url( $download_url ) . '" class="button button-secondary" target="_blank">' . esc_html__( 'Download PDF', 'alynt-certificate-generator' ) . '</a>';
		}

		if ( isset( $_GET['acg_error'] ) ) {
			$error_message = sanitize_text_field( wp_unslash( $_GET['acg_error'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$this->renderer->render_page(
			$templates,
			$selected_template,
			$success_message,
			$error_message,
			$variables
		);
	}

	/**
	 * Handle certificate generation form submission.
	 */
	public function handle_generate(): void {
		if ( ! \current_user_can( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to generate certificates.', 'alynt-certificate-generator' ) );
		}

		$template_id = isset( $_POST['template_id'] ) ? absint( wp_unslash( $_POST['template_id'] ) ) : 0;
		if ( $template_id < 1 ) {
			wp_die( esc_html__( 'Template is required.', 'alynt-certificate-generator' ) );
		}

		check_admin_referer( 'acg_single_generate_' . $template_id );

		$template = \get_post( $template_id );
		if ( ! $template || 'acg_cert_template' !== $template->post_type ) {
			wp_die( esc_html__( 'Template not found.', 'alynt-certificate-generator' ) );
		}

		$uploaded_files = array();
		if ( ! empty( $_FILES ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File data is validated after nonce and capability checks.
			$uploaded_files = $_FILES;
		}

		$variables = $this->get_template_variables( $template_id );
		$values    = array();

		foreach ( $variables as $variable ) {
			$type     = $variable['type'] ?? 'text';
			$key      = $variable['key'] ?? '';
			$required = ! empty( $variable['required'] );

			if ( '' === $key || 'auto' === $type ) {
				continue;
			}

			$field_name = 'acg_var_' . $key;

			if ( 'image' === $type ) {
				$upload = $this->image_upload_service->handle_upload( $field_name, $required, $uploaded_files );
				if ( is_wp_error( $upload ) ) {
					$this->redirect_with_error( $template_id, $upload->get_error_message() );
				}
				$values[ $key ] = $upload;
			} else {
				$value = isset( $_POST[ $field_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) ) : '';
				if ( $required && '' === $value ) {
					$this->redirect_with_error( $template_id, __( 'Required fields must be completed.', 'alynt-certificate-generator' ) );
				}
				$values[ $key ] = $value;
			}
		}

		$skip_notifications = isset( $_POST['skip_notifications'] );
		$user_id            = get_current_user_id();
		$ip_address         = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		$service = new Alynt_Certificate_Generator_Certificate_Service();
		$result  = $service->generate(
			$template_id,
			$values,
			'admin',
			$user_id,
			$ip_address,
			$skip_notifications
		);

		$redirect_url = admin_url( 'admin.php?page=alynt-single-generator&template_id=' . $template_id );

		if ( is_wp_error( $result ) ) {
			$redirect_url = add_query_arg( 'acg_error', rawurlencode( $result->get_error_message() ), $redirect_url );
		} else {
			$redirect_url = add_query_arg(
				array(
					'acg_generated'      => '1',
					'acg_certificate_id' => rawurlencode( $result['certificate_id'] ),
					'acg_download_url'   => rawurlencode( $result['download_url'] ),
				),
				$redirect_url
			);
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Redirect back to the generator with an error message.
	 *
	 * @param int    $template_id Template ID.
	 * @param string $message     Error message.
	 */
	private function redirect_with_error( int $template_id, string $message ): void {
		$redirect_url = admin_url( 'admin.php?page=alynt-single-generator&template_id=' . $template_id );
		$redirect_url = add_query_arg( 'acg_error', rawurlencode( $message ), $redirect_url );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * AJAX handler to get template variables.
	 */
	public function ajax_get_template_variables(): void {
		check_ajax_referer( 'acg_admin_nonce', 'nonce' );

		if ( ! \current_user_can( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'alynt-certificate-generator' ) ), 403 );
		}

		$template_id = isset( $_POST['template_id'] ) ? absint( wp_unslash( $_POST['template_id'] ) ) : 0;
		if ( $template_id < 1 ) {
			wp_send_json_error( array( 'message' => __( 'Template ID required.', 'alynt-certificate-generator' ) ), 400 );
		}

		$variables = $this->get_template_variables( $template_id );
		wp_send_json_success( array( 'variables' => $variables ) );
	}

	/**
	 * Get template variables.
	 *
	 * @param int $template_id Template ID.
	 * @return array
	 */
	private function get_template_variables( int $template_id ): array {
		$raw     = (string) \get_post_meta( $template_id, 'acg_template_variables', true );
		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : array();
	}
}
