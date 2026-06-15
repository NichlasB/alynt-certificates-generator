<?php
/**
 * Bulk generation admin page.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi;

defined( 'ABSPATH' ) || exit;

use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Diagnostics_Logger;

/**
 * Renders and handles the bulk certificate generation admin workflow.
 */
class Alynt_Certificate_Generator_Bulk_Generator_Page {
	/**
	 * Bulk generator data helper.
	 *
	 * @var Alynt_Certificate_Generator_Bulk_Generator_Data
	 */
	private $bulk_data;

	/**
	 * Constructor.
	 *
	 * @param Alynt_Certificate_Generator_Bulk_Generator_Data|null $bulk_data Bulk data helper.
	 */
	public function __construct( ?Alynt_Certificate_Generator_Bulk_Generator_Data $bulk_data = null ) {
		$this->bulk_data = null !== $bulk_data ? $bulk_data : new Alynt_Certificate_Generator_Bulk_Generator_Data();
	}

	/**
	 * Register admin-post handlers.
	 */
	public function register_actions(): void {
		\add_action( 'admin_post_acg_bulk_upload', array( $this, 'handle_upload' ) );
		\add_action( 'admin_post_acg_bulk_start', array( $this, 'handle_start' ) );
	}

	/**
	 * Render bulk generation page.
	 */
	public function render_page(): void {
		if ( ! \current_user_can( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE ) ) {
			\wp_die( esc_html__( 'You do not have permission to access this page.', 'alynt-certificate-generator' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin page routing parameters.
		$bulk_id = isset( $_GET['bulk_id'] ) ? sanitize_text_field( wp_unslash( $_GET['bulk_id'] ) ) : '';
		$step    = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : 'upload';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Bulk Certificate Generation', 'alynt-certificate-generator' ) . '</h1>';
		echo '<hr class="wp-header-end" />';

		if ( '' === $bulk_id || 'upload' === $step ) {
			$this->render_upload_form();
		} elseif ( 'map' === $step ) {
			$this->render_mapping_form( $bulk_id );
		} elseif ( 'progress' === $step ) {
			$this->render_progress_view( $bulk_id );
		} else {
			$this->render_upload_form();
		}

		echo '</div>';
	}

	/**
	 * Render upload form.
	 */
	private function render_upload_form(): void {
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

		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="acg_bulk_upload" />';
		wp_nonce_field( 'acg_bulk_upload' );

		echo '<p><label for="acg_bulk_template">' . esc_html__( 'Certificate Template', 'alynt-certificate-generator' ) . '</label></p>';
		echo '<select name="template_id" id="acg_bulk_template" required aria-required="true">';
		echo '<option value="">' . esc_html__( 'Select a template', 'alynt-certificate-generator' ) . '</option>';
		foreach ( $templates as $template_id ) {
			printf(
				'<option value="%1$s">%2$s</option>',
				esc_attr( (string) $template_id ),
				esc_html( get_the_title( $template_id ) )
			);
		}
		echo '</select>';

		echo '<p><label for="acg_bulk_file">' . esc_html__( 'CSV File', 'alynt-certificate-generator' ) . '</label></p>';
		echo '<input type="file" id="acg_bulk_file" name="csv_file" accept=".csv" required aria-required="true" />';

		echo '<p style="margin-top:12px;">';
		echo '<label><input type="checkbox" name="skip_notifications" value="1" /> ';
		echo esc_html__( 'Skip email notifications for this bulk job', 'alynt-certificate-generator' ) . '</label>';
		echo '</p>';

		submit_button( __( 'Upload CSV', 'alynt-certificate-generator' ) );
		echo '</form>';
	}

	/**
	 * Render mapping form.
	 *
	 * @param string $bulk_id Bulk ID.
	 */
	private function render_mapping_form( string $bulk_id ): void {
		$data = $this->bulk_data->get_bulk_data( $bulk_id );
		if ( ! $data ) {
			echo '<p>' . esc_html__( 'Bulk upload not found. Please upload a CSV.', 'alynt-certificate-generator' ) . '</p>';
			return;
		}

		$headers = $this->bulk_data->get_csv_headers( $data['file'] );
		if ( empty( $headers ) ) {
			echo '<p>' . esc_html__( 'CSV headers could not be read.', 'alynt-certificate-generator' ) . '</p>';
			return;
		}

		$variables = $this->get_template_variables( $data['template_id'] );

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="acg_bulk_start" />';
		echo '<input type="hidden" name="bulk_id" value="' . esc_attr( $bulk_id ) . '" />';
		wp_nonce_field( 'acg_bulk_start' );

		echo '<table class="widefat fixed striped">';
		echo '<caption class="screen-reader-text">' . esc_html__( 'Map template variables to CSV columns', 'alynt-certificate-generator' ) . '</caption>';
		echo '<thead><tr><th scope="col">' . esc_html__( 'Variable', 'alynt-certificate-generator' ) . '</th><th scope="col">' . esc_html__( 'CSV Column', 'alynt-certificate-generator' ) . '</th></tr></thead>';
		echo '<tbody>';
		foreach ( $variables as $variable ) {
			$key   = $variable['key'] ?? '';
			$label = $variable['label'] ?? $key;
			if ( '' === $key ) {
				continue;
			}
			echo '<tr>';
			echo '<th scope="row">' . esc_html( $label ) . '</th>';
			echo '<td>';
			printf(
				'<select name="mapping[%1$s]" aria-label="%2$s">',
				esc_attr( $key ),
				esc_attr(
					sprintf(
						/* translators: %s: template variable label. */
						__( 'CSV column for %s', 'alynt-certificate-generator' ),
						$label
					)
				)
			);
			echo '<option value="">' . esc_html__( 'Skip', 'alynt-certificate-generator' ) . '</option>';
			foreach ( $headers as $index => $header ) {
				printf(
					'<option value="%1$s">%2$s</option>',
					esc_attr( (string) $index ),
					esc_html( $header )
				);
			}
			echo '</select>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody>';
		echo '</table>';

		echo '<p style="margin-top:12px;">';
		echo '<label><input type="checkbox" name="skip_notifications" value="1" ' . checked( $data['skip_notifications'], true, false ) . ' /> ';
		echo esc_html__( 'Skip email notifications for this bulk job', 'alynt-certificate-generator' ) . '</label>';
		echo '</p>';

		submit_button( __( 'Start Bulk Generation', 'alynt-certificate-generator' ) );
		echo '</form>';
	}

	/**
	 * Render progress view.
	 *
	 * @param string $bulk_id Bulk ID.
	 */
	private function render_progress_view( string $bulk_id ): void {
		echo '<div id="acg-bulk-progress" data-bulk-id="' . esc_attr( $bulk_id ) . '" aria-live="polite" aria-atomic="true" aria-busy="true">';
		echo '<p>' . esc_html__( 'Processing bulk generation...', 'alynt-certificate-generator' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Processed:', 'alynt-certificate-generator' ) . '</strong> <span data-progress="processed">0</span></p>';
		echo '<p><strong>' . esc_html__( 'Failed:', 'alynt-certificate-generator' ) . '</strong> <span data-progress="failed">0</span></p>';
		echo '<p><strong>' . esc_html__( 'Total:', 'alynt-certificate-generator' ) . '</strong> <span data-progress="total">0</span></p>';
		echo '</div>';
	}

	/**
	 * Handle CSV upload.
	 */
	public function handle_upload(): void {
		if ( ! \current_user_can( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to upload CSV files.', 'alynt-certificate-generator' ) );
		}

		check_admin_referer( 'acg_bulk_upload' );

		$template_id = isset( $_POST['template_id'] ) ? absint( wp_unslash( $_POST['template_id'] ) ) : 0;
		if ( $template_id < 1 ) {
			wp_die( esc_html__( 'Template is required.', 'alynt-certificate-generator' ) );
		}

		if ( empty( $_FILES['csv_file'] ) || ! is_array( $_FILES['csv_file'] ) ) {
			wp_die( esc_html__( 'CSV file is required.', 'alynt-certificate-generator' ) );
		}

		$upload = wp_handle_upload(
			$_FILES['csv_file'],
			array(
				'test_form' => false,
				'mimes'     => array(
					'csv' => 'text/csv',
				),
			)
		);

		if ( isset( $upload['error'] ) ) {
			wp_die( esc_html( $upload['error'] ) );
		}

		$bulk_id            = wp_generate_password( 8, false, false );
		$skip_notifications = isset( $_POST['skip_notifications'] );

		$this->bulk_data->store_upload( $bulk_id, $upload['file'], $template_id, $skip_notifications );

		wp_safe_redirect( admin_url( 'admin.php?page=alynt-bulk-generator&step=map&bulk_id=' . rawurlencode( $bulk_id ) ) );
		exit;
	}

	/**
	 * Handle bulk start.
	 */
	public function handle_start(): void {
		if ( ! \current_user_can( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to start bulk processing.', 'alynt-certificate-generator' ) );
		}

		check_admin_referer( 'acg_bulk_start' );

		$bulk_id = isset( $_POST['bulk_id'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_id'] ) ) : '';
		$data    = $this->bulk_data->get_bulk_data( $bulk_id );
		if ( ! $data ) {
			wp_die( esc_html__( 'Bulk job not found.', 'alynt-certificate-generator' ) );
		}

		$mapping            = isset( $_POST['mapping'] ) && is_array( $_POST['mapping'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['mapping'] ) )
			: array();
		$skip_notifications = isset( $_POST['skip_notifications'] );
		$max_rows           = (int) apply_filters( 'alynt_certificate_generator_bulk_max_rows', 1000 );

		$row_count = $this->bulk_data->each_csv_row( $data['file'], static function ( array $row ): void {}, $max_rows );
		if ( is_wp_error( $row_count ) ) {
			wp_die( esc_html( $row_count->get_error_message() ) );
		}

		if ( 0 === $row_count ) {
			wp_die( esc_html__( 'CSV rows could not be read. Check that the file contains a header row and at least one data row.', 'alynt-certificate-generator' ) );
		}

		if ( ! $this->bulk_data->mark_started( $bulk_id ) ) {
			wp_die( esc_html__( 'This bulk job has already been started.', 'alynt-certificate-generator' ) );
		}

		set_transient( 'acg_bulk_' . $bulk_id . '_total', 0, DAY_IN_SECONDS );
		set_transient( 'acg_bulk_' . $bulk_id . '_processed', 0, DAY_IN_SECONDS );
		set_transient( 'acg_bulk_' . $bulk_id . '_failed', 0, DAY_IN_SECONDS );

		$total     = 0;
		$scheduled = 0;
		$failed    = 0;
		$result    = $this->bulk_data->each_csv_row(
			$data['file'],
			function ( array $row ) use ( $mapping, $data, $skip_notifications, $bulk_id, &$total, &$scheduled, &$failed ): void {
				++$total;

				$values = $this->map_bulk_row_values( $row, $mapping );
				$result = $this->schedule_bulk_row( $data['template_id'], $values, $skip_notifications, $bulk_id );
				if ( is_wp_error( $result ) ) {
					++$failed;
					return;
				}
				++$scheduled;
			}
		);

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		set_transient( 'acg_bulk_' . $bulk_id . '_total', $total, DAY_IN_SECONDS );
		set_transient( 'acg_bulk_' . $bulk_id . '_schedule_failed', $failed, DAY_IN_SECONDS );
		if ( 0 === $scheduled ) {
			wp_die( esc_html__( 'Bulk rows could not be scheduled for processing.', 'alynt-certificate-generator' ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=alynt-bulk-generator&step=progress&bulk_id=' . rawurlencode( $bulk_id ) ) );
		exit;
	}

	/**
	 * Map a CSV row to template variable values.
	 *
	 * @param array $row CSV row.
	 * @param array $mapping Mapping values.
	 * @return array<string, string>
	 */
	private function map_bulk_row_values( array $row, array $mapping ): array {
		$values = array();
		foreach ( $mapping as $key => $index ) {
			$index_raw = (string) $index;
			if ( '' === $index_raw ) {
				continue;
			}
			$index = (int) $index_raw;
			if ( ! isset( $row[ $index ] ) ) {
				continue;
			}
			$values[ sanitize_key( $key ) ] = $row[ $index ];
		}

		return $values;
	}

	/**
	 * Schedule a bulk row for processing.
	 *
	 * @param int    $template_id Template ID.
	 * @param array  $values Values.
	 * @param bool   $skip_notifications Skip notifications.
	 * @param string $bulk_id Bulk ID.
	 * @return bool|\WP_Error
	 */
	private function schedule_bulk_row( int $template_id, array $values, bool $skip_notifications, string $bulk_id ) {
		$args = array( $template_id, $values, $skip_notifications, $bulk_id );
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			$action_id = as_enqueue_async_action( 'alynt_certificate_generator_bulk_generate', $args, ALYNT_CERTIFICATE_GENERATOR_AS_GROUP );
			if ( ! $action_id ) {
				Alynt_Certificate_Generator_Diagnostics_Logger::log(
					'error',
					'cron',
					'bulk_action_scheduler_enqueue_failed',
					'Bulk row could not be queued with Action Scheduler.',
					array(
						'template_id' => $template_id,
						'bulk_id'     => $bulk_id,
					)
				);
			}
			return $action_id ? true : new \WP_Error( 'acg_bulk_schedule_failed', __( 'Bulk row could not be scheduled.', 'alynt-certificate-generator' ) );
		}

		$scheduled = wp_schedule_single_event( time(), 'alynt_certificate_generator_bulk_generate', $args );
		if ( is_wp_error( $scheduled ) ) {
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'error',
				'cron',
				'bulk_wp_cron_enqueue_error',
				'Bulk row could not be queued with WP-Cron.',
				array(
					'template_id' => $template_id,
					'bulk_id'     => $bulk_id,
					'error_code'  => $scheduled->get_error_code(),
				)
			);
			return $scheduled;
		}

		if ( ! $scheduled ) {
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'error',
				'cron',
				'bulk_wp_cron_enqueue_failed',
				'Bulk row could not be queued with WP-Cron.',
				array(
					'template_id' => $template_id,
					'bulk_id'     => $bulk_id,
				)
			);
		}
		return $scheduled ? true : new \WP_Error( 'acg_bulk_schedule_failed', __( 'Bulk row could not be scheduled.', 'alynt-certificate-generator' ) );
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
