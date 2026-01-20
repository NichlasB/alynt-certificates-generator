<?php
declare( strict_types=1 );

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Alynt\CertificateGenerator\Admin\Settings\Alynt_Certificate_Generator_Admin_Settings_Schema;
use Alynt\CertificateGenerator\Admin\Settings\Sanitize\Alynt_Certificate_Generator_Settings_Sanitizer;

final class SettingsSanitizerTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'get_bloginfo' )->justReturn( 'Site' );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'sanitize_email' )->returnArg( 1 );
		Functions\when( 'absint' )->alias( function( $value ) {
			return abs( (int) $value );
		} );
		Functions\when( 'wp_kses_post' )->returnArg( 1 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_webhook_rate_limit_respects_minimum(): void {
		$schema = new Alynt_Certificate_Generator_Admin_Settings_Schema();
		$sanitizer = new Alynt_Certificate_Generator_Settings_Sanitizer( $schema );

		$input = array(
			'webhook_rate_limit_per_minute' => '0',
		);
		$current = array(
			'pdf_storage_path' => 'keep',
		);

		$result = $sanitizer->sanitize_for_tab( $input, $current, 'webhooks' );

		$this->assertSame( 1, $result['webhook_rate_limit_per_minute'] );
		$this->assertSame( 'keep', $result['pdf_storage_path'] );
	}
}
