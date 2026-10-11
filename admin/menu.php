<?php
/**
 * Admin menu. The screens themselves (markup, assets, i18n lists, export helpers) are loaded lazily,
 * only when one of the plugin's own pages is opened.
 *
 * @package ExpertPriceManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'epm_register_admin_menu', 99 );
/** Top-level menu and the dashboard submenu. */
function epm_register_admin_menu() {
	if ( ! epm_can_access_price_tab() ) {
		return;
	}
	$hook = add_menu_page( __( 'Product Pricing', 'expert-price-manager' ), __( 'Price Manager', 'expert-price-manager' ), 'manage_woocommerce', 'epm-price-products', 'epm_render_admin_pricing_page', epm_menu_icon(), 3 );
	add_submenu_page( 'epm-price-products', __( 'Product Pricing', 'expert-price-manager' ), __( 'Product Pricing', 'expert-price-manager' ), 'manage_woocommerce', 'epm-price-products', 'epm_render_admin_pricing_page' );
	if ( $hook ) {
		add_action( 'load-' . $hook, 'epm_load_pricing_screen' );
	}
	$changes_hook = add_submenu_page( 'epm-price-products', __( 'Product Changes', 'expert-price-manager' ), __( 'Changes dashboard', 'expert-price-manager' ), 'manage_woocommerce', 'epm-changes', 'epm_render_changes_page' );
	if ( $changes_hook ) {
		add_action( 'load-' . $changes_hook, 'epm_load_changes_screen' );
	}
}

/** Load the pricing screen code (runs before assets are enqueued and before the page renders). */
function epm_load_pricing_screen() {
	require_once EPM_PLUGIN_DIR . 'admin/pricing-page.php';
}

/** Load the changes dashboard code. */
function epm_load_changes_screen() {
	require_once EPM_PLUGIN_DIR . 'admin/changes-page.php';
}
