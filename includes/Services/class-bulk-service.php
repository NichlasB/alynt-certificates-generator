<?php
/**
 * Bulk processing service.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Services;

class Alynt_Certificate_Generator_Bulk_Service {
	/**
	 * Process a single bulk row.
	 *
	 * @param int    $template_id Template ID.
	 * @param array  $values      Mapped values.
	 * @param bool   $skip_notifications Skip emails.
	 * @param string $bulk_id Bulk job ID.
	 */
	public function process_row( int $template_id, array $values, bool $skip_notifications, string $bulk_id ): void {
		$service = new Alynt_Certificate_Generator_Certificate_Service();
		$result  = $service->generate( $template_id, $values, 'bulk', 0, '', $skip_notifications );

		$this->increment_counter( $bulk_id, 'processed' );

		if ( is_wp_error( $result ) ) {
			$this->increment_counter( $bulk_id, 'failed' );
		}
	}

	/**
	 * Increment progress counters.
	 *
	 * @param string $bulk_id Bulk ID.
	 * @param string $type Counter type.
	 */
	private function increment_counter( string $bulk_id, string $type ): void {
		$key = 'acg_bulk_' . $bulk_id . '_' . $type;
		$count = (int) \get_transient( $key );
		\set_transient( $key, $count + 1, DAY_IN_SECONDS );
	}
}
