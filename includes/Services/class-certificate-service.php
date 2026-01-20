<?php
/**
 * Certificate generation service.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Services;

use Alynt\CertificateGenerator\Database\Alynt_Certificate_Generator_Certificate_Log;
use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Email_Service;
use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Webhook_Dispatcher;
use WP_Error;

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

	public function __construct() {
		$this->pdf_service = new Alynt_Certificate_Generator_Pdf_Service();
		$this->log         = new Alynt_Certificate_Generator_Certificate_Log();
	}

	/**
	 * Generate a certificate.
	 *
	 * @param int    $template_id Template ID.
	 * @param array  $input_values Input values keyed by variable key.
	 * @param string $method       Generation method (form/webhook/bulk/admin).
	 * @param int    $user_id      User ID.
	 * @param string $ip_address   IP address.
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
			return new WP_Error( 'acg_template_missing', __( 'Template not found.', 'alynt-certificate-generator' ) );
		}

		$image_id = (int) \get_post_meta( $template_id, 'acg_template_image_id', true );
		$template_path = $image_id ? \get_attached_file( $image_id ) : '';
		if ( '' === $template_path || ! file_exists( $template_path ) ) {
			return new WP_Error( 'acg_template_missing', __( 'Template image is missing.', 'alynt-certificate-generator' ) );
		}

		$orientation = (string) \get_post_meta( $template_id, 'acg_template_orientation', true );
		$orientation = '' !== $orientation ? $orientation : 'landscape';

		$variables = $this->get_template_variables( $template_id );
		$certificate_id = $this->generate_certificate_id();
		$download_token = $this->generate_token();
		$generated_at   = \current_time( 'mysql' );

		$resolved = $this->resolve_variables( $variables, $input_values, $certificate_id, $generated_at );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$output_path = $this->build_output_path( $template_id, $certificate_id );
		$rendered = $this->pdf_service->render_pdf( $template_path, $resolved, $orientation, $output_path );
		if ( is_wp_error( $rendered ) ) {
			return $rendered;
		}

		$log_id = $this->log->insert(
			array(
				'certificate_id'   => $certificate_id,
				'template_id'      => $template_id,
				'user_id'          => $user_id > 0 ? $user_id : null,
				'generated_by'     => $user_id > 0 ? (string) $user_id : $method,
				'ip_address'       => $ip_address,
				'method'           => $method,
				'variables_json'   => wp_json_encode( $resolved ),
				'pdf_path'         => $output_path,
				'download_token'   => $download_token,
				'created_at'       => $generated_at,
				'email_status_json' => wp_json_encode( array() ),
				'webhook_status'   => 'pending',
			)
		);

		$email_service = new Alynt_Certificate_Generator_Email_Service();
		$email_service->send_for_log(
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
	 * Resolve variable values.
	 *
	 * @param array  $variables Template variables.
	 * @param array  $input     User input values.
	 * @param string $certificate_id Certificate ID.
	 * @param string $generated_at   Generated timestamp.
	 * @return array|WP_Error
	 */
	private function resolve_variables( array $variables, array $input, string $certificate_id, string $generated_at ) {
		$missing = array();
		$resolved = array();
		$date_format_default = $this->get_setting( 'default_date_format', 'Y-m-d' );

		foreach ( $variables as $variable ) {
			$type = $variable['type'] ?? 'text';
			$key  = $variable['key'] ?? '';
			$required = ! empty( $variable['required'] );
			$value = '';

			if ( 'auto' === $type ) {
				$auto_type = $variable['auto_type'] ?? 'certificate_id';
				if ( 'generation_date' === $auto_type ) {
					$value = $this->format_date( $generated_at, $variable['date_format'] ?? $date_format_default );
				} else {
					$value = $certificate_id;
				}
			} elseif ( 'date' === $type ) {
				$value = isset( $input[ $key ] ) ? (string) $input[ $key ] : '';
				$value = $this->format_date( $value, $variable['date_format'] ?? $date_format_default );
			} else {
				$value = isset( $input[ $key ] ) ? $input[ $key ] : '';
			}

			if ( $required && '' === (string) $value && 'image' !== $type ) {
				$missing[] = $key;
			}

			$resolved[] = array_merge( $variable, array( 'value' => $value ) );
		}

		if ( ! empty( $missing ) ) {
			return new WP_Error(
				'acg_missing_fields',
				sprintf(
					__( 'Missing required fields: %s', 'alynt-certificate-generator' ),
					implode( ', ', $missing )
				)
			);
		}

		return $resolved;
	}

	/**
	 * Format date value.
	 *
	 * @param string $value  Input value.
	 * @param string $format Output format.
	 * @return string
	 */
	private function format_date( string $value, string $format ): string {
		$date = date_create( $value );
		if ( false === $date ) {
			return $value;
		}

		return $date->format( $format );
	}

	/**
	 * Build output PDF path.
	 *
	 * @param int    $template_id Template ID.
	 * @param string $certificate_id Certificate ID.
	 * @return string
	 */
	private function build_output_path( int $template_id, string $certificate_id ): string {
		$upload_dir = \wp_upload_dir();
		$base_dir   = trailingslashit( $upload_dir['basedir'] );

		$custom_path = trim( (string) $this->get_setting( 'pdf_storage_path', '' ) );
		if ( '' !== $custom_path ) {
			$base_dir .= trim( $custom_path, "/\\" ) . '/';
		} else {
			$base_dir .= 'alynt-certificates/' . gmdate( 'Y' ) . '/' . gmdate( 'm' ) . '/';
		}

		\wp_mkdir_p( $base_dir );

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
			'certificate_id'   => $certificate_id,
			'template_id'      => $template_id,
			'generated_at'     => $generated_at,
			'download_url'     => $download_url,
			'variables'        => $this->build_variable_map( $variables ),
		);

		$dispatcher = new Alynt_Certificate_Generator_Webhook_Dispatcher();
		$dispatcher->enqueue_outgoing( $template_id, $log_id, $payload, $settings['outgoing']['url'] );
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
		$raw = (string) \get_post_meta( $template_id, 'acg_template_webhook_settings', true );
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
		$raw = (string) \get_post_meta( $template_id, 'acg_template_variables', true );
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
		return bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Get a plugin setting.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	private function get_setting( string $key, $default ) {
		$settings = \get_option( ALYNT_CERTIFICATE_GENERATOR_SETTINGS_OPTION, array() );
		if ( ! is_array( $settings ) || ! array_key_exists( $key, $settings ) ) {
			return $default;
		}

		return $settings[ $key ];
	}
}
