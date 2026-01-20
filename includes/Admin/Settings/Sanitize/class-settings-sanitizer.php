<?php
/**
 * Settings sanitization helpers.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Admin\Settings\Sanitize;

use Alynt\CertificateGenerator\Admin\Settings\Alynt_Certificate_Generator_Admin_Settings_Schema;

class Alynt_Certificate_Generator_Settings_Sanitizer {
	/**
	 * Settings schema.
	 *
	 * @var Alynt_Certificate_Generator_Admin_Settings_Schema
	 */
	private $schema;

	public function __construct( Alynt_Certificate_Generator_Admin_Settings_Schema $schema ) {
		$this->schema = $schema;
	}

	/**
	 * Sanitize settings for the current tab and preserve others.
	 *
	 * @param array  $input   Raw input.
	 * @param array  $current Current stored values.
	 * @param string $tab     Active tab.
	 * @return array
	 */
	public function sanitize_for_tab( array $input, array $current, string $tab ): array {
		$sanitized = $current;
		$fields    = $this->schema->get_fields_for_tab( $tab );

		foreach ( $fields as $key => $field ) {
			$value             = $input[ $key ] ?? null;
			$sanitized[ $key ] = $this->sanitize_field( $value, $field );
		}

		foreach ( $this->schema->get_defaults() as $key => $default ) {
			if ( ! array_key_exists( $key, $sanitized ) ) {
				$sanitized[ $key ] = $default;
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize a single field.
	 *
	 * @param mixed $value Field value.
	 * @param array $field Schema data.
	 * @return mixed
	 */
	private function sanitize_field( $value, array $field ) {
		$type    = $field['type'] ?? 'text';
		$default = $field['default'] ?? '';

		switch ( $type ) {
			case 'checkbox':
				return (bool) $value;
			case 'number':
				$number = (int) $value;
				if ( isset( $field['min'] ) ) {
					$number = max( (int) $field['min'], $number );
				}
				return $number;
			case 'email':
				$email = sanitize_email( (string) $value );
				return '' !== $email ? $email : $default;
			case 'textarea':
				return sanitize_textarea_field( (string) $value );
			case 'select':
				$options = $field['options'] ?? array();
				if ( isset( $options[ $value ] ) ) {
					return (string) $value;
				}
				return $default;
			case 'text':
			default:
				return sanitize_text_field( (string) $value );
		}
	}
}
