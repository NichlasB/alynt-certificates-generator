<?php
/**
 * Email notification service.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Services;

use Alynt\CertificateGenerator\Database\Alynt_Certificate_Generator_Certificate_Log;
use WP_Error;

class Alynt_Certificate_Generator_Email_Service {
	/**
	 * Log access.
	 *
	 * @var Alynt_Certificate_Generator_Certificate_Log
	 */
	private $log;

	public function __construct() {
		$this->log = new Alynt_Certificate_Generator_Certificate_Log();
	}

	/**
	 * Send emails for a certificate log entry.
	 *
	 * @param array $log_entry Log entry data.
	 * @param array $variables Resolved variables.
	 * @param bool  $skip_notifications Skip flag.
	 * @return array<string, mixed>|WP_Error
	 */
	public function send_for_log( array $log_entry, array $variables, bool $skip_notifications = false ) {
		$template_id = isset( $log_entry['template_id'] ) ? (int) $log_entry['template_id'] : 0;
		if ( $template_id < 1 ) {
			return new WP_Error( 'acg_email_template_missing', __( 'Template ID missing for email dispatch.', 'alynt-certificate-generator' ) );
		}

		$placeholders = $this->build_placeholders( $log_entry, $variables );
		$email_templates = $this->get_email_templates( $template_id );

		$status = array();
		foreach ( $email_templates as $email_template ) {
			$status[ $email_template->ID ] = $this->send_template_email(
				$email_template->ID,
				$placeholders,
				$log_entry,
				$skip_notifications
			);
		}

		if ( isset( $log_entry['id'] ) ) {
			$this->log->update_email_status( (int) $log_entry['id'], $status );
		}

		return $status;
	}

	/**
	 * Get email templates for a certificate template.
	 *
	 * @param int $template_id Certificate template ID.
	 * @return array
	 */
	private function get_email_templates( int $template_id ): array {
		return \get_posts(
			array(
				'post_type'      => 'acg_email_template',
				'post_status'    => 'any',
				'numberposts'    => -1,
				'fields'         => 'all',
				'meta_query'     => array(
					array(
						'key'   => 'acg_email_template_id',
						'value' => $template_id,
					),
				),
				'no_found_rows'  => true,
				'cache_results'  => false,
				'suppress_filters' => true,
			)
		);
	}

	/**
	 * Send an email template.
	 *
	 * @param int   $template_post_id Template post ID.
	 * @param array $placeholders Placeholder map.
	 * @param array $log_entry Log entry.
	 * @param bool  $skip Skip notifications.
	 * @return array<string, mixed>
	 */
	private function send_template_email(
		int $template_post_id,
		array $placeholders,
		array $log_entry,
		bool $skip
	): array {
		$enabled = (bool) \get_post_meta( $template_post_id, 'acg_email_enabled', true );
		if ( ! $enabled || $skip ) {
			return array(
				'status'  => 'skipped',
				'message' => $skip ? 'Skipped by request.' : 'Template disabled.',
			);
		}

		$to_raw      = (string) \get_post_meta( $template_post_id, 'acg_email_to', true );
		$subject_raw = (string) \get_post_meta( $template_post_id, 'acg_email_subject', true );
		$body_raw    = (string) \get_post_meta( $template_post_id, 'acg_email_body', true );
		$attach_pdf  = (bool) \get_post_meta( $template_post_id, 'acg_email_attach_pdf', true );

		$to      = $this->replace_placeholders( $to_raw, $placeholders );
		$subject = sanitize_text_field( $this->replace_placeholders( $subject_raw, $placeholders ) );
		$body    = wp_kses_post( $this->replace_placeholders( $body_raw, $placeholders ) );

		$recipients = $this->sanitize_recipients( $to );
		if ( empty( $recipients ) ) {
			return array(
				'status'  => 'failed',
				'message' => 'No valid recipients found.',
			);
		}

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$from_name  = (string) $this->get_setting( 'email_from_name', '' );
		$from_email = (string) $this->get_setting( 'email_from_address', '' );
		if ( '' !== $from_name && '' !== $from_email ) {
			$headers[] = sprintf( 'From: %s <%s>', $from_name, $from_email );
		}

		$attachments = array();
		if ( $attach_pdf && ! empty( $log_entry['pdf_path'] ) && file_exists( $log_entry['pdf_path'] ) ) {
			$attachments[] = $log_entry['pdf_path'];
		}

		$sent = \wp_mail( $recipients, $subject, wpautop( $body ), $headers, $attachments );
		if ( ! $sent ) {
			return array(
				'status'  => 'failed',
				'message' => 'wp_mail failed.',
			);
		}

		return array(
			'status'   => 'sent',
			'message'  => '',
			'sent_at'  => current_time( 'mysql' ),
		);
	}

	/**
	 * Build placeholder map.
	 *
	 * @param array $log_entry Log entry.
	 * @param array $variables Resolved variables.
	 * @return array<string, string>
	 */
	private function build_placeholders( array $log_entry, array $variables ): array {
		$placeholders = array();
		foreach ( $variables as $variable ) {
			if ( isset( $variable['key'], $variable['value'] ) ) {
				$placeholders[ (string) $variable['key'] ] = (string) $variable['value'];
			}
		}

		if ( isset( $log_entry['certificate_id'] ) ) {
			$placeholders['certificate_id'] = (string) $log_entry['certificate_id'];
		}

		if ( isset( $log_entry['download_token'], $log_entry['certificate_id'] ) ) {
			$endpoint = sprintf(
				'acg/v1/certificates/%s/download',
				rawurlencode( (string) $log_entry['certificate_id'] )
			);
			$placeholders['download_url'] = add_query_arg(
				'token',
				rawurlencode( (string) $log_entry['download_token'] ),
				rest_url( $endpoint )
			);
		}

		if ( isset( $log_entry['created_at'] ) ) {
			$placeholders['generated_at'] = (string) $log_entry['created_at'];
		}

		return $placeholders;
	}

	/**
	 * Replace placeholders in content.
	 *
	 * @param string $content Content.
	 * @param array  $placeholders Placeholder map.
	 * @return string
	 */
	private function replace_placeholders( string $content, array $placeholders ): string {
		foreach ( $placeholders as $key => $value ) {
			$content = str_replace( '{' . $key . '}', (string) $value, $content );
		}

		return $content;
	}

	/**
	 * Sanitize recipient list.
	 *
	 * @param string $recipients Recipient string.
	 * @return array
	 */
	private function sanitize_recipients( string $recipients ): array {
		$parts = preg_split( '/[;,\\s]+/', $recipients );
		$valid = array();

		foreach ( $parts as $part ) {
			$email = sanitize_email( $part );
			if ( '' !== $email ) {
				$valid[] = $email;
			}
		}

		return array_unique( $valid );
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
