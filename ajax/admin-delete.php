<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
============================================================
 * Delete a product and create a Rank Math redirect (410 or 301).
 * Order of work: validate -> create the redirect -> delete the product.
 * If the deletion fails, the freshly created redirect is removed so the
 * remaining product is not wrongly served as 410.
 * ============================================================ */

function epm_rankmath_redirections_available() {
	return class_exists( '\RankMath\Redirections\Redirection' ) && class_exists( '\RankMath\Redirections\DB' );
}

/** Relative path of a URL (no domain, no leading/trailing slash) in the form Rank Math stores it. */
function epm_url_to_rankmath_source( $url ) {
	$path      = wp_parse_url( $url, PHP_URL_PATH );
	$path      = rawurldecode( (string) $path );
	$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	$home_path = rawurldecode( $home_path );
	if ( $home_path !== '/' && $home_path !== '' && strpos( $path, $home_path ) === 0 ) {
		$path = substr( $path, strlen( $home_path ) );
	}
	return trim( $path, '/' );
}

/**
 * Link to the Rank Math "Add redirection" screen with the product URL prefilled as the source.
 * The user picks the redirect type (301/302/307/410/451...) and the destination there.
 * Returns an empty string when the Redirections module is inactive or the product has no pretty permalink (draft).
 */
function epm_rankmath_new_redirect_link( $product ) {
	if ( ! epm_rankmath_redirections_available() ) {
		return '';
	}
	$source = epm_url_to_rankmath_source( $product->get_permalink() );
	if ( $source === '' || strpos( $source, '?' ) !== false ) {
		return '';
	}
	return add_query_arg(
		array(
			'page' => 'rank-math-redirections',
			'new'  => 1,
			'url'  => $source,
		),
		admin_url( 'admin.php' )
	);
}

function epm_product_order_count( $product_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'wc_order_product_lookup';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		return null; // WooCommerce analytics table is not available
	}
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- table name is built from $wpdb->prefix.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT order_id) FROM {$table} WHERE product_id = %d", $product_id ) );
}

add_action( 'wp_ajax_epm_admin_delete_preflight', 'epm_ajax_admin_delete_preflight' );
function epm_ajax_admin_delete_preflight() {
	check_ajax_referer( 'epm_admin_pricing_action', 'nonce' );
	$product_id = absint( $_POST['product_id'] ?? 0 );
	if ( ! epm_can_access_price_tab() || ! $product_id || ! current_user_can( 'delete_post', $product_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'expert-price-manager' ) ), 403 );
	}
	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		wp_send_json_error( array( 'message' => __( 'Product not found.', 'expert-price-manager' ) ), 404 );
	}
	$cat_url = '';
	$terms   = wp_get_post_terms( $product_id, 'product_cat' );
	if ( ! is_wp_error( $terms ) && $terms ) {
		$link    = get_term_link( $terms[0] );
		$cat_url = is_wp_error( $link ) ? '' : $link;
	}
	wp_send_json_success(
		array(
			'name'         => $product->get_name(),
			'url'          => $product->get_permalink(),
			'orders'       => epm_product_order_count( $product_id ),
			'category_url' => $cat_url,
			'rankmath'     => epm_rankmath_redirections_available(),
		)
	);
}

add_action( 'wp_ajax_epm_admin_delete_product', 'epm_ajax_admin_delete_product' );
function epm_ajax_admin_delete_product() {
	check_ajax_referer( 'epm_admin_pricing_action', 'nonce' );
	$product_id = absint( $_POST['product_id'] ?? 0 );
	if ( ! epm_can_access_price_tab() || ! $product_id || ! current_user_can( 'delete_post', $product_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'expert-price-manager' ) ), 403 );
	}
	$product = wc_get_product( $product_id );
	if ( ! $product || $product->is_type( 'variation' ) ) {
		wp_send_json_error( array( 'message' => __( 'Product not found.', 'expert-price-manager' ) ), 404 );
	}

	$mode = sanitize_key( $_POST['mode'] ?? '' );
	if ( ! in_array( $mode, array( 'trash', 'gone_410', 'redirect_cat', 'redirect_url' ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid delete type.', 'expert-price-manager' ) ), 400 );
	}

	// Move to trash: reversible, no redirect.
	if ( $mode === 'trash' ) {
		$ok = $product->delete( false );
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => __( 'Could not move the product to the trash.', 'expert-price-manager' ) ), 500 );
		}
		wp_send_json_success(
			array(
				'message'  => __( 'Moved to the trash.', 'expert-price-manager' ),
				'redirect' => '',
			)
		);
	}

	// Permanent delete + redirect
	if ( ! epm_rankmath_redirections_available() ) {
		wp_send_json_error( array( 'message' => __( 'The Rank Math Redirections module is not active. Activate it first (the product was not deleted).', 'expert-price-manager' ) ), 400 );
	}

	$permalink = get_permalink( $product_id );
	$source    = epm_url_to_rankmath_source( $permalink );
	if ( $source === '' || $product->get_status() === 'auto-draft' ) {
		wp_send_json_error( array( 'message' => __( 'No valid URL was found for this product; no redirect was created and the product was not deleted.', 'expert-price-manager' ) ), 400 );
	}

	$header_code = 410;
	$url_to      = '';
	if ( $mode === 'redirect_cat' || $mode === 'redirect_url' ) {
		$header_code = 301;
		if ( $mode === 'redirect_url' ) {
			$url_to = esc_url_raw( wp_unslash( $_POST['url_to'] ?? '' ) );
		} else {
			$terms  = wp_get_post_terms( $product_id, 'product_cat' );
			$link   = ( ! is_wp_error( $terms ) && $terms ) ? get_term_link( $terms[0] ) : '';
			$url_to = is_wp_error( $link ) ? '' : (string) $link;
		}
		if ( $url_to === '' || ! preg_match( '#^https?://#i', $url_to ) ) {
			wp_send_json_error( array( 'message' => __( 'The redirect destination URL is invalid (the product was not deleted).', 'expert-price-manager' ) ), 400 );
		}
		if ( untrailingslashit( $url_to ) === untrailingslashit( $permalink ) ) {
			wp_send_json_error( array( 'message' => __( 'The redirect destination cannot be the product itself.', 'expert-price-manager' ) ), 400 );
		}
	}

	$redirect_id = 0;
	try {
		$redirection = \RankMath\Redirections\Redirection::from(
			array(
				'sources'     => array(
					array(
						'pattern'    => $source,
						'comparison' => 'exact',
					),
				),
				'url_to'      => $url_to,
				'header_code' => $header_code,
				'status'      => 'active',
			)
		);
		$redirect_id = (int) $redirection->save();
	} catch ( \Throwable $e ) {
		wp_send_json_error( array( 'message' => __( 'Creating the redirect in Rank Math failed; the product was not deleted.', 'expert-price-manager' ) ), 500 );
	}
	if ( ! $redirect_id ) {
		wp_send_json_error( array( 'message' => __( 'Creating the redirect in Rank Math failed; the product was not deleted.', 'expert-price-manager' ) ), 500 );
	}

	$deleted = $product->delete( true );
	if ( ! $deleted ) {
		try {
			\RankMath\Redirections\DB::delete( array( $redirect_id ) );
		} catch ( \Throwable $e ) {
			$redirect_cleanup_failed = true; // The message below tells the user to check the redirect manually.
		}
		wp_send_json_error(
			array(
				/* translators: %s: URL path of the product. */
				'message' => sprintf( __( 'The product could not be deleted. Check the redirect created for the URL /%s in Rank Math.', 'expert-price-manager' ), $source ),
			),
			500
		);
	}

	wp_send_json_success(
		array(
			'message'  => $header_code === 410 ? __( 'Deleted and its URL was set to 410.', 'expert-price-manager' ) : __( 'Deleted and its URL was redirected with 301.', 'expert-price-manager' ),
			'redirect' => '/' . $source,
		)
	);
}
