<?php
/**
 * Database schema manager.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Manages custom table schemas and database lifecycle cleanup.
 */
class Alynt_Certificate_Generator_Database {
	/**
	 * Cron hook for database log cleanup.
	 *
	 * @var string
	 */
	public const CLEANUP_HOOK = 'alynt_certificate_generator_cleanup_logs';

	/**
	 * Current database schema version.
	 *
	 * @var string
	 */
	private const SCHEMA_VERSION = '1.1.0';

	/**
	 * Run schema migrations if needed.
	 */
	public static function maybe_migrate(): void {
		$installed_version = (string) \get_option( ALYNT_CERTIFICATE_GENERATOR_DB_VERSION_OPTION, '' );

		if ( self::SCHEMA_VERSION !== $installed_version ) {
			self::migrate();
		}
	}

	/**
	 * Create or update the plugin tables.
	 */
	public static function migrate(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate   = $wpdb->get_charset_collate();
		$certificate_table = self::get_certificate_log_table();
		$webhook_table     = self::get_webhook_log_table();

		$certificate_sql = "CREATE TABLE {$certificate_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			certificate_id varchar(100) NOT NULL,
			template_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NULL,
			generated_by varchar(50) NOT NULL DEFAULT '',
			ip_address varchar(45) NULL,
			method varchar(20) NOT NULL,
			variables_json longtext NOT NULL,
			pdf_path text NOT NULL,
			download_token varchar(64) NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NULL,
			email_status_json longtext NULL,
			webhook_status varchar(20) NOT NULL DEFAULT 'pending',
			PRIMARY KEY  (id),
			UNIQUE KEY certificate_id (certificate_id),
			KEY template_id (template_id),
			KEY user_id (user_id),
			KEY created_at (created_at),
			KEY download_token (download_token),
			KEY user_created (user_id, created_at),
			KEY template_status_created (template_id, webhook_status, created_at),
			KEY status_created (webhook_status, created_at)
		) {$charset_collate};";

		$webhook_sql = "CREATE TABLE {$webhook_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			direction varchar(20) NOT NULL,
			template_id bigint(20) unsigned NULL,
			certificate_log_id bigint(20) unsigned NULL,
			url text NULL,
			payload_json longtext NULL,
			response_code int(11) NULL,
			success tinyint(1) NOT NULL DEFAULT 0,
			error_message text NULL,
			attempt_number int(11) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			ip_address varchar(45) NULL,
			PRIMARY KEY  (id),
			KEY direction (direction),
			KEY template_id (template_id),
			KEY certificate_log_id (certificate_log_id),
			KEY created_at (created_at),
			KEY cert_created (certificate_log_id, created_at)
		) {$charset_collate};";

		\dbDelta( $certificate_sql );
		\dbDelta( $webhook_sql );

		\update_option( ALYNT_CERTIFICATE_GENERATOR_DB_VERSION_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * Schedule recurring log cleanup if it is missing.
	 *
	 * @return void
	 */
	public function maybe_schedule_log_cleanup(): void {
		if ( ! \wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			\wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	/**
	 * Run the scheduled log cleanup callback.
	 *
	 * @return void
	 */
	public function run_log_cleanup(): void {
		self::cleanup_expired_logs();
	}

	/**
	 * Remove custom-table rows tied to a deleted certificate template.
	 *
	 * @param int      $post_id Deleted post ID.
	 * @param \WP_Post $post    Deleted post object.
	 * @return void
	 */
	public function cleanup_deleted_post( int $post_id, \WP_Post $post ): void {
		if ( 'acg_cert_template' !== $post->post_type && 'acg_certificate_template' !== $post->post_type ) {
			return;
		}

		self::delete_logs_for_template( $post_id );
	}

	/**
	 * Delete certificate and webhook logs older than the retention cutoff.
	 *
	 * @return void
	 */
	public static function cleanup_expired_logs(): void {
		global $wpdb;

		$settings       = \get_option( ALYNT_CERTIFICATE_GENERATOR_SETTINGS_OPTION, array() );
		$retention_days = isset( $settings['log_retention_days'] ) ? absint( $settings['log_retention_days'] ) : 365;
		if ( $retention_days < 1 ) {
			$retention_days = 365;
		}

		$cutoff            = gmdate( 'Y-m-d H:i:s', time() - ( $retention_days * DAY_IN_SECONDS ) );
		$certificate_table = esc_sql( self::get_certificate_log_table() );
		$webhook_table     = esc_sql( self::get_webhook_log_table() );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table names are escaped above; dynamic values are prepared.
		$pdf_paths = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT pdf_path FROM {$certificate_table} WHERE created_at < %s LIMIT 1000",
				$cutoff
			)
		);
		self::delete_pdf_files( is_array( $pdf_paths ) ? $pdf_paths : array() );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$webhook_table} WHERE created_at < %s OR certificate_log_id IN (SELECT id FROM {$certificate_table} WHERE created_at < %s) LIMIT 1000",
				$cutoff,
				$cutoff
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$certificate_table} WHERE created_at < %s LIMIT 1000",
				$cutoff
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Delete webhook log rows for a certificate log.
	 *
	 * @param int $certificate_log_id Certificate log ID.
	 * @return void
	 */
	public static function delete_webhook_logs_for_certificate( int $certificate_log_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional custom-table cleanup before deleting the parent certificate log.
		$wpdb->delete(
			self::get_webhook_log_table(),
			array( 'certificate_log_id' => $certificate_log_id ),
			array( '%d' )
		);
	}

	/**
	 * Delete certificate and webhook logs tied to a certificate template.
	 *
	 * @param int $template_id Template post ID.
	 * @return void
	 */
	public static function delete_logs_for_template( int $template_id ): void {
		global $wpdb;

		$certificate_table = esc_sql( self::get_certificate_log_table() );
		$webhook_table     = esc_sql( self::get_webhook_log_table() );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table names are escaped above; dynamic values are prepared.
		$pdf_paths = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT pdf_path FROM {$certificate_table} WHERE template_id = %d",
				$template_id
			)
		);
		self::delete_pdf_files( is_array( $pdf_paths ) ? $pdf_paths : array() );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$webhook_table} WHERE template_id = %d OR certificate_log_id IN (SELECT id FROM {$certificate_table} WHERE template_id = %d)",
				$template_id,
				$template_id
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$certificate_table} WHERE template_id = %d",
				$template_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Get the certificate log table name.
	 *
	 * @return string
	 */
	public static function get_certificate_log_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'acg_certificate_log';
	}

	/**
	 * Get the webhook log table name.
	 *
	 * @return string
	 */
	public static function get_webhook_log_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'acg_webhook_log';
	}

	/**
	 * Delete generated PDF files recorded in log rows.
	 *
	 * @param array<int, string> $paths PDF paths.
	 * @return void
	 */
	private static function delete_pdf_files( array $paths ): void {
		$upload_dir = \wp_get_upload_dir();
		if ( empty( $upload_dir['basedir'] ) ) {
			return;
		}

		$uploads_base = wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) );
		foreach ( $paths as $path ) {
			$path = (string) $path;
			if ( '' === $path ) {
				continue;
			}

			$normalized_path = wp_normalize_path( $path );
			if ( 0 !== strpos( $normalized_path, $uploads_base ) || ! file_exists( $normalized_path ) ) {
				continue;
			}

			\wp_delete_file( $normalized_path );
		}
	}
}
