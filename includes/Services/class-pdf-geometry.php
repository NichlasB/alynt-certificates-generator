<?php
/**
 * PDF geometry helpers.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Converts certificate template coordinates between UI and PDF units.
 */
class Alynt_Certificate_Generator_Pdf_Geometry {
	/**
	 * Resolve a coordinate value to pixels.
	 *
	 * Handles both percentage format (0-1 range) and legacy pixel format (> 1).
	 *
	 * @param float $value     The coordinate value (percentage or pixels).
	 * @param int   $dimension The full dimension (width or height) in pixels.
	 * @return int
	 */
	public function resolve_coordinate( float $value, int $dimension ): int {
		if ( $value >= 0 && $value <= 1 ) {
			return (int) round( $value * $dimension );
		}

		return (int) round( $value );
	}

	/**
	 * Convert pixels to millimeters.
	 *
	 * @param int $px Pixels.
	 * @return float
	 */
	public function px_to_mm( int $px ): float {
		$dpi = 72;
		return ( $px / $dpi ) * 25.4;
	}
}
