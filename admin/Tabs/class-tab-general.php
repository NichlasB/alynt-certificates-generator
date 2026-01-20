<?php
/**
 * General settings tab.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi\Tabs;

class Alynt_Certificate_Generator_Tab_General extends Alynt_Certificate_Generator_Tab_Base {
	public function get_id(): string {
		return 'general';
	}

	public function get_title(): string {
		return __( 'General', 'alynt-certificate-generator' );
	}

	public function get_description(): string {
		return __( 'Core configuration for certificates and storage.', 'alynt-certificate-generator' );
	}
}
