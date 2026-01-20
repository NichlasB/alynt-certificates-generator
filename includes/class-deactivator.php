<?php
/**
 * Fired during plugin deactivation.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator;

class Alynt_Certificate_Generator_Deactivator {
	/**
	 * Run deactivation tasks.
	 */
	public static function deactivate(): void {
		if ( \function_exists( 'as_unschedule_all_actions' ) ) {
			\as_unschedule_all_actions( '', null, 'alynt_certificate_generator' );
		}
	}
}
