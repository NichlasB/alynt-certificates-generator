<?php
/**
 * Logs settings tab.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi\Tabs;

class Alynt_Certificate_Generator_Tab_Logs extends Alynt_Certificate_Generator_Tab_Base {
	public function get_id(): string {
		return 'logs';
	}

	public function get_title(): string {
		return __( 'Logs', 'alynt-certificate-generator' );
	}

	public function get_description(): string {
		return __( 'Retention and export options for logs.', 'alynt-certificate-generator' );
	}
}
