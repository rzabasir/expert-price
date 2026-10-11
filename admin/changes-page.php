<?php
/**
 * Change history dashboard (who changed prices/stock, and when).
 *
 * @package ExpertPriceManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once EPM_PLUGIN_DIR . 'admin/common.php';

add_action( 'admin_enqueue_scripts', 'epm_enqueue_changes_assets', 20 );
/** Assets of the dashboard (only on its own screen). */
function epm_enqueue_changes_assets() {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check.
	if ( 'epm-changes' !== $page ) {
		return;
	}
	wp_enqueue_style( 'epm-admin', EPM_PLUGIN_URL . 'assets/css/admin.css', array(), epm_asset_version( 'admin.css' ) );
	wp_enqueue_style( 'epm-changes', EPM_PLUGIN_URL . 'assets/css/changes.css', array( 'epm-admin' ), epm_asset_version( 'changes.css' ) );
	wp_enqueue_script( 'epm-changes', EPM_PLUGIN_URL . 'assets/js/changes.js', array(), epm_asset_version( 'changes.js' ), true );
	wp_localize_script(
		'epm-changes',
		'epmChanges',
		array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'epm_admin_pricing_action' ),
			'locale'    => str_replace( '_', '-', determine_locale() ),
			'retention' => EPM_LOG_RETENTION_DAYS,
			'i18n'      => epm_get_changes_js_i18n(),
		)
	);
}

/**
 * Strings used by assets/js/changes.js (key = English source string).
 *
 * @return array<string,string>
 */
function epm_get_changes_js_i18n() {
	return array(
		/* translators: %s: URL. */
		'The request was redirected to %s by another plugin or a security rule.' => __( 'The request was redirected to %s by another plugin or a security rule.', 'expert-price-manager' ),
		'Unexpected server response. Check the browser Network tab or the PHP error log.' => __( 'Unexpected server response. Check the browser Network tab or the PHP error log.', 'expert-price-manager' ),
		'Today'                                 => __( 'Today', 'expert-price-manager' ),
		'Yesterday'                             => __( 'Yesterday', 'expert-price-manager' ),
		'Last 7 days'                           => __( 'Last 7 days', 'expert-price-manager' ),
		'Last 30 days'                          => __( 'Last 30 days', 'expert-price-manager' ),
		'All users'                             => __( 'All users', 'expert-price-manager' ),
		'Customer orders'                       => __( 'Customer orders', 'expert-price-manager' ),
		'All change types'                      => __( 'All change types', 'expert-price-manager' ),
		'Price'                                 => __( 'Price', 'expert-price-manager' ),
		'Stock'                                 => __( 'Stock', 'expert-price-manager' ),
		'Other'                                 => __( 'Other', 'expert-price-manager' ),
		'Total changes'                         => __( 'Total changes', 'expert-price-manager' ),
		'Price changes'                         => __( 'Price changes', 'expert-price-manager' ),
		'Stock changes'                         => __( 'Stock changes', 'expert-price-manager' ),
		'Products changed'                      => __( 'Products changed', 'expert-price-manager' ),
		/* translators: %s: number of active users. */
		'%s active users'                       => __( '%s active users', 'expert-price-manager' ),
		/* translators: %s: percentage. */
		'%s vs previous period'                 => __( '%s vs previous period', 'expert-price-manager' ),
		'No change'                             => __( 'No change', 'expert-price-manager' ),
		'Change trend'                          => __( 'Change trend', 'expert-price-manager' ),
		'Change types'                          => __( 'Change types', 'expert-price-manager' ),
		'Most active users'                     => __( 'Most active users', 'expert-price-manager' ),
		/* translators: %s: time of day. */
		'Last change %s'                        => __( 'Last change %s', 'expert-price-manager' ),
		/* translators: 1: product name, 2: number of changes. */
		'Most price changes: %1$s (%2$s times)' => __( 'Most price changes: %1$s (%2$s times)', 'expert-price-manager' ),
		'Change log'                            => __( 'Change log', 'expert-price-manager' ),
		/* translators: %s: number of records. */
		'%s records'                            => __( '%s records', 'expert-price-manager' ),
		'Search product name or SKU...'         => __( 'Search product name or SKU...', 'expert-price-manager' ),
		'Time'                                  => __( 'Time', 'expert-price-manager' ),
		'Product'                               => __( 'Product', 'expert-price-manager' ),
		'Type'                                  => __( 'Type', 'expert-price-manager' ),
		'Change'                                => __( 'Change', 'expert-price-manager' ),
		'By'                                    => __( 'By', 'expert-price-manager' ),
		'Revert'                                => __( 'Revert', 'expert-price-manager' ),
		'Reverted'                              => __( 'Reverted', 'expert-price-manager' ),
		'Revert this change and restore the previous value?' => __( 'Revert this change and restore the previous value?', 'expert-price-manager' ),
		'The change was reverted.'              => __( 'The change was reverted.', 'expert-price-manager' ),
		'No changes in this period.'            => __( 'No changes in this period.', 'expert-price-manager' ),
		'Previous'                              => __( 'Previous', 'expert-price-manager' ),
		'Next'                                  => __( 'Next', 'expert-price-manager' ),
		/* translators: 1: first row, 2: last row, 3: total rows. */
		'Showing %1$s to %2$s of %3$s'          => __( 'Showing %1$s to %2$s of %3$s', 'expert-price-manager' ),
		'Error loading data.'                   => __( 'Error loading data.', 'expert-price-manager' ),
		'Server connection error.'              => __( 'Server connection error.', 'expert-price-manager' ),
		'Error'                                 => __( 'Error', 'expert-price-manager' ),
		'Live updates · every 30 seconds'       => __( 'Live updates · every 30 seconds', 'expert-price-manager' ),
		/* translators: %d: retention in days. */
		'History is kept for %d days and then deleted automatically.' => __( 'History is kept for %d days and then deleted automatically.', 'expert-price-manager' ),
		/* translators: 1: number of days, 2: date of the oldest record. */
		'Records older than the retention limit will be deleted in %1$s day(s) (oldest record: %2$s). Export what you need first.' => __( 'Records older than the retention limit will be deleted in %1$s day(s) (oldest record: %2$s). Export what you need first.', 'expert-price-manager' ),
		/* translators: %s: date of the oldest record. */
		'The oldest records (from %s) will be deleted in the next automatic cleanup (within 24 hours).' => __( 'The oldest records (from %s) will be deleted in the next automatic cleanup (within 24 hours).', 'expert-price-manager' ),
		'Reverted change'                       => __( 'Reverted change', 'expert-price-manager' ),
	);
}

/** Dashboard markup (content is filled by changes.js). */
function epm_render_changes_page() {
	?>
	<div class="wrap epm-admin-pricing epm-changes" id="epm-changes-app">
		<?php epm_render_hero( __( 'Product Changes Dashboard', 'expert-price-manager' ), __( 'Who changed prices and stock, when, and what the change was', 'expert-price-manager' ) ); ?>
		<div id="epm-ch-notice"></div>
		<div id="epm-ch-root"><p class="epm-ch-loading"><?php echo esc_html__( 'Loading...', 'expert-price-manager' ); ?></p></div>
		<?php epm_render_footer(); ?>
	</div>
	<?php
}
