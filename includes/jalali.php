<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Jalali (Persian) date helpers - used to record the last price/stock update time.
 *
 * @package ExpertPriceManager
 */

function epm_gregorian_to_jalali( $g_y, $g_m, $g_d ) {
	$g_days_in_month = array( 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
	$j_days_in_month = array( 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29 );
	$gy              = $g_y - 1600;
	$gm              = $g_m - 1;
	$gd              = $g_d - 1;
	$g_day_no        = 365 * $gy + (int) ( ( $gy + 3 ) / 4 ) - (int) ( ( $gy + 99 ) / 100 ) + (int) ( ( $gy + 399 ) / 400 );
	for ( $i = 0; $i < $gm; ++$i ) {
		$g_day_no += $g_days_in_month[ $i ];
	}
	if ( $gm > 1 && ( ( $g_y % 4 == 0 && $g_y % 100 != 0 ) || $g_y % 400 == 0 ) ) {
		++$g_day_no;
	}
	$g_day_no += $gd;
	$j_day_no  = $g_day_no - 79;
	$j_np      = (int) ( $j_day_no / 12053 );
	$j_day_no  = $j_day_no % 12053;
	$jy        = 979 + 33 * $j_np + 4 * (int) ( $j_day_no / 1461 );
	$j_day_no %= 1461;
	if ( $j_day_no >= 366 ) {
		$jy      += (int) ( ( $j_day_no - 1 ) / 365 );
		$j_day_no = ( $j_day_no - 1 ) % 365;
	}
	$jm = 0;
	for ( $i = 0; $i < 11 && $j_day_no >= $j_days_in_month[ $i ]; ++$i ) {
		$j_day_no -= $j_days_in_month[ $i ];
		++$jm;
	}
	$jm += 1;
	$jd  = $j_day_no + 1;
	return array( $jy, $jm, $jd );
}

/** Jalali date and time from a timestamp (based on the site time zone). */
function epm_jalali_datetime_from_timestamp( $timestamp, $with_time = true ) {
	$timestamp = (int) $timestamp;
	if ( $timestamp <= 0 ) {
		return '';
	}
	list($jy, $jm, $jd) = epm_gregorian_to_jalali( (int) wp_date( 'Y', $timestamp ), (int) wp_date( 'n', $timestamp ), (int) wp_date( 'j', $timestamp ) );
	$out                = sprintf( '%04d/%02d/%02d', $jy, $jm, $jd );
	return $with_time ? $out . ' ' . wp_date( 'H:i', $timestamp ) : $out;
}

function epm_current_jalali_datetime() {
	return epm_jalali_datetime_from_timestamp( time(), true );
}
