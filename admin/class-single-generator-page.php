<?php
/**
 * Single certificate generation admin page.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi;

use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Certificate_Service;

class Alynt_Certificate_Generator_Single_Generator_Page {
	/**
	 * Register admin-post handlers.
	 */
	public function register_actions(): void {
		\add_action( 'admin_post_acg_single_generate', array( $this, 'handle_generate' ) );
		\add_action( 'wp_ajax_acg_get_template_variables', array( $this, 'ajax_get_template_variables' ) );
	}

	/**
	 * Render single generation page.
	 */
	public function render_page(): void {
		if ( ! \current_user_can( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE ) ) {
			\wp_die( esc_html__( 'You do not have permission to access this page.', 'alynt-certificate-generator' ) );
		}

		$templates = \get_posts(
			array(
				'post_type'        => 'acg_cert_template',
				'post_status'      => 'any',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'cache_results'    => false,
				'suppress_filters' => true,
			)
		);

		$selected_template = isset( $_GET['template_id'] ) ? absint( wp_unslash( $_GET['template_id'] ) ) : 0;
		$success_message   = '';
		$error_message     = '';

		// Check for success/error messages from redirect.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of redirect params.
		if ( isset( $_GET['acg_generated'] ) && isset( $_GET['acg_certificate_id'] ) && isset( $_GET['acg_download_url'] ) ) {
			$certificate_id = sanitize_text_field( wp_unslash( $_GET['acg_certificate_id'] ) );
			$download_url   = esc_url_raw( wp_unslash( $_GET['acg_download_url'] ) );
			$success_message = sprintf(
				/* translators: %s: certificate ID */
				__( 'Certificate %s generated successfully.', 'alynt-certificate-generator' ),
				'<strong>' . esc_html( $certificate_id ) . '</strong>'
			);
			$success_message .= ' <a href="' . esc_url( $download_url ) . '" class="button button-secondary" target="_blank">' . esc_html__( 'Download PDF', 'alynt-certificate-generator' ) . '</a>';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of redirect params.
		if ( isset( $_GET['acg_error'] ) ) {
			$error_message = sanitize_text_field( wp_unslash( $_GET['acg_error'] ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Generate Certificate', 'alynt-certificate-generator' ) . '</h1>';

		if ( '' !== $success_message ) {
			echo '<div class="notice notice-success"><p>' . wp_kses_post( $success_message ) . '</p></div>';
		}

		if ( '' !== $error_message ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $error_message ) . '</p></div>';
		}

		if ( empty( $templates ) ) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'No certificate templates found. Please create a template first.', 'alynt-certificate-generator' );
			echo ' <a href="' . esc_url( admin_url( 'post-new.php?post_type=acg_cert_template' ) ) . '">' . esc_html__( 'Create Template', 'alynt-certificate-generator' ) . '</a>';
			echo '</p></div>';
			echo '</div>';
			return;
		}

		$this->render_template_selector( $templates, $selected_template );

		if ( $selected_template > 0 ) {
			$this->render_generation_form( $selected_template );
		}

		echo '</div>';
	}

	/**
	 * Render template selector.
	 *
	 * @param array $templates    Template IDs.
	 * @param int   $selected_id  Currently selected template ID.
	 */
	private function render_template_selector( array $templates, int $selected_id ): void {
		$page_url = admin_url( 'admin.php?page=alynt-single-generator' );

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
	 * @param int $template_id Template ID.
	 */
	private function render_generation_form( int $template_id ): void {
		$template = \get_post( $template_id );
		if ( ! $template || 'acg_cert_template' !== $template->post_type ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Template not found.', 'alynt-certificate-generator' ) . '</p></div>';
			return;
		}

		$variables = $this->get_template_variables( $template_id );
		$has_input_fields = false;

		// Check if there are any input fields (non-auto variables).
		foreach ( $variables as $variable ) {
			$type = $variable['type'] ?? 'text';
			if ( 'auto' !== $type ) {
				$has_input_fields = true;
				break;
			}
		}

		echo '<hr />';
		echo '<h2>' . esc_html__( 'Certificate Details', 'alynt-certificate-generator' ) . '</h2>';

		if ( empty( $variables ) ) {
			echo '<div class="notice notice-warning"><p>';
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

		// Skip notifications option.
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
			echo ' <span class="required" style="color: #d63638;">*</span>';
		}
		echo '</th>';
		echo '<td>';

		switch ( $type ) {
			case 'auto':
				$auto_type = $variable['auto_type'] ?? 'certificate_id';
				if ( 'generation_date' === $auto_type ) {
					$date_format = $variable['date_format'] ?? 'Y-m-d';
					echo '<em>' . esc_html__( 'Auto-generated: Generation date', 'alynt-certificate-generator' ) . '</em>';
					echo '<p class="description">' . sprintf(
						/* translators: %s: date format */
						esc_html__( 'Format: %s', 'alynt-certificate-generator' ),
						esc_html( $date_format )
					) . '</p>';
				} else {
					echo '<em>' . esc_html__( 'Auto-generated: Certificate ID', 'alynt-certificate-generator' ) . '</em>';
				}
				break;

			case 'date':
				echo '<input type="date" name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_id ) . '" class="regular-text" ' . ( $required ? 'required' : '' ) . ' />';
				if ( isset( $variable['date_format'] ) && '' !== $variable['date_format'] ) {
					echo '<p class="description">' . sprintf(
						/* translators: %s: date format */
						esc_html__( 'Will be formatted as: %s', 'alynt-certificate-generator' ),
						esc_html( $variable['date_format'] )
					) . '</p>';
				}
				break;

			case 'image':
				echo '<input type="file" name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_id ) . '" accept="image/png,image/jpeg" ' . ( $required ? 'required' : '' ) . ' />';
				echo '<p class="description">' . esc_html__( 'Accepted formats: JPG, PNG (max 5MB)', 'alynt-certificate-generator' ) . '</p>';
				break;

			case 'select':
				$options = isset( $variable['options'] ) && is_array( $variable['options'] ) ? $variable['options'] : array();
				echo '<select name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_id ) . '" class="regular-text" ' . ( $required ? 'required' : '' ) . '>';
				echo '<option value="">' . esc_html__( '— Select —', 'alynt-certificate-generator' ) . '</option>';
				foreach ( $options as $option ) {
					$opt_value = isset( $option['value'] ) ? $option['value'] : '';
					$opt_label = isset( $option['label'] ) ? $option['label'] : $opt_value;
					echo '<option value="' . esc_attr( $opt_label ) . '">' . esc_html( $opt_label ) . '</option>';
				}
				echo '</select>';
				break;

			case 'text':
			default:
				echo '<input type="text" name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_id ) . '" class="regular-text" ' . ( $required ? 'required' : '' ) . ' />';
				break;
		}

		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Handle certificate generation form submission.
	 */
	public function handle_generate(): void {
		if ( ! \current_user_can( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to generate certificates.', 'alynt-certificate-generator' ) );
		}

		$template_id = isset( $_POST['template_id'] ) ? absint( wp_unslash( $_POST['template_id'] ) ) : 0;
		if ( $template_id < 1 ) {
			wp_die( esc_html__( 'Template is required.', 'alynt-certificate-generator' ) );
		}

		check_admin_referer( 'acg_single_generate_' . $template_id );

		$template = \get_post( $template_id );
		if ( ! $template || 'acg_cert_template' !== $template->post_type ) {
			wp_die( esc_html__( 'Template not found.', 'alynt-certificate-generator' ) );
		}

		$variables = $this->get_template_variables( $template_id );
		$values    = array();

		foreach ( $variables as $variable ) {
			$type = $variable['type'] ?? 'text';
			$key  = $variable['key'] ?? '';

			if ( '' === $key || 'auto' === $type ) {
				continue;
			}

			$field_name = 'acg_var_' . $key;

			if ( 'image' === $type ) {
				$values[ $key ] = $this->handle_image_upload( $field_name );
			} else {
				$values[ $key ] = isset( $_POST[ $field_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) ) : '';
			}
		}

		$skip_notifications = isset( $_POST['skip_notifications'] );
		$user_id            = get_current_user_id();
		$ip_address         = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		$service = new Alynt_Certificate_Generator_Certificate_Service();
		$result  = $service->generate(
			$template_id,
			$values,
			'admin',
			$user_id,
			$ip_address,
			$skip_notifications
		);

		$redirect_url = admin_url( 'admin.php?page=alynt-single-generator&template_id=' . $template_id );

		if ( is_wp_error( $result ) ) {
			$redirect_url = add_query_arg( 'acg_error', rawurlencode( $result->get_error_message() ), $redirect_url );
		} else {
			$redirect_url = add_query_arg(
				array(
					'acg_generated'      => '1',
					'acg_certificate_id' => rawurlencode( $result['certificate_id'] ),
					'acg_download_url'   => rawurlencode( $result['download_url'] ),
				),
				$redirect_url
			);
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle image uploads for image variables.
	 *
	 * @param string $field_name Field name.
	 * @return int|string Attachment ID or empty string.
	 */
	private function handle_image_upload( string $field_name ) {
		if ( empty( $_FILES[ $field_name ]['name'] ) ) {
			return '';
		}

		$file = $_FILES[ $field_name ];
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			return '';
		}

		$max_size = 5 * 1024 * 1024;
		if ( $file['size'] > $max_size ) {
			return '';
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$upload = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => array(
					'jpg'  => 'image/jpeg',
					'jpeg' => 'image/jpeg',
					'png'  => 'image/png',
				),
			)
		);

		if ( isset( $upload['error'] ) ) {
			return '';
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $upload['type'],
				'post_title'     => sanitize_file_name( $file['name'] ),
				'post_status'    => 'inherit',
			),
			$upload['file']
		);

		$meta = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $meta );

		return $attachment_id;
	}

	/**
	 * AJAX handler to get template variables.
	 */
	public function ajax_get_template_variables(): void {
		check_ajax_referer( 'acg_admin_nonce', 'nonce' );

		if ( ! \current_user_can( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'alynt-certificate-generator' ) ), 403 );
		}

		$template_id = isset( $_POST['template_id'] ) ? absint( wp_unslash( $_POST['template_id'] ) ) : 0;
		if ( $template_id < 1 ) {
			wp_send_json_error( array( 'message' => __( 'Template ID required.', 'alynt-certificate-generator' ) ), 400 );
		}

		$variables = $this->get_template_variables( $template_id );
		wp_send_json_success( array( 'variables' => $variables ) );
	}

	/**
	 * Get template variables.
	 *
	 * @param int $template_id Template ID.
	 * @return array
	 */
	private function get_template_variables( int $template_id ): array {
		$raw     = (string) \get_post_meta( $template_id, 'acg_template_variables', true );
		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : array();
	}
}
