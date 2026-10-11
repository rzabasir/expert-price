<?php
/**
 * Toolbar shortcut and icon (loaded only for users who can use the plugin).
 *
 * @package ExpertPriceManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menu / toolbar icon: the Hilawin mark as an inline SVG (recoloured by WordPress to match the admin colour scheme).
 *
 * @return string Data URI.
 */
function epm_menu_icon() {
	return 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMCAyMCI+PHBhdGggZmlsbD0iI2E3YWFhZCIgZmlsbC1ydWxlPSJldmVub2RkIiBkPSJNOC4xNiA1LjQwIEw4LjQ2IDUuOTIgTDkuMDYgNi43MyBMOS4xNCA2LjY5IEwxMC4zMCA2LjY5IEwxMC45NCA2LjgxIEwxMS44OCA3LjI5IEwxMi4zOSA3Ljg0IEwxMi43NCA4LjQ4IEwxMi45OSA5LjUxIEwxMi45OSAxMC45NiBMMTIuNzggMTIuMDcgTDEyLjUyIDEyLjU5IEwxMi4xOCAxMi44OSBMMTEuNzEgMTMuMDYgTDEwLjgxIDEzLjIzIEwxMC4zOCAxMy4yMyBMMTAuMzQgMTMuMjcgTDkuMTQgMTMuMjMgTDguMjUgMTMuMDYgTDcuNjUgMTIuODAgTDcuMzEgMTIuNDIgTDcuMTggMTIuMDcgTDYuOTYgMTEuMDkgTDYuOTYgMTAuMTEgTDcuMDkgOS42NCBMNy4yNiA5LjI5IEw3LjUyIDkuMDAgTDcuNzggOC44MiBMOC4wMyA4Ljc0IEw4LjcyIDguNzggTDkuMTQgOS4xNyBMOS4yNyA5LjM4IEw5LjQ5IDkuOTggTDkuNjIgMTAuNjYgTDkuNjYgMTIuMjQgTDEwLjM4IDExLjc3IEwxMC44NiAxMS4zMCBMMTAuOTQgMTEuMTMgTDEwLjkwIDEwLjI4IEwxMC43MyA5LjUxIEwxMC40NyA4LjgyIEwxMC4xMyA4LjI3IEw5LjcwIDcuODQgTDkuMTkgNy41NCBMOC43MiA3LjQxIEw3LjkwIDcuNDEgTDcuMzEgNy41OCBMNi42NyA3Ljk3IEw2LjE5IDguNDggTDUuODEgOS4yNSBMNS42NCAxMC4wMiBMNS42NCAxMS4wOSBMNS44NSAxMi4yNCBMNi4xNSAxMy4wNiBMNi40NSAxMy40OCBMNy4wNSAxNC4wMCBMNy43OCAxNC4zMCBMOC43NiAxNC41MSBMOS42MiAxNC41NSBMOS42NiAxNC42MCBMMTAuODYgMTQuNTUgTDEyLjE4IDE0LjMwIEwxMi44MiAxNC4wNCBMMTMuMjUgMTMuNzQgTDEzLjgxIDEzLjA2IEwxNC4wNiAxMi4zNyBMMTQuMjggMTEuMzUgTDE0LjMyIDkuNDIgTDE0LjE1IDguNTIgTDEzLjcyIDcuNDYgTDEzLjQyIDYuOTkgTDEyLjg2IDYuMzkgTDEyLjQ0IDYuMDkgTDExLjcxIDUuNzUgTDEwLjk4IDUuNTMgTDkuODcgNS4zNlpNOS4zNiAxLjAwIEw4LjcyIDEuMTMgTDcuOTAgMS40MyBMNC4wMSAzLjY1IEwzLjMzIDQuMTIgTDIuNjUgNC44MSBMMi4zOSA1LjE1IEwxLjgzIDYuMjIgTDEuNTggNy4yNCBMMS41MyA3LjY3IEwxLjUzIDEyLjI5IEwxLjcxIDEzLjMxIEwyLjA5IDE0LjMwIEwyLjc3IDE1LjI4IEwzLjQyIDE1Ljg4IEw0LjI3IDE2LjQzIEw3Ljk1IDE4LjUzIEw4Ljk3IDE4Ljg3IEw5LjUzIDE4Ljk2IEwxMC40MyAxOC45NiBMMTEuMzMgMTguNzkgTDEyLjQ0IDE4LjMyIEwxNi40NiAxNS45NiBMMTcuNDggMTQuOTQgTDE4LjA4IDEzLjg3IEwxOC4yOSAxMy4yMyBMMTguNDIgMTIuNTAgTDE4LjQyIDcuNDYgTDE4LjI1IDYuNTYgTDE3LjgyIDUuNTMgTDE3LjE0IDQuNTkgTDE2LjI0IDMuODIgTDExLjg4IDEuMzQgTDExLjI0IDEuMTMgTDEwLjYwIDEuMDBaTTkuNTcgMi40MSBMMTAuMzggMi40MSBMMTEuMjQgMi42MiBMMTUuNTIgNS4wNiBMMTYuMjQgNS43NSBMMTYuNTQgNi4xNyBMMTYuOTMgNy4xMSBMMTcuMDEgNy43MSBMMTcuMDEgMTIuMjQgTDE2LjgwIDEzLjIzIEwxNi4zMyAxNC4wOCBMMTUuODEgMTQuNjQgTDE1LjM0IDE0Ljk4IEwxMS41OCAxNy4xNiBMMTAuOTQgMTcuNDIgTDEwLjE3IDE3LjU1IEw5LjQ0IDE3LjUwIEw4LjY3IDE3LjI5IEw0LjMxIDE0Ljc3IEwzLjk3IDE0LjQ3IEwzLjQyIDEzLjc0IEwzLjE2IDEzLjE5IEwyLjk5IDEyLjUwIEwyLjk5IDcuNDYgTDMuMjAgNi42NCBMMy40NiA2LjEzIEwzLjgwIDUuNjYgTDQuMTkgNS4yOCBMNC42NiA0LjkzIEw4LjI5IDIuODQgTDguODUgMi41OFoiLz48L3N2Zz4=';
}

add_action( 'admin_bar_menu', 'epm_admin_bar_menu', 80 );
/**
 * Quick access from the top toolbar (admin area and front end).
 *
 * @param WP_Admin_Bar $bar Admin bar instance.
 */
function epm_admin_bar_menu( $bar ) {
	if ( ! epm_can_access_price_tab() ) {
		return;
	}
	$icon = '<span class="epm-ab-icon" aria-hidden="true"></span>';
	$bar->add_node(
		array(
			'id'    => 'epm-quick',
			'title' => $icon . esc_html__( 'Price Manager', 'expert-price-manager' ),
			'href'  => admin_url( 'admin.php?page=epm-price-products' ),
		)
	);
	$bar->add_node(
		array(
			'id'     => 'epm-quick-pricing',
			'parent' => 'epm-quick',
			'title'  => esc_html__( 'Product Pricing', 'expert-price-manager' ),
			'href'   => admin_url( 'admin.php?page=epm-price-products' ),
		)
	);
	$bar->add_node(
		array(
			'id'     => 'epm-quick-changes',
			'parent' => 'epm-quick',
			'title'  => esc_html__( 'Changes dashboard', 'expert-price-manager' ),
			'href'   => admin_url( 'admin.php?page=epm-changes' ),
		)
	);
}

add_action( 'admin_enqueue_scripts', 'epm_admin_bar_icon_css', 30 );
add_action( 'wp_enqueue_scripts', 'epm_admin_bar_icon_css', 30 );
/** Fixed-size icon for the toolbar item (a background image cannot be stretched by theme CSS). */
function epm_admin_bar_icon_css() {
	if ( ! is_admin_bar_showing() || ! epm_can_access_price_tab() ) {
		return;
	}
	$css = '#wpadminbar #wp-admin-bar-epm-quick > .ab-item{display:flex !important;align-items:center;gap:6px}'
		. '#wpadminbar .epm-ab-icon{display:inline-block !important;flex:none;width:18px !important;height:18px !important;float:none !important;margin:0 !important;background:url("' . epm_menu_icon() . '") center/contain no-repeat !important}';
	wp_add_inline_style( 'admin-bar', $css );
}
