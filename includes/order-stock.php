<?php
/**
 * Lightweight helpers loaded on every request: who changed a product, and stock changes made by orders.
 *
 * @package ExpertPriceManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Display name of a user (empty when unknown or deleted).
 *
 * @param int $user_id User ID.
 * @return string
 */
function epm_user_label( $user_id ) {
	static $cache = array();
	$user_id      = (int) $user_id;
	if ( $user_id <= 0 ) {
		return '';
	}
	if ( ! isset( $cache[ $user_id ] ) ) {
		$user              = get_userdata( $user_id );
		$cache[ $user_id ] = $user ? $user->display_name : '';
	}
	return $cache[ $user_id ];
}

/**
 * Who made the last change: a user display name, or "a customer order" when stock was changed by an order.
 *
 * @param int $product_id Product ID.
 * @return string
 */
function epm_updated_by_label( $product_id ) {
	if ( get_post_meta( $product_id, '_epm_update_source', true ) === 'order' ) {
		return __( 'an order', 'expert-price-manager' );
	}
	return epm_user_label( (int) get_post_meta( $product_id, '_epm_price_updated_by', true ) );
}

/**
 * Stock changed outside this panel (an order, a refund, a cancellation...): record the time so the
 * "last updated" line stays accurate. Variations also stamp their parent product.
 *
 * @param WC_Product $product Product or variation whose stock changed.
 */
function epm_mark_stock_changed_by_order( $product ) {
	if ( ! $product ) {
		return;
	}
	epm_log_order_stock_change( $product );
	$ids = array( $product->get_id() );
	if ( $product->is_type( 'variation' ) && $product->get_parent_id() ) {
		$ids[] = $product->get_parent_id();
	}
	$now = epm_current_jalali_datetime();
	foreach ( $ids as $id ) {
		update_post_meta( $id, '_price_update_date', $now );
		update_post_meta( $id, '_epm_price_updated_by', 0 );
		update_post_meta( $id, '_epm_update_source', 'order' );
		epm_sync_theme_last_modified( $id );
	}
}
add_action( 'woocommerce_product_set_stock', 'epm_mark_stock_changed_by_order' );
add_action( 'woocommerce_variation_set_stock', 'epm_mark_stock_changed_by_order' );
