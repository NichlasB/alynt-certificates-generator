<?php
/**
 * Incoming webhook handler.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Rest;

use Alynt\CertificateGenerator\Database\Alynt_Certificate_Generator_Webhook_Log;
use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Certificate_Service;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class Alynt_Certificate_Generator_Webhook_Service {
	/**
	 * Log access.
	 *
	 * @var Alynt_Certificate_Generator_Webhook_Log
	 */
	private $log;

	/**
	 * Certificate service.
	 *
	 * @var Alynt_Certificate_Generator_Certificate_Service
	 */
	private $certificate_service;

	public function __construct(
		?Alynt_Certificate_Generator_Webhook_Log $log = null,
		?Alynt_Certificate_Generator_Certificate_Service $certificate_service = null
	) {
		$this->log = $log ? $log : new Alynt_Certificate_Generator_Webhook_Log();
		$this->certificate_service = $certificate_service ? $certificate_service : new Alynt_Certificate_Generator_Certificate_Service();
	}

	/**
	 * Handle incoming webhook.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_incoming( WP_REST_Request $request ) {
		$template_id = (int) $request['id'];
		$post = \get_post( $template_id );
		if ( ! $post || 'acg_certificate_template' !== $post->post_type ) {
			return new WP_Error( 'acg_template_missing', __( 'Template not found.', 'alynt-certificate-generator' ), array( 'status' => 404 ) );
		}

		$settings = $this->get_webhook_settings( $template_id );
		$api_key  = $settings['incoming']['api_key'];
		if ( '' === $api_key ) {
			return new WP_Error( 'acg_webhook_unconfigured', __( 'Webhook not configured.', 'alynt-certificate-generator' ), array( 'status' => 400 ) );
		}

		$provided_key = (string) $request->get_header( 'X-ACG-API-Key' );
		if ( '' === $provided_key || ! hash_equals( $api_key, $provided_key ) ) {
			$this->log_attempt( $template_id, $request, false, 401, 'API key mismatch.' );
			return new WP_Error( 'acg_webhook_auth', __( 'Authentication failed.', 'alynt-certificate-generator' ), array( 'status' => 401 ) );
		}

		$signature_secret = $settings['incoming']['signature_secret'];
		if ( '' !== $signature_secret ) {
			$signature = (string) $request->get_header( 'X-ACG-Signature' );
			$expected  = hash_hmac( 'sha256', (string) $request->get_body(), $signature_secret );
			$signature = str_replace( 'sha256=', '', $signature );
			if ( '' === $signature || ! hash_equals( $expected, $signature ) ) {
				$this->log_attempt( $template_id, $request, false, 401, 'Signature mismatch.' );
				return new WP_Error( 'acg_webhook_signature', __( 'Signature verification failed.', 'alynt-certificate-generator' ), array( 'status' => 401 ) );
			}
		}

		$rate_limit = $settings['incoming']['rate_limit'];
		$global_rate = (int) $this->get_setting( 'webhook_rate_limit_per_minute', 100 );
		$limit = $rate_limit > 0 ? $rate_limit : $global_rate;
		if ( ! $this->check_rate_limit( $template_id, $limit ) ) {
			$this->log_attempt( $template_id, $request, false, 429, 'Rate limit exceeded.' );
			return new WP_Error( 'acg_webhook_rate', __( 'Rate limit exceeded.', 'alynt-certificate-generator' ), array( 'status' => 429 ) );
		}

		$payload = $request->get_json_params();
		if ( empty( $payload ) ) {
			$payload = $request->get_body_params();
		}

		if ( ! is_array( $payload ) ) {
			$this->log_attempt( $template_id, $request, false, 400, 'Invalid payload.' );
			return new WP_Error( 'acg_webhook_invalid', __( 'Invalid payload.', 'alynt-certificate-generator' ), array( 'status' => 400 ) );
		}

		$items = $this->normalize_payload_items( $payload );
		$results = array();
		$all_success = true;
		$ip_address = $this->get_request_ip();

		foreach ( $items as $index => $item ) {
			$result = $this->certificate_service->generate(
				$template_id,
				$item,
				'webhook',
				0,
				$ip_address
			);

			if ( is_wp_error( $result ) ) {
				$all_success = false;
				$results[] = array(
					'index'  => $index,
					'success' => false,
					'error'  => $result->get_error_message(),
				);
				continue;
			}

			$results[] = array(
				'index'         => $index,
				'success'       => true,
				'certificate_id'=> $result['certificate_id'],
				'download_url'  => $result['download_url'],
			);
		}

		$status_code = $all_success ? 200 : 207;
		$this->log_attempt( $template_id, $request, $all_success, $status_code, $all_success ? '' : 'Partial failure.' );

		return new WP_REST_Response(
			array(
				'success' => $all_success,
				'results' => $results,
			),
			$status_code
		);
	}

	/**
	 * Normalize payload to a list of items.
	 *
	 * @param array $payload Payload.
	 * @return array
	 */
	private function normalize_payload_items( array $payload ): array {
		if ( isset( $payload['items'] ) && is_array( $payload['items'] ) ) {
			return $payload['items'];
		}

		$is_list = array_keys( $payload ) === range( 0, count( $payload ) - 1 );
		return $is_list ? $payload : array( $payload );
	}

	/**
	 * Check rate limit.
	 *
	 * @param int $template_id Template ID.
	 * @param int $limit Limit per minute.
	 * @return bool
	 */
	private function check_rate_limit( int $template_id, int $limit ): bool {
		$ip = $this->get_request_ip();
		$key = 'acg_webhook_rate_' . $template_id . '_' . md5( $ip );
		$count = (int) \get_transient( $key );

		if ( $count >= $limit ) {
			return false;
		}

		\set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Log webhook attempt.
	 *
	 * @param int              $template_id Template ID.
	 * @param WP_REST_Request  $request Request.
	 * @param bool             $success Success flag.
	 * @param int              $status_code Status code.
	 * @param string           $error Error message.
	 */
	private function log_attempt( int $template_id, WP_REST_Request $request, bool $success, int $status_code, string $error ): void {
		$route = $request->get_route();
		$url   = '' !== $route ? rest_url( $route ) : '';

		$this->log->insert(
			array(
				'direction'       => 'incoming',
				'template_id'     => $template_id,
				'certificate_log_id' => null,
				'url'             => esc_url_raw( $url ),
				'payload_json'    => wp_json_encode( $request->get_json_params() ),
				'response_code'   => $status_code,
				'success'         => $success ? 1 : 0,
				'error_message'   => $error,
				'attempt_number'  => 1,
				'created_at'      => current_time( 'mysql' ),
				'ip_address'      => $this->get_request_ip(),
			)
		);
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

		$incoming = array(
			'api_key'          => '',
			'signature_secret' => '',
			'rate_limit'       => 0,
		);

		if ( is_array( $decoded ) && isset( $decoded['incoming'] ) && is_array( $decoded['incoming'] ) ) {
			$incoming = array_merge( $incoming, $decoded['incoming'] );
		}

		return array(
			'incoming' => $incoming,
		);
	}

	/**
	 * Get request IP.
	 *
	 * @return string
	 */
	private function get_request_ip(): string {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	/**
	 * Get global setting.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default.
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
