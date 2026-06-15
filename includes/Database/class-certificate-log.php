<?php
/**
 * Certificate log data access.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Database;

defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * Reads and writes certificate generation log rows.
 */
class Alynt_Certificate_Generator_Certificate_Log {
	/**
	 * Insert a certificate log row.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @return int|WP_Error
	 */
	public function insert( array $data ) {
		global $wpdb;
		$table = Alynt_Certificate_Generator_Database::get_certificate_log_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Intentional custom-table write.
		$result = $wpdb->insert(
			$table,
			$data,
			array(
				'%s',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);
		if ( false === $result ) {
			return new WP_Error( 'acg_log_insert_failed', __( 'Certificate log could not be saved.', 'alynt-certificate-generator' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Fetch a log row by certificate ID and token.
	 *
	 * @param string $certificate_id Certificate ID.
	 * @param string $token          Download token.
	 * @return array<string, mixed>|null
	 */
	public function get_by_certificate_and_token( string $certificate_id, string $token ): ?array {
		global $wpdb;
		$table = esc_sql( Alynt_Certificate_Generator_Database::get_certificate_log_table() );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name is escaped above; dynamic values are prepared.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup by token.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE certificate_id = %s AND download_token = %s LIMIT 1",
				$certificate_id,
				$token
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Fetch a log row by certificate ID.
	 *
	 * @param string $certificate_id Certificate ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_certificate_id( string $certificate_id ): ?array {
		global $wpdb;
		$table = esc_sql( Alynt_Certificate_Generator_Database::get_certificate_log_table() );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name is escaped above; dynamic values are prepared.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup by unique certificate ID.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE certificate_id = %s LIMIT 1",
				$certificate_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Fetch a log row by ID.
	 *
	 * @param int $log_id Log ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_id( int $log_id ): ?array {
		global $wpdb;
		$table = esc_sql( Alynt_Certificate_Generator_Database::get_certificate_log_table() );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name is escaped above; dynamic value is prepared.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup by primary key.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				$log_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Update PDF path for a log entry.
	 *
	 * @param int    $log_id Log ID.
	 * @param string $path File path.
	 * @return bool|WP_Error
	 */
	public function update_pdf_path( int $log_id, string $path ) {
		global $wpdb;
		$table = Alynt_Certificate_Generator_Database::get_certificate_log_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional custom-table write.
		$result = $wpdb->update(
			$table,
			array(
				'pdf_path'   => $path,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $log_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return $this->resolve_write_result( $result, 'acg_log_update_failed', __( 'Certificate log could not be updated.', 'alynt-certificate-generator' ) );
	}

	/**
	 * Delete a log entry.
	 *
	 * @param int $log_id Log ID.
	 * @return bool|WP_Error
	 */
	public function delete( int $log_id ) {
		global $wpdb;
		$table = Alynt_Certificate_Generator_Database::get_certificate_log_table();

		Alynt_Certificate_Generator_Database::delete_webhook_logs_for_certificate( $log_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional custom-table delete.
		$result = $wpdb->delete(
			$table,
			array( 'id' => $log_id ),
			array( '%d' )
		);

		return $this->resolve_write_result( $result, 'acg_log_delete_failed', __( 'Certificate log could not be deleted.', 'alynt-certificate-generator' ) );
	}

	/**
	 * Update email status for a log entry.
	 *
	 * @param int   $log_id Log ID.
	 * @param array $status Status payload.
	 * @return bool|WP_Error
	 */
	public function update_email_status( int $log_id, array $status ) {
		global $wpdb;
		$table = Alynt_Certificate_Generator_Database::get_certificate_log_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional custom-table write.
		$result = $wpdb->update(
			$table,
			array(
				'email_status_json' => wp_json_encode( $status ),
				'updated_at'        => current_time( 'mysql' ),
			),
			array( 'id' => $log_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return $this->resolve_write_result( $result, 'acg_email_status_update_failed', __( 'Email status could not be saved.', 'alynt-certificate-generator' ) );
	}

	/**
	 * Update webhook status for a log entry.
	 *
	 * @param int    $log_id Log ID.
	 * @param string $status Status.
	 * @return bool|WP_Error
	 */
	public function update_webhook_status( int $log_id, string $status ) {
		global $wpdb;
		$table = Alynt_Certificate_Generator_Database::get_certificate_log_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional custom-table write.
		$result = $wpdb->update(
			$table,
			array(
				'webhook_status' => $status,
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => $log_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return $this->resolve_write_result( $result, 'acg_webhook_status_update_failed', __( 'Webhook status could not be saved.', 'alynt-certificate-generator' ) );
	}

	/**
	 * Convert a database write result to a consistent response.
	 *
	 * @param int|false $result  Write result.
	 * @param string    $code    Error code.
	 * @param string    $message Error message.
	 * @return bool|WP_Error
	 */
	private function resolve_write_result( $result, string $code, string $message ) {
		if ( false === $result ) {
			return new WP_Error( $code, $message );
		}

		return true;
	}
}
