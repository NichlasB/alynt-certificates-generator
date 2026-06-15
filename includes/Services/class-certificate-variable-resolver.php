<?php
/**
 * Certificate variable resolver.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Services;

defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * Resolves template variables for certificate generation.
 */
class Alynt_Certificate_Generator_Certificate_Variable_Resolver {
	/**
	 * Maximum scalar variable value length.
	 *
	 * @var int
	 */
	private const MAX_VALUE_LENGTH = 5000;

	/**
	 * Resolve variable values.
	 *
	 * @param array  $variables      Template variables.
	 * @param array  $input          User input values.
	 * @param string $certificate_id Certificate ID.
	 * @param string $generated_at   Generated timestamp.
	 * @return array|WP_Error
	 */
	public function resolve_variables( array $variables, array $input, string $certificate_id, string $generated_at ) {
		$missing             = array();
		$resolved            = array();
		$date_format_default = $this->get_setting( 'default_date_format', 'Y-m-d' );

		foreach ( $variables as $variable ) {
			$type     = $variable['type'] ?? 'text';
			$key      = $variable['key'] ?? '';
			$required = ! empty( $variable['required'] );
			$value    = '';

			if ( 'auto' === $type ) {
				$auto_type = $variable['auto_type'] ?? 'certificate_id';
				if ( 'generation_date' === $auto_type ) {
					$value = $this->format_date( $generated_at, $variable['date_format'] ?? $date_format_default );
				} else {
					$value = $certificate_id;
				}
			} elseif ( 'date' === $type ) {
				$value = isset( $input[ $key ] ) ? (string) $input[ $key ] : '';
				$value = $this->format_input_date( $value, $variable['date_format'] ?? $date_format_default, $key );
				if ( is_wp_error( $value ) ) {
					return $value;
				}
			} else {
				$value = isset( $input[ $key ] ) ? $input[ $key ] : '';
			}

			if ( $required && '' === (string) $value && 'image' !== $type ) {
				$missing[] = $key;
			}

			if ( 'image' !== $type && $this->get_string_length( (string) $value ) > self::MAX_VALUE_LENGTH ) {
				return new WP_Error(
					'acg_value_too_long',
					sprintf(
						/* translators: 1: field key, 2: maximum character count. */
						__( 'Field %1$s must be %2$d characters or fewer.', 'alynt-certificate-generator' ),
						$key,
						self::MAX_VALUE_LENGTH
					)
				);
			}

			$resolved[] = array_merge( $variable, array( 'value' => $value ) );
		}

		if ( ! empty( $missing ) ) {
			return new WP_Error(
				'acg_missing_fields',
				sprintf(
					/* translators: %s: Comma-separated list of missing field keys. */
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
	 * Strictly format a user-provided date value.
	 *
	 * @param string $value  Input value.
	 * @param string $format Output format.
	 * @param string $key    Field key.
	 * @return string|WP_Error
	 */
	private function format_input_date( string $value, string $format, string $key ) {
		if ( '' === $value ) {
			return '';
		}

		$date        = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		$last_errors = \DateTimeImmutable::getLastErrors();
		$has_errors  = is_array( $last_errors ) && ( $last_errors['warning_count'] > 0 || $last_errors['error_count'] > 0 );
		if ( false === $date || $has_errors ) {
			return new WP_Error(
				'acg_invalid_date',
				sprintf(
					/* translators: %s: field key. */
					__( 'Field %s must be a valid date in YYYY-MM-DD format.', 'alynt-certificate-generator' ),
					$key
				)
			);
		}

		return $date->format( $format );
	}

	/**
	 * Get a string length with multibyte support when available.
	 *
	 * @param string $value Value.
	 * @return int
	 */
	private function get_string_length( string $value ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}

	/**
	 * Get a plugin setting.
	 *
	 * @param string $key     Setting key.
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
