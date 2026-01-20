<?php
/**
 * Certificate log list table.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Alynt_Certificate_Generator_Certificate_Log_List extends \WP_List_Table {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'log',
				'plural'   => 'acg_certificate_logs',
				'ajax'     => false,
			)
		);
	}
	/**
	 * Prepare list items.
	 */
	public function prepare_items(): void {
		$per_page = 20;
		$paged    = $this->get_pagenum();
		$offset   = ( $paged - 1 ) * $per_page;

		$filters  = $this->get_filters();
		$results  = $this->query_logs( $filters, $per_page, $offset );

		$this->items = $results['items'];

		$this->set_pagination_args(
			array(
				'total_items' => $results['total'],
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Get table columns.
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'cb'             => '<input type="checkbox" />',
			'certificate_id' => __( 'Certificate ID', 'alynt-certificate-generator' ),
			'template'       => __( 'Template', 'alynt-certificate-generator' ),
			'method'         => __( 'Method', 'alynt-certificate-generator' ),
			'user'           => __( 'User', 'alynt-certificate-generator' ),
			'created_at'     => __( 'Date', 'alynt-certificate-generator' ),
			'email_status'   => __( 'Email', 'alynt-certificate-generator' ),
			'webhook_status' => __( 'Webhook', 'alynt-certificate-generator' ),
		);
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions(): array {
		return array(
			'bulk_delete' => __( 'Delete', 'alynt-certificate-generator' ),
			'bulk_resend' => __( 'Resend Emails', 'alynt-certificate-generator' ),
		);
	}

	/**
	 * Render checkbox column.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	protected function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="log_ids[]" value="%d" />',
			(int) $item['id']
		);
	}

	/**
	 * Render certificate id column with actions.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	protected function column_certificate_id( $item ): string {
		$log_id = (int) $item['id'];
		$certificate_id = esc_html( (string) $item['certificate_id'] );

		$actions = array(
			'view' => sprintf(
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
			'download' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $this->build_action_url( 'acg_download_certificate', $log_id ) ),
				esc_html__( 'Download', 'alynt-certificate-generator' )
			),
			'regenerate' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $this->build_action_url( 'acg_regenerate_certificate', $log_id ) ),
				esc_html__( 'Regenerate', 'alynt-certificate-generator' )
			),
			'resend' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $this->build_action_url( 'acg_resend_emails', $log_id ) ),
				esc_html__( 'Resend Emails', 'alynt-certificate-generator' )
			),
			'retry' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $this->build_action_url( 'acg_retry_webhook', $log_id ) ),
				esc_html__( 'Retry Webhook', 'alynt-certificate-generator' )
			),
			'delete' => sprintf(
				'<a href="%s" class="submitdelete">%s</a>',
				esc_url( $this->build_action_url( 'acg_delete_log', $log_id ) ),
				esc_html__( 'Delete', 'alynt-certificate-generator' )
			),
		);

		return $certificate_id . $this->row_actions( $actions );
	}

	/**
	 * Render default columns.
	 *
	 * @param array  $item Row data.
	 * @param string $column_name Column name.
	 * @return string
	 */
	protected function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'template':
				return esc_html( get_the_title( (int) $item['template_id'] ) );
			case 'method':
				return esc_html( (string) $item['method'] );
			case 'user':
				if ( ! empty( $item['user_id'] ) ) {
					$user = get_userdata( (int) $item['user_id'] );
					return $user ? esc_html( $user->display_name ) : esc_html__( 'Unknown', 'alynt-certificate-generator' );
				}
				return esc_html( (string) $item['generated_by'] );
			case 'created_at':
				return esc_html( (string) $item['created_at'] );
			case 'email_status':
				return $this->format_status( (string) $item['email_status_json'] );
			case 'webhook_status':
				return esc_html( (string) $item['webhook_status'] );
			default:
				return '';
		}
	}

	/**
	 * Render extra controls.
	 *
	 * @param string $which Position.
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		$filters = $this->get_filters();
		$templates = get_posts(
			array(
				'post_type'      => 'acg_cert_template',
				'post_status'    => 'any',
				'numberposts'    => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'cache_results'  => false,
				'suppress_filters' => true,
			)
		);

		echo '<div class="alignleft actions">';
		echo '<select name="template_id">';
		echo '<option value="">' . esc_html__( 'All Templates', 'alynt-certificate-generator' ) . '</option>';
		foreach ( $templates as $template_id ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( (string) $template_id ),
				selected( (string) $filters['template_id'], (string) $template_id, false ),
				esc_html( get_the_title( $template_id ) )
			);
		}
		echo '</select>';

		echo '<input type="date" name="date_from" value="' . esc_attr( $filters['date_from'] ) . '" />';
		echo '<input type="date" name="date_to" value="' . esc_attr( $filters['date_to'] ) . '" />';

		echo '<select name="webhook_status">';
		echo '<option value="">' . esc_html__( 'All Webhook Status', 'alynt-certificate-generator' ) . '</option>';
		foreach ( array( 'pending', 'sent', 'failed' ) as $status ) {
			printf(
				'<option value="%1$s" %2$s>%1$s</option>',
				esc_attr( $status ),
				selected( $filters['webhook_status'], $status, false )
			);
		}
		echo '</select>';

		submit_button( __( 'Filter', 'alynt-certificate-generator' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Get filters from request.
	 *
	 * @return array
	 */
	private function get_filters(): array {
		return array(
			'template_id'    => isset( $_GET['template_id'] ) ? absint( wp_unslash( $_GET['template_id'] ) ) : 0,
			'date_from'      => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'        => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'webhook_status' => isset( $_GET['webhook_status'] ) ? sanitize_key( wp_unslash( $_GET['webhook_status'] ) ) : '',
		);
	}

	/**
	 * Query log entries.
	 *
	 * @param array $filters Filters.
	 * @param int   $limit Limit.
	 * @param int   $offset Offset.
	 * @return array
	 */
	private function query_logs( array $filters, int $limit, int $offset ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'acg_certificate_log';

		$where   = array( '1=1' );
		$params  = array();

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
		$params[]  = $limit;
		$params[]  = $offset;

		$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$items = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		$total = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Format email status.
	 *
	 * @param string $status_json Status JSON.
	 * @return string
	 */
	private function format_status( string $status_json ): string {
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
