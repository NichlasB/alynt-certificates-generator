<?php
declare( strict_types=1 );

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Alynt\CertificateGenerator\Rest\Alynt_Certificate_Generator_Webhook_Service;
use Alynt\CertificateGenerator\Database\Alynt_Certificate_Generator_Webhook_Log;
use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Certificate_Service;

final class WebhookServiceTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'sanitize_key' )->returnArg( 1 );
		Functions\when( 'wp_unslash' )->returnArg( 1 );
		Functions\when( 'get_post' )->justReturn( (object) array( 'post_type' => 'acg_certificate_template' ) );
		Functions\when( 'get_post_meta' )->justReturn(
			json_encode(
				array(
					'incoming' => array(
						'api_key'          => 'secret',
						'signature_secret' => '',
						'rate_limit'       => 1,
					),
				)
			)
		);
		Functions\when( 'get_option' )->justReturn(
			array(
				'webhook_rate_limit_per_minute' => 1,
			)
		);
		Functions\when( 'get_transient' )->justReturn( 1 );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'current_time' )->justReturn( '2025-01-01 00:00:00' );
		Functions\when( 'rest_url' )->returnArg( 1 );
		Functions\when( 'esc_url_raw' )->returnArg( 1 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_rate_limit_returns_error(): void {
		$request = new WP_REST_Request( 'POST', '/acg/v1/templates/123/incoming' );
		$request['id'] = 123;
		$request->set_header( 'X-ACG-API-Key', 'secret' );
		$request->set_body( json_encode( array( 'name' => 'Test' ) ) );

		// Create mocks for the required dependencies.
		$log_stub = $this->createMock( Alynt_Certificate_Generator_Webhook_Log::class );
		$log_stub->method( 'insert' )->willReturn( 1 );

		$cert_stub = $this->createMock( Alynt_Certificate_Generator_Certificate_Service::class );

		$service = new Alynt_Certificate_Generator_Webhook_Service( $log_stub, $cert_stub );
		$result  = $service->handle_incoming( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'acg_webhook_rate', $result->get_error_code() );
	}
}
