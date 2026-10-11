<?php
/**
 * Change history: who changed what and when.
 *
 * Rows are kept for EPM_LOG_RETENTION_DAYS days and then purged automatically (daily WP-Cron).
 *
 * @package ExpertPriceManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const EPM_LOG_RETENTION_DAYS = 32;
const EPM_LOG_WARN_DAYS      = 30;
const EPM_DB_VERSION         = '1';

/** Name of the change-log table. */
function epm_log_table() {
	global $wpdb;
	return $wpdb->prefix . 'epm_changes';
}

/** Create or update the table (safe to call repeatedly). */
function epm_install_changelog() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table   = epm_log_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		created_at int(10) unsigned NOT NULL,
		product_id bigint(20) unsigned NOT NULL,
		parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		source varchar(10) NOT NULL DEFAULT 'user',
		field varchar(20) NOT NULL,
		old_value varchar(100) DEFAULT NULL,
		new_value varchar(100) DEFAULT NULL,
		product_name varchar(200) NOT NULL DEFAULT '',
		sku varchar(100) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		KEY created_at (created_at),
		KEY user_created (user_id,created_at),
		KEY product_id (product_id)
	) {$charset};"
	);
	update_option( 'epm_db_version', EPM_DB_VERSION, false );
}

/** Plugin activation. */
function epm_activate() {
	epm_install_changelog();
	epm_schedule_purge();
}

/** Plugin deactivation. */
function epm_deactivate() {
	wp_clear_scheduled_hook( 'epm_purge_changes' );
}

/** Make sure the table exists after an update and the purge event is scheduled. */
function epm_maybe_upgrade() {
	if ( get_option( 'epm_db_version' ) !== EPM_DB_VERSION ) {
		epm_install_changelog();
	}
	epm_schedule_purge();
}
add_action( 'admin_init', 'epm_maybe_upgrade' );

/** Schedule the daily purge. */
function epm_schedule_purge() {
	if ( ! wp_next_scheduled( 'epm_purge_changes' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'epm_purge_changes' );
	}
}

/** Delete history older than the retention period (in small chunks, index-based). */
function epm_purge_old_changes() {
	global $wpdb;
	$table  = epm_log_table();
	$cutoff = time() - EPM_LOG_RETENTION_DAYS * DAY_IN_SECONDS;
	for ( $i = 0; $i < 40; $i++ ) {
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %d LIMIT 5000", $cutoff ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- own table, name from $wpdb->prefix.
		if ( ! $deleted ) {
			break;
		}
	}
}
add_action( 'epm_purge_changes', 'epm_purge_old_changes' );

/** Field groups used by the dashboard filters. */
function epm_log_field_groups() {
	return array(
		'price' => array( 'regular_price', 'sale_price' ),
		'stock' => array( 'manage_stock', 'stock_quantity', 'stock_status' ),
		'other' => array( 'sku', 'sold_individually' ),
	);
}

/**
 * Normalised snapshot of the values we track.
 *
 * @param WC_Product $product Product or variation.
 * @return array<string,string>
 */
function epm_log_snapshot( $product ) {
	$qty = $product->get_manage_stock() ? $product->get_stock_quantity() : '';
	return array(
		'sku'               => (string) $product->get_sku(),
		'regular_price'     => (string) $product->get_regular_price(),
		'sale_price'        => (string) $product->get_sale_price(),
		'manage_stock'      => $product->get_manage_stock() ? '1' : '0',
		'stock_quantity'    => null === $qty || '' === $qty ? '' : (string) wc_stock_amount( $qty ),
		'stock_status'      => (string) $product->get_stock_status(),
		'sold_individually' => $product->get_sold_individually() ? '1' : '0',
	);
}

/**
 * Store one row for every field that differs between two snapshots.
 *
 * @param WC_Product $product Product or variation (after the change).
 * @param array      $before  Snapshot before.
 * @param array      $after   Snapshot after.
 * @param string     $source  user|order|revert.
 * @param int        $user_id User ID (0 for orders).
 */
function epm_log_diff( $product, array $before, array $after, $source = 'user', $user_id = 0 ) {
	global $wpdb;
	$name = $product->get_name();
	if ( $product->is_type( 'variation' ) ) {
		$name = wp_strip_all_tags( wc_get_formatted_variation( $product, true, false ) );
		$name = $name ? get_the_title( $product->get_parent_id() ) . ' · ' . $name : get_the_title( $product->get_parent_id() );
	}
	$now = time();
	foreach ( $after as $field => $new ) {
		$old = $before[ $field ] ?? null;
		if ( $old === $new ) {
			continue;
		}
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- own table.
			epm_log_table(),
			array(
				'created_at'   => $now,
				'product_id'   => $product->get_id(),
				'parent_id'    => $product->get_parent_id(),
				'user_id'      => (int) $user_id,
				'source'       => $source,
				'field'        => $field,
				'old_value'    => null === $old ? null : mb_substr( (string) $old, 0, 100 ),
				'new_value'    => mb_substr( (string) $new, 0, 100 ),
				'product_name' => mb_substr( $name, 0, 200 ),
				'sku'          => mb_substr( (string) $product->get_sku(), 0, 100 ),
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}
	update_post_meta( $product->get_id(), '_epm_last_stock', $after['stock_quantity'] );
}

/**
 * Stock changed by WooCommerce itself (order placed, cancelled, refunded...): log the quantity change.
 *
 * @param WC_Product $product Product or variation after the change.
 */
function epm_log_order_stock_change( $product ) {
	if ( ! $product || ! $product->get_manage_stock() ) {
		return;
	}
	$new  = (string) wc_stock_amount( $product->get_stock_quantity() );
	$last = get_post_meta( $product->get_id(), '_epm_last_stock', true );
	$old  = '' === $last ? null : (string) $last;
	if ( $old === $new ) {
		return;
	}
	// Only the quantity is recorded for orders.
	epm_log_diff( $product, array( 'stock_quantity' => $old ), array( 'stock_quantity' => $new ), 'order', 0 );
}
