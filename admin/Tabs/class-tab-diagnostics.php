<?php
/**
 * Diagnostics settings tab.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi\Tabs;

defined( 'ABSPATH' ) || exit;

class Alynt_Certificate_Generator_Tab_Diagnostics extends Alynt_Certificate_Generator_Tab_Base {
	public function get_id(): string {
		return 'diagnostics';
	}

	public function get_title(): string {
		return __( 'Diagnostics', 'alynt-certificate-generator' );
	}

	public function get_description(): string {
		return __( 'Admin-only diagnostic logging for support and troubleshooting. Sensitive values are redacted before storage.', 'alynt-certificate-generator' );
	}
}
