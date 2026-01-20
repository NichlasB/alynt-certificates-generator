<?php
/**
 * Admin notices.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\AdminUi;

class Alynt_Certificate_Generator_Admin_Notices {
	/**
	 * Render webhook failure notice.
	 */
	public function render_webhook_notice(): void {
		$message = get_transient( 'acg_webhook_failure_notice' );
		if ( ! $message ) {
			return;
		}

		delete_transient( 'acg_webhook_failure_notice' );

		echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
	}
}
