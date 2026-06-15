<?php
/**
 * Certificate log action helper.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi;

defined( 'ABSPATH' ) || exit;

class Alynt_Certificate_Generator_Certificate_Log_Action_Helper {
	/**
	 * Build output path for regenerated PDFs.
	 *
	 * @param int    $template_id    Template ID.
	 * @param string $certificate_id Certificate ID.
	 * @return string
	 */
	public function build_output_path( int $template_id, string $certificate_id ): string {
		$upload_dir = \wp_upload_dir();
		$base_dir   = trailingslashit( $upload_dir['basedir'] ) . 'alynt-certificates/' . gmdate( 'Y' ) . '/' . gmdate( 'm' ) . '/';
		\wp_mkdir_p( $base_dir );

		$template_slug = \sanitize_title( \get_the_title( $template_id ) );
		if ( '' === $template_slug ) {
			$template_slug = 'template';
		}

		$filename = sprintf( '%s-%s-%s.pdf', $template_slug, $certificate_id, time() );
		return $base_dir . $filename;
	}

	/**
	 * Build webhook payload.
	 *
	 * @param array $log       Log row.
	 * @param array $variables Resolved variables.
	 * @return array
	 */
	public function build_webhook_payload( array $log, array $variables ): array {
		return array(
			'certificate_id' => $log['certificate_id'],
			'template_id'    => (int) $log['template_id'],
			'generated_at'   => $log['created_at'],
			'download_url'   => add_query_arg(
				'token',
				rawurlencode( (string) $log['download_token'] ),
				rest_url( 'acg/v1/certificates/' . rawurlencode( (string) $log['certificate_id'] ) . '/download' )
			),
			'variables'      => $this->build_variable_map( $variables ),
		);
	}

	/**
	 * Get webhook settings.
	 *
	 * @param int $template_id Template ID.
	 * @return array
	 */
	public function get_webhook_settings( int $template_id ): array {
		$raw     = (string) \get_post_meta( $template_id, 'acg_template_webhook_settings', true );
		$decoded = json_decode( $raw, true );

		$outgoing = array(
			'url'     => '',
			'enabled' => false,
		);

		if ( is_array( $decoded ) && isset( $decoded['outgoing'] ) && is_array( $decoded['outgoing'] ) ) {
			$outgoing = array_merge( $outgoing, $decoded['outgoing'] );
		}

		return array(
			'outgoing' => $outgoing,
		);
	}

	/**
	 * Build variable map for webhook payloads.
	 *
	 * @param array $variables Resolved variables.
	 * @return array
	 */
	private function build_variable_map( array $variables ): array {
		$map = array();
		foreach ( $variables as $variable ) {
			if ( isset( $variable['key'] ) ) {
				$map[ (string) $variable['key'] ] = $variable['value'] ?? '';
			}
		}

		return $map;
	}
}
