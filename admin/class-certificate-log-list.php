<?php
/**
 * Certificate log list table.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Alynt_Certificate_Generator_Certificate_Log_List extends \WP_List_Table {
	/**
	 * Rendering helper.
	 *
	 * @var Alynt_Certificate_Generator_Certificate_Log_List_Renderer
	 */
	private $renderer;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->renderer = new Alynt_Certificate_Generator_Certificate_Log_List_Renderer();

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

		$filters = $this->get_filters();
		$results = $this->query_logs( $filters, $per_page, $offset );

		$this->items           = $results['items'];
		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			array(),
			'created_at',
		);

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
			'cb'             => '<input type="checkbox" aria-label="' . esc_attr__( 'Select all certificate logs', 'alynt-certificate-generator' ) . '" />',
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
			'<input type="checkbox" name="log_ids[]" value="%1$d" aria-label="%2$s" />',
			(int) $item['id'],
			esc_attr(
				sprintf(
					/* translators: %s: certificate ID. */
					__( 'Select certificate log %s', 'alynt-certificate-generator' ),
					(string) $item['certificate_id']
				)
			)
		);
	}

	/**
	 * Render certificate id column with actions.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	protected function column_certificate_id( $item ): string {
		$log_id         = (int) $item['id'];
		$certificate_id = esc_html( (string) $item['certificate_id'] );

		return $certificate_id . $this->row_actions( $this->renderer->build_certificate_actions( $log_id ) );
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
				return $this->renderer->format_status( (string) $item['email_status_json'] );
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

		$filters   = $this->get_filters();
		$templates = get_posts(
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

		echo '<div class="alignleft actions">';
		echo '<label class="screen-reader-text" for="acg_log_filter_template">' . esc_html__( 'Filter by template', 'alynt-certificate-generator' ) . '</label>';
		echo '<select name="template_id" id="acg_log_filter_template">';
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

		echo '<label class="screen-reader-text" for="acg_log_filter_date_from">' . esc_html__( 'Filter from date', 'alynt-certificate-generator' ) . '</label>';
		echo '<input type="date" id="acg_log_filter_date_from" name="date_from" value="' . esc_attr( $filters['date_from'] ) . '" />';
		echo '<label class="screen-reader-text" for="acg_log_filter_date_to">' . esc_html__( 'Filter to date', 'alynt-certificate-generator' ) . '</label>';
		echo '<input type="date" id="acg_log_filter_date_to" name="date_to" value="' . esc_attr( $filters['date_to'] ) . '" />';

		echo '<label class="screen-reader-text" for="acg_log_filter_webhook_status">' . esc_html__( 'Filter by webhook status', 'alynt-certificate-generator' ) . '</label>';
		echo '<select name="webhook_status" id="acg_log_filter_webhook_status">';
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
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list-table filters.
		return array(
			'template_id'    => isset( $_GET['template_id'] ) ? absint( wp_unslash( $_GET['template_id'] ) ) : 0,
			'date_from'      => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'        => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'webhook_status' => isset( $_GET['webhook_status'] ) ? sanitize_key( wp_unslash( $_GET['webhook_status'] ) ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
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
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = empty( $params )
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Query has no placeholders when no filters are applied; custom table name is escaped above.
			? (int) $wpdb->get_var( $count_sql )
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- WHERE placeholders and values are assembled from a fixed allowlist.
			: (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );

		$params[] = $limit;
		$params[] = $offset;

		$sql = "SELECT id, certificate_id, template_id, user_id, generated_by, method, created_at, email_status_json, webhook_status FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- WHERE placeholders and values are assembled from a fixed allowlist.
		$items = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		$items = is_array( $items ) ? $items : array();
		$this->prime_item_caches( $items );

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Prime post and user caches for list-table display labels.
	 *
	 * @param array $items Log rows.
	 */
	private function prime_item_caches( array $items ): void {
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
