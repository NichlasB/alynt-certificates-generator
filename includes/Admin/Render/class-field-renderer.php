<?php
/**
 * Settings field renderer.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Admin\Render;

class Alynt_Certificate_Generator_Field_Renderer {
	/**
	 * Render a settings field.
	 *
	 * @param string $option_name Option name.
	 * @param string $key         Field key.
	 * @param array  $field       Field schema.
	 * @param mixed  $value       Current value.
	 */
	public static function render_field( string $option_name, string $key, array $field, $value ): void {
		$type        = $field['type'] ?? 'text';
		$description = $field['description'] ?? '';
		$id          = $option_name . '_' . $key;
		$name        = $option_name . '[' . $key . ']';

		switch ( $type ) {
			case 'textarea':
				printf(
					'<textarea id="%1$s" name="%2$s" rows="5" class="large-text">%3$s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_textarea( (string) $value )
				);
				break;
			case 'checkbox':
				printf(
					'<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( (bool) $value, true, false ),
					esc_html__( 'Enabled', 'alynt-certificate-generator' )
				);
				break;
			case 'select':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
				foreach ( (array) ( $field['options'] ?? array() ) as $option_value => $label ) {
					printf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( (string) $option_value ),
						selected( (string) $value, (string) $option_value, false ),
						esc_html( (string) $label )
					);
				}
				echo '</select>';
				break;
			case 'email':
				printf(
					'<input type="email" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;
			case 'number':
				$min = isset( $field['min'] ) ? (int) $field['min'] : '';
				printf(
					'<input type="number" id="%1$s" name="%2$s" value="%3$s" class="small-text" %4$s />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					'' !== $min ? 'min="' . esc_attr( (string) $min ) . '"' : ''
				);
				break;
			case 'text':
			default:
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;
		}

		if ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
	}
}
