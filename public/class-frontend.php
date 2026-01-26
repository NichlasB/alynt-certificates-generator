<?php
/**
 * Frontend functionality.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Frontend;

use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Certificate_Service;

class Alynt_Certificate_Generator_Frontend {
	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	public function __construct( string $plugin_name, string $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
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

		$message = '';
		if ( isset( $_POST['acg_certificate_submit'] ) ) {
			$message = $this->handle_form_submission( $template_id, $variables );
		}

		$output = '<div class="acg-certificate-form">';
		if ( '' !== $message ) {
			$output .= '<div class="acg-certificate-message">' . $message . '</div>';
		}

		$output .= '<form method="post" enctype="multipart/form-data">';
		$output .= wp_nonce_field( 'acg_certificate_form_' . $template_id, 'acg_certificate_nonce', true, false );
		$output .= '<input type="hidden" name="acg_template_id" value="' . esc_attr( (string) $template_id ) . '" />';

		foreach ( $variables as $variable ) {
			$type = $variable['type'] ?? 'text';
			$key  = $variable['key'] ?? '';
			$label = $variable['label'] ?? $key;
			$required = ! empty( $variable['required'] );

			if ( '' === $key || 'auto' === $type ) {
				continue;
			}

			$field_name = 'acg_var_' . $key;
			$output .= '<p>';
			$output .= '<label for="' . esc_attr( $field_name ) . '">' . esc_html( $label ) . '</label><br />';

			if ( 'image' === $type ) {
				$output .= '<input type="file" name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_name ) . '" accept="image/png,image/jpeg" ' . ( $required ? 'required' : '' ) . ' />';
			} elseif ( 'select' === $type ) {
				$options = isset( $variable['options'] ) && is_array( $variable['options'] ) ? $variable['options'] : array();
				$output .= '<select name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_name ) . '" ' . ( $required ? 'required' : '' ) . '>';
				$output .= '<option value="">' . esc_html__( '— Select —', 'alynt-certificate-generator' ) . '</option>';
				foreach ( $options as $option ) {
					$opt_value = isset( $option['value'] ) ? $option['value'] : '';
					$opt_label = isset( $option['label'] ) ? $option['label'] : $opt_value;
					$output .= '<option value="' . esc_attr( $opt_label ) . '">' . esc_html( $opt_label ) . '</option>';
				}
				$output .= '</select>';
			} else {
				$input_type = 'date' === $type ? 'date' : 'text';
				$output .= '<input type="' . esc_attr( $input_type ) . '" name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_name ) . '" ' . ( $required ? 'required' : '' ) . ' />';
			}

			$output .= '</p>';
		}

		$output .= '<p><label><input type="checkbox" name="acg_skip_notifications" value="1" /> ';
		$output .= esc_html__( 'Skip email notifications for this certificate', 'alynt-certificate-generator' ) . '</label></p>';

		$output .= '<button type="submit" name="acg_certificate_submit" class="button">' . esc_html__( 'Generate Certificate', 'alynt-certificate-generator' ) . '</button>';
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

		$user_id = get_current_user_id();
		$per_page = 10;
		$paged = isset( $_GET['acg_page'] ) ? max( 1, absint( wp_unslash( $_GET['acg_page'] ) ) ) : 1;
		$offset = ( $paged - 1 ) * $per_page;

		$table = $wpdb->prefix . 'acg_certificate_log';

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT SQL_CALC_FOUND_ROWS * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			),
			ARRAY_A
		);
		$total = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );

		if ( empty( $items ) ) {
			return esc_html__( 'No certificates found.', 'alynt-certificate-generator' );
		}

		$output  = '<table class="acg-my-certificates">';
		$output .= '<thead><tr>';
		$output .= '<th>' . esc_html__( 'Certificate', 'alynt-certificate-generator' ) . '</th>';
		$output .= '<th>' . esc_html__( 'Date', 'alynt-certificate-generator' ) . '</th>';
		$output .= '<th>' . esc_html__( 'Download', 'alynt-certificate-generator' ) . '</th>';
		$output .= '</tr></thead><tbody>';

		foreach ( $items as $item ) {
			$template_title = get_the_title( (int) $item['template_id'] );
			$download_url = add_query_arg(
				'token',
				rawurlencode( (string) $item['download_token'] ),
				rest_url( 'acg/v1/certificates/' . rawurlencode( (string) $item['certificate_id'] ) . '/download' )
			);

			$output .= '<tr>';
			$output .= '<td>' . esc_html( $template_title ) . '</td>';
			$output .= '<td>' . esc_html( (string) $item['created_at'] ) . '</td>';
			$output .= '<td><a class="button" href="' . esc_url( $download_url ) . '">' . esc_html__( 'Download', 'alynt-certificate-generator' ) . '</a></td>';
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

		$raw = (string) \get_post_meta( $template_id, 'acg_template_permissions', true );
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
	 * Handle form submission.
	 *
	 * @param int   $template_id Template ID.
	 * @param array $variables Variable definitions.
	 * @return string
	 */
	private function handle_form_submission( int $template_id, array $variables ): string {
		if ( ! isset( $_POST['acg_certificate_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['acg_certificate_nonce'] ), 'acg_certificate_form_' . $template_id ) ) {
			return esc_html__( 'Security check failed.', 'alynt-certificate-generator' );
		}

		$values = array();
		foreach ( $variables as $variable ) {
			$type = $variable['type'] ?? 'text';
			$key  = $variable['key'] ?? '';
			if ( '' === $key || 'auto' === $type ) {
				continue;
			}

			$field_name = 'acg_var_' . $key;
			if ( 'image' === $type ) {
				$values[ $key ] = $this->handle_image_upload( $field_name, $variable );
			} else {
				$values[ $key ] = isset( $_POST[ $field_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) ) : '';
			}
		}

		$skip_notifications = isset( $_POST['acg_skip_notifications'] );

		$service = new Alynt_Certificate_Generator_Certificate_Service();
		$result  = $service->generate(
			$template_id,
			$values,
			'form',
			get_current_user_id(),
			$this->get_request_ip(),
			$skip_notifications
		);

		if ( is_wp_error( $result ) ) {
			return esc_html( $result->get_error_message() );
		}

		$link = esc_url( $result['download_url'] );
		return sprintf(
			'<div class="notice notice-success"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Certificate generated successfully.', 'alynt-certificate-generator' ),
			$link,
			esc_html__( 'Download PDF', 'alynt-certificate-generator' )
		);
	}

	/**
	 * Handle image uploads for image variables.
	 *
	 * @param string $field_name Field name.
	 * @param array  $variable Variable definition.
	 * @return int|string
	 */
	private function handle_image_upload( string $field_name, array $variable ) {
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
		$raw = (string) \get_post_meta( $template_id, 'acg_template_variables', true );
		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : array();
	}
}
