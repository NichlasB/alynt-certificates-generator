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

$settings = \get_option( 'alynt_certificate_generator_settings', array() );
$delete_cpt_posts = ! empty( $settings['delete_data_on_uninstall'] );
$delete_files     = ! empty( $settings['delete_files_on_uninstall'] );

\delete_option( 'alynt_certificate_generator_settings' );
\delete_option( 'alynt_certificate_generator_db_version' );

global $wpdb;

$certificate_table = $wpdb->prefix . 'acg_certificate_log';
$webhook_table     = $wpdb->prefix . 'acg_webhook_log';

// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table names are trusted.
$wpdb->query( "DROP TABLE IF EXISTS {$certificate_table}" );
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table names are trusted.
$wpdb->query( "DROP TABLE IF EXISTS {$webhook_table}" );

if ( $delete_cpt_posts ) {
	$post_ids = \get_posts(
		array(
			'post_type'      => array( 'acg_certificate_template', 'acg_email_template' ),
			'post_status'    => 'any',
			'numberposts'    => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'cache_results'  => false,
			'suppress_filters' => true,
		)
	);

	foreach ( $post_ids as $post_id ) {
		\wp_delete_post( (int) $post_id, true );
	}
}

if ( $delete_files ) {
	$upload_dir = \wp_get_upload_dir();
	if ( ! empty( $upload_dir['basedir'] ) ) {
		$certificate_dir = trailingslashit( $upload_dir['basedir'] ) . 'alynt-certificates';
		if ( is_dir( $certificate_dir ) ) {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $certificate_dir, \FilesystemIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ( $iterator as $file ) {
				if ( $file->isDir() ) {
					rmdir( $file->getPathname() );
					continue;
				}

				unlink( $file->getPathname() );
			}

			rmdir( $certificate_dir );
		}
	}
}
