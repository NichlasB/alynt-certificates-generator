<?php
/**
 * Template REST service.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class Alynt_Certificate_Generator_Template_Service {
	/**
	 * Update template variables.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_variables( WP_REST_Request $request ) {
		$template_id = (int) $request['id'];
		$post        = \get_post( $template_id );

		if ( ! $post || 'acg_certificate_template' !== $post->post_type ) {
			return new WP_Error(
				'acg_invalid_template',
				__( 'Template not found.', 'alynt-certificate-generator' ),
				array( 'status' => 404 )
			);
		}

		$variables = $request->get_param( 'variables' );
		if ( is_array( $variables ) || is_object( $variables ) ) {
			$variables = wp_json_encode( $variables );
		}

		if ( ! is_string( $variables ) ) {
			return new WP_Error(
				'acg_invalid_variables',
				__( 'Variables payload is invalid.', 'alynt-certificate-generator' ),
				array( 'status' => 400 )
			);
		}

		\update_post_meta( $template_id, 'acg_template_variables', $variables );

		return new WP_REST_Response(
			array(
				'success' => true,
			),
			200
		);
	}
}
