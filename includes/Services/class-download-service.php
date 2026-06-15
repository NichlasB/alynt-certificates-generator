<?php
/**
 * Secure download handler.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Services;

defined( 'ABSPATH' ) || exit;

use Alynt\CertificateGenerator\Database\Alynt_Certificate_Generator_Certificate_Log;
use WP_Error;
use WP_REST_Request;

class Alynt_Certificate_Generator_Download_Service {
	/**
	 * Certificate log access.
	 *
	 * @var Alynt_Certificate_Generator_Certificate_Log
	 */
	private $log;

	public function __construct( ?Alynt_Certificate_Generator_Certificate_Log $log = null ) {
		$this->log = $log ? $log : new Alynt_Certificate_Generator_Certificate_Log();
	}

	/**
	 * Serve a secure download.
	 *
	 * @param WP_REST_Request $request Request instance.
	 * @return WP_Error|void
	 */
	public function serve_download( WP_REST_Request $request ) {
		$certificate_id = sanitize_text_field( (string) $request['certificate_id'] );
		$token          = sanitize_text_field( (string) $request->get_param( 'token' ) );

		if ( '' === $certificate_id || '' === $token ) {
			return new WP_Error( 'acg_missing_token', __( 'Download token missing.', 'alynt-certificate-generator' ), array( 'status' => 401 ) );
		}

		$log = $this->log->get_by_certificate_and_token( $certificate_id, $token );
		if ( ! $log ) {
			return new WP_Error( 'acg_invalid_token', __( 'Invalid download token.', 'alynt-certificate-generator' ), array( 'status' => 403 ) );
		}

		$file_path = $log['pdf_path'] ?? '';
		if ( '' === $file_path || ! $this->is_safe_pdf_path( (string) $file_path ) || ! file_exists( $file_path ) ) {
			return new WP_Error( 'acg_file_missing', __( 'Certificate file not found.', 'alynt-certificate-generator' ), array( 'status' => 404 ) );
		}
		if ( ! is_readable( $file_path ) || 0 === filesize( $file_path ) ) {
			return new WP_Error( 'acg_file_unreadable', __( 'Certificate file could not be read.', 'alynt-certificate-generator' ), array( 'status' => 500 ) );
		}

		$filename = sanitize_file_name( basename( $file_path ) );
		if ( '' === $filename ) {
			$filename = 'certificate.pdf';
		}

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Cache-Control: private, max-age=0, no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );

		readfile( $file_path );
		exit;
	}

	/**
	 * Check that a stored PDF path stays inside WordPress uploads.
	 *
	 * @param string $file_path File path.
	 * @return bool
	 */
	private function is_safe_pdf_path( string $file_path ): bool {
		$upload_dir = wp_get_upload_dir();
		if ( empty( $upload_dir['basedir'] ) ) {
			return false;
		}

		$uploads_base = wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) );
		$normalized   = wp_normalize_path( $file_path );

		return 0 === strpos( $normalized, $uploads_base ) && 'pdf' === strtolower( pathinfo( $normalized, PATHINFO_EXTENSION ) );
	}
}
