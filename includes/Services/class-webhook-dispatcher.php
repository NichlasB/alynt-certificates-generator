<?php
/**
 * Outgoing webhook dispatcher.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Services;

use Alynt\CertificateGenerator\Database\Alynt_Certificate_Generator_Certificate_Log;
use Alynt\CertificateGenerator\Database\Alynt_Certificate_Generator_Webhook_Log;

class Alynt_Certificate_Generator_Webhook_Dispatcher {
	/**
	 * Webhook log.
	 *
	 * @var Alynt_Certificate_Generator_Webhook_Log
	 */
	private $webhook_log;

	/**
	 * Certificate log.
	 *
	 * @var Alynt_Certificate_Generator_Certificate_Log
	 */
	private $certificate_log;

	public function __construct() {
		$this->webhook_log     = new Alynt_Certificate_Generator_Webhook_Log();
		$this->certificate_log = new Alynt_Certificate_Generator_Certificate_Log();
	}

	/**
	 * Enqueue an outgoing webhook send.
	 *
	 * @param int    $template_id Template ID.
	 * @param int    $log_id Log ID.
	 * @param array  $payload Payload data.
	 * @param string $url Target URL.
	 * @param int    $attempt Attempt number.
	 */
	public function enqueue_outgoing( int $template_id, int $log_id, array $payload, string $url, int $attempt = 1 ): void {
		if ( '' === $url ) {
			return;
		}

		$args = array( $template_id, $log_id, $payload, $url, $attempt );

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'alynt_certificate_generator_send_webhook', $args, ALYNT_CERTIFICATE_GENERATOR_AS_GROUP );
			return;
		}

		wp_schedule_single_event( time(), 'alynt_certificate_generator_send_webhook', $args );
	}

	/**
	 * Handle sending a webhook.
	 *
	 * @param int    $template_id Template ID.
	 * @param int    $log_id Log ID.
	 * @param array  $payload Payload.
	 * @param string $url Target URL.
	 * @param int    $attempt Attempt number.
	 */
	public function handle_send( int $template_id, int $log_id, array $payload, string $url, int $attempt = 1 ): void {
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		$success = false;
		$response_code = 0;
		$error_message = '';

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
		} else {
			$response_code = (int) wp_remote_retrieve_response_code( $response );
			$success       = $response_code >= 200 && $response_code < 300;
			if ( ! $success ) {
				$error_message = sprintf( 'HTTP %d', $response_code );
			}
		}

		$this->webhook_log->insert(
			array(
				'direction'        => 'outgoing',
				'template_id'      => $template_id,
				'certificate_log_id' => $log_id,
				'url'              => esc_url_raw( $url ),
				'payload_json'     => wp_json_encode( $payload ),
				'response_code'    => $response_code,
				'success'          => $success ? 1 : 0,
				'error_message'    => $error_message,
				'attempt_number'   => $attempt,
				'created_at'       => current_time( 'mysql' ),
			)
		);

		if ( $success ) {
			$this->certificate_log->update_webhook_status( $log_id, 'sent' );
			return;
		}

		if ( $attempt >= 3 ) {
			$this->certificate_log->update_webhook_status( $log_id, 'failed' );
			$this->set_admin_notice( $log_id, $url );
		} else {
			$this->certificate_log->update_webhook_status( $log_id, 'pending' );
		}

		$delays = $this->get_retry_schedule();
		$delay_index = $attempt - 1;
		if ( isset( $delays[ $delay_index ] ) ) {
			$next_attempt = $attempt + 1;
			$timestamp    = time() + (int) $delays[ $delay_index ];
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action(
					$timestamp,
					'alynt_certificate_generator_send_webhook',
					array( $template_id, $log_id, $payload, $url, $next_attempt ),
					ALYNT_CERTIFICATE_GENERATOR_AS_GROUP
				);
				return;
			}

			wp_schedule_single_event( $timestamp, 'alynt_certificate_generator_send_webhook', array( $template_id, $log_id, $payload, $url, $next_attempt ) );
		}
	}

	/**
	 * Get retry schedule.
	 *
	 * @return array<int, int>
	 */
	private function get_retry_schedule(): array {
		$value = (string) $this->get_setting( 'webhook_retry_schedule', '60,300,1800,7200' );
		$parts = array_filter(
			array_map(
				static function ( $part ) {
					$part = trim( (string) $part );
					return $part !== '' ? (int) $part : 0;
				},
				explode( ',', $value )
			),
			static function ( $part ) {
				return $part > 0;
			}
		);

		return array_values( $parts );
	}

	/**
	 * Store admin notice for webhook failures.
	 *
	 * @param int    $log_id Log ID.
	 * @param string $url Webhook URL.
	 */
	private function set_admin_notice( int $log_id, string $url ): void {
		set_transient(
			'acg_webhook_failure_notice',
			sprintf(
				'Outgoing webhook failed for log #%d. URL: %s',
				$log_id,
				$url
			),
			MINUTE_IN_SECONDS * 5
		);
	}

	/**
	 * Get a plugin setting.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	private function get_setting( string $key, $default ) {
		$settings = get_option( ALYNT_CERTIFICATE_GENERATOR_SETTINGS_OPTION, array() );
		if ( ! is_array( $settings ) || ! array_key_exists( $key, $settings ) ) {
			return $default;
		}

		return $settings[ $key ];
	}
}
