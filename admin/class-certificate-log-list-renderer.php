<?php
/**
 * Certificate log list table rendering helpers.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi;

defined( 'ABSPATH' ) || exit;

class Alynt_Certificate_Generator_Certificate_Log_List_Renderer {
	/**
	 * Build row actions for a certificate log.
	 *
	 * @param int $log_id Log ID.
	 * @return array
	 */
	public function build_certificate_actions( int $log_id ): array {
		return array(
			'view'       => sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					add_query_arg(
						array(
							'page'   => 'alynt-certificate-logs',
							'view'   => 'detail',
							'log_id' => $log_id,
						),
						admin_url( 'admin.php' )
					)
				),
				esc_html__( 'View', 'alynt-certificate-generator' )
			),
			'download'   => $this->build_action_link( 'acg_download_certificate', $log_id, __( 'Download', 'alynt-certificate-generator' ) ),
			'regenerate' => $this->build_action_link( 'acg_regenerate_certificate', $log_id, __( 'Regenerate', 'alynt-certificate-generator' ) ),
			'resend'     => $this->build_action_link( 'acg_resend_emails', $log_id, __( 'Resend Emails', 'alynt-certificate-generator' ) ),
			'retry'      => $this->build_action_link( 'acg_retry_webhook', $log_id, __( 'Retry Webhook', 'alynt-certificate-generator' ) ),
			'delete'     => sprintf(
				'<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
				esc_url( $this->build_action_url( 'acg_delete_log', $log_id ) ),
				esc_attr__( 'Are you sure you want to delete this certificate log and its PDF?', 'alynt-certificate-generator' ),
				esc_html__( 'Delete', 'alynt-certificate-generator' )
			),
		);
	}

	/**
	 * Format email status.
	 *
	 * @param string $status_json Status JSON.
	 * @return string
	 */
	public function format_status( string $status_json ): string {
		$decoded = json_decode( $status_json, true );
		if ( ! is_array( $decoded ) ) {
			return esc_html__( 'Pending', 'alynt-certificate-generator' );
		}

		$summary = array();
		foreach ( $decoded as $status ) {
			if ( isset( $status['status'] ) ) {
				$summary[] = $status['status'];
			}
		}

		return esc_html( implode( ', ', array_unique( $summary ) ) );
	}

	/**
	 * Build a standard row action link.
	 *
	 * @param string $action Action.
	 * @param int    $log_id Log ID.
	 * @param string $label  Link label.
	 * @return string
	 */
	private function build_action_link( string $action, int $log_id, string $label ): string {
		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( $this->build_action_url( $action, $log_id ) ),
			esc_html( $label )
		);
	}

	/**
	 * Build action URL with nonce.
	 *
	 * @param string $action Action.
	 * @param int    $log_id Log ID.
	 * @return string
	 */
	private function build_action_url( string $action, int $log_id ): string {
		$url = add_query_arg(
			array(
				'action' => $action,
				'log_id' => $log_id,
			),
			admin_url( 'admin-post.php' )
		);

		return wp_nonce_url( $url, 'acg_log_action_' . $log_id );
	}
}
