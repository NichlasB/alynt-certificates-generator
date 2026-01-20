<?php
/**
 * Internationalization functionality.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator;

class Alynt_Certificate_Generator_I18n {
	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain(): void {
		\load_plugin_textdomain(
			ALYNT_CERTIFICATE_GENERATOR_TEXT_DOMAIN,
			false,
			dirname( ALYNT_CERTIFICATE_GENERATOR_PLUGIN_BASENAME ) . '/languages/'
		);
	}
}
