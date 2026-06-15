<?php
/**
 * Single certificate generation admin page renderer.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi;

defined( 'ABSPATH' ) || exit;

class Alynt_Certificate_Generator_Single_Generator_Page_Renderer {
	/**
	 * Render the page shell.
	 *
	 * @param array  $templates         Template IDs.
	 * @param int    $selected_template Selected template ID.
	 * @param string $success_message   Success notice HTML.
	 * @param string $error_message     Error notice text.
	 * @param array  $variables         Template variable definitions.
	 */
	public function render_page(
		array $templates,
		int $selected_template,
		string $success_message,
		string $error_message,
		array $variables
	): void {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Generate Certificate', 'alynt-certificate-generator' ) . '</h1>';

		if ( '' !== $success_message ) {
			echo '<div class="notice notice-success is-dismissible" role="status" aria-live="polite"><p>' . wp_kses_post( $success_message ) . '</p></div>';
		}

		if ( '' !== $error_message ) {
			echo '<div class="notice notice-error is-dismissible" role="alert"><p>' . esc_html( $error_message ) . '</p></div>';
		}
		echo '<hr class="wp-header-end" />';

		if ( empty( $templates ) ) {
			echo '<div class="notice notice-warning" role="status" aria-live="polite"><p>';
			echo esc_html__( 'No certificate templates found. Please create a template first.', 'alynt-certificate-generator' );
			echo ' <a href="' . esc_url( admin_url( 'post-new.php?post_type=acg_cert_template' ) ) . '">' . esc_html__( 'Create Template', 'alynt-certificate-generator' ) . '</a>';
			echo '</p></div>';
			echo '</div>';
			return;
		}

		$this->render_template_selector( $templates, $selected_template );

		if ( $selected_template > 0 ) {
			$this->render_generation_form( $selected_template, $variables );
		}

		echo '</div>';
	}

	/**
	 * Render template selector.
	 *
	 * @param array $templates   Template IDs.
	 * @param int   $selected_id Currently selected template ID.
	 */
	private function render_template_selector( array $templates, int $selected_id ): void {
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" style="margin-bottom: 20px;">';
		echo '<input type="hidden" name="page" value="alynt-single-generator" />';

		echo '<table class="form-table"><tbody>';
		echo '<tr>';
		echo '<th scope="row"><label for="acg_template_select">' . esc_html__( 'Certificate Template', 'alynt-certificate-generator' ) . '</label></th>';
		echo '<td>';
		echo '<select name="template_id" id="acg_template_select" onchange="this.form.submit()">';
		echo '<option value="">' . esc_html__( '— Select a template —', 'alynt-certificate-generator' ) . '</option>';

		foreach ( $templates as $template_id ) {
			$title = get_the_title( $template_id );
			if ( '' === $title ) {
				/* translators: %d: template post ID. */
				$title = sprintf( __( 'Template #%d', 'alynt-certificate-generator' ), $template_id );
			}
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( (string) $template_id ),
				selected( $selected_id, $template_id, false ),
				esc_html( $title )
			);
		}

		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Select a template to see its variables and generate a certificate.', 'alynt-certificate-generator' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		echo '</tbody></table>';
		echo '</form>';
	}

	/**
	 * Render the generation form for a specific template.
	 *
	 * @param int   $template_id Template ID.
	 * @param array $variables   Template variable definitions.
	 */
	private function render_generation_form( int $template_id, array $variables ): void {
		$template = \get_post( $template_id );
		if ( ! $template || 'acg_cert_template' !== $template->post_type ) {
			echo '<div class="notice notice-error is-dismissible" role="alert"><p>' . esc_html__( 'Template not found.', 'alynt-certificate-generator' ) . '</p></div>';
			return;
		}

		echo '<hr />';
		echo '<h2>' . esc_html__( 'Certificate Details', 'alynt-certificate-generator' ) . '</h2>';

		if ( empty( $variables ) ) {
			echo '<div class="notice notice-warning" role="status" aria-live="polite"><p>';
			echo esc_html__( 'This template has no variables configured.', 'alynt-certificate-generator' );
			echo ' <a href="' . esc_url( get_edit_post_link( $template_id ) ) . '">' . esc_html__( 'Edit Template', 'alynt-certificate-generator' ) . '</a>';
			echo '</p></div>';
		}

		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="acg_single_generate" />';
		echo '<input type="hidden" name="template_id" value="' . esc_attr( (string) $template_id ) . '" />';
		wp_nonce_field( 'acg_single_generate_' . $template_id );

		echo '<table class="form-table"><tbody>';

		foreach ( $variables as $variable ) {
			$this->render_variable_field( $variable );
		}

		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Options', 'alynt-certificate-generator' ) . '</th>';
		echo '<td>';
		echo '<label><input type="checkbox" name="skip_notifications" value="1" /> ';
		echo esc_html__( 'Skip email notifications for this certificate', 'alynt-certificate-generator' ) . '</label>';
		echo '</td>';
		echo '</tr>';

		echo '</tbody></table>';

		submit_button( __( 'Generate Certificate', 'alynt-certificate-generator' ), 'primary', 'submit', true );
		echo '</form>';
	}

	/**
	 * Render a single variable field.
	 *
	 * @param array $variable Variable definition.
	 */
	private function render_variable_field( array $variable ): void {
		$type     = $variable['type'] ?? 'text';
		$key      = $variable['key'] ?? '';
		$label    = $variable['label'] ?? $key;
		$required = ! empty( $variable['required'] );

		if ( '' === $key ) {
			return;
		}

		$field_name = 'acg_var_' . $key;
		$field_id   = 'acg_var_' . sanitize_html_class( $key );

		echo '<tr>';
		echo '<th scope="row">';
		echo '<label for="' . esc_attr( $field_id ) . '">' . esc_html( $label ) . '</label>';
		if ( $required && 'auto' !== $type ) {
			echo ' <span class="required" aria-hidden="true" style="color: #d63638;">*</span>';
			echo '<span class="screen-reader-text"> ' . esc_html__( 'required', 'alynt-certificate-generator' ) . '</span>';
		}
		echo '</th>';
		echo '<td>';

		switch ( $type ) {
			case 'auto':
				$this->render_auto_variable_field( $variable );
				break;

			case 'date':
				$this->render_date_variable_field( $field_name, $field_id, $variable, $required );
				break;

			case 'image':
				echo '<input type="file" name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_id ) . '" accept="image/png,image/jpeg" ' . ( $required ? 'required aria-required="true"' : '' ) . ' />';
				echo '<p class="description">' . esc_html__( 'Accepted formats: JPG, PNG (max 5MB)', 'alynt-certificate-generator' ) . '</p>';
				break;

			case 'select':
				$this->render_select_variable_field( $field_name, $field_id, $variable, $required );
				break;

			case 'text':
			default:
				echo '<input type="text" name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_id ) . '" class="regular-text" ' . ( $required ? 'required aria-required="true"' : '' ) . ' />';
				break;
		}

		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Render an auto variable field description.
	 *
	 * @param array $variable Variable definition.
	 */
	private function render_auto_variable_field( array $variable ): void {
		$auto_type = $variable['auto_type'] ?? 'certificate_id';
		if ( 'generation_date' === $auto_type ) {
			$date_format = $variable['date_format'] ?? 'Y-m-d';
			echo '<em>' . esc_html__( 'Auto-generated: Generation date', 'alynt-certificate-generator' ) . '</em>';
			echo '<p class="description">' . sprintf(
				/* translators: %s: date format */
				esc_html__( 'Format: %s', 'alynt-certificate-generator' ),
				esc_html( $date_format )
			) . '</p>';
			return;
		}

		echo '<em>' . esc_html__( 'Auto-generated: Certificate ID', 'alynt-certificate-generator' ) . '</em>';
	}

	/**
	 * Render a date variable input.
	 *
	 * @param string $field_name Field name.
	 * @param string $field_id   Field ID.
	 * @param array  $variable   Variable definition.
	 * @param bool   $required   Whether the field is required.
	 */
	private function render_date_variable_field( string $field_name, string $field_id, array $variable, bool $required ): void {
		echo '<input type="date" name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_id ) . '" class="regular-text" ' . ( $required ? 'required aria-required="true"' : '' ) . ' />';
		if ( isset( $variable['date_format'] ) && '' !== $variable['date_format'] ) {
			echo '<p class="description">' . sprintf(
				/* translators: %s: date format */
				esc_html__( 'Will be formatted as: %s', 'alynt-certificate-generator' ),
				esc_html( $variable['date_format'] )
			) . '</p>';
		}
	}

	/**
	 * Render a select variable input.
	 *
	 * @param string $field_name Field name.
	 * @param string $field_id   Field ID.
	 * @param array  $variable   Variable definition.
	 * @param bool   $required   Whether the field is required.
	 */
	private function render_select_variable_field( string $field_name, string $field_id, array $variable, bool $required ): void {
		$options = isset( $variable['options'] ) && is_array( $variable['options'] ) ? $variable['options'] : array();
		echo '<select name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_id ) . '" class="regular-text" ' . ( $required ? 'required aria-required="true"' : '' ) . '>';
		echo '<option value="">' . esc_html__( '— Select —', 'alynt-certificate-generator' ) . '</option>';
		foreach ( $options as $option ) {
			$opt_value = isset( $option['value'] ) ? $option['value'] : '';
			$opt_label = isset( $option['label'] ) ? $option['label'] : $opt_value;
			echo '<option value="' . esc_attr( $opt_label ) . '">' . esc_html( $opt_label ) . '</option>';
		}
		echo '</select>';
	}
}
