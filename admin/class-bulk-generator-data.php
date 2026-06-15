<?php
/**
 * Bulk generator transient and CSV helpers.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi;

defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * Stores bulk job upload metadata and reads CSV data.
 */
class Alynt_Certificate_Generator_Bulk_Generator_Data {
	/**
	 * Store uploaded bulk job metadata.
	 *
	 * @param string $bulk_id            Bulk ID.
	 * @param string $file               CSV file path.
	 * @param int    $template_id        Template ID.
	 * @param bool   $skip_notifications Skip notifications.
	 */
	public function store_upload( string $bulk_id, string $file, int $template_id, bool $skip_notifications ): void {
		set_transient( 'acg_bulk_' . $bulk_id . '_file', $file, DAY_IN_SECONDS );
		set_transient( 'acg_bulk_' . $bulk_id . '_template', $template_id, DAY_IN_SECONDS );
		set_transient( 'acg_bulk_' . $bulk_id . '_skip', $skip_notifications ? 1 : 0, DAY_IN_SECONDS );
	}

	/**
	 * Mark a bulk job as started.
	 *
	 * @param string $bulk_id Bulk ID.
	 * @return bool True when the job was not already started.
	 */
	public function mark_started( string $bulk_id ): bool {
		$key = 'acg_bulk_' . $bulk_id . '_started';
		if ( get_transient( $key ) ) {
			return false;
		}

		set_transient( $key, 1, DAY_IN_SECONDS );
		return true;
	}

	/**
	 * Get bulk data from transients.
	 *
	 * @param string $bulk_id Bulk ID.
	 * @return array|null
	 */
	public function get_bulk_data( string $bulk_id ): ?array {
		if ( '' === $bulk_id ) {
			return null;
		}

		$file        = get_transient( 'acg_bulk_' . $bulk_id . '_file' );
		$template_id = (int) get_transient( 'acg_bulk_' . $bulk_id . '_template' );
		$skip        = (int) get_transient( 'acg_bulk_' . $bulk_id . '_skip' );

		if ( ! $file || $template_id < 1 ) {
			return null;
		}

		return array(
			'file'               => $file,
			'template_id'        => $template_id,
			'skip_notifications' => $skip === 1,
		);
	}

	/**
	 * Read CSV headers.
	 *
	 * @param string $file File path.
	 * @return array
	 */
	public function get_csv_headers( string $file ): array {
		if ( ! file_exists( $file ) ) {
			return array();
		}

		$handle = fopen( $file, 'r' );
		if ( ! $handle ) {
			return array();
		}

		$headers = fgetcsv( $handle );
		fclose( $handle );

		return is_array( $headers ) ? $headers : array();
	}

	/**
	 * Read CSV rows.
	 *
	 * @param string $file File path.
	 * @return array
	 */
	public function get_csv_rows( string $file ): array {
		if ( ! file_exists( $file ) ) {
			return array();
		}

		$rows   = array();
		$handle = fopen( $file, 'r' );
		if ( ! $handle ) {
			return $rows;
		}

		$headers = fgetcsv( $handle );
		if ( ! is_array( $headers ) ) {
			fclose( $handle );
			return $rows;
		}

		while ( true ) {
			$data = fgetcsv( $handle );
			if ( false === $data ) {
				break;
			}

			if ( empty( $data ) ) {
				continue;
			}
			$rows[] = $data;
		}

		fclose( $handle );
		return $rows;
	}

	/**
	 * Iterate CSV rows without loading the full file into memory.
	 *
	 * @param string   $file     File path.
	 * @param callable $callback Callback receiving each row.
	 * @param int      $max_rows Maximum rows to process before returning an error.
	 * @return int|WP_Error Number of processed data rows, or error.
	 */
	public function each_csv_row( string $file, callable $callback, int $max_rows = 0 ) {
		if ( ! file_exists( $file ) ) {
			return new WP_Error( 'acg_bulk_csv_missing', __( 'CSV file could not be found.', 'alynt-certificate-generator' ) );
		}

		$handle = fopen( $file, 'r' );
		if ( ! $handle ) {
			return new WP_Error( 'acg_bulk_csv_unreadable', __( 'CSV file could not be opened.', 'alynt-certificate-generator' ) );
		}

		$headers = fgetcsv( $handle );
		if ( ! is_array( $headers ) ) {
			fclose( $handle );
			return new WP_Error( 'acg_bulk_csv_headers_missing', __( 'CSV headers could not be read.', 'alynt-certificate-generator' ) );
		}

		$count = 0;
		while ( true ) {
			$row = fgetcsv( $handle );
			if ( false === $row ) {
				break;
			}

			if ( empty( $row ) ) {
				continue;
			}

			if ( $max_rows > 0 && $count >= $max_rows ) {
				fclose( $handle );
				return new WP_Error(
					'acg_bulk_csv_too_large',
					sprintf(
						/* translators: %d: maximum rows per bulk job. */
						__( 'CSV files may contain no more than %d data rows per bulk job.', 'alynt-certificate-generator' ),
						$max_rows
					)
				);
			}

			++$count;
			$callback( $row );
		}

		fclose( $handle );
		return $count;
	}
}
