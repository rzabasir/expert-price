<?php
/**
 * AJAX: change-history dashboard data and "revert" action.
 *
 * @package ExpertPriceManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Time range (site time zone) of a dashboard period.
 *
 * @param string $period today|yesterday|7|30.
 * @return array{0:int,1:int,2:int} Start, end (exclusive) and number of days.
 */
function epm_log_period_range( $period ) {
	$tz    = wp_timezone();
	$today = new DateTimeImmutable( 'today', $tz );
	switch ( $period ) {
		case 'yesterday':
			$start = $today->modify( '-1 day' );
			$end   = $today;
			$days  = 1;
			break;
		case '7':
			$start = $today->modify( '-6 days' );
			$end   = $today->modify( '+1 day' );
			$days  = 7;
			break;
		case '30':
			$start = $today->modify( '-29 days' );
			$end   = $today->modify( '+1 day' );
			$days  = 30;
			break;
		default:
			$start = $today;
			$end   = $today->modify( '+1 day' );
			$days  = 1;
	}
	return array( $start->getTimestamp(), $end->getTimestamp(), $days );
}

/** Translated label of a tracked field. */
function epm_log_field_label( $field ) {
	$labels = array(
		'regular_price'     => __( 'Regular price', 'expert-price-manager' ),
		'sale_price'        => __( 'Sale price', 'expert-price-manager' ),
		'stock_quantity'    => __( 'Stock quantity', 'expert-price-manager' ),
		'stock_status'      => __( 'Stock status', 'expert-price-manager' ),
		'manage_stock'      => __( 'Manage stock', 'expert-price-manager' ),
		'sku'               => 'SKU',
		'sold_individually' => __( 'Sold individually', 'expert-price-manager' ),
	);
	return $labels[ $field ] ?? $field;
}

/** Human-readable value of a stored change. */
function epm_log_display_value( $field, $value ) {
	if ( null === $value || '' === $value ) {
		return '—';
	}
	if ( 'manage_stock' === $field || 'sold_individually' === $field ) {
		return '1' === $value ? __( 'On', 'expert-price-manager' ) : __( 'Off', 'expert-price-manager' );
	}
	if ( 'stock_status' === $field ) {
		return 'instock' === $value ? __( 'In stock', 'expert-price-manager' ) : __( 'Out of stock', 'expert-price-manager' );
	}
	return $value;
}

/** Which group a field belongs to. */
function epm_log_field_group( $field ) {
	foreach ( epm_log_field_groups() as $group => $fields ) {
		if ( in_array( $field, $fields, true ) ) {
			return $group;
		}
	}
	return 'other';
}

/** Name used for a user id / source pair. */
function epm_log_who( $user_id, $source ) {
	if ( 'order' === $source ) {
		return __( 'Customer order', 'expert-price-manager' );
	}
	$name = epm_user_label( $user_id );
	return '' !== $name ? $name : __( 'Unknown user', 'expert-price-manager' );
}

/** SQL fragment (and args) limiting rows to a field group. */
function epm_log_type_clause( $type, array &$args ) {
	$groups = epm_log_field_groups();
	if ( ! isset( $groups[ $type ] ) ) {
		return '';
	}
	$args = array_merge( $args, $groups[ $type ] );
	return ' AND field IN (' . implode( ',', array_fill( 0, count( $groups[ $type ] ), '%s' ) ) . ')';
}

add_action( 'wp_ajax_epm_log_dashboard', 'epm_ajax_log_dashboard' );
/** Everything the dashboard needs in one request. */
function epm_ajax_log_dashboard() {
	check_ajax_referer( 'epm_admin_pricing_action', 'nonce' );
	if ( ! epm_can_access_price_tab() ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'expert-price-manager' ) ), 403 );
	}
	global $wpdb;
	$table = epm_log_table();

	$period = sanitize_key( wp_unslash( $_POST['period'] ?? 'today' ) );
	if ( ! in_array( $period, array( 'today', 'yesterday', '7', '30' ), true ) ) {
		$period = 'today';
	}
	$user_f = sanitize_text_field( wp_unslash( $_POST['user'] ?? '' ) );
	$type_f = sanitize_key( wp_unslash( $_POST['type'] ?? '' ) );
	$search = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );
	$page   = max( 1, absint( $_POST['page'] ?? 1 ) );
	$per    = 15;

	list($start, $end, $days) = epm_log_period_range( $period );
	$prev_start               = $start - ( $end - $start );

	// Extra filters (user / type / search) used for the KPIs, the table and the chart.
	$where = ' AND created_at >= %d AND created_at < %d';
	$args  = array();
	$filt  = '';
	$fargs = array();
	if ( 'order' === $user_f ) {
		$filt .= " AND source = 'order'";
	} elseif ( '' !== $user_f && ctype_digit( $user_f ) ) {
		$filt   .= " AND source <> 'order' AND user_id = %d";
		$fargs[] = (int) $user_f;
	}
	$filt .= epm_log_type_clause( $type_f, $fargs );
	if ( '' !== $search ) {
		$like    = '%' . $wpdb->esc_like( $search ) . '%';
		$filt   .= ' AND (product_name LIKE %s OR sku LIKE %s)';
		$fargs[] = $like;
		$fargs[] = $like;
	}

	$groups = epm_log_field_groups();
	$in     = function ( $g ) use ( $groups ) {
		return "'" . implode( "','", $groups[ $g ] ) . "'";
	};

	// Totals for the period and the previous period in a single query (hot path: index on created_at).
	$sql = "SELECT
			SUM(created_at >= %d AND created_at < %d) AS cur_total,
			SUM(created_at >= %d AND created_at < %d AND field IN ({$in('price')})) AS cur_price,
			SUM(created_at >= %d AND created_at < %d AND field IN ({$in('stock')})) AS cur_stock,
			SUM(created_at >= %d AND created_at < %d AND field IN ({$in('other')})) AS cur_other,
			SUM(created_at >= %d AND created_at < %d) AS prev_total,
			SUM(created_at >= %d AND created_at < %d AND field IN ({$in('price')})) AS prev_price,
			SUM(created_at >= %d AND created_at < %d AND field IN ({$in('stock')})) AS prev_stock,
			COUNT(DISTINCT CASE WHEN created_at >= %d AND created_at < %d THEN COALESCE(NULLIF(parent_id,0),product_id) END) AS cur_products,
			COUNT(DISTINCT CASE WHEN created_at >= %d AND created_at < %d AND source <> 'order' THEN user_id END) AS cur_users
		FROM {$table} WHERE created_at >= %d AND created_at < %d" . $filt;
	$a   = array(
		$start,
		$end,
		$start,
		$end,
		$start,
		$end,
		$start,
		$end,
		$prev_start,
		$start,
		$prev_start,
		$start,
		$prev_start,
		$start,
		$start,
		$end,
		$start,
		$end,
		$prev_start,
		$end,
	);
	$row = $wpdb->get_row( $wpdb->prepare( $sql, array_merge( $a, $fargs ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- own table, placeholders built above.
	$kpi = array();
	foreach ( array( 'cur_total', 'cur_price', 'cur_stock', 'cur_other', 'prev_total', 'prev_price', 'prev_stock', 'cur_products', 'cur_users' ) as $k ) {
		$kpi[ $k ] = (int) ( $row[ $k ] ?? 0 );
	}

	// Daily series for the chart (at least the last 7 days).
	$chart_days  = max( 7, $days );
	$today       = new DateTimeImmutable( 'today', wp_timezone() );
	$chart_start = $today->modify( '-' . ( $chart_days - 1 ) . ' days' )->getTimestamp();
	$chart_end   = $today->modify( '+1 day' )->getTimestamp();
	$series_sql  = "SELECT FLOOR((created_at - %d) / 86400) AS d,
			SUM(field IN ({$in('price')})) AS p, SUM(field IN ({$in('stock')})) AS s, SUM(field IN ({$in('other')})) AS o
		FROM {$table} WHERE created_at >= %d AND created_at < %d" . $filt . ' GROUP BY d';
	$series_rows = $wpdb->get_results( $wpdb->prepare( $series_sql, array_merge( array( $chart_start, $chart_start, $chart_end ), $fargs ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- own table.
	$by_day      = array();
	foreach ( (array) $series_rows as $r ) {
		$by_day[ (int) $r['d'] ] = array( (int) $r['p'], (int) $r['s'], (int) $r['o'] );
	}
	$series = array();
	for ( $i = 0; $i < $chart_days; $i++ ) {
		$day      = $today->modify( '-' . ( $chart_days - 1 - $i ) . ' days' );
		$v        = $by_day[ $i ] ?? array( 0, 0, 0 );
		$series[] = array(
			'label' => $chart_days - 1 === $i ? __( 'Today', 'expert-price-manager' ) : wp_date( $chart_days > 7 ? 'j' : 'l', $day->getTimestamp() + 12 * HOUR_IN_SECONDS ),
			'price' => $v[0],
			'stock' => $v[1],
			'other' => $v[2],
		);
	}

	// Users (period, current filters).
	$users_sql  = "SELECT user_id, source = 'order' AS is_order, COUNT(*) AS c, MAX(created_at) AS last_at
		FROM {$table} WHERE created_at >= %d AND created_at < %d" . $filt . ' GROUP BY user_id, is_order ORDER BY c DESC LIMIT 6';
	$users_rows = $wpdb->get_results( $wpdb->prepare( $users_sql, array_merge( array( $start, $end ), $fargs ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- own table.
	$users      = array();
	foreach ( (array) $users_rows as $r ) {
		$users[] = array(
			'id'       => (int) $r['is_order'] ? 'order' : (int) $r['user_id'],
			'name'     => epm_log_who( (int) $r['user_id'], (int) $r['is_order'] ? 'order' : 'user' ),
			'count'    => (int) $r['c'],
			'last_at'  => wp_date( 'H:i', (int) $r['last_at'] ),
			'is_order' => (bool) $r['is_order'],
		);
	}

	// Top product by price changes.
	$top = $wpdb->get_row( $wpdb->prepare( "SELECT product_name, COUNT(*) AS c FROM {$table} WHERE created_at >= %d AND created_at < %d AND field IN ({$in('price')})" . $filt . ' GROUP BY COALESCE(NULLIF(parent_id,0),product_id), product_name ORDER BY c DESC LIMIT 1', array_merge( array( $start, $end ), $fargs ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- own table.

	// Table.
	$total = (int) $kpi['cur_total'];
	$list  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE created_at >= %d AND created_at < %d" . $filt . ' ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d', array_merge( array( $start, $end ), $fargs, array( $per, ( $page - 1 ) * $per ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- own table.
	$rows  = array();
	foreach ( (array) $list as $r ) {
		$pid   = (int) ( $r['parent_id'] ? $r['parent_id'] : $r['product_id'] );
		$field = $r['field'];
		$pct   = null;
		if ( in_array( $field, array( 'regular_price', 'sale_price' ), true ) && is_numeric( $r['old_value'] ) && is_numeric( $r['new_value'] ) && (float) $r['old_value'] > 0 ) {
			$pct = round( ( (float) $r['new_value'] - (float) $r['old_value'] ) / (float) $r['old_value'] * 100 );
		}
		$rows[] = array(
			'id'         => (int) $r['id'],
			'date'       => epm_jalali_datetime_from_timestamp( (int) $r['created_at'], false ),
			'time'       => wp_date( 'H:i', (int) $r['created_at'] ),
			'name'       => $r['product_name'],
			'sku'        => $r['sku'],
			'thumb'      => (string) get_the_post_thumbnail_url( $pid, 'thumbnail' ),
			'edit_link'  => get_edit_post_link( $pid, 'raw' ),
			'group'      => epm_log_field_group( $field ),
			'field'      => epm_log_field_label( $field ),
			'old'        => epm_log_display_value( $field, $r['old_value'] ),
			'new'        => epm_log_display_value( $field, $r['new_value'] ),
			'pct'        => $pct,
			'who'        => epm_log_who( (int) $r['user_id'], $r['source'] ),
			'is_order'   => 'order' === $r['source'],
			'is_revert'  => 'revert' === $r['source'],
			'can_revert' => 'user' === $r['source'] && 'order' !== $r['source'],
		);
	}

	// Retention notice: show it when the oldest record is close to the purge threshold.
	$oldest = (int) $wpdb->get_var( "SELECT MIN(created_at) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- own table, no user input.
	$notice = null;
	if ( $oldest ) {
		$age = (int) floor( ( time() - $oldest ) / DAY_IN_SECONDS );
		if ( $age >= EPM_LOG_WARN_DAYS ) {
			$notice = array(
				'days_left' => max( 0, EPM_LOG_RETENTION_DAYS - $age ),
				'oldest'    => epm_jalali_datetime_from_timestamp( $oldest, false ),
			);
		}
	}

	// Users for the filter drop-down (all users with history).
	$filter_users = array();
	if ( 1 === $page ) {
		$fu = $wpdb->get_results( "SELECT DISTINCT user_id, source = 'order' AS is_order FROM {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- own table, no user input.
		foreach ( (array) $fu as $r ) {
			$filter_users[] = array(
				'id'   => (int) $r['is_order'] ? 'order' : (int) $r['user_id'],
				'name' => epm_log_who( (int) $r['user_id'], (int) $r['is_order'] ? 'order' : 'user' ),
			);
		}
	}

	wp_send_json_success(
		array(
			'kpi'          => $kpi,
			'series'       => $series,
			'users'        => $users,
			'filter_users' => $filter_users,
			'top_product'  => $top ? array(
				'name'  => $top['product_name'],
				'count' => (int) $top['c'],
			) : null,
			'rows'         => $rows,
			'total'        => $total,
			'per_page'     => $per,
			'notice'       => $notice,
		)
	);
}

add_action( 'wp_ajax_epm_log_revert', 'epm_ajax_log_revert' );
/** Put a field back to the value it had before a logged change. */
function epm_ajax_log_revert() {
	check_ajax_referer( 'epm_admin_pricing_action', 'nonce' );
	if ( ! epm_can_access_price_tab() ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'expert-price-manager' ) ), 403 );
	}
	global $wpdb;
	$id  = absint( $_POST['id'] ?? 0 );
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . epm_log_table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- own table.
	if ( ! $row || 'user' !== $row['source'] ) {
		wp_send_json_error( array( 'message' => __( 'This change cannot be reverted.', 'expert-price-manager' ) ), 400 );
	}
	$product = wc_get_product( (int) $row['product_id'] );
	if ( ! $product ) {
		wp_send_json_error( array( 'message' => __( 'Product not found.', 'expert-price-manager' ) ), 404 );
	}
	$before = epm_log_snapshot( $product );
	$field  = $row['field'];
	$old    = (string) $row['old_value'];
	if ( ! array_key_exists( $field, $before ) || $before[ $field ] !== (string) $row['new_value'] ) {
		wp_send_json_error( array( 'message' => __( 'The value has changed since then; it cannot be reverted automatically.', 'expert-price-manager' ) ), 409 );
	}
	if ( 'regular_price' === $field && '' === $old ) {
		wp_send_json_error( array( 'message' => __( 'This change cannot be reverted.', 'expert-price-manager' ) ), 400 );
	}
	switch ( $field ) {
		case 'sku':
			$dup = '' !== $old ? wc_get_product_id_by_sku( $old ) : 0;
			if ( $dup && (int) $dup !== $product->get_id() ) {
				wp_send_json_error( array( 'message' => __( 'This SKU is already used by another product.', 'expert-price-manager' ) ), 409 );
			}
			$product->set_sku( $old );
			break;
		case 'regular_price':
			$product->set_regular_price( $old );
			break;
		case 'sale_price':
			$product->set_sale_price( $old );
			break;
		case 'manage_stock':
			$product->set_manage_stock( '1' === $old );
			break;
		case 'stock_quantity':
			if ( ! $product->get_manage_stock() || '' === $old ) {
				wp_send_json_error( array( 'message' => __( 'This change cannot be reverted.', 'expert-price-manager' ) ), 400 );
			}
			$product->set_stock_quantity( (float) $old );
			break;
		case 'stock_status':
			$product->set_stock_status( 'instock' === $old ? 'instock' : 'outofstock' );
			break;
		case 'sold_individually':
			$product->set_sold_individually( '1' === $old );
			break;
		default:
			wp_send_json_error( array( 'message' => __( 'This change cannot be reverted.', 'expert-price-manager' ) ), 400 );
	}
	$product->save();
	epm_log_diff( $product, $before, epm_log_snapshot( $product ), 'revert', get_current_user_id() );
	$pid = (int) ( $product->get_parent_id() ? $product->get_parent_id() : $product->get_id() );
	update_post_meta( $pid, '_price_update_date', epm_current_jalali_datetime() );
	update_post_meta( $pid, '_epm_price_updated_by', get_current_user_id() );
	delete_post_meta( $pid, '_epm_update_source' );
	epm_sync_theme_last_modified( $pid );
	wc_delete_product_transients( $product->get_id() );
	wp_send_json_success();
}
