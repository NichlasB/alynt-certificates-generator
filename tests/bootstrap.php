<?php
declare( strict_types=1 );

require dirname( __DIR__ ) . '/vendor/autoload.php';

// Register the plugin autoloader.
require_once dirname( __DIR__ ) . '/includes/class-loader.php';
new Alynt\CertificateGenerator\Alynt_Certificate_Generator_Loader( dirname( __DIR__ ) );

if ( ! defined( 'ALYNT_CERTIFICATE_GENERATOR_SETTINGS_OPTION' ) ) {
	define( 'ALYNT_CERTIFICATE_GENERATOR_SETTINGS_OPTION', 'alynt_certificate_generator_settings' );
}
if ( ! defined( 'ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE' ) ) {
	define( 'ALYNT_CERTIFICATE_GENERATOR_CAPABILITY_MANAGE', 'manage_options' );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;

		public function __construct( string $code = '', string $message = '', array $data = array() ) {
			$this->code = $code;
			$this->message = $message;
			$this->data = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $value ): bool {
		return $value instanceof WP_Error;
	}
}

require_once __DIR__ . '/stubs/class-wp-rest-request.php';
