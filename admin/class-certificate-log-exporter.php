<?php
/**
 * Certificate log CSV exporter.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi;

defined( 'ABSPATH' ) || exit;

class Alynt_Certificate_Generator_Certificate_Log_Exporter {
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

		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="acg-certificate-logs.csv"' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'ID', 'Certificate ID', 'Template', 'Method', 'User', 'Date', 'Email Status', 'Webhook Status' ) );

		$batch_size = 200;
		$offset     = 0;
		do {
			$items = $this->query_logs_for_export( $filters, $batch_size, $offset );
			$this->prime_export_caches( $items );

			foreach ( $items as $item ) {
				fputcsv(
					$output,
					array(
						$item['id'],
						$item['certificate_id'],
						get_the_title( (int) $item['template_id'] ),
						$item['method'],
						$this->get_user_label( $item ),
						$item['created_at'],
						$item['email_status_json'],
						$item['webhook_status'],
					)
				);
			}

			$count   = count( $items );
			$offset += $batch_size;
		} while ( $count === $batch_size );

		fclose( $output );
		exit;
	}

	/**
	 * Build export URL with current filters.
	 *
	 * @return string
	 */
	public function build_export_url(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter values copied into a nonce-protected export URL.
		$args = array(
			'action'         => 'acg_export_logs',
			'template_id'    => isset( $_GET['template_id'] ) ? absint( wp_unslash( $_GET['template_id'] ) ) : '',
			'date_from'      => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'        => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'webhook_status' => isset( $_GET['webhook_status'] ) ? sanitize_key( wp_unslash( $_GET['webhook_status'] ) ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$url = add_query_arg( $args, admin_url( 'admin-post.php' ) );
		return wp_nonce_url( $url, 'acg_export_logs' );
	}

	/**
	 * Query logs for CSV export.
	 *
	 * @param array $filters Filters.
	 * @param int   $limit   Limit.
	 * @param int   $offset  Offset.
	 * @return array
	 */
	private function query_logs_for_export( array $filters, int $limit, int $offset ): array {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'acg_certificate_log' );

		$where  = array( '1=1' );
		$params = array();

		if ( $filters['template_id'] ) {
			$where[]  = 'template_id = %d';
			$params[] = $filters['template_id'];
		}

		if ( '' !== $filters['webhook_status'] ) {
			$where[]  = 'webhook_status = %s';
			$params[] = $filters['webhook_status'];
		}

		if ( '' !== $filters['date_from'] ) {
			$where[]  = 'created_at >= %s';
			$params[] = $filters['date_from'] . ' 00:00:00';
		}

		if ( '' !== $filters['date_to'] ) {
			$where[]  = 'created_at <= %s';
			$params[] = $filters['date_to'] . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );
		$sql       = "SELECT id, certificate_id, template_id, user_id, generated_by, method, created_at, email_status_json, webhook_status FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";

		$params[] = $limit;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table export query; WHERE placeholders and values are assembled from a fixed allowlist.
		$items = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		return is_array( $items ) ? $items : array();
	}

	/**
	 * Get user label for an export row.
	 *
	 * @param array $item Export row.
	 * @return string
	 */
	private function get_user_label( array $item ): string {
		if ( empty( $item['user_id'] ) ) {
			return (string) $item['generated_by'];
		}

		$user = get_userdata( (int) $item['user_id'] );
		return $user ? $user->display_name : '';
	}

	/**
	 * Prime post and user caches for an export batch.
	 *
	 * @param array $items Log rows.
	 */
	private function prime_export_caches( array $items ): void {
		$template_ids = array_filter( array_unique( array_map( 'intval', wp_list_pluck( $items, 'template_id' ) ) ) );
		if ( ! empty( $template_ids ) ) {
			_prime_post_caches( $template_ids, false, false );
		}

		$user_ids = array_filter( array_unique( array_map( 'intval', wp_list_pluck( $items, 'user_id' ) ) ) );
		if ( ! empty( $user_ids ) && function_exists( 'cache_users' ) ) {
			cache_users( $user_ids );
		}
	}
}
