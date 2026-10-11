<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once EPM_PLUGIN_DIR . 'ajax/admin-delete.php';

/** Convert Persian/Arabic digits and common separators to Latin so the value can be validated. */
function epm_normalize_number( $value ) {
	$value = strtr(
		(string) $value,
		array(
			'۰'        => '0',
			'۱'        => '1',
			'۲'        => '2',
			'۳'        => '3',
			'۴'        => '4',
			'۵'        => '5',
			'۶'        => '6',
			'۷'        => '7',
			'۸'        => '8',
			'۹'        => '9',
			'٠'        => '0',
			'١'        => '1',
			'٢'        => '2',
			'٣'        => '3',
			'٤'        => '4',
			'٥'        => '5',
			'٦'        => '6',
			'٧'        => '7',
			'٨'        => '8',
			'٩'        => '9',
			'٫'        => '.',
			'٬'        => '',
			'،'        => '',
			','        => '',
			' '        => '',
			"\xC2\xA0" => '',
		)
	);
	return trim( $value );
}

add_action( 'wp_ajax_epm_admin_get_products', 'epm_ajax_admin_get_products' );
function epm_ajax_admin_get_products() {
	check_ajax_referer( 'epm_admin_pricing_action', 'nonce' );
	if ( ! epm_can_access_price_tab() ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'expert-price-manager' ) ), 403 );
	}

	$page        = max( 1, (int) ( $_POST['page'] ?? 1 ) );
	$per_page    = min( 500, max( 1, (int) ( $_POST['per_page'] ?? 20 ) ) );
	$search      = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );
	$sort        = sanitize_key( $_POST['sort'] ?? 'name_asc' );
	$brand_id    = absint( $_POST['brand_id'] ?? 0 );
	$stock       = sanitize_key( $_POST['stock_status'] ?? '' );
	$category_id = absint( $_POST['category_id'] ?? 0 );
	$brand_tax   = epm_get_brand_taxonomy();

	$args = array(
		'post_type'      => 'product',
		'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
		'posts_per_page' => $per_page,
		'paged'          => $page,
		'fields'         => 'ids',
	);
	epm_apply_product_search( $args, $search );

	$tax_query = array();
	if ( $category_id ) {
		$tax_query[] = array(
			'taxonomy'         => 'product_cat',
			'field'            => 'term_id',
			'terms'            => array( $category_id ),
			'include_children' => true,
		);
	}
	if ( $brand_tax && $brand_id ) {
		$tax_query[] = array(
			'taxonomy' => $brand_tax,
			'field'    => 'term_id',
			'terms'    => array( $brand_id ),
		);
	}
	if ( $tax_query ) {
		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}
		$args['tax_query'] = $tax_query;
	}

	switch ( $sort ) {
		case 'name_desc':
			$args['orderby'] = 'title';
			$args['order']   = 'DESC';
			break;
		case 'price_asc':
		case 'price_desc':
			// Keep products without _price in the results (instead of dropping them from the list).
			$args['meta_query'] = array(
				'relation'    => 'OR',
				'epm_price'   => array(
					'key'  => '_price',
					'type' => 'NUMERIC',
				),
				'epm_noprice' => array(
					'key'     => '_price',
					'compare' => 'NOT EXISTS',
				),
			);
			if ( in_array( $stock, array( 'instock', 'outofstock' ), true ) ) {
				$args['meta_query'] = array(
					'relation' => 'AND',
					array(
						'key'   => '_stock_status',
						'value' => $stock,
					),
					$args['meta_query'],
				);
			}
			$stock_done         = true;
			$args['orderby']    = array( 'epm_price' => $sort === 'price_asc' ? 'ASC' : 'DESC' );
			break;
		default:
			$args['orderby'] = 'title';
			$args['order']   = 'ASC';
	}

	if ( empty( $stock_done ) && in_array( $stock, array( 'instock', 'outofstock' ), true ) ) {
		$args['meta_query'] = array(
			array(
				'key'   => '_stock_status',
				'value' => $stock,
			),
		);
	}

	$query = new WP_Query( $args );
	$items = array();
	foreach ( $query->posts as $post_id ) {
		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			continue;
		}
		$is_variable = $product->is_type( 'variable' );
		$editable    = $is_variable || in_array( $product->get_type(), array( 'simple', 'external' ), true );
		$items[]     = array(
			'id'                => $product->get_id(),
			'name'              => $product->get_name(),
			'sku'               => $product->get_sku(),
			'edit_link'         => get_edit_post_link( $product->get_id(), 'raw' ),
			'view_link'         => $product->get_permalink(),
			'redirect_link'     => epm_rankmath_new_redirect_link( $product ),
			'regular_price'     => $is_variable ? '' : $product->get_regular_price(),
			'sale_price'        => $is_variable ? '' : $product->get_sale_price(),
			'manage_stock'      => (bool) $product->get_manage_stock(),
			'stock_quantity'    => $product->get_manage_stock() ? $product->get_stock_quantity() : '',
			'in_stock'          => $product->get_stock_status() === 'instock',
			'sold_individually' => (bool) $product->get_sold_individually(),
			'is_variable'       => $is_variable,
			'editable'          => $editable,
			'type'              => $product->get_type(),
			'price_updated'     => (string) get_post_meta( $product->get_id(), '_price_update_date', true ),
			'updated_by'        => epm_updated_by_label( $product->get_id() ),
			'can_delete'        => current_user_can( 'delete_post', $product->get_id() ),
			'variation_count'   => $is_variable ? count( $product->get_children() ) : 0,
		);
	}
	wp_send_json_success(
		array(
			'items'    => $items,
			'total'    => (int) $query->found_posts,
			'per_page' => $per_page,
		)
	);
}

/** Human-readable variation label based on attribute names and real values. */
function epm_admin_variation_label( $variation, $parent_product ) {
	$parts = array();
	foreach ( (array) $variation->get_attributes() as $attribute_name => $attribute_value ) {
		$attribute_name  = (string) $attribute_name;
		$attribute_value = (string) $attribute_value;
		$label           = wc_attribute_label( $attribute_name, $parent_product );
		if ( $label === '' ) {
			$label = $attribute_name;
		}
		$value_label = $attribute_value;
		if ( $attribute_value !== '' && taxonomy_exists( $attribute_name ) ) {
			$term = get_term_by( 'slug', $attribute_value, $attribute_name );
			if ( $term && ! is_wp_error( $term ) ) {
				$value_label = $term->name;
			}
		}
		if ( $value_label === '' ) {
			$value_label = __( 'All options', 'expert-price-manager' );
		}
		$parts[] = sanitize_text_field( $label ) . ': ' . sanitize_text_field( $value_label );
	}
	return $parts ? implode( wp_get_list_item_separator(), $parts ) : __( 'Product variation', 'expert-price-manager' );
}

add_action( 'wp_ajax_epm_admin_get_variations', 'epm_ajax_admin_get_variations' );
function epm_ajax_admin_get_variations() {
	check_ajax_referer( 'epm_admin_pricing_action', 'nonce' );
	if ( ! epm_can_access_price_tab() ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'expert-price-manager' ) ), 403 );
	}
	$product_id = absint( $_POST['product_id'] ?? 0 );
	$product    = wc_get_product( $product_id );
	if ( ! $product || ! $product->is_type( 'variable' ) ) {
		wp_send_json_error( array( 'message' => __( 'Variable product not found.', 'expert-price-manager' ) ), 404 );
	}
	$items = array();
	foreach ( $product->get_children() as $variation_id ) {
		$variation = wc_get_product( $variation_id );
		if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
			continue;
		}
		$items[] = array(
			'id'             => $variation->get_id(),
			'label'          => epm_admin_variation_label( $variation, $product ),
			'sku'            => $variation->get_sku(),
			'edit_link'      => get_edit_post_link( $product->get_id(), 'raw' ),
			'view_link'      => $variation->get_permalink(),
			'regular_price'  => $variation->get_regular_price(),
			'sale_price'     => $variation->get_sale_price(),
			'manage_stock'   => (bool) $variation->get_manage_stock(),
			'stock_quantity' => $variation->get_manage_stock() ? $variation->get_stock_quantity() : '',
			'in_stock'       => $variation->get_stock_status() === 'instock',
		);
	}
	wp_send_json_success(
		array(
			'items' => $items,
			'total' => count( $items ),
		)
	);
}

add_action( 'wp_ajax_epm_admin_save_price', 'epm_ajax_admin_save_price' );
function epm_ajax_admin_save_price() {
	check_ajax_referer( 'epm_admin_pricing_action', 'nonce' );
	if ( ! epm_can_access_price_tab() ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'expert-price-manager' ) ), 403 );
	}

	$product_id = absint( $_POST['product_id'] ?? 0 );
	$product    = wc_get_product( $product_id );
	if ( ! $product ) {
		wp_send_json_error( array( 'message' => __( 'Product not found.', 'expert-price-manager' ) ), 404 );
	}
	if ( $product->is_type( 'variable' ) ) {
		wp_send_json_error( array( 'message' => __( 'The price of a variable product must be changed through its variations.', 'expert-price-manager' ) ), 400 );
	}
	if ( ! in_array( $product->get_type(), array( 'simple', 'external', 'variation' ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'The price of this product type cannot be changed from this panel.', 'expert-price-manager' ) ), 400 );
	}

	$sku      = wc_clean( wp_unslash( $_POST['sku'] ?? '' ) );
	$regular  = epm_normalize_number( sanitize_text_field( wp_unslash( $_POST['regular_price'] ?? '' ) ) );
	$sale     = epm_normalize_number( sanitize_text_field( wp_unslash( $_POST['sale_price'] ?? '' ) ) );
	$stock    = epm_normalize_number( sanitize_text_field( wp_unslash( $_POST['stock_quantity'] ?? '' ) ) );
	$in_stock = sanitize_text_field( wp_unslash( $_POST['in_stock'] ?? '0' ) ) === '1';

	foreach ( array( array( $regular, __( 'The regular price is invalid.', 'expert-price-manager' ) ), array( $sale, __( 'The sale price is invalid.', 'expert-price-manager' ) ), array( $stock, __( 'The stock quantity is invalid.', 'expert-price-manager' ) ) ) as $check ) {
		if ( $check[0] !== '' && ! is_numeric( $check[0] ) ) {
			wp_send_json_error( array( 'message' => $check[1] ), 400 );
		}
	}
	if ( ( $regular !== '' && (float) $regular < 0 ) || ( $sale !== '' && (float) $sale < 0 ) || ( $stock !== '' && (float) $stock < 0 ) ) {
		wp_send_json_error( array( 'message' => __( 'Values cannot be negative.', 'expert-price-manager' ) ), 400 );
	}
	// Prevent accidentally erasing the price: a product that has a price cannot be emptied.
	if ( $regular === '' && $product->get_regular_price() !== '' ) {
		wp_send_json_error( array( 'message' => __( 'The regular price must not be empty (to prevent accidentally erasing the price).', 'expert-price-manager' ) ), 400 );
	}
	if ( $sale !== '' && ( $regular === '' || (float) $sale >= (float) $regular ) ) {
		wp_send_json_error( array( 'message' => __( 'The sale price must be lower than the regular price.', 'expert-price-manager' ) ), 400 );
	}
	if ( $sku !== '' ) {
		$duplicate = wc_get_product_id_by_sku( $sku );
		if ( $duplicate && (int) $duplicate !== $product_id ) {
			wp_send_json_error( array( 'message' => __( 'This SKU is already used by another product.', 'expert-price-manager' ) ), 409 );
		}
	}

	$regular = wc_format_decimal( $regular );
	$sale    = wc_format_decimal( $sale );

	/**
	 * Stock rules:
	 * - "Manage stock" can be switched on/off per product. When it is on, the quantity is stored.
	 * - With stock management on: "In stock" unchecked makes the quantity 0; checked with an empty or 0 quantity uses 1.
	 * - With stock management off only the stock status changes.
	 * - "Sold individually" limits purchases to 1 item per order (simple products only; variations inherit it from the parent).
	 */
	$manages_stock = isset( $_POST['manage_stock'] )
		? sanitize_text_field( wp_unslash( $_POST['manage_stock'] ) ) === '1'
		: (bool) $product->get_manage_stock();

	$before = epm_log_snapshot( $product );

	$product->set_sku( $sku );
	$product->set_regular_price( $regular );
	$product->set_sale_price( $sale );
	$product->set_manage_stock( $manages_stock );
	if ( $manages_stock ) {
		$stock_amount = $stock !== '' ? (float) wc_stock_amount( $stock ) : 0;
		if ( ! $in_stock ) {
			$stock_amount = 0;
		} elseif ( $stock_amount <= 0 ) {
			$stock_amount = 1;
		}
		$product->set_stock_quantity( $stock_amount );
	}
	$product->set_stock_status( $in_stock ? 'instock' : 'outofstock' );
	if ( isset( $_POST['sold_individually'] ) && ! $product->is_type( 'variation' ) ) {
		$product->set_sold_individually( sanitize_text_field( wp_unslash( $_POST['sold_individually'] ) ) === '1' );
	}
	$product->save();

	epm_log_diff( $product, $before, epm_log_snapshot( $product ), 'user', get_current_user_id() );

	$updated = epm_current_jalali_datetime();
	update_post_meta( $product_id, '_price_update_date', $updated );
	update_post_meta( $product_id, '_epm_price_updated_by', get_current_user_id() );
	delete_post_meta( $product_id, '_epm_update_source' );
	epm_sync_theme_last_modified( $product->get_parent_id() ? $product->get_parent_id() : $product_id );
	wc_delete_product_transients( $product_id );

	wp_send_json_success(
		array(
			'sku'               => $product->get_sku(),
			'regular_price'     => $product->get_regular_price(),
			'sale_price'        => $product->get_sale_price(),
			'updated_at'        => $updated,
			'updated_by'        => epm_user_label( get_current_user_id() ),
			'manage_stock'      => (bool) $product->get_manage_stock(),
			'stock_quantity'    => $product->get_stock_quantity(),
			'in_stock'          => $product->get_stock_status() === 'instock',
			'sold_individually' => (bool) $product->get_sold_individually(),
		)
	);
}

add_action( 'wp_ajax_epm_admin_poll', 'epm_ajax_admin_poll' );
/** Lightweight live refresh: returns the latest update info and stock of the products shown on the page. */
function epm_ajax_admin_poll() {
	check_ajax_referer( 'epm_admin_pricing_action', 'nonce' );
	if ( ! epm_can_access_price_tab() ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'expert-price-manager' ) ), 403 );
	}
	$ids = isset( $_POST['ids'] ) ? array_slice( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) ), 0, 200 ) : array();
	$out = array();
	foreach ( $ids as $id ) {
		$product = wc_get_product( $id );
		if ( ! $product ) {
			continue;
		}
		$out[ $id ] = array(
			'updated_at'     => (string) get_post_meta( $id, '_price_update_date', true ),
			'updated_by'     => epm_updated_by_label( $id ),
			'manage_stock'   => (bool) $product->get_manage_stock(),
			'stock_quantity' => $product->get_manage_stock() ? $product->get_stock_quantity() : '',
			'in_stock'       => $product->get_stock_status() === 'instock',
		);
	}
	wp_send_json_success( $out );
}
