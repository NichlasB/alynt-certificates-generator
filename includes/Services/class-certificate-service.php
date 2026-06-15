<?php
/**
 * Certificate generation service.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Services;

defined( 'ABSPATH' ) || exit;

use Alynt\CertificateGenerator\Database\Alynt_Certificate_Generator_Certificate_Log;
use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Email_Service;
use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Webhook_Dispatcher;
use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Diagnostics_Logger;
use WP_Error;

/**
 * Orchestrates certificate PDF generation, logging, notifications, and webhooks.
 */
class Alynt_Certificate_Generator_Certificate_Service {
	/**
	 * PDF service.
	 *
	 * @var Alynt_Certificate_Generator_Pdf_Service
	 */
	private $pdf_service;

	/**
	 * Log service.
	 *
	 * @var Alynt_Certificate_Generator_Certificate_Log
	 */
	private $log;

	/**
	 * Variable resolver.
	 *
	 * @var Alynt_Certificate_Generator_Certificate_Variable_Resolver
	 */
	private $variable_resolver;

	/**
	 * Constructor.
	 *
	 * @param Alynt_Certificate_Generator_Pdf_Service|null                   $pdf_service       PDF service.
	 * @param Alynt_Certificate_Generator_Certificate_Log|null               $log               Certificate log service.
	 * @param Alynt_Certificate_Generator_Certificate_Variable_Resolver|null $variable_resolver Variable resolver.
	 */
	public function __construct(
		?Alynt_Certificate_Generator_Pdf_Service $pdf_service = null,
		?Alynt_Certificate_Generator_Certificate_Log $log = null,
		?Alynt_Certificate_Generator_Certificate_Variable_Resolver $variable_resolver = null
	) {
		$this->pdf_service       = null !== $pdf_service ? $pdf_service : new Alynt_Certificate_Generator_Pdf_Service();
		$this->log               = null !== $log ? $log : new Alynt_Certificate_Generator_Certificate_Log();
		$this->variable_resolver = null !== $variable_resolver ? $variable_resolver : new Alynt_Certificate_Generator_Certificate_Variable_Resolver();
	}

	/**
	 * Generate a certificate.
	 *
	 * @param int    $template_id Template ID.
	 * @param array  $input_values Input values keyed by variable key.
	 * @param string $method       Generation method (form/webhook/bulk/admin).
	 * @param int    $user_id      User ID.
	 * @param string $ip_address   IP address.
	 * @param bool   $skip_notifications Skip notifications.
	 * @return array|WP_Error
	 */
	public function generate(
		int $template_id,
		array $input_values,
		string $method,
		int $user_id = 0,
		string $ip_address = '',
		bool $skip_notifications = false
	) {
		$template = \get_post( $template_id );
		if ( ! $template || 'acg_cert_template' !== $template->post_type ) {
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'warning',
				'filesystem',
				'template_missing',
				'Certificate generation template was not found.',
				array(
					'template_id' => $template_id,
					'method'      => $method,
				)
			);
			return new WP_Error( 'acg_template_missing', __( 'Template not found.', 'alynt-certificate-generator' ) );
		}

		$image_id      = (int) \get_post_meta( $template_id, 'acg_template_image_id', true );
		$template_path = $image_id ? \get_attached_file( $image_id ) : '';
		if ( '' === $template_path || ! file_exists( $template_path ) ) {
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'error',
				'filesystem',
				'template_image_missing',
				'Certificate template image was missing.',
				array(
					'template_id' => $template_id,
					'image_id'    => $image_id,
					'method'      => $method,
				)
			);
			return new WP_Error( 'acg_template_missing', __( 'Template image is missing.', 'alynt-certificate-generator' ) );
		}

		$orientation = (string) \get_post_meta( $template_id, 'acg_template_orientation', true );
		$orientation = '' !== $orientation ? $orientation : 'landscape';

		$variables      = $this->get_template_variables( $template_id );
		$certificate_id = $this->generate_certificate_id();
		$download_token = $this->generate_token();
		$generated_at   = \current_time( 'mysql' );

		$resolved = $this->variable_resolver->resolve_variables( $variables, $input_values, $certificate_id, $generated_at );
		if ( is_wp_error( $resolved ) ) {
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'warning',
				'admin_action',
				'variable_resolution_failed',
				'Certificate variables could not be resolved.',
				array(
					'template_id' => $template_id,
					'method'      => $method,
					'error_code'  => $resolved->get_error_code(),
				)
			);
			return $resolved;
		}

		$output_path = $this->build_output_path( $template_id, $certificate_id );
		if ( '' === $output_path ) {
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'error',
				'filesystem',
				'pdf_storage_unwritable',
				'Certificate PDF storage directory was not writable.',
				array(
					'template_id' => $template_id,
					'method'      => $method,
				)
			);
			return new WP_Error( 'acg_pdf_storage_unwritable', __( 'Certificate storage directory is not writable.', 'alynt-certificate-generator' ) );
		}

		$rendered = $this->pdf_service->render_pdf( $template_path, $resolved, $orientation, $output_path, $template_id );
		if ( is_wp_error( $rendered ) ) {
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'error',
				'filesystem',
				'pdf_render_failed',
				'Certificate PDF rendering failed.',
				array(
					'template_id' => $template_id,
					'method'      => $method,
					'error_code'  => $rendered->get_error_code(),
				)
			);
			return $rendered;
		}

		$log_id = $this->log->insert(
			array(
				'certificate_id'    => $certificate_id,
				'template_id'       => $template_id,
				'user_id'           => $user_id > 0 ? $user_id : null,
				'generated_by'      => $user_id > 0 ? (string) $user_id : $method,
				'ip_address'        => $ip_address,
				'method'            => $method,
				'variables_json'    => wp_json_encode( $resolved ),
				'pdf_path'          => $output_path,
				'download_token'    => $download_token,
				'created_at'        => $generated_at,
				'email_status_json' => wp_json_encode( array() ),
				'webhook_status'    => 'pending',
			)
		);
		if ( is_wp_error( $log_id ) ) {
			if ( file_exists( $output_path ) ) {
				wp_delete_file( $output_path );
			}
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'critical',
				'database',
				'certificate_log_insert_failed',
				'Certificate log row could not be saved after PDF generation.',
				array(
					'template_id' => $template_id,
					'method'      => $method,
					'error_code'  => $log_id->get_error_code(),
				)
			);
			return $log_id;
		}

		$email_service = new Alynt_Certificate_Generator_Email_Service();
		$email_result  = $email_service->send_for_log(
			array(
				'id'             => $log_id,
				'template_id'    => $template_id,
				'certificate_id' => $certificate_id,
				'download_token' => $download_token,
				'pdf_path'       => $output_path,
				'created_at'     => $generated_at,
			),
			$resolved,
			$skip_notifications
		);
		if ( is_wp_error( $email_result ) ) {
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'warning',
				'external_api',
				'email_send_failed',
				'Certificate email delivery failed.',
				array(
					'template_id'     => $template_id,
					'certificate_log' => $log_id,
					'error_code'      => $email_result->get_error_code(),
				)
			);
			return $email_result;
		}

		$this->dispatch_outgoing_webhook(
			$template_id,
			$log_id,
			$resolved,
			$certificate_id,
			$generated_at,
			$this->build_download_url( $certificate_id, $download_token )
		);

		return array(
			'log_id'         => $log_id,
			'certificate_id' => $certificate_id,
			'download_token' => $download_token,
			'download_url'   => $this->build_download_url( $certificate_id, $download_token ),
			'pdf_path'       => $output_path,
			'generated_at'   => $generated_at,
		);
	}

	/**
	 * Build output PDF path.
	 *
	 * @param int    $template_id Template ID.
	 * @param string $certificate_id Certificate ID.
	 * @return string
	 */
	private function build_output_path( int $template_id, string $certificate_id ): string {
		$upload_dir   = \wp_upload_dir();
		$uploads_base = wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) );

		$custom_path = trim( (string) $this->get_setting( 'pdf_storage_path', '' ) );
		if ( '' !== $custom_path ) {
			$custom_path = trim( wp_normalize_path( $custom_path ), '/' );
			if ( false !== strpos( $custom_path, '..' ) || 0 === strpos( $custom_path, '/' ) || preg_match( '/\A[A-Za-z]:/', $custom_path ) ) {
				return '';
			}
			$base_dir = $uploads_base . $custom_path . '/';
		} else {
			$base_dir = $uploads_base . 'alynt-certificates/' . gmdate( 'Y' ) . '/' . gmdate( 'm' ) . '/';
		}

		$base_dir = wp_normalize_path( $base_dir );
		if ( 0 !== strpos( $base_dir, $uploads_base ) ) {
			return '';
		}

		if ( ! \wp_mkdir_p( $base_dir ) || ! is_dir( $base_dir ) || ! is_writable( $base_dir ) ) {
			return '';
		}

		$template_slug = \sanitize_title( \get_the_title( $template_id ) );
		if ( '' === $template_slug ) {
			$template_slug = 'template';
		}

		$timestamp = time();
		$filename  = sprintf( '%s-%s-%s.pdf', $template_slug, $certificate_id, $timestamp );

		return $base_dir . $filename;
	}

	/**
	 * Build secure download URL.
	 *
	 * @param string $certificate_id Certificate ID.
	 * @param string $token Token.
	 * @return string
	 */
	private function build_download_url( string $certificate_id, string $token ): string {
		$endpoint = sprintf( 'acg/v1/certificates/%s/download', rawurlencode( $certificate_id ) );
		return add_query_arg( 'token', rawurlencode( $token ), rest_url( $endpoint ) );
	}

	/**
	 * Dispatch outgoing webhook if configured.
	 *
	 * @param int    $template_id Template ID.
	 * @param int    $log_id Log ID.
	 * @param array  $variables Resolved variables.
	 * @param string $certificate_id Certificate ID.
	 * @param string $generated_at Timestamp.
	 * @param string $download_url Download URL.
	 */
	private function dispatch_outgoing_webhook(
		int $template_id,
		int $log_id,
		array $variables,
		string $certificate_id,
		string $generated_at,
		string $download_url
	): void {
		$settings = $this->get_webhook_settings( $template_id );
		if ( empty( $settings['outgoing']['enabled'] ) || empty( $settings['outgoing']['url'] ) ) {
			return;
		}

		$payload = array(
			'certificate_id' => $certificate_id,
			'template_id'    => $template_id,
			'generated_at'   => $generated_at,
			'download_url'   => $download_url,
			'variables'      => $this->build_variable_map( $variables ),
		);

		$dispatcher = new Alynt_Certificate_Generator_Webhook_Dispatcher();
		$result     = $dispatcher->enqueue_outgoing( $template_id, $log_id, $payload, $settings['outgoing']['url'] );
		if ( is_wp_error( $result ) ) {
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'warning',
				'external_api',
				'outgoing_webhook_enqueue_failed',
				'Outgoing webhook could not be queued.',
				array(
					'template_id'     => $template_id,
					'certificate_log' => $log_id,
					'error_code'      => $result->get_error_code(),
				)
			);
		}
	}

	/**
	 * Build variable map for payload.
	 *
	 * @param array $variables Resolved variables.
	 * @return array<string, mixed>
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

	/**
	 * Get webhook settings.
	 *
	 * @param int $template_id Template ID.
	 * @return array
	 */
	private function get_webhook_settings( int $template_id ): array {
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
	 * Resolve template variables.
	 *
	 * @param int $template_id Template ID.
	 * @return array
	 */
	private function get_template_variables( int $template_id ): array {
		$raw     = (string) \get_post_meta( $template_id, 'acg_template_variables', true );
		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Generate unique certificate ID.
	 *
	 * @return string
	 */
	private function generate_certificate_id(): string {
		$prefix = (string) $this->get_setting( 'certificate_id_prefix', 'ACG-' );
		$format = (string) $this->get_setting( 'certificate_id_format', '{prefix}{id}' );
		$random = strtoupper( wp_generate_password( 10, false, false ) );

		return str_replace(
			array( '{prefix}', '{id}' ),
			array( $prefix, $random ),
			$format
		);
	}

	/**
	 * Generate download token.
	 *
	 * @return string
	 */
	private function generate_token(): string {
		try {
			return bin2hex( random_bytes( 16 ) );
		} catch ( \Exception $e ) {
			return wp_generate_password( 32, false, false );
		}
	}

	/**
	 * Get a plugin setting.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default_value Default value.
	 * @return mixed
	 */
	private function get_setting( string $key, $default_value ) {
		$settings = \get_option( ALYNT_CERTIFICATE_GENERATOR_SETTINGS_OPTION, array() );
		if ( ! is_array( $settings ) || ! array_key_exists( $key, $settings ) ) {
			return $default_value;
		}

		return $settings[ $key ];
	}
}
