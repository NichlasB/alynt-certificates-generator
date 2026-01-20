<?php
/**
 * Webhooks settings tab.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi\Tabs;

class Alynt_Certificate_Generator_Tab_Webhooks extends Alynt_Certificate_Generator_Tab_Base {
	public function get_id(): string {
		return 'webhooks';
	}

	public function get_title(): string {
		return __( 'Webhooks', 'alynt-certificate-generator' );
	}

	public function get_description(): string {
		return __( 'Global webhook settings and retry defaults.', 'alynt-certificate-generator' );
	}
}
