<?php
/**
 * Fonts settings tab.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi\Tabs;

class Alynt_Certificate_Generator_Tab_Fonts extends Alynt_Certificate_Generator_Tab_Base {
	public function get_id(): string {
		return 'fonts';
	}

	public function get_title(): string {
		return __( 'Fonts', 'alynt-certificate-generator' );
	}

	public function get_description(): string {
		return __( 'Manage custom fonts for certificate templates. Upload TTF or OTF font files downloaded from Google Fonts or other sources.', 'alynt-certificate-generator' );
	}
}
