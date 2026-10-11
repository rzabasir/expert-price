<?php
/**
 * Plugin Name:       Expert Price & Stock Manager
 * Description:       Fast table-based management of WooCommerce prices, sale prices, stock and SKUs, with custom XLSX export and Rank Math redirects when deleting products.
 * Version:           1.1.14
 * Requires at least: 6.0
 * Tested up to:      7.1
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0
 * WC tested up to:   11.0
 * Author:            Hilawin
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       expert-price-manager
 * Domain Path:       /languages
 *
 * Data persistence: this plugin has no custom tables or options for prices/stock.
 * Every value (regular price, sale price, stock, SKU) is saved directly on the
 * WooCommerce product (standard postmeta such as _regular_price/_sale_price/_stock).
 * Deactivating, deleting or updating the plugin therefore never changes the prices or
 * stock already saved from this panel. Redirects created in Rank Math are stored by
 * Rank Math itself and also stay after this plugin is removed.
 *
 * @package ExpertPriceManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EPM_VERSION', '1.1.14' );
define( 'EPM_PLUGIN_FILE', __FILE__ );
define( 'EPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load the translation files bundled in /languages (e.g. the Persian translation).
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'expert-price-manager', false, dirname( plugin_basename( EPM_PLUGIN_FILE ) ) . '/languages' );
	}
);

/*
 * Lazy loading: only a few small files run on every request. Everything else is loaded when needed -
 * the admin bar file for users who can use the plugin, AJAX handlers for the matching AJAX action,
 * and each screen's code when that screen opens.
 */
require_once EPM_PLUGIN_DIR . 'includes/core.php';
require_once EPM_PLUGIN_DIR . 'includes/jalali.php';
require_once EPM_PLUGIN_DIR . 'includes/changelog.php';
require_once EPM_PLUGIN_DIR . 'includes/order-stock.php';

register_activation_hook( EPM_PLUGIN_FILE, 'epm_activate' );
register_deactivation_hook( EPM_PLUGIN_FILE, 'epm_deactivate' );

add_action(
	'init',
	function () {
		if ( ! wp_doing_ajax() && ! wp_doing_cron() && is_user_logged_in() && current_user_can( 'manage_woocommerce' ) ) {
			require_once EPM_PLUGIN_DIR . 'includes/admin-bar.php';
		}
	},
	5
);

if ( is_admin() ) {
	if ( wp_doing_ajax() ) {
		$epm_action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks which file to load.
		if ( 0 === strpos( $epm_action, 'epm_' ) ) {
			if ( 0 === strpos( $epm_action, 'epm_export_' ) ) {
				require_once EPM_PLUGIN_DIR . 'ajax/admin-export.php';
			} elseif ( 0 === strpos( $epm_action, 'epm_log_' ) ) {
				require_once EPM_PLUGIN_DIR . 'ajax/admin-changelog.php';
			} elseif ( 0 === strpos( $epm_action, 'epm_admin_delete_' ) ) {
				require_once EPM_PLUGIN_DIR . 'ajax/admin-delete.php';
			} else {
				require_once EPM_PLUGIN_DIR . 'ajax/admin-pricing.php';
			}
		}
		unset( $epm_action );
	} else {
		require_once EPM_PLUGIN_DIR . 'admin/menu.php';
	}
}
