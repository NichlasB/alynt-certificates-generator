<?php
/**
 * Database schema manager.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator\Database;

class Alynt_Certificate_Generator_Database {
	/**
	 * Current database schema version.
	 *
	 * @var string
	 */
	private const SCHEMA_VERSION = '1.0.0';

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

		$charset_collate  = $wpdb->get_charset_collate();
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
			email_status_json longtext NULL,
			webhook_status varchar(20) NOT NULL DEFAULT 'pending',
			PRIMARY KEY  (id),
			UNIQUE KEY certificate_id (certificate_id),
			KEY template_id (template_id),
			KEY user_id (user_id),
			KEY created_at (created_at),
			KEY download_token (download_token)
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
			KEY created_at (created_at)
		) {$charset_collate};";

		\dbDelta( $certificate_sql );
		\dbDelta( $webhook_sql );

		\update_option( ALYNT_CERTIFICATE_GENERATOR_DB_VERSION_OPTION, self::SCHEMA_VERSION );
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
}
