<?php
declare( strict_types=1 );

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Alynt\CertificateGenerator\Services\Alynt_Certificate_Generator_Download_Service;
use Alynt\CertificateGenerator\Database\Alynt_Certificate_Generator_Certificate_Log;

final class DownloadServiceTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( '__' )->returnArg( 1 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_missing_token_returns_error(): void {
		$request = new WP_REST_Request( 'GET', '/acg/v1/certificates/test/download' );
		$request['certificate_id'] = 'CERT-123';
		$request->set_param( 'token', '' );

		// Create a mock of the Certificate Log.
		$stub_log = $this->createMock( Alynt_Certificate_Generator_Certificate_Log::class );
		$stub_log->method( 'get_by_certificate_and_token' )->willReturn( null );

		$service = new Alynt_Certificate_Generator_Download_Service( $stub_log );
		$result  = $service->serve_download( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'acg_missing_token', $result->get_error_code() );
	}
}
