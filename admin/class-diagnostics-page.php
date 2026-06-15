<?php
/**
 * Diagnostics admin tab.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi;

defined( 'ABSPATH' ) || exit;

use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Diagnostics_Logger;

/**
 * Renders diagnostics health, event viewer, export, and clear actions.
 */
class Alynt_Certificate_Generator_Diagnostics_Page {
	/**
	 * Register admin-post handlers.
	 */
	public function register_actions(): void {
		\add_action( 'admin_post_acg_export_diagnostics', array( $this, 'handle_export' ) );
		\add_action( 'admin_post_acg_clear_diagnostics', array( $this, 'handle_clear' ) );
	}

	/**
	 * Render diagnostics tab content.
	 */
	public function render(): void {
		if ( ! \current_user_can( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE ) ) {
			\wp_die( esc_html__( 'You do not have permission to access this page.', 'alynt-certificate-generator' ) );
		}

		$filters = $this->get_filters();
		$events  = array_reverse( Alynt_Certificate_Generator_Diagnostics_Logger::get_events( $filters ) );
		$events  = array_slice( $events, 0, 100 );

		$this->render_notice();
		$this->render_health_panel();
		$this->render_actions();
		$this->render_filters( $filters );
		$this->render_events_table( $events );
	}

	/**
	 * Export stored diagnostics as JSON.
	 */
	public function handle_export(): void {
		if ( ! \current_user_can( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE ) ) {
			\wp_die( esc_html__( 'You do not have permission to export diagnostics.', 'alynt-certificate-generator' ) );
		}

		\check_admin_referer( 'acg_export_diagnostics' );

		$filename = 'acg-diagnostics-' . gmdate( 'Ymd-His' ) . '.json';
		header( 'Content-Type: application/json; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		echo wp_json_encode(
			array(
				'generated_at' => gmdate( 'c' ),
				'health'       => Alynt_Certificate_Generator_Diagnostics_Logger::get_health(),
				'events'       => Alynt_Certificate_Generator_Diagnostics_Logger::get_events(),
			),
			JSON_PRETTY_PRINT
		);
		exit;
	}

	/**
	 * Clear stored diagnostics.
	 */
	public function handle_clear(): void {
		if ( ! \current_user_can( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE ) ) {
			\wp_die( esc_html__( 'You do not have permission to clear diagnostics.', 'alynt-certificate-generator' ) );
		}

		\check_admin_referer( 'acg_clear_diagnostics' );
		Alynt_Certificate_Generator_Diagnostics_Logger::clear();

		\wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => 'alynt-certificate-generator',
					'tab'             => 'diagnostics',
					'acg_diag_notice' => 'cleared',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render completion notices.
	 */
	private function render_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice flag.
		$notice = isset( $_GET['acg_diag_notice'] ) ? sanitize_key( wp_unslash( $_GET['acg_diag_notice'] ) ) : '';
		if ( 'cleared' !== $notice ) {
			return;
		}

		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html__( 'Diagnostics were cleared.', 'alynt-certificate-generator' );
		echo '</p></div>';
	}

	/**
	 * Render health summary.
	 */
	private function render_health_panel(): void {
		$health = Alynt_Certificate_Generator_Diagnostics_Logger::get_health();

		echo '<h2>' . esc_html__( 'Health', 'alynt-certificate-generator' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width: 760px;">';
		echo '<caption class="screen-reader-text">' . esc_html__( 'Diagnostics health summary', 'alynt-certificate-generator' ) . '</caption>';
		echo '<tbody>';
		$this->render_health_row( __( 'Plugin version', 'alynt-certificate-generator' ), (string) $health['plugin_version'] );
		$this->render_health_row( __( 'WordPress version', 'alynt-certificate-generator' ), (string) $health['wordpress_version'] );
		$this->render_health_row( __( 'PHP version', 'alynt-certificate-generator' ), (string) $health['php_version'] );
		$this->render_health_row( __( 'Diagnostics enabled', 'alynt-certificate-generator' ), $health['diagnostics_enabled'] ? __( 'Yes', 'alynt-certificate-generator' ) : __( 'No', 'alynt-certificate-generator' ) );
		$this->render_health_row( __( 'Storage backend', 'alynt-certificate-generator' ), (string) $health['storage_backend'] );
		$this->render_health_row( __( 'Retention days', 'alynt-certificate-generator' ), (string) $health['retention_days'] );
		$this->render_health_row( __( 'Maximum events', 'alynt-certificate-generator' ), (string) $health['max_events'] );
		$this->render_health_row( __( 'Stored events', 'alynt-certificate-generator' ), (string) $health['event_count'] );
		$this->render_health_row( __( 'Last event', 'alynt-certificate-generator' ), '' !== $health['last_event'] ? (string) $health['last_event'] : __( 'None', 'alynt-certificate-generator' ) );
		$this->render_health_row( __( 'Cleanup scheduled', 'alynt-certificate-generator' ), $health['cleanup_scheduled'] ? __( 'Yes', 'alynt-certificate-generator' ) : __( 'No', 'alynt-certificate-generator' ) );
		echo '</tbody></table>';
	}

	/**
	 * Render a health row.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 */
	private function render_health_row( string $label, string $value ): void {
		printf(
			'<tr><th scope="row">%1$s</th><td>%2$s</td></tr>',
			esc_html( $label ),
			esc_html( $value )
		);
	}

	/**
	 * Render export and clear controls.
	 */
	private function render_actions(): void {
		$export_url = \wp_nonce_url(
			add_query_arg( 'action', 'acg_export_diagnostics', admin_url( 'admin-post.php' ) ),
			'acg_export_diagnostics'
		);

		echo '<h2>' . esc_html__( 'Actions', 'alynt-certificate-generator' ) . '</h2>';
		echo '<p>';
		echo '<a class="button" href="' . esc_url( $export_url ) . '">' . esc_html__( 'Export diagnostics', 'alynt-certificate-generator' ) . '</a> ';
		echo '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Clear all stored diagnostic events? This action cannot be undone.', 'alynt-certificate-generator' ) ) . '\');">';
		echo '<input type="hidden" name="action" value="acg_clear_diagnostics" />';
		\wp_nonce_field( 'acg_clear_diagnostics' );
		\submit_button( __( 'Clear diagnostics', 'alynt-certificate-generator' ), 'delete', 'submit', false );
		echo '</form>';
	}

	/**
	 * Render event filters.
	 *
	 * @param array<string, string> $filters Filters.
	 */
	private function render_filters( array $filters ): void {
		echo '<h2>' . esc_html__( 'Recent Events', 'alynt-certificate-generator' ) . '</h2>';
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
		echo '<input type="hidden" name="page" value="alynt-certificate-generator" />';
		echo '<input type="hidden" name="tab" value="diagnostics" />';
		echo '<label for="acg_diag_level">' . esc_html__( 'Level', 'alynt-certificate-generator' ) . '</label> ';
		echo '<select id="acg_diag_level" name="level">';
		$this->render_filter_option( '', __( 'All levels', 'alynt-certificate-generator' ), $filters['level'] ?? '' );
		foreach ( array( 'debug', 'info', 'warning', 'error', 'critical' ) as $level ) {
			$this->render_filter_option( $level, ucfirst( $level ), $filters['level'] ?? '' );
		}
		echo '</select> ';
		echo '<label for="acg_diag_category">' . esc_html__( 'Category', 'alynt-certificate-generator' ) . '</label> ';
		echo '<input type="text" id="acg_diag_category" name="category" value="' . esc_attr( $filters['category'] ?? '' ) . '" class="regular-text" /> ';
		\submit_button( __( 'Filter', 'alynt-certificate-generator' ), 'secondary', 'submit', false );
		echo '</form>';
	}

	/**
	 * Render a filter option.
	 *
	 * @param string $value Selected value.
	 * @param string $label Label.
	 * @param string $selected Selected value.
	 */
	private function render_filter_option( string $value, string $label, string $selected ): void {
		printf(
			'<option value="%1$s" %2$s>%3$s</option>',
			esc_attr( $value ),
			selected( $selected, $value, false ),
			esc_html( $label )
		);
	}

	/**
	 * Render recent events table.
	 *
	 * @param array<int, array<string, mixed>> $events Events.
	 */
	private function render_events_table( array $events ): void {
		echo '<table class="widefat striped">';
		echo '<caption class="screen-reader-text">' . esc_html__( 'Recent diagnostic events', 'alynt-certificate-generator' ) . '</caption>';
		echo '<thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Time', 'alynt-certificate-generator' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Level', 'alynt-certificate-generator' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Category', 'alynt-certificate-generator' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Code', 'alynt-certificate-generator' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Message', 'alynt-certificate-generator' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Context', 'alynt-certificate-generator' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $events ) ) {
			echo '<tr><td colspan="6">';
			echo '<h3>' . esc_html__( 'No diagnostic events found', 'alynt-certificate-generator' ) . '</h3>';
			echo '<p>' . esc_html__( 'Diagnostic events will appear here after logging is enabled and an event is recorded.', 'alynt-certificate-generator' ) . '</p>';
			echo '</td></tr>';
		}

		foreach ( $events as $event ) {
			$context = isset( $event['context'] ) && is_array( $event['context'] ) ? $event['context'] : array();
			printf(
				'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td>%4$s</td><td>%5$s</td><td><code>%6$s</code></td></tr>',
				esc_html( (string) ( $event['timestamp'] ?? '' ) ),
				esc_html( (string) ( $event['level'] ?? '' ) ),
				esc_html( (string) ( $event['category'] ?? '' ) ),
				esc_html( (string) ( $event['code'] ?? '' ) ),
				esc_html( (string) ( $event['message'] ?? '' ) ),
				esc_html( wp_json_encode( $context ) )
			);
		}

		echo '</tbody></table>';
	}

	/**
	 * Get sanitized read-only filters.
	 *
	 * @return array<string, string>
	 */
	private function get_filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters for admin diagnostics table.
		return array(
			'level'    => isset( $_GET['level'] ) ? sanitize_key( wp_unslash( $_GET['level'] ) ) : '',
			'category' => isset( $_GET['category'] ) ? sanitize_key( wp_unslash( $_GET['category'] ) ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}
}
