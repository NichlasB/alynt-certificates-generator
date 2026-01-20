<?php
/**
 * Certificate log data access.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Database;

class Alynt_Certificate_Generator_Certificate_Log {
	/**
	 * Insert a certificate log row.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @return int
	 */
	public function insert( array $data ): int {
		global $wpdb;
		$table = Alynt_Certificate_Generator_Database::get_certificate_log_table();

		$wpdb->insert( $table, $data );
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
		$table = Alynt_Certificate_Generator_Database::get_certificate_log_table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE certificate_id = %s AND download_token = %s LIMIT 1",
				$certificate_id,
				$token
			),
			ARRAY_A
		);

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
		$table = Alynt_Certificate_Generator_Database::get_certificate_log_table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE certificate_id = %s LIMIT 1",
				$certificate_id
			),
			ARRAY_A
		);

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
		$table = Alynt_Certificate_Generator_Database::get_certificate_log_table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				$log_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Update PDF path for a log entry.
	 *
	 * @param int    $log_id Log ID.
	 * @param string $path File path.
	 */
	public function update_pdf_path( int $log_id, string $path ): void {
		global $wpdb;
		$table = Alynt_Certificate_Generator_Database::get_certificate_log_table();

		$wpdb->update(
			$table,
			array(
				'pdf_path' => $path,
			),
			array( 'id' => $log_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete a log entry.
	 *
	 * @param int $log_id Log ID.
	 */
	public function delete( int $log_id ): void {
		global $wpdb;
		$table = Alynt_Certificate_Generator_Database::get_certificate_log_table();

		$wpdb->delete(
			$table,
			array( 'id' => $log_id ),
			array( '%d' )
		);
	}

	/**
	 * Update email status for a log entry.
	 *
	 * @param int   $log_id Log ID.
	 * @param array $status Status payload.
	 * @return void
	 */
	public function update_email_status( int $log_id, array $status ): void {
		global $wpdb;
		$table = Alynt_Certificate_Generator_Database::get_certificate_log_table();

		$wpdb->update(
			$table,
			array(
				'email_status_json' => wp_json_encode( $status ),
			),
			array( 'id' => $log_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Update webhook status for a log entry.
	 *
	 * @param int    $log_id Log ID.
	 * @param string $status Status.
	 * @return void
	 */
	public function update_webhook_status( int $log_id, string $status ): void {
		global $wpdb;
		$table = Alynt_Certificate_Generator_Database::get_certificate_log_table();

		$wpdb->update(
			$table,
			array(
				'webhook_status' => $status,
			),
			array( 'id' => $log_id ),
			array( '%s' ),
			array( '%d' )
		);
	}
}
