<?php
/**
 * Fired during plugin deactivation.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator;

defined( 'ABSPATH' ) || exit;

use Alynt\CertificateGenerator\Database\Alynt_Certificate_Generator_Database;
use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Diagnostics_Logger;

/**
 * Handles plugin deactivation cleanup.
 */
class Alynt_Certificate_Generator_Deactivator {
	/**
	 * Run deactivation tasks.
	 */
	public static function deactivate(): void {
		if ( \function_exists( 'as_unschedule_all_actions' ) ) {
			\as_unschedule_all_actions( '', null, 'alynt_certificate_generator' );
		}

		\wp_clear_scheduled_hook( Alynt_Certificate_Generator_Database::CLEANUP_HOOK );
		\wp_clear_scheduled_hook( Alynt_Certificate_Generator_Diagnostics_Logger::CLEANUP_HOOK );
		\wp_clear_scheduled_hook( 'alynt_certificate_generator_send_webhook' );
		\wp_clear_scheduled_hook( 'alynt_certificate_generator_bulk_generate' );
	}
}
