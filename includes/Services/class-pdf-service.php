<?php
/**
 * PDF rendering service.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Services;

use TCPDF;
use WP_Error;

class Alynt_Certificate_Generator_Pdf_Service {
	/**
	 * Render a PDF from template and variables.
	 *
	 * @param string $template_path Template image file path.
	 * @param array  $variables     Variable definitions with resolved values.
	 * @param string $orientation   Page orientation.
	 * @param string $output_path   Destination PDF path.
	 * @return bool|WP_Error
	 */
	public function render_pdf( string $template_path, array $variables, string $orientation, string $output_path ) {
		$template_image = $this->create_template_image( $template_path, $variables );
		if ( is_wp_error( $template_image ) ) {
			return $template_image;
		}

		$image_path  = $template_image['path'];
		$image_width = $template_image['width'];
		$image_height = $template_image['height'];

		$width_mm  = $this->px_to_mm( $image_width );
		$height_mm = $this->px_to_mm( $image_height );

		$pdf = new TCPDF( strtoupper( substr( $orientation, 0, 1 ) ), 'mm', array( $width_mm, $height_mm ), true, 'UTF-8', false );
		$pdf->SetMargins( 0, 0, 0 );
		$pdf->SetAutoPageBreak( false, 0 );
		$pdf->setPrintHeader( false );
		$pdf->setPrintFooter( false );
		$pdf->AddPage();

		$pdf->Image( $image_path, 0, 0, $width_mm, $height_mm, '', '', '', false, 300, '', false, false, 0, false, false, false );

		foreach ( $variables as $variable ) {
			if ( ! isset( $variable['type'] ) || 'image' === $variable['type'] ) {
				continue;
			}

			$text = isset( $variable['value'] ) ? (string) $variable['value'] : '';
			if ( '' === $text ) {
				continue;
			}

			$style = $variable['style'] ?? array();
			$font_family = $this->map_font_family( $style['font_family'] ?? 'Helvetica' );
			$font_style  = $this->get_font_style( $style );
			$font_size   = isset( $style['font_size'] ) ? (float) $style['font_size'] : 24;

			$pdf->SetFont( $font_family, $font_style, $font_size );

			$color = $this->hex_to_rgb( $style['color'] ?? '#000000' );
			$pdf->SetTextColor( $color['r'], $color['g'], $color['b'] );

			$x_mm = $this->px_to_mm( (int) ( $variable['x'] ?? 0 ) );
			$y_mm = $this->px_to_mm( (int) ( $variable['y'] ?? 0 ) );

			$align = $style['align'] ?? 'left';
			$text_width = $pdf->GetStringWidth( $text );

			if ( 'center' === $align ) {
				$x_mm -= $text_width / 2;
			} elseif ( 'right' === $align ) {
				$x_mm -= $text_width;
			}

			$pdf->Text( $x_mm, $y_mm, $text );
		}

		$pdf->Output( $output_path, 'F' );

		if ( $image_path && file_exists( $image_path ) && $image_path !== $template_path ) {
			unlink( $image_path );
		}

		return true;
	}

	/**
	 * Create a flattened template image with image variables.
	 *
	 * @param string $template_path Template image path.
	 * @param array  $variables     Variable data.
	 * @return array|WP_Error
	 */
	private function create_template_image( string $template_path, array $variables ) {
		$image_info = getimagesize( $template_path );
		if ( ! $image_info ) {
			return new WP_Error( 'acg_template_invalid', __( 'Template image is invalid.', 'alynt-certificate-generator' ) );
		}

		$mime = $image_info['mime'];
		if ( 'image/jpeg' === $mime ) {
			$template = imagecreatefromjpeg( $template_path );
		} elseif ( 'image/png' === $mime ) {
			$template = imagecreatefrompng( $template_path );
		} else {
			return new WP_Error( 'acg_template_invalid', __( 'Template image format not supported.', 'alynt-certificate-generator' ) );
		}

		if ( ! $template ) {
			return new WP_Error( 'acg_template_invalid', __( 'Template image could not be loaded.', 'alynt-certificate-generator' ) );
		}

		$width  = imagesx( $template );
		$height = imagesy( $template );

		foreach ( $variables as $variable ) {
			if ( ! isset( $variable['type'] ) || 'image' !== $variable['type'] ) {
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

			$ratio = min( $max_width / $src_width, $max_height / $src_height, 1 );
			$dest_width  = (int) round( $src_width * $ratio );
			$dest_height = (int) round( $src_height * $ratio );

			$dest_x = (int) ( $variable['x'] ?? 0 );
			$dest_y = (int) ( $variable['y'] ?? 0 );

			imagecopyresampled(
				$template,
				$overlay,
				$dest_x,
				$dest_y,
				0,
				0,
				$dest_width,
				$dest_height,
				$src_width,
				$src_height
			);

			imagedestroy( $overlay );
		}

		$temp_file = wp_tempnam( 'acg-template.png' );
		if ( ! $temp_file ) {
			imagedestroy( $template );
			return new WP_Error( 'acg_temp_failed', __( 'Temporary file could not be created.', 'alynt-certificate-generator' ) );
		}

		imagepng( $template, $temp_file );
		imagedestroy( $template );

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

	/**
	 * Convert pixels to millimeters.
	 *
	 * @param int $px Pixels.
	 * @return float
	 */
	private function px_to_mm( int $px ): float {
		$dpi = 72;
		return ( $px / $dpi ) * 25.4;
	}

	/**
	 * Map UI font names to TCPDF fonts.
	 *
	 * @param string $font Font name.
	 * @return string
	 */
	private function map_font_family( string $font ): string {
		$map = array(
			'Arial'           => 'helvetica',
			'Helvetica'       => 'helvetica',
			'Verdana'         => 'helvetica',
			'Times New Roman' => 'times',
			'Georgia'         => 'times',
			'Courier New'     => 'courier',
		);

		return $map[ $font ] ?? 'helvetica';
	}

	/**
	 * Build TCPDF font style string.
	 *
	 * @param array $style Style data.
	 * @return string
	 */
	private function get_font_style( array $style ): string {
		$style_string = '';
		if ( ! empty( $style['bold'] ) ) {
			$style_string .= 'B';
		}
		if ( ! empty( $style['italic'] ) ) {
			$style_string .= 'I';
		}

		return $style_string;
	}

	/**
	 * Convert hex to RGB.
	 *
	 * @param string $hex Hex color.
	 * @return array<string, int>
	 */
	private function hex_to_rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		$int = hexdec( $hex );
		return array(
			'r' => ( $int >> 16 ) & 255,
			'g' => ( $int >> 8 ) & 255,
			'b' => $int & 255,
		);
	}
}
