<?php
/**
 * Certificate log admin page.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi;

defined( 'ABSPATH' ) || exit;

use Alynt\CertificateGenerator\Database\Alynt_Certificate_Generator_Certificate_Log;
use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Email_Service;
use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Pdf_Service;
use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Webhook_Dispatcher;
use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Diagnostics_Logger;

class Alynt_Certificate_Generator_Certificate_Log_Page {
	/**
	 * Log service.
	 *
	 * @var Alynt_Certificate_Generator_Certificate_Log
	 */
	private $log;

	/**
	 * PDF service.
	 *
	 * @var Alynt_Certificate_Generator_Pdf_Service
	 */
	private $pdf_service;

	/**
	 * Email service.
	 *
	 * @var Alynt_Certificate_Generator_Email_Service
	 */
	private $email_service;

	/**
	 * Webhook dispatcher.
	 *
	 * @var Alynt_Certificate_Generator_Webhook_Dispatcher
	 */
	private $webhook_dispatcher;

	/**
	 * Page renderer.
	 *
	 * @var Alynt_Certificate_Generator_Certificate_Log_Page_Renderer
	 */
	private $renderer;

	/**
	 * CSV exporter.
	 *
	 * @var Alynt_Certificate_Generator_Certificate_Log_Exporter
	 */
	private $exporter;

	/**
	 * Action helper.
	 *
	 * @var Alynt_Certificate_Generator_Certificate_Log_Action_Helper
	 */
	private $action_helper;

	public function __construct() {
		$this->log                = new Alynt_Certificate_Generator_Certificate_Log();
		$this->pdf_service        = new Alynt_Certificate_Generator_Pdf_Service();
		$this->email_service      = new Alynt_Certificate_Generator_Email_Service();
		$this->webhook_dispatcher = new Alynt_Certificate_Generator_Webhook_Dispatcher();
		$this->renderer           = new Alynt_Certificate_Generator_Certificate_Log_Page_Renderer();
		$this->exporter           = new Alynt_Certificate_Generator_Certificate_Log_Exporter();
		$this->action_helper      = new Alynt_Certificate_Generator_Certificate_Log_Action_Helper();
	}

	/**
	 * Register admin-post handlers.
	 */
	public function register_actions(): void {
		\add_action( 'admin_post_acg_download_certificate', array( $this, 'handle_download' ) );
		\add_action( 'admin_post_acg_regenerate_certificate', array( $this, 'handle_regenerate' ) );
		\add_action( 'admin_post_acg_resend_emails', array( $this, 'handle_resend_emails' ) );
		\add_action( 'admin_post_acg_retry_webhook', array( $this, 'handle_retry_webhook' ) );
		\add_action( 'admin_post_acg_delete_log', array( $this, 'handle_delete' ) );
		\add_action( 'admin_post_acg_export_logs', array( $this, 'handle_export' ) );
	}

	/**
	 * Render the log page.
	 */
	public function render_page(): void {
		if ( ! \current_user_can( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE ) ) {
			\wp_die( esc_html__( 'You do not have permission to access this page.', 'alynt-certificate-generator' ) );
		}

		$this->handle_bulk_actions();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin page routing and notices.
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
		if ( 'detail' === $view && isset( $_GET['log_id'] ) ) {
			$this->render_detail_view( absint( wp_unslash( $_GET['log_id'] ) ) );
			return;
		}

		$list = new Alynt_Certificate_Generator_Certificate_Log_List();
		$list->prepare_items();

		$notice = isset( $_GET['acg_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['acg_notice'] ) ) : '';
		$error  = isset( $_GET['acg_error'] ) ? sanitize_text_field( wp_unslash( $_GET['acg_error'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$this->renderer->render_list_page( $list, $this->exporter->build_export_url(), $notice, $error );
	}

	/**
	 * Handle download action.
	 */
	public function handle_download(): void {
		$log_id = $this->get_log_id_from_request();
		$log    = $this->log->get_by_id( $log_id );
		if ( ! $log ) {
			wp_die( esc_html__( 'Log entry not found.', 'alynt-certificate-generator' ) );
		}

		$url = add_query_arg(
			'token',
			rawurlencode( (string) $log['download_token'] ),
			rest_url( 'acg/v1/certificates/' . rawurlencode( (string) $log['certificate_id'] ) . '/download' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Handle regenerate action.
	 */
	public function handle_regenerate(): void {
		$log_id = $this->get_log_id_from_request();
		$log    = $this->log->get_by_id( $log_id );
		if ( ! $log ) {
			wp_die( esc_html__( 'Log entry not found.', 'alynt-certificate-generator' ) );
		}

		$template_id   = (int) $log['template_id'];
		$image_id      = (int) \get_post_meta( $template_id, 'acg_template_image_id', true );
		$template_path = $image_id ? \get_attached_file( $image_id ) : '';
		$orientation   = (string) \get_post_meta( $template_id, 'acg_template_orientation', true );
		$orientation   = '' !== $orientation ? $orientation : 'landscape';
		if ( '' === $template_path || ! file_exists( $template_path ) ) {
			wp_die( esc_html__( 'Template image is missing.', 'alynt-certificate-generator' ) );
		}

		$variables = json_decode( (string) $log['variables_json'], true );
		if ( ! is_array( $variables ) ) {
			wp_die( esc_html__( 'Variables not found for this log entry.', 'alynt-certificate-generator' ) );
		}

		$output_path = $this->action_helper->build_output_path( $template_id, (string) $log['certificate_id'] );
		$result      = $this->pdf_service->render_pdf( $template_path, $variables, $orientation, $output_path );
		if ( is_wp_error( $result ) ) {
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'error',
				'admin_action',
				'certificate_regenerate_failed',
				'Admin certificate regeneration failed.',
				array(
					'log_id'      => $log_id,
					'template_id' => $template_id,
					'error_code'  => $result->get_error_code(),
				)
			);
			wp_die( esc_html( $result->get_error_message() ) );
		}

		$updated = $this->log->update_pdf_path( $log_id, $output_path );
		if ( is_wp_error( $updated ) ) {
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'error',
				'database',
				'certificate_regenerate_update_failed',
				'Regenerated certificate PDF path could not be saved.',
				array(
					'log_id'      => $log_id,
					'template_id' => $template_id,
					'error_code'  => $updated->get_error_code(),
				)
			);
			wp_safe_redirect( $this->redirect_url( 'acg_error', $updated->get_error_message() ) );
			exit;
		}

		wp_safe_redirect( $this->redirect_url( 'acg_notice', __( 'Certificate regenerated successfully.', 'alynt-certificate-generator' ) ) );
		exit;
	}

	/**
	 * Handle resend emails action.
	 */
	public function handle_resend_emails(): void {
		$log_id = $this->get_log_id_from_request();
		$result = $this->resend_emails( $log_id );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( $this->redirect_url( 'acg_error', $result->get_error_message() ) );
			exit;
		}

		wp_safe_redirect( $this->redirect_url( 'acg_notice', __( 'Emails resent successfully.', 'alynt-certificate-generator' ) ) );
		exit;
	}

	/**
	 * Handle retry webhook action.
	 */
	public function handle_retry_webhook(): void {
		$log_id = $this->get_log_id_from_request();
		$log    = $this->log->get_by_id( $log_id );
		if ( ! $log ) {
			wp_die( esc_html__( 'Log entry not found.', 'alynt-certificate-generator' ) );
		}

		$settings = $this->action_helper->get_webhook_settings( (int) $log['template_id'] );
		if ( empty( $settings['outgoing']['enabled'] ) || empty( $settings['outgoing']['url'] ) ) {
			wp_die( esc_html__( 'Outgoing webhook not configured.', 'alynt-certificate-generator' ) );
		}

		$variables = json_decode( (string) $log['variables_json'], true );
		if ( ! is_array( $variables ) ) {
			wp_die( esc_html__( 'Variables not found for this log entry.', 'alynt-certificate-generator' ) );
		}

		$result = $this->webhook_dispatcher->enqueue_outgoing(
			(int) $log['template_id'],
			$log_id,
			$this->action_helper->build_webhook_payload( $log, $variables ),
			$settings['outgoing']['url'],
			1
		);
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( $this->redirect_url( 'acg_error', $result->get_error_message() ) );
			exit;
		}

		wp_safe_redirect( $this->redirect_url( 'acg_notice', __( 'Webhook retry scheduled.', 'alynt-certificate-generator' ) ) );
		exit;
	}

	/**
	 * Handle delete action.
	 */
	public function handle_delete(): void {
		$log_id = $this->get_log_id_from_request();
		$result = $this->delete_log( $log_id );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( $this->redirect_url( 'acg_error', $result->get_error_message() ) );
			exit;
		}

		wp_safe_redirect( $this->redirect_url( 'acg_notice', __( 'Certificate log deleted.', 'alynt-certificate-generator' ) ) );
		exit;
	}

	/**
	 * Handle CSV export.
	 */
	public function handle_export(): void {
		$this->exporter->handle_export();
	}

	/**
	 * Handle bulk actions from list table.
	 */
	private function handle_bulk_actions(): void {
		if ( empty( $_POST['log_ids'] ) || ! is_array( $_POST['log_ids'] ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['action'] ?? '' ) );
		if ( '' === $action || '-1' === $action ) {
			$action = sanitize_key( wp_unslash( $_POST['action2'] ?? '' ) );
		}

		if ( '' === $action || '-1' === $action ) {
			return;
		}

		check_admin_referer( 'bulk-acg_certificate_logs' );

		$log_ids = array_map( 'absint', wp_unslash( $_POST['log_ids'] ) );

		if ( 'bulk_delete' === $action ) {
			foreach ( $log_ids as $log_id ) {
				$this->delete_log( $log_id );
			}
		}

		if ( 'bulk_resend' === $action ) {
			foreach ( $log_ids as $log_id ) {
				$this->resend_emails( $log_id );
			}
		}
	}

	/**
	 * Render detail view for a log entry.
	 *
	 * @param int $log_id Log ID.
	 */
	private function render_detail_view( int $log_id ): void {
		$log = $this->log->get_by_id( $log_id );
		if ( ! $log ) {
			$this->renderer->render_missing_detail();
			return;
		}

		$this->renderer->render_detail_view( $log );
	}

	/**
	 * Delete a log entry and associated file.
	 *
	 * @param int $log_id Log ID.
	 */
	private function delete_log( int $log_id ) {
		$log = $this->log->get_by_id( $log_id );
		if ( ! $log ) {
			return new \WP_Error( 'acg_log_missing', __( 'Log entry not found.', 'alynt-certificate-generator' ) );
		}

		if ( $log && ! empty( $log['pdf_path'] ) && $this->is_safe_pdf_path( (string) $log['pdf_path'] ) && file_exists( $log['pdf_path'] ) ) {
			if ( ! wp_delete_file( $log['pdf_path'] ) ) {
				Alynt_Certificate_Generator_Diagnostics_Logger::log(
					'error',
					'admin_action',
					'certificate_pdf_delete_failed',
					'Certificate PDF could not be deleted during log deletion.',
					array(
						'log_id' => $log_id,
					)
				);
				return new \WP_Error( 'acg_pdf_delete_failed', __( 'Certificate PDF could not be deleted.', 'alynt-certificate-generator' ) );
			}
		}

		$result = $this->log->delete( $log_id );
		if ( is_wp_error( $result ) ) {
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'error',
				'database',
				'certificate_log_delete_failed',
				'Certificate log row could not be deleted.',
				array(
					'log_id'     => $log_id,
					'error_code' => $result->get_error_code(),
				)
			);
		}

		return $result;
	}

	/**
	 * Resend emails for a log entry.
	 *
	 * @param int $log_id Log ID.
	 */
	private function resend_emails( int $log_id ) {
		$log = $this->log->get_by_id( $log_id );
		if ( ! $log ) {
			return new \WP_Error( 'acg_log_missing', __( 'Log entry not found.', 'alynt-certificate-generator' ) );
		}

		$variables = json_decode( (string) $log['variables_json'], true );
		if ( ! is_array( $variables ) ) {
			return new \WP_Error( 'acg_log_variables_missing', __( 'Variables not found for this log entry.', 'alynt-certificate-generator' ) );
		}

		$result = $this->email_service->send_for_log( $log, $variables, false );
		if ( is_wp_error( $result ) ) {
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'warning',
				'admin_action',
				'certificate_email_resend_failed',
				'Admin email resend failed.',
				array(
					'log_id'     => $log_id,
					'error_code' => $result->get_error_code(),
				)
			);
		}
		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Get log ID from request.
	 *
	 * @return int
	 */
	private function get_log_id_from_request(): int {
		if ( ! \current_user_can( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'alynt-certificate-generator' ) );
		}

		if ( ! isset( $_GET['log_id'] ) ) {
			wp_die( esc_html__( 'Log ID missing.', 'alynt-certificate-generator' ) );
		}

		$log_id = absint( wp_unslash( $_GET['log_id'] ) );
		check_admin_referer( 'acg_log_action_' . $log_id );

		return $log_id;
	}

	/**
	 * Get redirect URL.
	 *
	 * @param string $message_key Query argument key for the notice/error.
	 * @param string $message     Query argument value for the notice/error.
	 * @return string
	 */
	private function redirect_url( string $message_key = '', string $message = '' ): string {
		$url = admin_url( 'admin.php?page=alynt-certificate-logs' );
		if ( '' === $message_key || '' === $message ) {
			return $url;
		}

		return add_query_arg( $message_key, rawurlencode( $message ), $url );
	}

	/**
	 * Check that a stored PDF path stays inside WordPress uploads.
	 *
	 * @param string $file_path File path.
	 * @return bool
	 */
	private function is_safe_pdf_path( string $file_path ): bool {
		$upload_dir = wp_get_upload_dir();
		if ( empty( $upload_dir['basedir'] ) ) {
			return false;
		}

		$uploads_base = wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) );
		$normalized   = wp_normalize_path( $file_path );

		return 0 === strpos( $normalized, $uploads_base ) && 'pdf' === strtolower( pathinfo( $normalized, PATHINFO_EXTENSION ) );
	}
}
