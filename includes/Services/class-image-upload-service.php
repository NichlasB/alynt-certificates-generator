<?php
/**
 * Image upload service.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Services;

defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * Handles certificate image variable uploads.
 */
class Alynt_Certificate_Generator_Image_Upload_Service {
	/**
	 * Maximum image upload size in bytes.
	 *
	 * @var int
	 */
	private const MAX_IMAGE_SIZE = 5242880;

	/**
	 * Handle an uploaded image variable.
	 *
	 * @param string $field_name Field name.
	 * @param bool   $required   Whether the image is required.
	 * @param array  $files      Uploaded files.
	 * @return int|string|WP_Error Attachment ID, empty string, or upload error.
	 */
	public function handle_upload( string $field_name, bool $required, array $files ) {
		if ( empty( $files[ $field_name ]['name'] ) ) {
			if ( $required ) {
				return new WP_Error( 'acg_image_required', __( 'Required image fields must be completed.', 'alynt-certificate-generator' ) );
			}
			return '';
		}

		$file = $files[ $field_name ];
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			return new WP_Error( 'acg_image_upload_failed', __( 'Image upload failed.', 'alynt-certificate-generator' ) );
		}

		if ( $file['size'] > self::MAX_IMAGE_SIZE ) {
			return new WP_Error( 'acg_image_too_large', __( 'Image uploads must be 5MB or smaller.', 'alynt-certificate-generator' ) );
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
			return new WP_Error( 'acg_image_upload_failed', $upload['error'] );
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $upload['type'],
				'post_title'     => sanitize_file_name( $file['name'] ),
				'post_status'    => 'inherit',
			),
			$upload['file']
		);
		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return new WP_Error( 'acg_image_attachment_failed', __( 'Uploaded image could not be saved.', 'alynt-certificate-generator' ) );
		}

		$meta = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $meta );

		return $attachment_id;
	}
}
