<?php
/**
 * Bulk status REST service.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class Alynt_Certificate_Generator_Bulk_Service {
	/**
	 * Get bulk status.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_status( WP_REST_Request $request ) {
		if ( ! current_user_can( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE ) ) {
			return new WP_Error( 'acg_forbidden', __( 'Access denied.', 'alynt-certificate-generator' ), array( 'status' => 403 ) );
		}

		$bulk_id = sanitize_text_field( (string) $request['bulk_id'] );
		if ( '' === $bulk_id ) {
			return new WP_Error( 'acg_invalid_bulk', __( 'Bulk ID missing.', 'alynt-certificate-generator' ), array( 'status' => 400 ) );
		}

		$total     = (int) get_transient( 'acg_bulk_' . $bulk_id . '_total' );
		$processed = (int) get_transient( 'acg_bulk_' . $bulk_id . '_processed' );
		$failed    = (int) get_transient( 'acg_bulk_' . $bulk_id . '_failed' );

		return new WP_REST_Response(
			array(
				'total'     => $total,
				'processed' => $processed,
				'failed'    => $failed,
			),
			200
		);
	}
}
