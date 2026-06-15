<?php
/**
 * PDF template image compositor.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Services;

defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * Flattens certificate template images with image variables before PDF rendering.
 */
class Alynt_Certificate_Generator_Pdf_Image_Compositor {
	/**
	 * PDF geometry helpers.
	 *
	 * @var Alynt_Certificate_Generator_Pdf_Geometry
	 */
	private $geometry;

	/**
	 * Constructor.
	 *
	 * @param Alynt_Certificate_Generator_Pdf_Geometry|null $geometry PDF geometry helpers.
	 */
	public function __construct( ?Alynt_Certificate_Generator_Pdf_Geometry $geometry = null ) {
		$this->geometry = null !== $geometry ? $geometry : new Alynt_Certificate_Generator_Pdf_Geometry();
	}

	/**
	 * Create a flattened template image with image variables.
	 *
	 * @param string $template_path Template image path.
	 * @param array  $variables     Variable data.
	 * @return array|WP_Error
	 */
	public function create_template_image( string $template_path, array $variables ) {
		$image_info = getimagesize( $template_path );
		if ( ! $image_info ) {
			return new WP_Error( 'acg_template_invalid', __( 'Template image is invalid.', 'alynt-certificate-generator' ) );
		}

		if ( ! in_array( $image_info['mime'], array( 'image/jpeg', 'image/png' ), true ) ) {
			return new WP_Error( 'acg_template_invalid', __( 'Template image format not supported.', 'alynt-certificate-generator' ) );
		}

		$template = $this->load_image_resource( $template_path );
		if ( ! $template ) {
			return new WP_Error( 'acg_template_invalid', __( 'Template image could not be loaded.', 'alynt-certificate-generator' ) );
		}

		$width  = imagesx( $template );
		$height = imagesy( $template );

		foreach ( $variables as $variable ) {
			if ( ! isset( $variable['type'] ) || 'image' !== $variable['type'] ) {
				continue;
			}

			if ( isset( $variable['display_on_certificate'] ) && false === $variable['display_on_certificate'] ) {
				continue;
			}

			$image_value = $variable['value'] ?? null;
			if ( empty( $image_value ) ) {
				continue;
			}

			$image_path = $this->resolve_image_path( $image_value );
			if ( ! $image_path || ! file_exists( $image_path ) ) {
				continue;
			}

			$overlay = $this->load_image_resource( $image_path );
			if ( ! $overlay ) {
				continue;
			}

			$max_width  = isset( $variable['image_max_width'] ) ? (int) $variable['image_max_width'] : imagesx( $overlay );
			$max_height = isset( $variable['image_max_height'] ) ? (int) $variable['image_max_height'] : imagesy( $overlay );

			$src_width  = imagesx( $overlay );
			$src_height = imagesy( $overlay );

			if ( $src_width < 1 || $src_height < 1 ) {
				unset( $overlay );
				continue;
			}

			if ( $max_width < 1 || $max_height < 1 ) {
				unset( $overlay );
				continue;
			}

			$ratio       = min( $max_width / $src_width, $max_height / $src_height, 1 );
			$dest_width  = (int) round( $src_width * $ratio );
			$dest_height = (int) round( $src_height * $ratio );

			$x_raw = isset( $variable['x'] ) ? (float) $variable['x'] : 0;
			$y_raw = isset( $variable['y'] ) ? (float) $variable['y'] : 0;

			imagecopyresampled(
				$template,
				$overlay,
				$this->geometry->resolve_coordinate( $x_raw, $width ),
				$this->geometry->resolve_coordinate( $y_raw, $height ),
				0,
				0,
				$dest_width,
				$dest_height,
				$src_width,
				$src_height
			);

			unset( $overlay );
		}

		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$temp_file = \wp_tempnam( 'acg-template.png' );
		if ( ! $temp_file ) {
			unset( $template );
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'error',
				'filesystem',
				'template_temp_create_failed',
				'Temporary template image file could not be created.'
			);
			return new WP_Error( 'acg_temp_failed', __( 'Temporary file could not be created.', 'alynt-certificate-generator' ) );
		}

		$saved = imagepng( $template, $temp_file );
		unset( $template );
		if ( ! $saved || ! file_exists( $temp_file ) ) {
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'error',
				'filesystem',
				'template_temp_save_failed',
				'Temporary template image file could not be saved.'
			);
			return new WP_Error( 'acg_temp_failed', __( 'Temporary image could not be saved.', 'alynt-certificate-generator' ) );
		}

		return array(
			'path'   => $temp_file,
			'width'  => $width,
			'height' => $height,
		);
	}

	/**
	 * Resolve image path from variable value.
	 *
	 * @param mixed $value Image value.
	 * @return string|null
	 */
	private function resolve_image_path( $value ): ?string {
		if ( is_numeric( $value ) ) {
			return \get_attached_file( (int) $value );
		}

		if ( is_string( $value ) && file_exists( $value ) ) {
			return $value;
		}

		return null;
	}

	/**
	 * Load a GD image resource.
	 *
	 * @param string $path Image file path.
	 * @return resource|false
	 */
	private function load_image_resource( string $path ) {
		$info = getimagesize( $path );
		if ( ! $info ) {
			return false;
		}

		if ( 'image/jpeg' === $info['mime'] ) {
			return imagecreatefromjpeg( $path );
		}

		if ( 'image/png' === $info['mime'] ) {
			return imagecreatefrompng( $path );
		}

		return false;
	}
}
