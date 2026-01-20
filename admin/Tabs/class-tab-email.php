<?php
/**
 * Email settings tab.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi\Tabs;

class Alynt_Certificate_Generator_Tab_Email extends Alynt_Certificate_Generator_Tab_Base {
	public function get_id(): string {
		return 'email';
	}

	public function get_title(): string {
		return __( 'Email', 'alynt-certificate-generator' );
	}

	public function get_description(): string {
		return __( 'Default email sender details and footer content.', 'alynt-certificate-generator' );
	}
}
