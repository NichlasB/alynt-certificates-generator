<?php
/**
 * Outgoing webhook dispatcher.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Services;

defined( 'ABSPATH' ) || exit;

use Alynt\CertificateGenerator\Database\Alynt_Certificate_Generator_Certificate_Log;
use Alynt\CertificateGenerator\Database\Alynt_Certificate_Generator_Webhook_Log;
use WP_Error;

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
	 * @return bool|WP_Error
	 */
	public function enqueue_outgoing( int $template_id, int $log_id, array $payload, string $url, int $attempt = 1 ) {
		if ( '' === $url ) {
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'warning',
				'external_api',
				'webhook_url_missing',
				'Outgoing webhook URL was missing.',
				array(
					'template_id'     => $template_id,
					'certificate_log' => $log_id,
				)
			);
			return new WP_Error( 'acg_webhook_url_missing', __( 'Webhook URL is missing.', 'alynt-certificate-generator' ) );
		}
		if ( ! $this->is_https_url( $url ) ) {
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'warning',
				'external_api',
				'webhook_url_not_https',
				'Outgoing webhook URL did not use HTTPS.',
				array(
					'template_id'     => $template_id,
					'certificate_log' => $log_id,
				)
			);
			return new WP_Error( 'acg_webhook_url_invalid', __( 'Webhook URL must use HTTPS.', 'alynt-certificate-generator' ) );
		}

		$args = array( $template_id, $log_id, $payload, $url, $attempt );

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			$action_id = as_enqueue_async_action( 'alynt_certificate_generator_send_webhook', $args, ALYNT_CERTIFICATE_GENERATOR_AS_GROUP );
			if ( ! $action_id ) {
				Alynt_Certificate_Generator_Diagnostics_Logger::log(
					'error',
					'cron',
					'webhook_action_scheduler_enqueue_failed',
					'Outgoing webhook could not be queued with Action Scheduler.',
					array(
						'template_id'     => $template_id,
						'certificate_log' => $log_id,
						'attempt'         => $attempt,
					)
				);
			}
			return $action_id ? true : new WP_Error( 'acg_webhook_schedule_failed', __( 'Webhook could not be scheduled.', 'alynt-certificate-generator' ) );
		}

		$scheduled = wp_schedule_single_event( time(), 'alynt_certificate_generator_send_webhook', $args );
		if ( is_wp_error( $scheduled ) ) {
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'error',
				'cron',
				'webhook_wp_cron_enqueue_error',
				'Outgoing webhook could not be queued with WP-Cron.',
				array(
					'template_id'     => $template_id,
					'certificate_log' => $log_id,
					'attempt'         => $attempt,
					'error_code'      => $scheduled->get_error_code(),
				)
			);
			return $scheduled;
		}

		if ( ! $scheduled ) {
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'error',
				'cron',
				'webhook_wp_cron_enqueue_failed',
				'Outgoing webhook could not be queued with WP-Cron.',
				array(
					'template_id'     => $template_id,
					'certificate_log' => $log_id,
					'attempt'         => $attempt,
				)
			);
		}
		return $scheduled ? true : new WP_Error( 'acg_webhook_schedule_failed', __( 'Webhook could not be scheduled.', 'alynt-certificate-generator' ) );
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
		if ( ! $this->is_https_url( $url ) ) {
			$this->certificate_log->update_webhook_status( $log_id, 'failed' );
			$this->set_admin_notice( $log_id, $url, __( 'Webhook URL must use HTTPS.', 'alynt-certificate-generator' ) );
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'warning',
				'external_api',
				'webhook_send_url_not_https',
				'Outgoing webhook send was blocked because the URL was not HTTPS.',
				array(
					'template_id'     => $template_id,
					'certificate_log' => $log_id,
					'attempt'         => $attempt,
				)
			);
			return;
		}

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

		$success       = false;
		$response_code = 0;
		$error_message = '';

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'error',
				'external_api',
				'webhook_http_error',
				'Outgoing webhook HTTP request failed.',
				array(
					'template_id'     => $template_id,
					'certificate_log' => $log_id,
					'attempt'         => $attempt,
					'error_code'      => $response->get_error_code(),
				)
			);
		} else {
			$response_code = (int) wp_remote_retrieve_response_code( $response );
			$success       = $response_code >= 200 && $response_code < 300;
			if ( ! $success ) {
				$error_message = sprintf( 'HTTP %d', $response_code );
				Alynt_Certificate_Generator_Diagnostics_Logger::log(
					'warning',
					'external_api',
					'webhook_http_non_success',
					'Outgoing webhook returned a non-success status code.',
					array(
						'template_id'     => $template_id,
						'certificate_log' => $log_id,
						'attempt'         => $attempt,
						'response_code'   => $response_code,
					)
				);
			}
		}

		$log_result = $this->webhook_log->insert(
			array(
				'direction'          => 'outgoing',
				'template_id'        => $template_id,
				'certificate_log_id' => $log_id,
				'url'                => esc_url_raw( $url ),
				'payload_json'       => wp_json_encode( $payload ),
				'response_code'      => $response_code,
				'success'            => $success ? 1 : 0,
				'error_message'      => $error_message,
				'attempt_number'     => $attempt,
				'created_at'         => current_time( 'mysql' ),
			)
		);
		if ( is_wp_error( $log_result ) ) {
			$this->set_admin_notice( $log_id, $url, $log_result->get_error_message() );
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'error',
				'database',
				'outgoing_webhook_log_failed',
				'Outgoing webhook operational log could not be saved.',
				array(
					'template_id'     => $template_id,
					'certificate_log' => $log_id,
					'attempt'         => $attempt,
					'error_code'      => $log_result->get_error_code(),
				)
			);
		}

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

		$delays      = $this->get_retry_schedule();
		$delay_index = $attempt - 1;
		if ( isset( $delays[ $delay_index ] ) ) {
			$next_attempt = $attempt + 1;
			$timestamp    = time() + (int) $delays[ $delay_index ];
			if ( function_exists( 'as_schedule_single_action' ) ) {
				$action_id = as_schedule_single_action(
					$timestamp,
					'alynt_certificate_generator_send_webhook',
					array( $template_id, $log_id, $payload, $url, $next_attempt ),
					ALYNT_CERTIFICATE_GENERATOR_AS_GROUP
				);
				if ( ! $action_id ) {
					$this->certificate_log->update_webhook_status( $log_id, 'failed' );
					$this->set_admin_notice( $log_id, $url, __( 'Webhook retry could not be scheduled.', 'alynt-certificate-generator' ) );
					Alynt_Certificate_Generator_Diagnostics_Logger::log(
						'error',
						'cron',
						'webhook_retry_action_scheduler_failed',
						'Outgoing webhook retry could not be scheduled.',
						array(
							'template_id'     => $template_id,
							'certificate_log' => $log_id,
							'attempt'         => $next_attempt,
						)
					);
				}
				return;
			}

			$scheduled = wp_schedule_single_event( $timestamp, 'alynt_certificate_generator_send_webhook', array( $template_id, $log_id, $payload, $url, $next_attempt ) );
			if ( ! $scheduled || is_wp_error( $scheduled ) ) {
				$this->certificate_log->update_webhook_status( $log_id, 'failed' );
				$this->set_admin_notice( $log_id, $url, __( 'Webhook retry could not be scheduled.', 'alynt-certificate-generator' ) );
				Alynt_Certificate_Generator_Diagnostics_Logger::log(
					'error',
					'cron',
					'webhook_retry_wp_cron_failed',
					'Outgoing webhook retry could not be scheduled.',
					array(
						'template_id'     => $template_id,
						'certificate_log' => $log_id,
						'attempt'         => $next_attempt,
						'error_code'      => is_wp_error( $scheduled ) ? $scheduled->get_error_code() : '',
					)
				);
			}
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
	 * Determine whether a webhook URL is HTTPS.
	 *
	 * @param string $url Webhook URL.
	 * @return bool
	 */
	private function is_https_url( string $url ): bool {
		return 'https' === wp_parse_url( $url, PHP_URL_SCHEME );
	}

	/**
	 * Store admin notice for webhook failures.
	 *
	 * @param int    $log_id Log ID.
	 * @param string $url Webhook URL.
	 * @param string $message Optional custom notice message.
	 */
	private function set_admin_notice( int $log_id, string $url, string $message = '' ): void {
		if ( '' === $message ) {
			$message = sprintf(
				/* translators: 1: log ID, 2: webhook URL. */
				__( 'Outgoing webhook failed for log #%1$d. URL: %2$s', 'alynt-certificate-generator' ),
				$log_id,
				$url
			);
		}

		set_transient(
			'acg_webhook_failure_notice',
			$message,
			MINUTE_IN_SECONDS * 5
		);
	}

	/**
	 * Get a plugin setting.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default_value Default value.
	 * @return mixed
	 */
	private function get_setting( string $key, $default_value ) {
		$settings = get_option( ALYNT_CERTIFICATE_GENERATOR_SETTINGS_OPTION, array() );
		if ( ! is_array( $settings ) || ! array_key_exists( $key, $settings ) ) {
			return $default_value;
		}

		return $settings[ $key ];
	}
}
