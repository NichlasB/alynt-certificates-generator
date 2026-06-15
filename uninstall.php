<?php
/**
 * Cleanup on uninstall.
 *
 * @package AlyntCertificateGenerator
 */

declare( strict_types=1 );

namespace Alynt\CertificateGenerator;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Recursively remove a plugin-owned upload directory.
 *
 * @param string $directory Absolute directory path.
 */
function alynt_certificate_generator_delete_upload_directory( string $directory ): void {
	if ( ! is_dir( $directory ) ) {
		return;
	}

	$iterator = new \RecursiveIteratorIterator(
		new \RecursiveDirectoryIterator( $directory, \FilesystemIterator::SKIP_DOTS ),
		\RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $iterator as $file ) {
		if ( $file->isDir() ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Recursive directory cleanup during uninstall.
			rmdir( $file->getPathname() );
			continue;
		}

		\wp_delete_file( $file->getPathname() );
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Recursive directory cleanup during uninstall.
	rmdir( $directory );
}

$settings         = \get_option( 'alynt_certificate_generator_settings', array() );
$delete_cpt_posts = ! empty( $settings['delete_data_on_uninstall'] );
$delete_files     = ! empty( $settings['delete_files_on_uninstall'] );

\delete_option( 'alynt_certificate_generator_settings' );
\delete_option( 'alynt_certificate_generator_db_version' );
\delete_option( 'acg_diagnostics_events' );
\delete_option( 'acg_custom_fonts' );
\delete_option( 'acg_cert_template_migrated' );

if ( \function_exists( 'as_unschedule_all_actions' ) ) {
	\as_unschedule_all_actions( '', null, 'alynt_certificate_generator' );
}

\wp_clear_scheduled_hook( 'alynt_certificate_generator_cleanup_logs' );
\wp_clear_scheduled_hook( 'alynt_certificate_generator_cleanup_diagnostics' );
\wp_clear_scheduled_hook( 'alynt_certificate_generator_send_webhook' );
\wp_clear_scheduled_hook( 'alynt_certificate_generator_bulk_generate' );
\delete_transient( 'acg_webhook_failure_notice' );

global $wpdb;

$certificate_table = esc_sql( $wpdb->prefix . 'acg_certificate_log' );
$webhook_table     = esc_sql( $wpdb->prefix . 'acg_webhook_log' );
$options_table     = esc_sql( $wpdb->options );

$transient_patterns = array(
	'_transient_acg_bulk_%',
	'_transient_timeout_acg_bulk_%',
	'_transient_acg_webhook_rate_%',
	'_transient_timeout_acg_webhook_rate_%',
	'_transient_acg_template_error_%',
	'_transient_timeout_acg_template_error_%',
);

$post_meta_keys = array(
	'acg_template_image_id',
	'acg_template_orientation',
	'acg_template_variables',
	'acg_template_permissions',
	'acg_template_webhook_settings',
	'acg_template_fonts',
	'acg_email_enabled',
	'acg_email_to',
	'acg_email_subject',
	'acg_email_body',
	'acg_email_attach_pdf',
	'acg_email_template_id',
);

foreach ( $transient_patterns as $pattern ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional transient cleanup on uninstall; table name is escaped above.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$options_table} WHERE option_name LIKE %s", $pattern ) );
}

// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional custom-table cleanup on uninstall; table name is escaped above.
$wpdb->query( "DROP TABLE IF EXISTS {$certificate_table}" );
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional custom-table cleanup on uninstall; table name is escaped above.
$wpdb->query( "DROP TABLE IF EXISTS {$webhook_table}" );
if ( $delete_cpt_posts ) {
	$post_ids = \get_posts(
		array(
			'post_type'        => array( 'acg_cert_template', 'acg_certificate_template', 'acg_email_template' ),
			'post_status'      => 'any',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'cache_results'    => false,
			'suppress_filters' => true,
		)
	);

	foreach ( $post_ids as $template_post_id ) {
		\wp_delete_post( (int) $template_post_id, true );
	}

	foreach ( $post_meta_keys as $post_meta_key ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Intentional plugin post-meta cleanup on uninstall.
		$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $post_meta_key ), array( '%s' ) );
	}
}

if ( $delete_files ) {
	$upload_dir = \wp_get_upload_dir();
	if ( ! empty( $upload_dir['basedir'] ) ) {
		$plugin_upload_directories = array(
			'alynt-certificates',
			'alynt-certificate-fonts',
		);

		foreach ( $plugin_upload_directories as $plugin_upload_directory ) {
			alynt_certificate_generator_delete_upload_directory(
				trailingslashit( $upload_dir['basedir'] ) . $plugin_upload_directory
			);
		}
	}
}
