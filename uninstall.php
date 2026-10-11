<?php
/**
 * Uninstall routine.
 *
 * Removes only data created by this plugin itself: the saved export templates of users,
 * temporary export files and the change history table. Product prices, stock and Rank Math redirects are NOT touched.
 *
 * @package ExpertPriceManager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_metadata( 'user', 0, 'epm_export_templates', '', true );

// The change history table and its scheduled purge belong to this plugin.
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}epm_changes" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange
delete_option( 'epm_db_version' );
delete_post_meta_by_key( '_epm_last_stock' );
delete_post_meta_by_key( '_epm_update_source' );
wp_clear_scheduled_hook( 'epm_purge_changes' );

$epm_upload = wp_upload_dir();
$epm_dir    = trailingslashit( $epm_upload['basedir'] ) . 'epm-exports';
if ( is_dir( $epm_dir ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	if ( WP_Filesystem() ) {
		global $wp_filesystem;
		$wp_filesystem->delete( $epm_dir, true );
	}
}
