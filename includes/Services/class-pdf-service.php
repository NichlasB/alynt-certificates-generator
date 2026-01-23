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
	 * Font service for custom font resolution.
	 *
	 * @var Alynt_Certificate_Generator_Font_Service
	 */
	private $font_service;

	/**
	 * Loaded custom fonts cache for current PDF.
	 *
	 * @var array
	 */
	private $loaded_fonts = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->font_service = new Alynt_Certificate_Generator_Font_Service();
	}

	/**
	 * Render a PDF from template and variables.
	 *
	 * @param string $template_path Template image file path.
	 * @param array  $variables     Variable definitions with resolved values.
	 * @param string $orientation   Page orientation.
	 * @param string $output_path   Destination PDF path.
	 * @param int    $template_id   Optional template ID for font resolution.
	 * @return bool|WP_Error
	 */
	public function render_pdf( string $template_path, array $variables, string $orientation, string $output_path, int $template_id = 0 ) {
		$template_image = $this->create_template_image( $template_path, $variables );
		if ( is_wp_error( $template_image ) ) {
			return $template_image;
		}

		$image_path   = $template_image['path'];
		$image_width  = $template_image['width'];
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

		// Reset loaded fonts for this PDF generation.
		$this->loaded_fonts = array();

		foreach ( $variables as $variable ) {
			if ( ! isset( $variable['type'] ) || 'image' === $variable['type'] ) {
				continue;
			}

			// Skip variables not meant to display on certificate.
			if ( isset( $variable['display_on_certificate'] ) && false === $variable['display_on_certificate'] ) {
				continue;
			}

			$text = isset( $variable['value'] ) ? (string) $variable['value'] : '';
			if ( '' === $text ) {
				continue;
			}

			$style     = $variable['style'] ?? array();
			$is_bold   = ! empty( $style['bold'] );
			$is_italic = ! empty( $style['italic'] );
			$font_size = isset( $style['font_size'] ) ? (float) $style['font_size'] : 24;

			// Resolve font using FontService.
			$font_info = $this->resolve_and_load_font(
				$pdf,
				$style['font_family'] ?? 'Helvetica',
				$is_bold,
				$is_italic,
				$template_id
			);

			$pdf->SetFont( $font_info['tcpdf_name'], $font_info['style'], $font_size );

			$color = $this->hex_to_rgb( $style['color'] ?? '#000000' );
			$pdf->SetTextColor( $color['r'], $color['g'], $color['b'] );

			// Get coordinates, handling both percentage (0-1) and legacy pixel formats.
			$x_raw = isset( $variable['x'] ) ? (float) $variable['x'] : 0;
			$y_raw = isset( $variable['y'] ) ? (float) $variable['y'] : 0;

			// DEBUG: Log coordinate conversion.
			error_log( 'ACG PDF DEBUG: Variable "' . ( $variable['key'] ?? 'unknown' ) . '" raw coords: x=' . $x_raw . ', y=' . $y_raw );
			error_log( 'ACG PDF DEBUG: Image dimensions: width=' . $image_width . ', height=' . $image_height );

			// Convert coordinates to pixels, then to mm.
			$x_px = $this->resolve_coordinate( $x_raw, $image_width );
			$y_px = $this->resolve_coordinate( $y_raw, $image_height );

			error_log( 'ACG PDF DEBUG: After resolve_coordinate: x_px=' . $x_px . ', y_px=' . $y_px );

			$x_mm = $this->px_to_mm( $x_px );
			$y_mm = $this->px_to_mm( $y_px );

			error_log( 'ACG PDF DEBUG: After px_to_mm: x_mm=' . $x_mm . ', y_mm=' . $y_mm );

			$align      = $style['align'] ?? 'left';
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

			// Skip variables not meant to display on certificate.
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

			$ratio       = min( $max_width / $src_width, $max_height / $src_height, 1 );
			$dest_width  = (int) round( $src_width * $ratio );
			$dest_height = (int) round( $src_height * $ratio );

			// Get coordinates, handling both percentage (0-1) and legacy pixel formats.
			$x_raw = isset( $variable['x'] ) ? (float) $variable['x'] : 0;
			$y_raw = isset( $variable['y'] ) ? (float) $variable['y'] : 0;

			$dest_x = $this->resolve_coordinate( $x_raw, $width );
			$dest_y = $this->resolve_coordinate( $y_raw, $height );

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
	 * Resolve a coordinate value to pixels.
	 *
	 * Handles both percentage format (0-1 range) and legacy pixel format (> 1).
	 * - Values between 0 and 1 (inclusive) are treated as percentages.
	 * - Values greater than 1 are treated as legacy pixel coordinates.
	 *
	 * @param float $value     The coordinate value (percentage or pixels).
	 * @param int   $dimension The full dimension (width or height) in pixels.
	 * @return int The coordinate in pixels.
	 */
	private function resolve_coordinate( float $value, int $dimension ): int {
		// Check if value is in percentage format (0-1 range).
		if ( $value >= 0 && $value <= 1 ) {
			// Convert percentage to pixels.
			return (int) round( $value * $dimension );
		}

		// Legacy pixel format - return as-is (casted to int).
		return (int) round( $value );
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
	 * Resolve and load a font for PDF generation.
	 *
	 * This method handles both system fonts and custom uploaded fonts.
	 * For custom fonts, it loads them into TCPDF using addFont().
	 *
	 * @param TCPDF  $pdf         TCPDF instance.
	 * @param string $font_family Font family name.
	 * @param bool   $bold        Is bold style requested.
	 * @param bool   $italic      Is italic style requested.
	 * @param int    $template_id Template ID for font resolution.
	 * @return array Font info with 'tcpdf_name' and 'style' keys.
	 */
	private function resolve_and_load_font( TCPDF $pdf, string $font_family, bool $bold, bool $italic, int $template_id ): array {
		// Use FontService to resolve the font.
		$font_info = $this->font_service->resolve_font_for_pdf( $font_family, $bold, $italic, $template_id );

		$tcpdf_name = $font_info['tcpdf_name'];
		$style = '';

		// If it's a custom font, we need to load it.
		if ( $font_info['is_custom'] && ! empty( $font_info['file_path'] ) ) {
			// Check if we've already loaded this font.
			$cache_key = $tcpdf_name;
			if ( ! isset( $this->loaded_fonts[ $cache_key ] ) ) {
				// Get the font directory path.
				$font_dir = dirname( $font_info['file_path'] ) . '/';

				// Add the custom font to TCPDF.
				try {
					$pdf->addFont( $tcpdf_name, '', $font_info['file_path'], '', $font_dir );
					$this->loaded_fonts[ $cache_key ] = true;
				} catch ( \Exception $e ) {
					// Fall back to helvetica if font loading fails.
					error_log( 'ACG PDF: Failed to load custom font ' . $tcpdf_name . ': ' . $e->getMessage() );
					return array(
						'tcpdf_name' => 'helvetica',
						'style'      => $this->build_font_style( $bold, $italic ),
					);
				}
			}

			// For custom fonts, we don't use TCPDF style modifiers since each weight is a separate file.
			// However, if the user requested bold/italic but we don't have that weight, we may simulate.
			if ( $font_info['simulate_bold'] || $font_info['simulate_italic'] ) {
				$style = $this->build_font_style( $font_info['simulate_bold'], $font_info['simulate_italic'] );
			}
		} else {
			// System font - use TCPDF style modifiers.
			$style = $this->build_font_style( $bold, $italic );
		}

		return array(
			'tcpdf_name' => $tcpdf_name,
			'style'      => $style,
		);
	}

	/**
	 * Map UI font names to TCPDF fonts (legacy fallback).
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
	 * @param bool $bold   Bold style.
	 * @param bool $italic Italic style.
	 * @return string
	 */
	private function build_font_style( bool $bold, bool $italic ): string {
		$style_string = '';
		if ( $bold ) {
			$style_string .= 'B';
		}
		if ( $italic ) {
			$style_string .= 'I';
		}

		return $style_string;
	}

	/**
	 * Build TCPDF font style string from style array (legacy).
	 *
	 * @param array $style Style data.
	 * @return string
	 */
	private function get_font_style( array $style ): string {
		return $this->build_font_style(
			! empty( $style['bold'] ),
			! empty( $style['italic'] )
		);
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
