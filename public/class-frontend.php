<?php
/**
 * Frontend functionality.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Frontend;

defined( 'ABSPATH' ) || exit;

use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Certificate_Service;
use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Image_Upload_Service;

/** Frontend shortcode handlers. */
class Alynt_Certificate_Generator_Frontend {
	/**
	 * Image upload service.
	 *
	 * @var Alynt_Certificate_Generator_Image_Upload_Service
	 */
	private $image_upload_service;

	/**
	 * Constructor.
	 *
	 * @param string                                                $plugin_name          Plugin name.
	 * @param string                                                $version              Plugin version.
	 * @param Alynt_Certificate_Generator_Image_Upload_Service|null $image_upload_service Image upload service.
	 */
	public function __construct( string $plugin_name, string $version, ?Alynt_Certificate_Generator_Image_Upload_Service $image_upload_service = null ) {
		unset( $plugin_name, $version );
		$this->image_upload_service = null !== $image_upload_service ? $image_upload_service : new Alynt_Certificate_Generator_Image_Upload_Service();
	}

	/**
	 * Register shortcodes.
	 */
	public function register_shortcodes(): void {
		\add_shortcode( 'alynt_certificate_form', array( $this, 'render_certificate_form' ) );
		\add_shortcode( 'alynt_my_certificates', array( $this, 'render_my_certificates' ) );
	}

	/**
	 * Render the certificate form placeholder.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_certificate_form( array $atts = array() ): string {
		$atts = \shortcode_atts(
			array(
				'template' => '',
			),
			$atts,
			'alynt_certificate_form'
		);

		$template_id = absint( $atts['template'] );
		if ( 0 === $template_id ) {
			return esc_html__( 'Template not specified.', 'alynt-certificate-generator' );
		}

		if ( ! $this->can_access_template( $template_id ) ) {
			return esc_html__( 'You do not have access to this certificate form.', 'alynt-certificate-generator' );
		}

		$variables = $this->get_template_variables( $template_id );
		if ( empty( $variables ) ) {
			return esc_html__( 'No variables configured for this template.', 'alynt-certificate-generator' );
		}

		$this->enqueue_frontend_assets();

		$submission = $this->get_empty_submission_result();
		if ( $this->is_certificate_form_submission( $template_id ) ) {
			$submission = $this->handle_form_submission( $template_id, $variables );
		}

		$output = '<div class="acg-certificate-form">';
		if ( ! empty( $submission['errors'] ) ) {
			$output .= $this->render_error_summary( $submission );
		} elseif ( '' !== $submission['message_html'] ) {
			$output .= sprintf(
				'<div class="acg-certificate-message acg-certificate-message--%1$s" role="status" aria-live="polite">%2$s</div>',
				esc_attr( $submission['message_type'] ),
				$submission['message_html']
			);
		}

		$output .= '<form method="post" enctype="multipart/form-data" novalidate>';
		$output .= wp_nonce_field( 'acg_certificate_form_' . $template_id, 'acg_certificate_nonce', true, false );
		$output .= '<input type="hidden" name="acg_template_id" value="' . esc_attr( (string) $template_id ) . '" />';

		foreach ( $variables as $variable ) {
			$type     = $variable['type'] ?? 'text';
			$key      = $variable['key'] ?? '';
			$label    = $variable['label'] ?? $key;
			$required = ! empty( $variable['required'] );

			if ( '' === $key || 'auto' === $type ) {
				continue;
			}

			$field_name = 'acg_var_' . $key;
			$field_id   = $this->get_field_id( $template_id, $field_name );
			$error_id   = $field_id . '_error';
			$error      = isset( $submission['errors'][ $field_name ] ) ? (string) $submission['errors'][ $field_name ] : '';
			$value      = isset( $submission['values'][ $field_name ] ) ? (string) $submission['values'][ $field_name ] : '';
			$invalid    = '' !== $error;
			$described  = $invalid ? ' aria-describedby="' . esc_attr( $error_id ) . '"' : '';
			$focus      = $field_name === $submission['first_error_field'] ? ' data-acg-focus-target="true"' : '';
			$required_a = $required ? ' aria-required="true"' : '';
			$invalid_a  = $invalid ? ' aria-invalid="true"' : '';

			$output .= '<p class="acg-field">';
			$output .= '<label for="' . esc_attr( $field_id ) . '">' . esc_html( $label ) . '</label><br />';

			if ( 'image' === $type ) {
				$output .= '<input type="file" name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_id ) . '" accept="image/png,image/jpeg"' . $required_a . $invalid_a . $described . $focus . ' />';
			} elseif ( 'select' === $type ) {
				$options = isset( $variable['options'] ) && is_array( $variable['options'] ) ? $variable['options'] : array();
				$output .= '<select name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_id ) . '"' . $required_a . $invalid_a . $described . $focus . '>';
				$output .= '<option value="">' . esc_html__( 'Select an option', 'alynt-certificate-generator' ) . '</option>';
				foreach ( $options as $option ) {
					$opt_value = isset( $option['value'] ) ? $option['value'] : '';
					$opt_label = isset( $option['label'] ) ? $option['label'] : $opt_value;
					$selected  = selected( $value, (string) $opt_label, false );
					$output   .= '<option value="' . esc_attr( $opt_label ) . '"' . $selected . '>' . esc_html( $opt_label ) . '</option>';
				}
				$output .= '</select>';
			} else {
				$input_type = 'date' === $type ? 'date' : 'text';
				$output    .= '<input type="' . esc_attr( $input_type ) . '" name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_id ) . '" value="' . esc_attr( $value ) . '"' . $required_a . $invalid_a . $described . $focus . ' />';
			}

			if ( $invalid ) {
				$output .= '<p id="' . esc_attr( $error_id ) . '" class="acg-field-error" role="alert">' . esc_html( $error ) . '</p>';
			}

			$output .= '</p>';
		}

		$skip_checked = ! empty( $submission['skip_notifications'] ) ? ' checked' : '';
		$output      .= '<p><label><input type="checkbox" name="acg_skip_notifications" value="1"' . $skip_checked . ' /> ';
		$output      .= esc_html__( 'Skip email notifications for this certificate', 'alynt-certificate-generator' ) . '</label></p>';

		$output .= '<button type="submit" name="acg_certificate_submit" class="button acg-button">' . esc_html__( 'Generate Certificate', 'alynt-certificate-generator' ) . '</button>';
		$output .= '</form></div>';

		return $output;
	}

	/**
	 * Render the \"My Certificates\" placeholder.
	 *
	 * @return string
	 */
	public function render_my_certificates(): string {
		if ( ! \is_user_logged_in() ) {
			return esc_html__( 'Please log in to view your certificates.', 'alynt-certificate-generator' );
		}
		global $wpdb;

		$user_id  = get_current_user_id();
		$per_page = 10;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only shortcode pagination parameter.
		$paged  = isset( $_GET['acg_page'] ) ? max( 1, absint( wp_unslash( $_GET['acg_page'] ) ) ) : 1;
		$offset = ( $paged - 1 ) * $per_page;

		$table = esc_sql( $wpdb->prefix . 'acg_certificate_log' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table name is escaped above; dynamic values are prepared.
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT certificate_id, template_id, download_token, created_at FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$items = is_array( $items ) ? $items : array();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table name is escaped above; dynamic value is prepared.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$this->prime_certificate_item_caches( $items );

		if ( empty( $items ) ) {
			$output  = '<section class="acg-empty-state" aria-labelledby="acg-my-certificates-empty-title">';
			$output .= '<h2 id="acg-my-certificates-empty-title">' . esc_html__( 'No certificates yet', 'alynt-certificate-generator' ) . '</h2>';
			$output .= '<p>' . esc_html__( 'Generated certificates will appear here when they are available for your account.', 'alynt-certificate-generator' ) . '</p>';
			$output .= '</section>';
			return $output;
		}

		$output  = '<table class="acg-my-certificates">';
		$output .= '<caption class="screen-reader-text">' . esc_html__( 'My certificates', 'alynt-certificate-generator' ) . '</caption>';
		$output .= '<thead><tr>';
		$output .= '<th scope="col">' . esc_html__( 'Certificate', 'alynt-certificate-generator' ) . '</th>';
		$output .= '<th scope="col">' . esc_html__( 'Date', 'alynt-certificate-generator' ) . '</th>';
		$output .= '<th scope="col">' . esc_html__( 'Download', 'alynt-certificate-generator' ) . '</th>';
		$output .= '</tr></thead><tbody>';

		foreach ( $items as $item ) {
			$template_title = get_the_title( (int) $item['template_id'] );
			$download_url   = add_query_arg(
				'token',
				rawurlencode( (string) $item['download_token'] ),
				rest_url( 'acg/v1/certificates/' . rawurlencode( (string) $item['certificate_id'] ) . '/download' )
			);

			$output .= '<tr>';
			$output .= '<td>' . esc_html( $template_title ) . '</td>';
			$output .= '<td>' . esc_html( (string) $item['created_at'] ) . '</td>';
			$output .= '<td><a class="button acg-button" href="' . esc_url( $download_url ) . '">' . esc_html__( 'Download', 'alynt-certificate-generator' ) . '</a></td>';
			$output .= '</tr>';
		}

		$output .= '</tbody></table>';

		$pagination = paginate_links(
			array(
				'current' => $paged,
				'total'   => max( 1, (int) ceil( $total / $per_page ) ),
				'type'    => 'list',
				'format'  => '?acg_page=%#%',
			)
		);

		if ( $pagination ) {
			$output .= $pagination;
		}

		return $output;
	}

	/**
	 * Check access permissions for a template.
	 *
	 * @param int $template_id Template ID.
	 * @return bool
	 */
	private function can_access_template( int $template_id ): bool {
		if ( ! \is_user_logged_in() ) {
			return false;
		}

		$raw     = (string) \get_post_meta( $template_id, 'acg_template_permissions', true );
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return true;
		}

		$access = $decoded['access'] ?? 'any';
		if ( 'any' === $access ) {
			return true;
		}

		$roles = $decoded['roles'] ?? array();
		if ( ! is_array( $roles ) ) {
			return false;
		}

		$user = wp_get_current_user();
		return ! empty( array_intersect( $roles, (array) $user->roles ) );
	}

	/**
	 * Build an empty form submission result.
	 *
	 * @return array<string, mixed>
	 */
	private function get_empty_submission_result(): array {
		return array(
			'message_html'       => '',
			'message_type'       => 'status',
			'errors'             => array(),
			'values'             => array(),
			'first_error_field'  => '',
			'skip_notifications' => false,
		);
	}

	/**
	 * Build a stable field ID for a shortcode form control.
	 *
	 * @param int    $template_id Template ID.
	 * @param string $field_name  Field name.
	 * @return string
	 */
	private function get_field_id( int $template_id, string $field_name ): string {
		return sanitize_key( 'acg_' . $template_id . '_' . $field_name );
	}

	/**
	 * Handle form submission.
	 *
	 * @param int   $template_id Template ID.
	 * @param array $variables Variable definitions.
	 * @return array<string, mixed>
	 */
	private function handle_form_submission( int $template_id, array $variables ): array {
		$submission                       = $this->get_empty_submission_result();
		$submission['skip_notifications'] = isset( $_POST['acg_skip_notifications'] );

		$nonce = isset( $_POST['acg_certificate_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['acg_certificate_nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'acg_certificate_form_' . $template_id ) ) {
			$submission['errors']['form'] = __( 'Security check failed. Please refresh the page and try again.', 'alynt-certificate-generator' );
			return $submission;
		}

		$uploaded_files = array();
		if ( ! empty( $_FILES ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File data is validated after nonce verification.
			$uploaded_files = $_FILES;
		}

		foreach ( $variables as $variable ) {
			$type     = $variable['type'] ?? 'text';
			$key      = $variable['key'] ?? '';
			$label    = $variable['label'] ?? $key;
			$required = ! empty( $variable['required'] );
			if ( '' === $key || 'auto' === $type ) {
				continue;
			}

			$field_name = 'acg_var_' . $key;
			if ( 'image' === $type ) {
				if ( $required && empty( $uploaded_files[ $field_name ]['name'] ) ) {
					$submission['errors'][ $field_name ] = sprintf(
						/* translators: %s: field label. */
						__( '%s is required. Upload a PNG or JPEG image and try again.', 'alynt-certificate-generator' ),
						$label
					);
				}
				continue;
			}

			$value                               = isset( $_POST[ $field_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) ) : '';
			$submission['values'][ $field_name ] = $value;

			if ( $required && '' === $value ) {
				$submission['errors'][ $field_name ] = sprintf(
					/* translators: %s: field label. */
					__( '%s is required. Complete this field and try again.', 'alynt-certificate-generator' ),
					$label
				);
				continue;
			}

			if ( 'date' === $type && '' !== $value && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
				$submission['errors'][ $field_name ] = sprintf(
					/* translators: %s: field label. */
					__( '%s must be a valid date. Use the date picker or enter a date in YYYY-MM-DD format.', 'alynt-certificate-generator' ),
					$label
				);
				continue;
			}

			if ( 'select' === $type && '' !== $value && ! $this->is_valid_select_value( $value, $variable ) ) {
				$submission['errors'][ $field_name ] = sprintf(
					/* translators: %s: field label. */
					__( '%s has an invalid selection. Choose one of the available options and try again.', 'alynt-certificate-generator' ),
					$label
				);
			}
		}

		if ( ! empty( $submission['errors'] ) ) {
			$submission['first_error_field'] = (string) array_key_first( $submission['errors'] );
			return $submission;
		}

		$values = array();
		foreach ( $variables as $variable ) {
			$type     = $variable['type'] ?? 'text';
			$key      = $variable['key'] ?? '';
			$required = ! empty( $variable['required'] );
			if ( '' === $key || 'auto' === $type ) {
				continue;
			}

			$field_name = 'acg_var_' . $key;
			if ( 'image' === $type ) {
				$upload = $this->image_upload_service->handle_upload( $field_name, $required, $uploaded_files );
				if ( is_wp_error( $upload ) ) {
					$submission['errors'][ $field_name ] = $upload->get_error_message();
					$submission['first_error_field']     = $field_name;
					return $submission;
				}
				$values[ $key ] = $upload;
			} else {
				$values[ $key ] = isset( $submission['values'][ $field_name ] ) ? $submission['values'][ $field_name ] : '';
			}
		}

		$service   = new Alynt_Certificate_Generator_Certificate_Service();
		$generated = $service->generate(
			$template_id,
			$values,
			'form',
			get_current_user_id(),
			$this->get_request_ip(),
			$submission['skip_notifications']
		);

		if ( is_wp_error( $generated ) ) {
			$submission['errors']['form'] = $generated->get_error_message();
			return $submission;
		}

		$link = esc_url( $generated['download_url'] );
		return array(
			'message_html'       => sprintf(
				'<p>%s <a href="%s">%s</a></p>',
				esc_html__( 'Certificate generated successfully.', 'alynt-certificate-generator' ),
				$link,
				esc_html__( 'Download PDF', 'alynt-certificate-generator' )
			),
			'message_type'       => 'success',
			'errors'             => array(),
			'values'             => array(),
			'first_error_field'  => '',
			'skip_notifications' => false,
		);
	}

	/**
	 * Check whether a select submission matches one of the configured options.
	 *
	 * @param string $value    Submitted value.
	 * @param array  $variable Variable definition.
	 * @return bool
	 */
	private function is_valid_select_value( string $value, array $variable ): bool {
		$options = isset( $variable['options'] ) && is_array( $variable['options'] ) ? $variable['options'] : array();
		foreach ( $options as $option ) {
			$opt_value = isset( $option['value'] ) ? $option['value'] : '';
			$opt_label = isset( $option['label'] ) ? $option['label'] : $opt_value;
			if ( $value === (string) $opt_label ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Render a field-level error summary for a failed frontend submission.
	 *
	 * @param array<string, mixed> $submission Submission result.
	 * @return string
	 */
	private function render_error_summary( array $submission ): string {
		$errors = is_array( $submission['errors'] ) ? $submission['errors'] : array();
		if ( empty( $errors ) ) {
			return '';
		}

		$output  = '<div class="acg-certificate-message acg-certificate-message--error" role="alert">';
		$output .= '<p>' . esc_html__( 'Could not generate the certificate. Review the highlighted fields and try again.', 'alynt-certificate-generator' ) . '</p>';
		$output .= '<ul>';

		foreach ( $errors as $message ) {
			$output .= '<li>' . esc_html( (string) $message ) . '</li>';
		}

		$output .= '</ul></div>';

		return $output;
	}

	/**
	 * Enqueue frontend assets when the shortcode renders.
	 */
	private function enqueue_frontend_assets(): void {
		$script_path = ALYNT_CERTIFICATE_GENERATOR_PLUGIN_DIR . 'assets/dist/frontend/index.js';
		$style_path  = ALYNT_CERTIFICATE_GENERATOR_PLUGIN_DIR . 'assets/dist/frontend/index.css';

		if ( file_exists( $script_path ) ) {
			wp_enqueue_script( 'alynt-certificate-generator-frontend', ALYNT_CERTIFICATE_GENERATOR_PLUGIN_URL . 'assets/dist/frontend/index.js', array(), (string) filemtime( $script_path ), true );
		}

		if ( file_exists( $style_path ) ) {
			wp_enqueue_style( 'alynt-certificate-generator-frontend', ALYNT_CERTIFICATE_GENERATOR_PLUGIN_URL . 'assets/dist/frontend/index.css', array(), (string) filemtime( $style_path ) );
		}
	}

	/**
	 * Check whether this shortcode instance owns the current form submission.
	 *
	 * @param int $template_id Template ID.
	 * @return bool
	 */
	private function is_certificate_form_submission( int $template_id ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This only routes the request; handle_form_submission() verifies the nonce before processing.
		if ( ! isset( $_POST['acg_certificate_submit'], $_POST['acg_template_id'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This only matches the submitted shortcode instance before nonce verification in the handler.
		$submitted_template_id = absint( wp_unslash( $_POST['acg_template_id'] ) );
		return $template_id === $submitted_template_id;
	}

	/**
	 * Get client IP address.
	 *
	 * @return string
	 */
	private function get_request_ip(): string {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
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

	/**
	 * Prime post caches for certificate list template titles.
	 *
	 * @param array $items Certificate rows.
	 */
	private function prime_certificate_item_caches( array $items ): void {
		$template_ids = array_filter( array_unique( array_map( 'intval', wp_list_pluck( $items, 'template_id' ) ) ) );
		if ( ! empty( $template_ids ) ) {
			_prime_post_caches( $template_ids, false, false );
		}
	}
}
