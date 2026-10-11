<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once EPM_PLUGIN_DIR . 'includes/class-epm-xlsx-writer.php';

// The export builds large temporary files inside the plugin's protected uploads folder and
// appends to them incrementally, which WP_Filesystem cannot do; direct file functions are intentional here.
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

/*
============================================================
 * Custom XLSX export of products.
 * Steps: start (create token and store filters) -> batch (repeated) -> finish -> download
 * ============================================================ */

function epm_export_dir() {
	$upload = wp_upload_dir();
	$dir    = trailingslashit( $upload['basedir'] ) . 'epm-exports';
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( $dir . '/index.html' ) ) {
		file_put_contents( $dir . '/index.html', '' );
	}
	if ( ! file_exists( $dir . '/.htaccess' ) ) {
		file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" );
	}
	return $dir;
}

function epm_export_cleanup() {
	foreach ( (array) glob( epm_export_dir() . '/epm-*' ) as $file ) {
		if ( is_file( $file ) && filemtime( $file ) < time() - HOUR_IN_SECONDS ) {
			wp_delete_file( $file );
		}
	}
}

/** Selectable columns: key => [label, group, numeric?] */
function epm_export_columns() {
	return array(
		'id'                => array( 'ID', __( 'Basic', 'expert-price-manager' ), true ),
		'name'              => array( __( 'Product name', 'expert-price-manager' ), __( 'Basic', 'expert-price-manager' ), false ),
		'sku'               => array( 'SKU', __( 'Basic', 'expert-price-manager' ), false ),
		'type'              => array( __( 'Product type', 'expert-price-manager' ), __( 'Basic', 'expert-price-manager' ), false ),
		'status'            => array( __( 'Publish status', 'expert-price-manager' ), __( 'Basic', 'expert-price-manager' ), false ),
		'url'               => array( __( 'Link', 'expert-price-manager' ), __( 'Basic', 'expert-price-manager' ), false ),
		'parent_id'         => array( __( 'Parent ID (for variations)', 'expert-price-manager' ), __( 'Basic', 'expert-price-manager' ), true ),
		'regular_price'     => array( __( 'Regular price', 'expert-price-manager' ), __( 'Price', 'expert-price-manager' ), true ),
		'sale_price'        => array( __( 'Sale price', 'expert-price-manager' ), __( 'Price', 'expert-price-manager' ), true ),
		'price'             => array( __( 'Current price', 'expert-price-manager' ), __( 'Price', 'expert-price-manager' ), true ),
		'sale_from'         => array( __( 'Sale start (Jalali)', 'expert-price-manager' ), __( 'Price', 'expert-price-manager' ), false ),
		'sale_to'           => array( __( 'Sale end (Jalali)', 'expert-price-manager' ), __( 'Price', 'expert-price-manager' ), false ),
		'manage_stock'      => array( __( 'Manage stock', 'expert-price-manager' ), __( 'Stock', 'expert-price-manager' ), false ),
		'stock_quantity'    => array( __( 'Stock quantity', 'expert-price-manager' ), __( 'Stock', 'expert-price-manager' ), true ),
		'sold_individually' => array( __( 'Sold individually', 'expert-price-manager' ), __( 'Stock', 'expert-price-manager' ), false ),
		'stock_status'      => array( __( 'Stock status', 'expert-price-manager' ), __( 'Stock', 'expert-price-manager' ), false ),
		'categories'        => array( __( 'Categories', 'expert-price-manager' ), __( 'Taxonomy', 'expert-price-manager' ), false ),
		'brands'            => array( __( 'Brands', 'expert-price-manager' ), __( 'Taxonomy', 'expert-price-manager' ), false ),
		'tags'              => array( __( 'Tags', 'expert-price-manager' ), __( 'Taxonomy', 'expert-price-manager' ), false ),
		'seo_title'         => array( __( 'SEO title (Rank Math)', 'expert-price-manager' ), __( 'SEO', 'expert-price-manager' ), false ),
		'seo_description'   => array( __( 'Meta description (Rank Math)', 'expert-price-manager' ), __( 'SEO', 'expert-price-manager' ), false ),
		'seo_focus_keyword' => array( __( 'Focus keyword', 'expert-price-manager' ), __( 'SEO', 'expert-price-manager' ), false ),
		'seo_noindex'       => array( 'noindex', __( 'SEO', 'expert-price-manager' ), false ),
		'created'           => array( __( 'Created date (Jalali)', 'expert-price-manager' ), __( 'Other', 'expert-price-manager' ), false ),
		'modified'          => array( __( 'Modified date (Jalali)', 'expert-price-manager' ), __( 'Other', 'expert-price-manager' ), false ),
		'price_updated'     => array( __( 'Last price update from this panel', 'expert-price-manager' ), __( 'Other', 'expert-price-manager' ), false ),
		'price_updated_by'  => array( __( 'Last updated by', 'expert-price-manager' ), __( 'Other', 'expert-price-manager' ), false ),
		'total_sales'       => array( __( 'Total sales', 'expert-price-manager' ), __( 'Other', 'expert-price-manager' ), true ),
		'weight'            => array( __( 'Weight', 'expert-price-manager' ), __( 'Other', 'expert-price-manager' ), true ),
		'dimensions'        => array( __( 'Dimensions (L×W×H)', 'expert-price-manager' ), __( 'Other', 'expert-price-manager' ), false ),
		'image'             => array( __( 'Featured image URL', 'expert-price-manager' ), __( 'Other', 'expert-price-manager' ), false ),
	);
}

function epm_export_terms( $product_id, $taxonomy ) {
	if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
		return '';
	}
	$names = wp_get_post_terms( $product_id, $taxonomy, array( 'fields' => 'names' ) );
	return is_wp_error( $names ) ? '' : implode( wp_get_list_item_separator(), $names );
}

function epm_export_value( $key, $product, $parent ) {
	$src    = $parent ?: $product; // For variations: taxonomy and SEO come from the parent
	$id     = $product->get_id();
	$src_id = $src->get_id();
	switch ( $key ) {
		case 'id':
			return $id;
		case 'name':
			return $product->is_type( 'variation' ) ? $product->get_name() : $product->get_name();
		case 'sku':
			return $product->get_sku();
		case 'type':
			return $product->get_type();
		case 'status':
			return $product->get_status();
		case 'url':
			return $src->get_permalink();
		case 'parent_id':
			return $parent ? $parent->get_id() : '';
		case 'regular_price':
			return $product->is_type( 'variable' ) ? '' : $product->get_regular_price();
		case 'sale_price':
			return $product->is_type( 'variable' ) ? '' : $product->get_sale_price();
		case 'price':
			return $product->is_type( 'variable' ) ? '' : $product->get_price();
		case 'sale_from':
			$d = $product->get_date_on_sale_from();
			return $d ? epm_jalali_datetime_from_timestamp( $d->getTimestamp(), false ) : '';
		case 'sale_to':
			$d = $product->get_date_on_sale_to();
			return $d ? epm_jalali_datetime_from_timestamp( $d->getTimestamp(), false ) : '';
		case 'manage_stock':
			return $product->get_manage_stock() ? __( 'Yes', 'expert-price-manager' ) : __( 'No', 'expert-price-manager' );
		case 'stock_quantity':
			return $product->get_manage_stock() ? $product->get_stock_quantity() : '';
		case 'sold_individually':
			return $product->get_sold_individually() ? __( 'Yes', 'expert-price-manager' ) : __( 'No', 'expert-price-manager' );
		case 'stock_status':
			return $product->get_stock_status();
		case 'categories':
			return epm_export_terms( $src_id, 'product_cat' );
		case 'brands':
			return epm_export_terms( $src_id, epm_get_brand_taxonomy() );
		case 'tags':
			return epm_export_terms( $src_id, 'product_tag' );
		case 'seo_title':
			return get_post_meta( $src_id, 'rank_math_title', true );
		case 'seo_description':
			return get_post_meta( $src_id, 'rank_math_description', true );
		case 'seo_focus_keyword':
			return get_post_meta( $src_id, 'rank_math_focus_keyword', true );
		case 'seo_noindex':
			$robots = get_post_meta( $src_id, 'rank_math_robots', true );
			return ( is_array( $robots ) && in_array( 'noindex', $robots, true ) ) ? __( 'Yes', 'expert-price-manager' ) : '';
		case 'created':
			$d = $product->get_date_created();
			return $d ? epm_jalali_datetime_from_timestamp( $d->getTimestamp() ) : '';
		case 'modified':
			$d = $product->get_date_modified();
			return $d ? epm_jalali_datetime_from_timestamp( $d->getTimestamp() ) : '';
		case 'price_updated':
			return get_post_meta( $id, '_price_update_date', true );
		case 'price_updated_by':
			return epm_user_label( (int) get_post_meta( $id, '_epm_price_updated_by', true ) );
		case 'total_sales':
			return (int) get_post_meta( $src_id, 'total_sales', true );
		case 'weight':
			return $product->get_weight();
		case 'dimensions':
			$l = $product->get_length();
			$w = $product->get_width();
			$h = $product->get_height();
			return ( $l || $w || $h ) ? $l . '×' . $w . '×' . $h : '';
		case 'image':
			$img = $src->get_image_id();
			return $img ? (string) wp_get_attachment_url( $img ) : '';
	}
	return '';
}

function epm_export_sanitize_meta_keys( $raw ) {
	$keys = array();
	foreach ( preg_split( '/[\s,،]+/u', (string) $raw ) as $k ) {
		$k = trim( $k );
		if ( $k !== '' && preg_match( '/^[A-Za-z0-9_\-\.]{1,100}$/', $k ) ) {
			$keys[] = $k;
		}
	}
	return array_slice( array_values( array_unique( $keys ) ), 0, 20 );
}

function epm_export_state_path( $token ) {
	return epm_export_dir() . '/epm-' . $token . '.json';
}

function epm_export_valid_token( $token ) {
	return is_string( $token ) && preg_match( '/^[a-f0-9]{32}$/', $token );
}

add_action( 'wp_ajax_epm_export_start', 'epm_ajax_export_start' );
function epm_ajax_export_start() {
	check_ajax_referer( 'epm_admin_pricing_action', 'nonce' );
	if ( ! epm_can_access_price_tab() ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'expert-price-manager' ) ), 403 );
	}
	epm_export_cleanup();

	$all       = epm_export_columns();
	$requested = isset( $_POST['columns'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['columns'] ) ) : array();
	$columns   = array();
	foreach ( $requested as $c ) {
		if ( isset( $all[ $c ] ) && ! in_array( $c, $columns, true ) ) {
			$columns[] = $c;
		}
	}
	$meta_keys = epm_export_sanitize_meta_keys( wp_unslash( $_POST['meta_keys'] ?? '' ) );
	if ( ! $columns && ! $meta_keys ) {
		wp_send_json_error( array( 'message' => __( 'Select at least one column.', 'expert-price-manager' ) ), 400 );
	}

	$statuses = array_intersect( array_map( 'sanitize_key', (array) wp_unslash( $_POST['statuses'] ?? array() ) ), array( 'publish', 'draft', 'private', 'pending' ) );
	if ( ! $statuses ) {
		$statuses = array( 'publish', 'draft', 'private', 'pending' );
	}
	$stock_filter = sanitize_key( $_POST['stock_filter'] ?? '' );
	if ( ! in_array( $stock_filter, array( '', 'instock', 'outofstock' ), true ) ) {
		$stock_filter = '';
	}
	$extra = sanitize_key( $_POST['extra_filter'] ?? '' );
	if ( ! in_array( $extra, array( '', 'on_sale', 'no_price', 'no_sku' ), true ) ) {
		$extra = '';
	}

	$filters = array(
		'search'          => sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ),
		'category_id'     => absint( $_POST['category_id'] ?? 0 ),
		'brand_id'        => absint( $_POST['brand_id'] ?? 0 ),
		'statuses'        => array_values( $statuses ),
		'stock_filter'    => $stock_filter,
		'extra_filter'    => $extra,
		'with_variations' => ! empty( $_POST['with_variations'] ) && $_POST['with_variations'] === '1',
	);

	$token     = bin2hex( random_bytes( 16 ) );
	$dir       = epm_export_dir();
	$state     = array(
		'user'      => get_current_user_id(),
		'columns'   => $columns,
		'meta_keys' => $meta_keys,
		'filters'   => $filters,
		'page'      => 0,
		'done'      => false,
		'writer'    => array(),
	);
	$rows_file = $dir . '/epm-' . $token . '.rows';

	$writer = new EPM_Xlsx_Writer( $rows_file );
	$header = array();
	foreach ( $columns as $c ) {
		$header[] = $all[ $c ][0];
	}
	foreach ( $meta_keys as $mk ) {
		$header[] = 'meta: ' . $mk;
	}
	$writer->add_row( $header, true );
	$state['writer'] = $writer->get_state();
	file_put_contents( epm_export_state_path( $token ), wp_json_encode( $state ) );

	$count_args = epm_export_query_args( $filters, 1, 1 );
	$q          = new WP_Query( $count_args );
	wp_send_json_success(
		array(
			'token'      => $token,
			'total'      => (int) $q->found_posts,
			'batch_size' => 100,
		)
	);
}

function epm_export_query_args( $f, $paged, $per_page ) {
	$args = array(
		'post_type'      => 'product',
		'post_status'    => $f['statuses'],
		'posts_per_page' => $per_page,
		'paged'          => $paged,
		'orderby'        => 'ID',
		'order'          => 'ASC',
		'fields'         => 'ids',
		'no_found_rows'  => false,
	);
	epm_apply_product_search( $args, $f['search'] );
	$tax = array();
	if ( $f['category_id'] ) {
		$tax[] = array(
			'taxonomy'         => 'product_cat',
			'field'            => 'term_id',
			'terms'            => array( $f['category_id'] ),
			'include_children' => true,
		);
	}
	$brand_tax = epm_get_brand_taxonomy();
	if ( $brand_tax && $f['brand_id'] ) {
		$tax[] = array(
			'taxonomy' => $brand_tax,
			'field'    => 'term_id',
			'terms'    => array( $f['brand_id'] ),
		);
	}
	if ( count( $tax ) > 1 ) {
		$tax['relation'] = 'AND';
	}
	if ( $tax ) {
		$args['tax_query'] = $tax;
	}
	$meta = array();
	if ( $f['stock_filter'] !== '' ) {
		$meta[] = array(
			'key'   => '_stock_status',
			'value' => $f['stock_filter'],
		);
	}
	switch ( $f['extra_filter'] ) {
		case 'on_sale':
			$meta[] = array(
				'key'     => '_sale_price',
				'value'   => '',
				'compare' => '!=',
			);
			break;
		case 'no_price':
			$meta[] = array(
				'relation' => 'OR',
				array(
					'key'     => '_regular_price',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'   => '_regular_price',
					'value' => '',
				),
			);
			break;
		case 'no_sku':
			$meta[] = array(
				'relation' => 'OR',
				array(
					'key'     => '_sku',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'   => '_sku',
					'value' => '',
				),
			);
			break;
	}
	if ( count( $meta ) > 1 ) {
		$meta['relation'] = 'AND';
	}
	if ( $meta ) {
		$args['meta_query'] = $meta;
	}
	return $args;
}

add_action( 'wp_ajax_epm_export_batch', 'epm_ajax_export_batch' );
function epm_ajax_export_batch() {
	check_ajax_referer( 'epm_admin_pricing_action', 'nonce' );
	if ( ! epm_can_access_price_tab() ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'expert-price-manager' ) ), 403 );
	}
	$token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
	if ( ! epm_export_valid_token( $token ) || ! file_exists( epm_export_state_path( $token ) ) ) {
		wp_send_json_error( array( 'message' => __( 'The export session is invalid or has expired.', 'expert-price-manager' ) ), 400 );
	}
	$state = json_decode( (string) file_get_contents( epm_export_state_path( $token ) ), true );
	if ( ! is_array( $state ) || (int) $state['user'] !== get_current_user_id() ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'expert-price-manager' ) ), 403 );
	}

	$per_page    = 100;
	$page        = (int) $state['page'] + 1;
	$f           = $state['filters'];
	$query       = new WP_Query( epm_export_query_args( $f, $page, $per_page ) );
	$total_pages = (int) $query->max_num_pages;

	$writer = new EPM_Xlsx_Writer( epm_export_dir() . '/epm-' . $token . '.rows' );
	$writer->set_state( $state['writer'] );
	$all = epm_export_columns();

	$emit = function ( $product, $parent ) use ( $writer, $state, $all ) {
		$row = array();
		foreach ( $state['columns'] as $c ) {
			$row[] = array(
				'v' => epm_export_value( $c, $product, $parent ),
				'n' => ! empty( $all[ $c ][2] ),
			);
		}
		foreach ( $state['meta_keys'] as $mk ) {
			$v = get_post_meta( $product->get_id(), $mk, true );
			if ( is_array( $v ) || is_object( $v ) ) {
				$v = wp_json_encode( $v, JSON_UNESCAPED_UNICODE );
			}
			$row[] = array(
				'v' => $v,
				'n' => false,
			);
		}
		$writer->add_row( $row );
	};

	foreach ( $query->posts as $pid ) {
		$product = wc_get_product( $pid );
		if ( ! $product ) {
			continue;
		}
		$emit( $product, null );
		if ( ! empty( $f['with_variations'] ) && $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $vid ) {
				$variation = wc_get_product( $vid );
				if ( $variation && $variation->is_type( 'variation' ) ) {
					$emit( $variation, $product );
				}
			}
		}
	}

	$state['page']   = $page;
	$state['writer'] = $writer->get_state();
	$finished        = $page >= $total_pages;
	$state['done']   = $finished;
	file_put_contents( epm_export_state_path( $token ), wp_json_encode( $state ) );

	if ( $finished ) {
		$xlsx = epm_export_dir() . '/epm-' . $token . '.xlsx';
		$res  = $writer->finish( $xlsx );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ), 500 );
		}
		wp_delete_file( epm_export_dir() . '/epm-' . $token . '.rows' );
	}
	wp_send_json_success(
		array(
			'page'  => $page,
			'pages' => $total_pages,
			'done'  => $finished,
			'rows'  => max( 0, $state['writer']['rows'] - 1 ),
		)
	);
}

add_action( 'wp_ajax_epm_export_download', 'epm_ajax_export_download' );
function epm_ajax_export_download() {
	if ( ! epm_can_access_price_tab() || ! check_admin_referer( 'epm_export_download' ) ) {
		wp_die( esc_html__( 'Unauthorized access.', 'expert-price-manager' ), '', 403 );
	}
	$token      = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
	$file       = epm_export_dir() . '/epm-' . $token . '.xlsx';
	$state_file = epm_export_state_path( $token );
	if ( ! epm_export_valid_token( $token ) || ! file_exists( $file ) || ! file_exists( $state_file ) ) {
		wp_die( esc_html__( 'The export file was not found or has expired.', 'expert-price-manager' ) );
	}
	$state = json_decode( (string) file_get_contents( $state_file ), true );
	if ( ! is_array( $state ) || (int) $state['user'] !== get_current_user_id() ) {
		wp_die( esc_html__( 'Unauthorized access.', 'expert-price-manager' ), '', 403 );
	}
	$name = 'products-' . wp_date( 'Ymd-His' ) . '.xlsx';
	nocache_headers();
	header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $name ) . '"' );
	header( 'Content-Length: ' . (int) filesize( $file ) );
	readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming a generated export file.
	wp_delete_file( $file );
	wp_delete_file( $state_file );
	exit;
}

/* ---- Saved column templates (per user) ---- */
function epm_export_get_templates() {
	$t = get_user_meta( get_current_user_id(), 'epm_export_templates', true );
	return is_array( $t ) ? $t : array();
}

add_action( 'wp_ajax_epm_export_save_template', 'epm_ajax_export_save_template' );
function epm_ajax_export_save_template() {
	check_ajax_referer( 'epm_admin_pricing_action', 'nonce' );
	if ( ! epm_can_access_price_tab() ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'expert-price-manager' ) ), 403 );
	}
	$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	if ( $name === '' ) {
		wp_send_json_error( array( 'message' => __( 'Enter a template name.', 'expert-price-manager' ) ), 400 );
	}
	$all       = epm_export_columns();
	$cols      = array_values(
		array_filter(
			array_map( 'sanitize_key', (array) wp_unslash( $_POST['columns'] ?? array() ) ),
			function ( $c ) use ( $all ) {
				return isset( $all[ $c ] );
			}
		)
	);
	$templates = epm_export_get_templates();
	if ( count( $templates ) >= 30 && ! isset( $templates[ $name ] ) ) {
		wp_send_json_error( array( 'message' => __( 'A maximum of 30 templates is allowed.', 'expert-price-manager' ) ), 400 );
	}
	$templates[ $name ] = array(
		'columns'   => $cols,
		'meta_keys' => implode( ',', epm_export_sanitize_meta_keys( wp_unslash( $_POST['meta_keys'] ?? '' ) ) ),
	);
	update_user_meta( get_current_user_id(), 'epm_export_templates', $templates );
	wp_send_json_success( array( 'templates' => $templates ) );
}

add_action( 'wp_ajax_epm_export_delete_template', 'epm_ajax_export_delete_template' );
function epm_ajax_export_delete_template() {
	check_ajax_referer( 'epm_admin_pricing_action', 'nonce' );
	if ( ! epm_can_access_price_tab() ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'expert-price-manager' ) ), 403 );
	}
	$name      = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$templates = epm_export_get_templates();
	unset( $templates[ $name ] );
	update_user_meta( get_current_user_id(), 'epm_export_templates', $templates );
	wp_send_json_success( array( 'templates' => $templates ) );
}
