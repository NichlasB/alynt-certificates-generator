<?php
/**
 * Certificate template meta manager.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi;

defined( 'ABSPATH' ) || exit;

use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Font_Service;

class Alynt_Certificate_Generator_Template_Meta_Manager {
	/**
	 * Save template metadata.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_template_meta( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$nonce = isset( $_POST['acg_template_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['acg_template_nonce'] ) ) : '';
		if ( '' === $nonce || ! \wp_verify_nonce( $nonce, 'acg_template_save' ) ) {
			return;
		}

		if ( ! \current_user_can( ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE, $post_id ) ) {
			return;
		}

		$font_file = array();
		if ( isset( $_FILES['acg_template_font_file'] ) && is_array( $_FILES['acg_template_font_file'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File data is validated by the font service after nonce and capability checks.
			$font_file = $_FILES['acg_template_font_file'];
		}

		if ( isset( $_POST['acg_template_image_id'] ) ) {
			$image_id    = absint( wp_unslash( $_POST['acg_template_image_id'] ) );
			$orientation = isset( $_POST['acg_template_orientation'] ) ? sanitize_key( wp_unslash( $_POST['acg_template_orientation'] ) ) : 'landscape';

			if ( '' === $orientation ) {
				$orientation = 'landscape';
			}

			$validation = $this->validate_template_image( $image_id, $orientation );
			if ( ! $validation['valid'] ) {
				$this->store_admin_error( $post_id, $validation['message'] );
			} else {
				\update_post_meta( $post_id, 'acg_template_image_id', $image_id );
			}

			if ( in_array( $orientation, array( 'landscape', 'portrait' ), true ) ) {
				\update_post_meta( $post_id, 'acg_template_orientation', $orientation );
			}
		}

		if ( isset( $_POST['acg_template_variables'] ) ) {
			$variables_json = sanitize_textarea_field( wp_unslash( $_POST['acg_template_variables'] ) );
			\update_post_meta( $post_id, 'acg_template_variables', $variables_json );
			$saved = \get_post_meta( $post_id, 'acg_template_variables', true );
		}

		$this->save_permissions(
			$post_id,
			isset( $_POST['acg_template_access'] ) ? sanitize_key( wp_unslash( $_POST['acg_template_access'] ) ) : '',
			isset( $_POST['acg_template_roles'] ) && is_array( $_POST['acg_template_roles'] )
				? array_map( 'sanitize_key', wp_unslash( $_POST['acg_template_roles'] ) )
				: array()
		);

		$this->save_webhook_settings(
			$post_id,
			array(
				'api_key'          => isset( $_POST['acg_webhook_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['acg_webhook_api_key'] ) ) : null,
				'signature_secret' => isset( $_POST['acg_webhook_signature_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['acg_webhook_signature_secret'] ) ) : null,
				'rate_limit'       => isset( $_POST['acg_webhook_rate_limit'] ) ? absint( wp_unslash( $_POST['acg_webhook_rate_limit'] ) ) : null,
				'outgoing_url'     => isset( $_POST['acg_webhook_outgoing_url'] ) ? esc_url_raw( wp_unslash( $_POST['acg_webhook_outgoing_url'] ) ) : null,
				'outgoing_enabled' => isset( $_POST['acg_webhook_outgoing_enabled'] ),
			)
		);

		$this->save_template_fonts(
			$post_id,
			isset( $_POST['acg_template_font_family'] ) ? sanitize_text_field( wp_unslash( $_POST['acg_template_font_family'] ) ) : '',
			isset( $_POST['acg_template_font_weight'] ) ? sanitize_key( wp_unslash( $_POST['acg_template_font_weight'] ) ) : 'regular',
			$font_file
		);
	}

	/**
	 * Display admin errors for template save.
	 */
	public function render_admin_errors(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only post ID for displaying a transient admin notice.
		if ( ! isset( $_GET['post'] ) ) {
			return;
		}

		$post_id = absint( wp_unslash( $_GET['post'] ) );
		if ( ! $post_id ) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$transient_key = 'acg_template_error_' . $post_id;
		$message       = get_transient( $transient_key );
		if ( ! $message ) {
			return;
		}

		delete_transient( $transient_key );

		echo '<div class="notice notice-error" role="alert"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Get permissions data.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function get_permissions( int $post_id ): array {
		$raw     = (string) \get_post_meta( $post_id, 'acg_template_permissions', true );
		$decoded = json_decode( $raw, true );

		if ( ! is_array( $decoded ) ) {
			return array(
				'access' => 'any',
				'roles'  => array(),
			);
		}

		return array(
			'access' => isset( $decoded['access'] ) ? (string) $decoded['access'] : 'any',
			'roles'  => isset( $decoded['roles'] ) && is_array( $decoded['roles'] ) ? $decoded['roles'] : array(),
		);
	}

	/**
	 * Get webhook settings.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function get_webhook_settings( int $post_id ): array {
		$raw     = (string) \get_post_meta( $post_id, 'acg_template_webhook_settings', true );
		$decoded = json_decode( $raw, true );

		$incoming = array(
			'api_key'          => '',
			'signature_secret' => '',
			'rate_limit'       => 0,
		);
		$outgoing = array(
			'url'     => '',
			'enabled' => false,
		);

		if ( is_array( $decoded ) ) {
			if ( isset( $decoded['incoming'] ) && is_array( $decoded['incoming'] ) ) {
				$incoming = array_merge( $incoming, $decoded['incoming'] );
			}
			if ( isset( $decoded['outgoing'] ) && is_array( $decoded['outgoing'] ) ) {
				$outgoing = array_merge( $outgoing, $decoded['outgoing'] );
			}
		}

		return array(
			'incoming' => $incoming,
			'outgoing' => $outgoing,
		);
	}

	/**
	 * Save permissions data.
	 *
	 * @param int      $post_id Post ID.
	 * @param string   $access Access mode.
	 * @param string[] $roles User roles.
	 */
	private function save_permissions( int $post_id, string $access, array $roles ): void {
		if ( '' === $access ) {
			return;
		}

		if ( 'roles' !== $access ) {
			$roles = array();
		}

		\update_post_meta(
			$post_id,
			'acg_template_permissions',
			wp_json_encode(
				array(
					'access' => $access,
					'roles'  => $roles,
				)
			)
		);
	}

	/**
	 * Save webhook settings.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $input Webhook input.
	 */
	private function save_webhook_settings( int $post_id, array $input ): void {
		if ( ! array_key_exists( 'api_key', $input ) || null === $input['api_key'] ) {
			return;
		}

		$current          = $this->get_webhook_settings( $post_id );
		$api_key          = (string) $input['api_key'];
		$signature_secret = null !== $input['signature_secret'] ? (string) $input['signature_secret'] : $current['incoming']['signature_secret'];
		$rate_limit       = null !== $input['rate_limit'] ? (int) $input['rate_limit'] : $current['incoming']['rate_limit'];

		if ( '' === $api_key ) {
			$api_key = wp_generate_password( 32, false, false );
		}

		$outgoing_url     = null !== $input['outgoing_url'] ? $this->sanitize_outgoing_webhook_url( (string) $input['outgoing_url'] ) : $current['outgoing']['url'];
		$outgoing_enabled = (bool) $input['outgoing_enabled'];

		\update_post_meta(
			$post_id,
			'acg_template_webhook_settings',
			wp_json_encode(
				array(
					'incoming' => array(
						'api_key'          => $api_key,
						'signature_secret' => $signature_secret,
						'rate_limit'       => $rate_limit,
					),
					'outgoing' => array(
						'url'     => $outgoing_url,
						'enabled' => $outgoing_enabled,
					),
				)
			)
		);
	}

	/**
	 * Save template-specific fonts.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $family_name Font family name.
	 * @param string $weight Font weight.
	 * @param array  $font_file Uploaded font file.
	 */
	private function save_template_fonts( int $post_id, string $family_name, string $weight, array $font_file ): void {
		if ( '' === $family_name || empty( $font_file['tmp_name'] ) ) {
			return;
		}

		$font_service = new Alynt_Certificate_Generator_Font_Service();
		$result       = $font_service->upload_font(
			$font_file,
			$family_name,
			$weight,
			$post_id
		);

		if ( is_wp_error( $result ) ) {
			$this->store_admin_error( $post_id, $result->get_error_message() );
		}
	}

	/**
	 * Sanitize outgoing webhook URL and require HTTPS.
	 *
	 * @param string $url Submitted URL.
	 * @return string
	 */
	private function sanitize_outgoing_webhook_url( string $url ): string {
		$url = esc_url_raw( $url, array( 'https' ) );
		if ( '' === $url || 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) ) {
			return '';
		}

		return $url;
	}

	/**
	 * Validate template image.
	 *
	 * @param int    $image_id    Attachment ID.
	 * @param string $orientation Orientation.
	 * @return array<string, mixed>
	 */
	private function validate_template_image( int $image_id, string $orientation ): array {
		if ( 0 === $image_id ) {
			return array(
				'valid'   => true,
				'message' => '',
			);
		}

		$file_path = \get_attached_file( $image_id );
		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return array(
				'valid'   => false,
				'message' => __( 'Selected image file not found.', 'alynt-certificate-generator' ),
			);
		}

		$file_type = \wp_check_filetype( $file_path );
		$allowed   = array( 'jpg', 'jpeg', 'png' );
		if ( empty( $file_type['ext'] ) || ! in_array( strtolower( $file_type['ext'] ), $allowed, true ) ) {
			return array(
				'valid'   => false,
				'message' => __( 'Template image must be a JPG or PNG file.', 'alynt-certificate-generator' ),
			);
		}

		$file_size = filesize( $file_path );
		if ( $file_size && $file_size > 5 * 1024 * 1024 ) {
			return array(
				'valid'   => false,
				'message' => __( 'Template image exceeds the 5MB size limit.', 'alynt-certificate-generator' ),
			);
		}

		$metadata = \wp_get_attachment_metadata( $image_id );
		$width    = isset( $metadata['width'] ) ? (int) $metadata['width'] : 0;
		$height   = isset( $metadata['height'] ) ? (int) $metadata['height'] : 0;

		if ( $width < 1 || $height < 1 ) {
			return array(
				'valid'   => false,
				'message' => __( 'Template image dimensions could not be read.', 'alynt-certificate-generator' ),
			);
		}

		if ( $width > 6000 || $height > 6000 ) {
			return array(
				'valid'   => false,
				'message' => __( 'Template image exceeds the 6000x6000 maximum resolution.', 'alynt-certificate-generator' ),
			);
		}

		if ( 'portrait' === $orientation ) {
			if ( $width < 800 || $height < 1000 ) {
				return array(
					'valid'   => false,
					'message' => __( 'Portrait templates must be at least 800x1000 pixels.', 'alynt-certificate-generator' ),
				);
			}
		} elseif ( $width < 1000 || $height < 800 ) {
			return array(
				'valid'   => false,
				'message' => __( 'Landscape templates must be at least 1000x800 pixels.', 'alynt-certificate-generator' ),
			);
		}

		return array(
			'valid'   => true,
			'message' => '',
		);
	}

	/**
	 * Store admin error message for next load.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $message Error message.
	 */
	private function store_admin_error( int $post_id, string $message ): void {
		set_transient( 'acg_template_error_' . $post_id, $message, 30 );
	}
}
