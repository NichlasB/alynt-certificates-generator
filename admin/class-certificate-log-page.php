<?php
/**
 * Certificate log admin page.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi;

use Alynt\CertificateGenerator\Database\Alynt_Certificate_Generator_Certificate_Log;
use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Email_Service;
use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Pdf_Service;
use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Webhook_Dispatcher;

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

	public function __construct() {
		$this->log                = new Alynt_Certificate_Generator_Certificate_Log();
		$this->pdf_service         = new Alynt_Certificate_Generator_Pdf_Service();
		$this->email_service       = new Alynt_Certificate_Generator_Email_Service();
		$this->webhook_dispatcher  = new Alynt_Certificate_Generator_Webhook_Dispatcher();
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

		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
		if ( 'detail' === $view && isset( $_GET['log_id'] ) ) {
			$this->render_detail_view( absint( wp_unslash( $_GET['log_id'] ) ) );
			return;
		}

		$list = new Alynt_Certificate_Generator_Certificate_Log_List();
		$list->prepare_items();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Certificate Logs', 'alynt-certificate-generator' ) . '</h1>';
		echo '<p>';
		echo '<a class="button" href="' . esc_url( $this->build_export_url() ) . '">' . esc_html__( 'Export CSV', 'alynt-certificate-generator' ) . '</a>';
		echo '</p>';

		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="alynt-certificate-logs" />';
		$list->display();
		echo '</form>';
		echo '</div>';
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
			echo '<div class="wrap"><p>' . esc_html__( 'Log entry not found.', 'alynt-certificate-generator' ) . '</p></div>';
			return;
		}

		$variables = json_decode( (string) $log['variables_json'], true );
		$email_status = $log['email_status_json'] ?? '';

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Certificate Log Detail', 'alynt-certificate-generator' ) . '</h1>';
		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=alynt-certificate-logs' ) ) . '">' . esc_html__( 'Back to Logs', 'alynt-certificate-generator' ) . '</a></p>';

		echo '<table class="widefat striped">';
		echo '<tbody>';
		echo '<tr><th>' . esc_html__( 'Certificate ID', 'alynt-certificate-generator' ) . '</th><td>' . esc_html( (string) $log['certificate_id'] ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Template', 'alynt-certificate-generator' ) . '</th><td>' . esc_html( get_the_title( (int) $log['template_id'] ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Method', 'alynt-certificate-generator' ) . '</th><td>' . esc_html( (string) $log['method'] ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Generated By', 'alynt-certificate-generator' ) . '</th><td>' . esc_html( (string) $log['generated_by'] ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'IP Address', 'alynt-certificate-generator' ) . '</th><td>' . esc_html( (string) $log['ip_address'] ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Created At', 'alynt-certificate-generator' ) . '</th><td>' . esc_html( (string) $log['created_at'] ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Email Status', 'alynt-certificate-generator' ) . '</th><td><pre>' . esc_html( (string) $email_status ) . '</pre></td></tr>';
		echo '<tr><th>' . esc_html__( 'Webhook Status', 'alynt-certificate-generator' ) . '</th><td>' . esc_html( (string) $log['webhook_status'] ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'PDF Path', 'alynt-certificate-generator' ) . '</th><td><code>' . esc_html( (string) $log['pdf_path'] ) . '</code></td></tr>';
		echo '</tbody>';
		echo '</table>';

		if ( is_array( $variables ) ) {
			echo '<h2>' . esc_html__( 'Variables', 'alynt-certificate-generator' ) . '</h2>';
			echo '<pre>' . esc_html( wp_json_encode( $variables, JSON_PRETTY_PRINT ) ) . '</pre>';
		}

		echo '</div>';
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

		$template_id = (int) $log['template_id'];
		$image_id = (int) \get_post_meta( $template_id, 'acg_template_image_id', true );
		$template_path = $image_id ? \get_attached_file( $image_id ) : '';
		$orientation = (string) \get_post_meta( $template_id, 'acg_template_orientation', true );
		$orientation = '' !== $orientation ? $orientation : 'landscape';

		$variables = json_decode( (string) $log['variables_json'], true );
		if ( ! is_array( $variables ) ) {
			wp_die( esc_html__( 'Variables not found for this log entry.', 'alynt-certificate-generator' ) );
		}

		$output_path = $this->build_output_path( $template_id, (string) $log['certificate_id'] );
		$result = $this->pdf_service->render_pdf( $template_path, $variables, $orientation, $output_path );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		$this->log->update_pdf_path( $log_id, $output_path );
		wp_safe_redirect( $this->redirect_url() );
		exit;
	}

	/**
	 * Handle resend emails action.
	 */
	public function handle_resend_emails(): void {
		$log_id = $this->get_log_id_from_request();
		$this->resend_emails( $log_id );
		wp_safe_redirect( $this->redirect_url() );
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

		$settings = $this->get_webhook_settings( (int) $log['template_id'] );
		if ( empty( $settings['outgoing']['enabled'] ) || empty( $settings['outgoing']['url'] ) ) {
			wp_die( esc_html__( 'Outgoing webhook not configured.', 'alynt-certificate-generator' ) );
		}

		$variables = json_decode( (string) $log['variables_json'], true );
		if ( ! is_array( $variables ) ) {
			wp_die( esc_html__( 'Variables not found for this log entry.', 'alynt-certificate-generator' ) );
		}

		$payload = array(
			'certificate_id' => $log['certificate_id'],
			'template_id'    => (int) $log['template_id'],
			'generated_at'   => $log['created_at'],
			'download_url'   => add_query_arg(
				'token',
				rawurlencode( (string) $log['download_token'] ),
				rest_url( 'acg/v1/certificates/' . rawurlencode( (string) $log['certificate_id'] ) . '/download' )
			),
			'variables'      => $this->build_variable_map( $variables ),
		);

		$this->webhook_dispatcher->enqueue_outgoing(
			(int) $log['template_id'],
			$log_id,
			$payload,
			$settings['outgoing']['url'],
			1
		);

		wp_safe_redirect( $this->redirect_url() );
		exit;
	}

	/**
	 * Handle delete action.
	 */
	public function handle_delete(): void {
		$log_id = $this->get_log_id_from_request();
		$this->delete_log( $log_id );
		wp_safe_redirect( $this->redirect_url() );
		exit;
	}

	/**
	 * Handle CSV export.
	 */
	public function handle_export(): void {
		if ( ! \current_user_can( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to export logs.', 'alynt-certificate-generator' ) );
		}

		check_admin_referer( 'acg_export_logs' );

		$filters = array(
			'template_id'    => isset( $_GET['template_id'] ) ? absint( wp_unslash( $_GET['template_id'] ) ) : 0,
			'date_from'      => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'        => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'webhook_status' => isset( $_GET['webhook_status'] ) ? sanitize_key( wp_unslash( $_GET['webhook_status'] ) ) : '',
		);

		$items = $this->query_logs_for_export( $filters );

		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="acg-certificate-logs.csv"' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'ID', 'Certificate ID', 'Template', 'Method', 'User', 'Date', 'Email Status', 'Webhook Status' ) );

		foreach ( $items as $item ) {
			$user_label = '';
			if ( ! empty( $item['user_id'] ) ) {
				$user = get_userdata( (int) $item['user_id'] );
				$user_label = $user ? $user->display_name : '';
			} else {
				$user_label = (string) $item['generated_by'];
			}

			fputcsv(
				$output,
				array(
					$item['id'],
					$item['certificate_id'],
					get_the_title( (int) $item['template_id'] ),
					$item['method'],
					$user_label,
					$item['created_at'],
					$item['email_status_json'],
					$item['webhook_status'],
				)
			);
		}

		fclose( $output );
		exit;
	}

	/**
	 * Delete a log entry and associated file.
	 *
	 * @param int $log_id Log ID.
	 */
	private function delete_log( int $log_id ): void {
		$log = $this->log->get_by_id( $log_id );
		if ( $log && ! empty( $log['pdf_path'] ) && file_exists( $log['pdf_path'] ) ) {
			unlink( $log['pdf_path'] );
		}

		$this->log->delete( $log_id );
	}

	/**
	 * Resend emails for a log entry.
	 *
	 * @param int $log_id Log ID.
	 */
	private function resend_emails( int $log_id ): void {
		$log = $this->log->get_by_id( $log_id );
		if ( ! $log ) {
			return;
		}

		$variables = json_decode( (string) $log['variables_json'], true );
		if ( ! is_array( $variables ) ) {
			return;
		}

		$this->email_service->send_for_log( $log, $variables, false );
	}

	/**
	 * Build output path for regenerated PDFs.
	 *
	 * @param int    $template_id Template ID.
	 * @param string $certificate_id Certificate ID.
	 * @return string
	 */
	private function build_output_path( int $template_id, string $certificate_id ): string {
		$upload_dir = \wp_upload_dir();
		$base_dir   = trailingslashit( $upload_dir['basedir'] ) . 'alynt-certificates/' . gmdate( 'Y' ) . '/' . gmdate( 'm' ) . '/';
		\wp_mkdir_p( $base_dir );

		$template_slug = \sanitize_title( \get_the_title( $template_id ) );
		if ( '' === $template_slug ) {
			$template_slug = 'template';
		}

		$filename = sprintf( '%s-%s-%s.pdf', $template_slug, $certificate_id, time() );
		return $base_dir . $filename;
	}

	/**
	 * Build variable map for webhook payloads.
	 *
	 * @param array $variables Resolved variables.
	 * @return array
	 */
	private function build_variable_map( array $variables ): array {
		$map = array();
		foreach ( $variables as $variable ) {
			if ( isset( $variable['key'] ) ) {
				$map[ (string) $variable['key'] ] = $variable['value'] ?? '';
			}
		}

		return $map;
	}

	/**
	 * Build export URL with current filters.
	 *
	 * @return string
	 */
	private function build_export_url(): string {
		$args = array(
			'action'         => 'acg_export_logs',
			'template_id'    => isset( $_GET['template_id'] ) ? absint( wp_unslash( $_GET['template_id'] ) ) : '',
			'date_from'      => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'        => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'webhook_status' => isset( $_GET['webhook_status'] ) ? sanitize_key( wp_unslash( $_GET['webhook_status'] ) ) : '',
		);

		$url = add_query_arg( $args, admin_url( 'admin-post.php' ) );
		return wp_nonce_url( $url, 'acg_export_logs' );
	}

	/**
	 * Query logs for CSV export.
	 *
	 * @param array $filters Filters.
	 * @return array
	 */
	private function query_logs_for_export( array $filters ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'acg_certificate_log';

		$where  = array( '1=1' );
		$params = array();

		if ( $filters['template_id'] ) {
			$where[] = 'template_id = %d';
			$params[] = $filters['template_id'];
		}

		if ( '' !== $filters['webhook_status'] ) {
			$where[] = 'webhook_status = %s';
			$params[] = $filters['webhook_status'];
		}

		if ( '' !== $filters['date_from'] ) {
			$where[] = 'created_at >= %s';
			$params[] = $filters['date_from'] . ' 00:00:00';
		}

		if ( '' !== $filters['date_to'] ) {
			$where[] = 'created_at <= %s';
			$params[] = $filters['date_to'] . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );
		$sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC";

		if ( ! empty( $params ) ) {
			return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		}

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Get webhook settings.
	 *
	 * @param int $template_id Template ID.
	 * @return array
	 */
	private function get_webhook_settings( int $template_id ): array {
		$raw = (string) \get_post_meta( $template_id, 'acg_template_webhook_settings', true );
		$decoded = json_decode( $raw, true );

		$outgoing = array(
			'url'     => '',
			'enabled' => false,
		);

		if ( is_array( $decoded ) && isset( $decoded['outgoing'] ) && is_array( $decoded['outgoing'] ) ) {
			$outgoing = array_merge( $outgoing, $decoded['outgoing'] );
		}

		return array(
			'outgoing' => $outgoing,
		);
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
	 * @return string
	 */
	private function redirect_url(): string {
		return admin_url( 'admin.php?page=alynt-certificate-logs' );
	}
}
