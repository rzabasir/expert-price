<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
 * Base settings - this plugin is only available to Shop Managers
 * and Administrators.
 * ============================================================ */

add_action( 'before_woocommerce_init', 'epm_declare_woocommerce_compatibility' );
function epm_declare_woocommerce_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', EPM_PLUGIN_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', EPM_PLUGIN_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'product_block_editor', plugin_basename( EPM_PLUGIN_FILE ), true );
	}
}

add_action( 'plugins_loaded', 'epm_check_woocommerce_dependency' );
function epm_check_woocommerce_dependency() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p><strong>Expert Price & Stock Manager:</strong> ' . esc_html__( 'requires WooCommerce to run.', 'expert-price-manager' ) . '</p></div>';
			}
		);
	}
}

add_filter(
	'plugin_action_links_' . plugin_basename( EPM_PLUGIN_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=epm-price-products' ) ) . '">' . esc_html__( 'Settings', 'expert-price-manager' ) . '</a>' );
		return $links;
	}
);

/**
 * Only users with manage_woocommerce (shop managers and administrators)
 * can see or use this plugin.
 */
function epm_can_access_price_tab() {
	return current_user_can( 'manage_woocommerce' );
}

/** Brand taxonomy - used when it exists on the site. */
function epm_get_brand_taxonomy() {
	return taxonomy_exists( 'product_brand' ) ? 'product_brand' : false;
}

/* ============================================================
 * Product search by name or by link/slug.
 * - If the input is a URL or a path (contains /), the last path segment is treated as the
 *   product slug (both decoded and percent-encoded, the way WordPress stores non-Latin slugs).
 * - Otherwise: all words must appear in the title, or the phrase must appear in the slug.
 * ============================================================ */

/** Candidate slugs (decoded and encoded) taken from a URL or path. */
function epm_search_slug_candidates( $input ) {
	$path = (string) wp_parse_url( $input, PHP_URL_PATH );
	if ( $path === '' && strpos( $input, '/' ) === false ) {
		$path = $input;
	}
	$parts = array_values( array_filter( explode( '/', $path ), 'strlen' ) );
	if ( ! $parts ) {
		return array();
	}
	$slug = rawurldecode( end( $parts ) );
	$slug = trim( mb_strtolower( $slug, 'UTF-8' ) );
	if ( $slug === '' ) {
		return array();
	}
	return array_values( array_unique( array( $slug, strtolower( rawurlencode( $slug ) ) ) ) );
}

function epm_apply_product_search( array &$args, $search ) {
	$search = trim( (string) $search );
	if ( $search !== '' ) {
		$args['epm_search'] = $search;
	}
}

add_filter( 'posts_where', 'epm_search_posts_where', 10, 2 );
function epm_search_posts_where( $where, $query ) {
	$term = $query->get( 'epm_search' );
	if ( ! is_string( $term ) || $term === '' ) {
		return $where;
	}
	global $wpdb;
	$clauses = array();

	$is_url = (bool) preg_match( '#^https?://#i', $term ) || ( strpos( $term, '/' ) !== false && strpos( $term, ' ' ) === false );
	if ( $is_url ) {
		foreach ( epm_search_slug_candidates( $term ) as $slug ) {
			$clauses[] = $wpdb->prepare( "{$wpdb->posts}.post_name = %s", $slug );
		}
		if ( ! $clauses ) {
			$clauses[] = '1=0';
		}
	} else {
		$words       = preg_split( '/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY );
		$title_parts = array();
		foreach ( $words as $w ) {
			$title_parts[] = $wpdb->prepare( "{$wpdb->posts}.post_title LIKE %s", '%' . $wpdb->esc_like( $w ) . '%' );
		}
		if ( $title_parts ) {
			$clauses[] = '(' . implode( ' AND ', $title_parts ) . ')';
		}
		$slug_like = mb_strtolower( str_replace( ' ', '-', $term ), 'UTF-8' );
		foreach ( array_unique( array( $slug_like, strtolower( rawurlencode( $slug_like ) ) ) ) as $s ) {
			$clauses[] = $wpdb->prepare( "{$wpdb->posts}.post_name LIKE %s", '%' . $wpdb->esc_like( $s ) . '%' );
		}
	}
	return $where . ' AND (' . implode( ' OR ', $clauses ) . ')';
}

/**
 * Cache-busting version of an asset: the file modification time, so edited CSS/JS is never served stale.
 *
 * @param string $file File name inside assets/css or assets/js.
 * @return string
 */
function epm_asset_version( $file ) {
	$dir  = 'js' === pathinfo( $file, PATHINFO_EXTENSION ) ? 'js' : 'css';
	$path = EPM_PLUGIN_DIR . 'assets/' . $dir . '/' . $file;
	return EPM_VERSION . '.' . ( file_exists( $path ) ? filemtime( $path ) : 0 );
}

if ( wp_doing_ajax() ) {
	add_action( 'admin_init', 'epm_guard_ajax_redirects', 0 );
}
/**
 * Our own AJAX calls must answer with JSON. When another plugin hijacks the request with a redirect
 * (login/phone verification, security plugins...), report which plugin did it instead of failing silently.
 */
function epm_guard_ajax_redirects() {
	if ( ! wp_doing_ajax() ) {
		return;
	}
	$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only inspects the action name.
	if ( 0 !== strpos( $action, 'epm_' ) ) {
		return;
	}
	add_filter(
		'wp_redirect',
		function ( $location ) {
			$plugin = '';
			foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 25 ) as $frame ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- diagnostics only.
				$file = isset( $frame['file'] ) ? wp_normalize_path( $frame['file'] ) : '';
				$base = wp_normalize_path( WP_PLUGIN_DIR ) . '/';
				if ( '' !== $file && 0 === strpos( $file, $base ) && 0 !== strpos( $file, wp_normalize_path( EPM_PLUGIN_DIR ) ) ) {
					$rest   = substr( $file, strlen( $base ) );
					$plugin = strtok( $rest, '/' );
					break;
				}
			}
			$message = sprintf(
				/* translators: 1: URL, 2: plugin folder name. */
				__( 'The request was redirected to %1$s by another plugin or a security rule (%2$s).', 'expert-price-manager' ),
				$location,
				'' !== $plugin ? $plugin : __( 'unknown source', 'expert-price-manager' )
			);
			wp_send_json_error( array( 'message' => $message ), 409 );
			return $location;
		},
		0
	);
}

/**
 * Does this site use the "show last modified" product fields (_show_last_modified / _custom_last_modified)?
 * Detected once from the post meta table and cached for a day.
 *
 * @return bool
 */
function epm_theme_last_modified_in_use() {
	$cached = get_transient( 'epm_theme_lm_detected' );
	if ( false === $cached ) {
		global $wpdb;
		$found  = $wpdb->get_var( "SELECT 1 FROM {$wpdb->postmeta} WHERE meta_key IN ('_show_last_modified','_custom_last_modified') LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed query, cached.
		$cached = $found ? 'yes' : 'no';
		set_transient( 'epm_theme_lm_detected', $cached, DAY_IN_SECONDS );
	}
	return 'yes' === $cached;
}

/**
 * Keep the theme's "last updated" display of a product in sync: switch it on and write today's Jalali date.
 * Filter `epm_theme_last_modified_value` changes the value or format; `epm_sync_theme_last_modified` can disable it.
 *
 * @param int $product_id Product ID (parent for variations).
 */
function epm_sync_theme_last_modified( $product_id ) {
	if ( ! apply_filters( 'epm_sync_theme_last_modified', epm_theme_last_modified_in_use(), $product_id ) ) {
		return;
	}
	$value = apply_filters( 'epm_theme_last_modified_value', epm_jalali_datetime_from_timestamp( time(), false ), $product_id );
	update_post_meta( $product_id, '_show_last_modified', 'yes' );
	update_post_meta( $product_id, '_custom_last_modified', $value );
}
