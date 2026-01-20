<?php
/**
 * Webhook log data access.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Database;

class Alynt_Certificate_Generator_Webhook_Log {
	/**
	 * Insert a webhook log row.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @return int
	 */
	public function insert( array $data ): int {
		global $wpdb;
		$table = Alynt_Certificate_Generator_Database::get_webhook_log_table();

		$wpdb->insert( $table, $data );
		return (int) $wpdb->insert_id;
	}
}
