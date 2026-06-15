<?php
/**
 * Structured diagnostics logger.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Stores bounded, redacted diagnostic events for admin support.
 */
class Alynt_Certificate_Generator_Diagnostics_Logger {
	public const OPTION_KEY   = 'acg_diagnostics_events';
	public const CLEANUP_HOOK = 'alynt_certificate_generator_cleanup_diagnostics';

	private const DEFAULT_RETENTION_DAYS = 14;
	private const DEFAULT_MAX_EVENTS     = 200;
	private const MAX_CONTEXT_LENGTH     = 500;

	/**
	 * Supported severity levels ordered from least to most severe.
	 *
	 * @var array<string, int>
	 */
	private static $level_order = array(
		'debug'    => 10,
		'info'     => 20,
		'warning'  => 30,
		'error'    => 40,
		'critical' => 50,
	);

	/**
	 * Store a diagnostic event if diagnostics are enabled.
	 *
	 * @param string $level    Severity level.
	 * @param string $category Event category.
	 * @param string $code     Short event key.
	 * @param string $message  Summary message.
	 * @param array  $context  Event context.
	 */
	public static function log( string $level, string $category, string $code, string $message, array $context = array() ): void {
		$settings = self::get_settings();
		if ( empty( $settings['diagnostics_enabled'] ) ) {
			return;
		}

		$level = self::normalize_level( $level );
		if ( ! self::meets_threshold( $level, (string) ( $settings['diagnostics_min_level'] ?? 'warning' ) ) ) {
			return;
		}

		$events   = self::get_events();
		$events[] = array(
			'timestamp'  => gmdate( 'c' ),
			'level'      => $level,
			'category'   => sanitize_key( $category ),
			'code'       => sanitize_key( $code ),
			'message'    => sanitize_text_field( $message ),
			'context'    => self::redact_context( $context ),
			'request_id' => self::get_request_id(),
		);

		$events = self::apply_retention( $events, $settings );
		\update_option( self::OPTION_KEY, $events, false );
	}

	/**
	 * Get stored diagnostic events.
	 *
	 * @param array<string, string> $filters Optional filters.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_events( array $filters = array() ): array {
		$events = \get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $events ) ) {
			return array();
		}

		$events = array_values( array_filter( $events, 'is_array' ) );
		if ( empty( $filters ) ) {
			return $events;
		}

		return array_values(
			array_filter(
				$events,
				static function ( array $event ) use ( $filters ): bool {
					if ( ! empty( $filters['level'] ) && ( $event['level'] ?? '' ) !== $filters['level'] ) {
						return false;
					}

					if ( ! empty( $filters['category'] ) && ( $event['category'] ?? '' ) !== $filters['category'] ) {
						return false;
					}

					return true;
				}
			)
		);
	}

	/**
	 * Delete all diagnostic events.
	 */
	public static function clear(): void {
		\delete_option( self::OPTION_KEY );
	}

	/**
	 * Schedule recurring diagnostics cleanup.
	 */
	public function maybe_schedule_cleanup(): void {
		if ( ! \wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			\wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	/**
	 * Run retention cleanup.
	 */
	public function run_cleanup(): void {
		$settings = self::get_settings();
		$events   = self::apply_retention( self::get_events(), $settings );

		if ( empty( $events ) ) {
			self::clear();
			return;
		}

		\update_option( self::OPTION_KEY, $events, false );
	}

	/**
	 * Get basic health data for the diagnostics UI.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_health(): array {
		$settings = self::get_settings();
		$events   = self::get_events();
		$last     = end( $events );

		return array(
			'plugin_version'      => ALYNT_CERTIFICATE_GENERATOR_VERSION,
			'wordpress_version'   => \get_bloginfo( 'version' ),
			'php_version'         => PHP_VERSION,
			'diagnostics_enabled' => ! empty( $settings['diagnostics_enabled'] ),
			'storage_backend'     => 'option_ring_buffer',
			'retention_days'      => self::get_retention_days( $settings ),
			'max_events'          => self::get_max_events( $settings ),
			'event_count'         => count( $events ),
			'last_event'          => is_array( $last ) ? (string) ( $last['timestamp'] ?? '' ) : '',
			'cleanup_scheduled'   => (bool) \wp_next_scheduled( self::CLEANUP_HOOK ),
		);
	}

	/**
	 * Get plugin settings with defaults.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_settings(): array {
		$settings = \get_option( ALYNT_CERTIFICATE_GENERATOR_SETTINGS_OPTION, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return array_merge(
			array(
				'diagnostics_enabled'        => false,
				'diagnostics_retention_days' => self::DEFAULT_RETENTION_DAYS,
				'diagnostics_min_level'      => 'warning',
				'diagnostics_max_events'     => self::DEFAULT_MAX_EVENTS,
			),
			$settings
		);
	}

	/**
	 * Apply event count and age retention.
	 *
	 * @param array<int, array<string, mixed>> $events Events.
	 * @param array<string, mixed>             $settings Settings.
	 * @return array<int, array<string, mixed>>
	 */
	private static function apply_retention( array $events, array $settings ): array {
		$retention_days = self::get_retention_days( $settings );
		$cutoff         = time() - ( $retention_days * DAY_IN_SECONDS );

		$events = array_values(
			array_filter(
				$events,
				static function ( array $event ) use ( $cutoff ): bool {
					$timestamp = isset( $event['timestamp'] ) ? strtotime( (string) $event['timestamp'] ) : false;
					return false !== $timestamp && $timestamp >= $cutoff;
				}
			)
		);

		$max_events = self::get_max_events( $settings );
		if ( count( $events ) > $max_events ) {
			$events = array_slice( $events, -1 * $max_events );
		}

		return $events;
	}

	/**
	 * Redact context values recursively.
	 *
	 * @param mixed $context Context value.
	 * @return mixed
	 */
	private static function redact_context( $context ) {
		if ( is_array( $context ) ) {
			$redacted = array();
			foreach ( $context as $key => $value ) {
				$key_string = is_string( $key ) ? $key : (string) $key;
				if ( self::is_sensitive_key( $key_string ) ) {
					$redacted[ $key_string ] = '[redacted]';
					continue;
				}

				$redacted[ $key_string ] = self::redact_context( $value );
			}

			return $redacted;
		}

		if ( is_string( $context ) ) {
			return strlen( $context ) > self::MAX_CONTEXT_LENGTH
				? substr( $context, 0, self::MAX_CONTEXT_LENGTH ) . '...'
				: $context;
		}

		if ( is_scalar( $context ) || null === $context ) {
			return $context;
		}

		return '[unsupported]';
	}

	/**
	 * Determine whether a context key is sensitive.
	 *
	 * @param string $key Context key.
	 */
	private static function is_sensitive_key( string $key ): bool {
		return (bool) preg_match(
			'/password|secret|api[_-]?key|token|authorization|cookie|nonce|signature|payload|body|variables|download/i',
			$key
		);
	}

	/**
	 * Normalize severity level.
	 *
	 * @param string $level Level.
	 */
	private static function normalize_level( string $level ): string {
		$level = sanitize_key( $level );
		return isset( self::$level_order[ $level ] ) ? $level : 'warning';
	}

	/**
	 * Check minimum severity threshold.
	 *
	 * @param string $level     Event level.
	 * @param string $threshold Minimum level.
	 */
	private static function meets_threshold( string $level, string $threshold ): bool {
		$threshold = self::normalize_level( $threshold );
		return self::$level_order[ $level ] >= self::$level_order[ $threshold ];
	}

	/**
	 * Get retention days.
	 *
	 * @param array<string, mixed> $settings Settings.
	 */
	private static function get_retention_days( array $settings ): int {
		return max( 1, (int) ( $settings['diagnostics_retention_days'] ?? self::DEFAULT_RETENTION_DAYS ) );
	}

	/**
	 * Get max event count.
	 *
	 * @param array<string, mixed> $settings Settings.
	 */
	private static function get_max_events( array $settings ): int {
		return max( 10, (int) ( $settings['diagnostics_max_events'] ?? self::DEFAULT_MAX_EVENTS ) );
	}

	/**
	 * Build a lightweight request identifier.
	 */
	private static function get_request_id(): string {
		if ( isset( $_SERVER['HTTP_X_REQUEST_ID'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REQUEST_ID'] ) );
		}

		return substr( wp_hash( microtime( true ) . wp_rand() ), 0, 12 );
	}
}
