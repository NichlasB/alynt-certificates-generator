<?php
/**
 * PDF rendering service.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Services;

defined( 'ABSPATH' ) || exit;

use TCPDF;
use WP_Error;

/**
 * Renders certificate PDFs from image templates and resolved variables.
 */
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
	 * Template image compositor.
	 *
	 * @var Alynt_Certificate_Generator_Pdf_Image_Compositor
	 */
	private $image_compositor;

	/**
	 * PDF geometry helpers.
	 *
	 * @var Alynt_Certificate_Generator_Pdf_Geometry
	 */
	private $geometry;

	/**
	 * Constructor.
	 *
	 * @param Alynt_Certificate_Generator_Font_Service|null         $font_service     Font service.
	 * @param Alynt_Certificate_Generator_Pdf_Image_Compositor|null $image_compositor Template image compositor.
	 * @param Alynt_Certificate_Generator_Pdf_Geometry|null         $geometry         PDF geometry helpers.
	 */
	public function __construct(
		?Alynt_Certificate_Generator_Font_Service $font_service = null,
		?Alynt_Certificate_Generator_Pdf_Image_Compositor $image_compositor = null,
		?Alynt_Certificate_Generator_Pdf_Geometry $geometry = null
	) {
		$this->font_service     = null !== $font_service ? $font_service : new Alynt_Certificate_Generator_Font_Service();
		$this->geometry         = null !== $geometry ? $geometry : new Alynt_Certificate_Generator_Pdf_Geometry();
		$this->image_compositor = null !== $image_compositor ? $image_compositor : new Alynt_Certificate_Generator_Pdf_Image_Compositor( $this->geometry );
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
		$template_image = $this->image_compositor->create_template_image( $template_path, $variables );
		if ( is_wp_error( $template_image ) ) {
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'error',
				'filesystem',
				'template_image_composition_failed',
				'Template image composition failed before PDF rendering.',
				array(
					'template_id' => $template_id,
					'error_code'  => $template_image->get_error_code(),
				)
			);
			return $template_image;
		}

		$image_path   = $template_image['path'];
		$image_width  = $template_image['width'];
		$image_height = $template_image['height'];

		$width_mm  = $this->geometry->px_to_mm( $image_width );
		$height_mm = $this->geometry->px_to_mm( $image_height );

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

			// Convert coordinates to pixels, then to mm.
			$x_px = $this->geometry->resolve_coordinate( $x_raw, $image_width );
			$y_px = $this->geometry->resolve_coordinate( $y_raw, $image_height );

			$x_mm = $this->geometry->px_to_mm( $x_px );
			$y_mm = $this->geometry->px_to_mm( $y_px );

			$align     = $style['align'] ?? 'left';
			$max_width = isset( $style['text_max_width'] ) ? (int) $style['text_max_width'] : 0;

			if ( $max_width > 0 ) {
				$max_width = min( $max_width, $image_width );
				$this->write_wrapped_text( $pdf, $text, $x_mm, $y_mm, $max_width, $font_size, $align, $style );
				continue;
			}

			$text_width = $pdf->GetStringWidth( $text );
			if ( 'center' === $align ) {
				$x_mm -= $text_width / 2;
			} elseif ( 'right' === $align ) {
				$x_mm -= $text_width;
			}

			$pdf->Text( $x_mm, $y_mm, $text );
		}

		try {
			$pdf->Output( $output_path, 'F' );
		} catch ( \Exception $e ) {
			$this->delete_temp_image( $image_path, $template_path );
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'error',
				'filesystem',
				'pdf_write_exception',
				'TCPDF threw an exception while saving the certificate PDF.',
				array(
					'template_id' => $template_id,
					'exception'   => get_class( $e ),
				)
			);
			return new WP_Error( 'acg_pdf_write_failed', __( 'Certificate PDF could not be saved.', 'alynt-certificate-generator' ) );
		}

		if ( ! file_exists( $output_path ) || 0 === filesize( $output_path ) ) {
			$this->delete_temp_image( $image_path, $template_path );
			Alynt_Certificate_Generator_Diagnostics_Logger::log(
				'error',
				'filesystem',
				'pdf_write_empty',
				'Certificate PDF was missing or empty after rendering.',
				array(
					'template_id' => $template_id,
				)
			);
			return new WP_Error( 'acg_pdf_write_failed', __( 'Certificate PDF could not be saved.', 'alynt-certificate-generator' ) );
		}

		$this->delete_temp_image( $image_path, $template_path );

		return true;
	}

	/**
	 * Delete a generated temporary template image.
	 *
	 * @param string $image_path     Image path.
	 * @param string $template_path  Original template path.
	 */
	private function delete_temp_image( string $image_path, string $template_path ): void {
		if ( $image_path && file_exists( $image_path ) && $image_path !== $template_path ) {
			wp_delete_file( $image_path );
		}
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
		$style      = '';

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
	 * Write text inside a max-width wrapping box.
	 *
	 * @param TCPDF  $pdf          PDF instance.
	 * @param string $text         Text content.
	 * @param float  $x_mm         Anchor X coordinate in millimeters.
	 * @param float  $y_mm         Y coordinate in millimeters.
	 * @param int    $max_width_px Max text box width in template pixels.
	 * @param float  $font_size    Font size in points.
	 * @param string $align        Text alignment.
	 * @param array  $style        Variable style data.
	 */
	private function write_wrapped_text(
		TCPDF $pdf,
		string $text,
		float $x_mm,
		float $y_mm,
		int $max_width_px,
		float $font_size,
		string $align,
		array $style
	): void {
		$box_width_mm = $this->geometry->px_to_mm( $max_width_px );
		if ( $box_width_mm <= 0 ) {
			$pdf->Text( $x_mm, $y_mm, $text );
			return;
		}

		if ( 'center' === $align ) {
			$x_mm -= $box_width_mm / 2;
		} elseif ( 'right' === $align ) {
			$x_mm -= $box_width_mm;
		}

		$line_height = isset( $style['line_height'] ) ? (float) $style['line_height'] : 1.2;
		if ( $line_height < 0.8 ) {
			$line_height = 0.8;
		} elseif ( $line_height > 3 ) {
			$line_height = 3;
		}

		$cell_height_mm = ( $font_size * $line_height / 72 ) * 25.4;
		$pdf_align      = $this->map_text_align_for_pdf( $align );

		$pdf->MultiCell(
			$box_width_mm,
			$cell_height_mm,
			$text,
			0,
			$pdf_align,
			false,
			1,
			$x_mm,
			$y_mm,
			true,
			0,
			false,
			true,
			0,
			'T',
			false
		);
	}

	/**
	 * Map style alignment to TCPDF alignment.
	 *
	 * @param string $align Alignment value.
	 * @return string
	 */
	private function map_text_align_for_pdf( string $align ): string {
		if ( 'center' === $align ) {
			return 'C';
		}

		if ( 'right' === $align ) {
			return 'R';
		}

		return 'L';
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
