<?php
/**
 * Webhook log data access.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Database;

defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * Writes incoming and outgoing webhook attempt log rows.
 */
class Alynt_Certificate_Generator_Webhook_Log {
	/**
	 * Insert a webhook log row.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @return int|WP_Error
	 */
	public function insert( array $data ) {
		global $wpdb;
		$table = Alynt_Certificate_Generator_Database::get_webhook_log_table();

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
				'%d',
				'%d',
				'%s',
				'%d',
				'%s',
				'%s',
			)
		);
		if ( false === $result ) {
			return new WP_Error( 'acg_webhook_log_insert_failed', __( 'Webhook log could not be saved.', 'alynt-certificate-generator' ) );
		}

		return (int) $wpdb->insert_id;
	}
}
