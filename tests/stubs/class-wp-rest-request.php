<?php
declare( strict_types=1 );

if ( class_exists( 'WP_REST_Request' ) ) {
	return;
}

class WP_REST_Request implements ArrayAccess {
	private $params = array();
	private $headers = array();
	private $body = '';
	private $route = '';

	public function __construct( string $method = 'GET', string $route = '' ) {
		$this->route = $route;
	}

	public function offsetExists( $offset ): bool {
		return isset( $this->params[ $offset ] );
	}

	#[\ReturnTypeWillChange]
	public function offsetGet( $offset ) {
		return $this->params[ $offset ] ?? null;
	}

	public function offsetSet( $offset, $value ): void {
		$this->params[ $offset ] = $value;
	}

	public function offsetUnset( $offset ): void {
		unset( $this->params[ $offset ] );
	}

	public function set_param( string $key, $value ): void {
		$this->params[ $key ] = $value;
	}

	public function get_param( string $key ) {
		return $this->params[ $key ] ?? null;
	}

	public function set_header( string $key, string $value ): void {
		$this->headers[ strtolower( $key ) ] = $value;
	}

	public function get_header( string $key ): string {
		$key = strtolower( $key );
		return $this->headers[ $key ] ?? '';
	}

	public function set_body( string $body ): void {
		$this->body = $body;
	}

	public function get_body(): string {
		return $this->body;
	}

	public function get_json_params(): array {
		$params = json_decode( $this->body, true );
		return is_array( $params ) ? $params : array();
	}

	public function get_body_params(): array {
		return array();
	}

	public function get_route(): string {
		return $this->route;
	}
}
