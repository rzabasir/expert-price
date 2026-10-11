<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single pricing page - only for shop managers and administrators.
 * Four main fields: SKU, regular price, sale price, stock quantity.
 * Variable products show a collapsible box with the variations and the same fields.
 *
 * @package ExpertPriceManager
 */

require_once EPM_PLUGIN_DIR . 'ajax/admin-export.php';
require_once EPM_PLUGIN_DIR . 'admin/common.php';

add_action( 'admin_enqueue_scripts', 'epm_enqueue_admin_assets', 20 );
function epm_enqueue_admin_assets() {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check.
	if ( 'epm-price-products' !== $page ) {
		return;
	}
	wp_enqueue_style( 'epm-admin', EPM_PLUGIN_URL . 'assets/css/admin.css', array(), epm_asset_version( 'admin.css' ) );
	wp_enqueue_script( 'epm-admin', EPM_PLUGIN_URL . 'assets/js/admin.js', array(), epm_asset_version( 'admin.js' ), true );
	wp_localize_script(
		'epm-admin',
		'epmData',
		array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'epm_admin_pricing_action' ),
			'downloadBase' => wp_nonce_url( admin_url( 'admin-ajax.php?action=epm_export_download' ), 'epm_export_download' ),
			'templates'    => (object) epm_export_get_templates(),
			'i18n'         => epm_get_js_i18n(),
		)
	);
}

/**
 * Strings used by assets/js/admin.js. The array key is the English source string,
 * the value is its translation for the current locale.
 *
 * @return array<string,string>
 */
function epm_get_js_i18n() {
	return array(
		/* translators: %s: URL. */
		'The request was redirected to %s by another plugin or a security rule.' => __( 'The request was redirected to %s by another plugin or a security rule.', 'expert-price-manager' ),
		'Unexpected server response. Check the browser Network tab or the PHP error log.' => __( 'Unexpected server response. Check the browser Network tab or the PHP error log.', 'expert-price-manager' ),
		'Save / update'                              => __( 'Save / update', 'expert-price-manager' ),
		'Save'                                       => __( 'Save', 'expert-price-manager' ),
		'Delete product'                             => __( 'Delete product', 'expert-price-manager' ),
		'Saved.'                                     => __( 'Saved.', 'expert-price-manager' ),
		'Error'                                      => __( 'Error', 'expert-price-manager' ),
		'Server connection error. Please try again.' => __( 'Server connection error. Please try again.', 'expert-price-manager' ),
		'Loading variations...'                      => __( 'Loading variations...', 'expert-price-manager' ),
		'No variations found.'                       => __( 'No variations found.', 'expert-price-manager' ),
		'Variation'                                  => __( 'Variation', 'expert-price-manager' ),
		'Regular price'                              => __( 'Regular price', 'expert-price-manager' ),
		'Sale price'                                 => __( 'Sale price', 'expert-price-manager' ),
		'Stock quantity'                             => __( 'Stock quantity', 'expert-price-manager' ),
		'In stock'                                   => __( 'In stock', 'expert-price-manager' ),
		'Manage stock'                               => __( 'Manage stock', 'expert-price-manager' ),
		'Sold individually'                          => __( 'Sold individually', 'expert-price-manager' ),
		'Track stock quantity for this product'      => __( 'Track stock quantity for this product', 'expert-price-manager' ),
		'Limit purchases to 1 item per order'        => __( 'Limit purchases to 1 item per order', 'expert-price-manager' ),
		'Actions'                                    => __( 'Actions', 'expert-price-manager' ),
		'Error loading variations.'                  => __( 'Error loading variations.', 'expert-price-manager' ),
		'Previous'                                   => __( 'Previous', 'expert-price-manager' ),
		/* translators: %s: placeholder replaced by a value in the script. */
		'Page %1$s of %2$s (%3$s products)'          => __( 'Page %1$s of %2$s (%3$s products)', 'expert-price-manager' ),
		'Next'                                       => __( 'Next', 'expert-price-manager' ),
		'Loading...'                                 => __( 'Loading...', 'expert-price-manager' ),
		'Error loading products.'                    => __( 'Error loading products.', 'expert-price-manager' ),
		'No products found.'                         => __( 'No products found.', 'expert-price-manager' ),
		'View'                                       => __( 'View', 'expert-price-manager' ),
		'Edit'                                       => __( 'Edit', 'expert-price-manager' ),
		'Add a redirect in Rank Math'                => __( 'Add a redirect in Rank Math', 'expert-price-manager' ),
		'Redirect'                                   => __( 'Redirect', 'expert-price-manager' ),
		/* translators: %s: placeholder replaced by a value in the script. */
		'%s variations'                              => __( '%s variations', 'expert-price-manager' ),
		/* translators: 1: date and time, 2: user display name. */
		'Last updated: %1$s by %2$s'                 => __( 'Last updated: %1$s by %2$s', 'expert-price-manager' ),
		/* translators: %s: placeholder replaced by a value in the script. */
		'Last updated: %s'                           => __( 'Last updated: %s', 'expert-price-manager' ),
		/* translators: %s: placeholder replaced by a value in the script. */
		'The price of this product type (%s) cannot be edited from this panel.' => __( 'The price of this product type (%s) cannot be edited from this panel.', 'expert-price-manager' ),
		'This product has several variations; set the price and stock of each variation in the box below.' => __( 'This product has several variations; set the price and stock of each variation in the box below.', 'expert-price-manager' ),
		'Server connection error.'                   => __( 'Server connection error.', 'expert-price-manager' ),
		'Move to trash'                              => __( 'Move to trash', 'expert-price-manager' ),
		'Delete permanently + set 410'               => __( 'Delete permanently + set 410', 'expert-price-manager' ),
		'Delete permanently + 301 to category'       => __( 'Delete permanently + 301 to category', 'expert-price-manager' ),
		'Delete permanently + 301 to destination'    => __( 'Delete permanently + 301 to destination', 'expert-price-manager' ),
		'Checking...'                                => __( 'Checking...', 'expert-price-manager' ),
		/* translators: %s: placeholder replaced by a value in the script. */
		'URL: %s'                                    => __( 'URL: %s', 'expert-price-manager' ),
		' — The Rank Math Redirections module is not active; only moving to the trash (without a redirect) is possible.' => __( ' — The Rank Math Redirections module is not active; only moving to the trash (without a redirect) is possible.', 'expert-price-manager' ),
		/* translators: %s: placeholder replaced by a value in the script. */
		'This product appears in %s existing orders. Deleting it permanently breaks the product link in past orders and reports.' => __( 'This product appears in %s existing orders. Deleting it permanently breaks the product link in past orders and reports.', 'expert-price-manager' ),
		'Enter the redirect destination URL.'        => __( 'Enter the redirect destination URL.', 'expert-price-manager' ),
		'The destination URL must start with http:// or https://.' => __( 'The destination URL must start with http:// or https://.', 'expert-price-manager' ),
		'The product will be deleted permanently and cannot be restored. Continue?' => __( 'The product will be deleted permanently and cannot be restored. Continue?', 'expert-price-manager' ),
		'— Select a template —'                      => __( '— Select a template —', 'expert-price-manager' ),
		'Enter a template name.'                     => __( 'Enter a template name.', 'expert-price-manager' ),
		'Template saved.'                            => __( 'Template saved.', 'expert-price-manager' ),
		'Template deleted.'                          => __( 'Template deleted.', 'expert-price-manager' ),
		'Select at least one column.'                => __( 'Select at least one column.', 'expert-price-manager' ),
		'Preparing...'                               => __( 'Preparing...', 'expert-price-manager' ),
		'Error building the export.'                 => __( 'Error building the export.', 'expert-price-manager' ),
		'No products found for these filters.'       => __( 'No products found for these filters.', 'expert-price-manager' ),
		/* translators: %s: placeholder replaced by a value in the script. */
		'%1$s rows out of %2$s products'             => __( '%1$s rows out of %2$s products', 'expert-price-manager' ),
		/* translators: %s: placeholder replaced by a value in the script. */
		'Download XLSX file (%s rows)'               => __( 'Download XLSX file (%s rows)', 'expert-price-manager' ),
	);
}


function epm_render_admin_pricing_page() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		echo '<div class="wrap"><h1>' . esc_html__( 'Product Pricing', 'expert-price-manager' ) . '</h1><div class="notice notice-error"><p>' . esc_html__( 'Please install and activate WooCommerce first.', 'expert-price-manager' ) . '</p></div></div>';
		return;
	}
	if ( ! epm_can_access_price_tab() ) {
		wp_die( esc_html__( 'This page is only accessible to shop managers and administrators.', 'expert-price-manager' ) );
	}

	$categories     = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		)
	);
	$brand_tax      = epm_get_brand_taxonomy();
	$brands         = $brand_tax ? get_terms(
		array(
			'taxonomy'   => $brand_tax,
			'hide_empty' => false,
		)
	) : array();
	$export_columns = epm_export_columns();
	$default_cols   = array( 'id', 'name', 'sku', 'regular_price', 'sale_price', 'stock_quantity', 'stock_status' );
	?>
	<div class="wrap epm-admin-pricing">
		<?php epm_render_hero( __( 'Product Pricing', 'expert-price-manager' ), __( 'Edit prices, SKU and stock of all your products in one place', 'expert-price-manager' ) ); ?>

		<div class="epm-toolbar">
			<input type="text" id="epm-admin-search" placeholder="<?php echo esc_attr__( 'Search by product name or URL/slug...', 'expert-price-manager' ); ?>">
			<select id="epm-admin-category-filter">
				<option value="0"><?php echo esc_html__( 'All categories', 'expert-price-manager' ); ?></option>
				<?php
				foreach ( $categories as $cat ) :
					?>
					<option value="<?php echo esc_attr( $cat->term_id ); ?>"><?php echo esc_html( $cat->name ); ?></option><?php endforeach; ?>
			</select>
			<?php if ( $brand_tax ) : ?>
			<select id="epm-admin-brand-filter">
				<option value="0"><?php echo esc_html__( 'All brands', 'expert-price-manager' ); ?></option>
				<?php
				foreach ( $brands as $brand ) :
					?>
					<option value="<?php echo esc_attr( $brand->term_id ); ?>"><?php echo esc_html( $brand->name ); ?></option><?php endforeach; ?>
			</select>
			<?php endif; ?>
			<select id="epm-admin-stock">
				<option value=""><?php echo esc_html__( 'All stock statuses', 'expert-price-manager' ); ?></option>
				<option value="instock"><?php echo esc_html__( 'In stock', 'expert-price-manager' ); ?></option>
				<option value="outofstock"><?php echo esc_html__( 'Out of stock', 'expert-price-manager' ); ?></option>
			</select>
			<select id="epm-admin-sort">
				<option value="name_asc"><?php echo esc_html__( 'Name (A–Z)', 'expert-price-manager' ); ?></option>
				<option value="name_desc"><?php echo esc_html__( 'Name (Z–A)', 'expert-price-manager' ); ?></option>
				<option value="price_asc"><?php echo esc_html__( 'Price (low to high)', 'expert-price-manager' ); ?></option>
				<option value="price_desc"><?php echo esc_html__( 'Price (high to low)', 'expert-price-manager' ); ?></option>
			</select>
			<label class="epm-perpage"><?php echo esc_html__( 'Items per page:', 'expert-price-manager' ); ?>
				<select id="epm-admin-perpage">
					<?php foreach ( array( 10, 20, 50, 100, 200 ) as $n ) : ?>
					<option value="<?php echo (int) $n; ?>"<?php selected( 20, $n ); ?>><?php echo (int) $n; ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<button type="button" class="button" id="epm-export-toggle"><?php echo esc_html__( 'Export XLSX', 'expert-price-manager' ); ?></button>
		</div>

		<div id="epm-export-panel" class="epm-export-panel" style="display:none;">
			<h2><?php echo esc_html__( 'Export products to XLSX', 'expert-price-manager' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'The search, category and brand filters of this page apply to the export. Choose columns and reorder them with the arrows.', 'expert-price-manager' ); ?></p>
			<div class="epm-export-grid">
				<div>
					<h3><?php echo esc_html__( 'Columns', 'expert-price-manager' ); ?></h3>
					<ul id="epm-export-cols" class="epm-export-cols">
						<?php
						$ordered = array_merge( $default_cols, array_diff( array_keys( $export_columns ), $default_cols ) );
						foreach ( $ordered as $key ) :
							$def = $export_columns[ $key ];
							?>
						<li data-key="<?php echo esc_attr( $key ); ?>">
							<label><input type="checkbox" class="epm-col-check" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $default_cols, true ) ); ?>> <?php echo esc_html( $def[0] ); ?></label>
							<span class="epm-col-group"><?php echo esc_html( $def[1] ); ?></span>
							<span class="epm-col-move"><button type="button" class="button-link epm-col-up" title="<?php echo esc_attr__( 'Move up', 'expert-price-manager' ); ?>">&#9650;</button> <button type="button" class="button-link epm-col-down" title="<?php echo esc_attr__( 'Move down', 'expert-price-manager' ); ?>">&#9660;</button></span>
						</li>
						<?php endforeach; ?>
					</ul>
					<p><label><?php echo esc_html__( 'Custom meta (meta keys separated by comma or space, e.g. ACF fields):', 'expert-price-manager' ); ?><br>
						<input type="text" id="epm-export-meta" class="large-text" placeholder="_my_meta, acf_field" dir="ltr"></label></p>
				</div>
				<div>
					<h3><?php echo esc_html__( 'Filters and options', 'expert-price-manager' ); ?></h3>
					<p><?php echo esc_html__( 'Publish status:', 'expert-price-manager' ); ?>
						<label><input type="checkbox" class="epm-status-check" value="publish" checked> <?php echo esc_html__( 'Published', 'expert-price-manager' ); ?></label>
						<label><input type="checkbox" class="epm-status-check" value="draft" checked> <?php echo esc_html__( 'Draft', 'expert-price-manager' ); ?></label>
						<label><input type="checkbox" class="epm-status-check" value="private" checked> <?php echo esc_html__( 'Private', 'expert-price-manager' ); ?></label>
						<label><input type="checkbox" class="epm-status-check" value="pending" checked> <?php echo esc_html__( 'Pending', 'expert-price-manager' ); ?></label>
					</p>
					<p><label><?php echo esc_html__( 'Stock:', 'expert-price-manager' ); ?> <select id="epm-export-stock"><option value=""><?php echo esc_html__( 'All', 'expert-price-manager' ); ?></option><option value="instock"><?php echo esc_html__( 'In stock only', 'expert-price-manager' ); ?></option><option value="outofstock"><?php echo esc_html__( 'Out of stock only', 'expert-price-manager' ); ?></option></select></label></p>
					<p><label><?php echo esc_html__( 'Only:', 'expert-price-manager' ); ?> <select id="epm-export-extra"><option value=""><?php echo esc_html__( 'All products', 'expert-price-manager' ); ?></option><option value="on_sale"><?php echo esc_html__( 'On sale', 'expert-price-manager' ); ?></option><option value="no_price"><?php echo esc_html__( 'Without price', 'expert-price-manager' ); ?></option><option value="no_sku"><?php echo esc_html__( 'Without SKU', 'expert-price-manager' ); ?></option></select></label></p>
					<p><label><input type="checkbox" id="epm-export-variations"> <?php echo esc_html__( 'Put each variation of variable products on its own row', 'expert-price-manager' ); ?></label></p>

					<h3><?php echo esc_html__( 'Saved templates', 'expert-price-manager' ); ?></h3>
					<p>
						<select id="epm-template-select"><option value=""><?php echo esc_html__( '— Select a template —', 'expert-price-manager' ); ?></option></select>
						<button type="button" class="button" id="epm-template-delete"><?php echo esc_html__( 'Delete template', 'expert-price-manager' ); ?></button>
					</p>
					<p>
						<input type="text" id="epm-template-name" placeholder="<?php echo esc_attr__( 'New template name', 'expert-price-manager' ); ?>">
						<button type="button" class="button" id="epm-template-save"><?php echo esc_html__( 'Save columns as template', 'expert-price-manager' ); ?></button>
					</p>
				</div>
			</div>
			<p>
				<button type="button" class="button button-primary" id="epm-export-start"><?php echo esc_html__( 'Build export file', 'expert-price-manager' ); ?></button>
				<span id="epm-export-status"></span>
			</p>
			<div id="epm-export-progress" class="epm-progress" style="display:none;"><div id="epm-export-bar"></div></div>
			<p id="epm-export-result" style="display:none;"></p>
		</div>

		<div id="epm-delete-modal" class="epm-modal" style="display:none;" role="dialog" aria-modal="true">
			<div class="epm-modal-box">
				<h2><?php echo esc_html__( 'Delete product', 'expert-price-manager' ); ?></h2>
				<p id="epm-delete-name" class="epm-delete-name"></p>
				<p id="epm-delete-info" class="description"></p>
				<p><strong><?php echo esc_html__( 'Create a redirect for this product URL?', 'expert-price-manager' ); ?></strong></p>
				<p><label><input type="radio" name="epm-want-redirect" value="no" checked> <?php echo esc_html__( 'No — just move it to the trash (can be restored)', 'expert-price-manager' ); ?></label></p>
				<p><label><input type="radio" name="epm-want-redirect" value="yes"> <?php echo esc_html__( 'Yes — delete the product permanently and create a redirect (Rank Math)', 'expert-price-manager' ); ?></label></p>
				<div id="epm-redirect-types" style="display:none;margin-right:22px;">
					<p><strong><?php echo esc_html__( 'Redirect type:', 'expert-price-manager' ); ?></strong></p>
					<p><label><input type="radio" name="epm-delete-mode" value="gone_410" checked> <?php echo esc_html__( '410 — this page is permanently gone (no destination)', 'expert-price-manager' ); ?></label></p>
					<p><label><input type="radio" name="epm-delete-mode" value="redirect_cat"> <?php echo esc_html__( '301 — redirect to the product category', 'expert-price-manager' ); ?></label></p>
					<p><label><input type="radio" name="epm-delete-mode" value="redirect_url"> <?php echo esc_html__( '301 — redirect to a custom URL', 'expert-price-manager' ); ?></label></p>
					<p><input type="url" id="epm-delete-url" class="large-text" placeholder="https://example.com/..." style="display:none;" dir="ltr"></p>
				</div>
				<p id="epm-delete-warning" class="epm-delete-warning" style="display:none;"></p>
				<p>
					<button type="button" class="button button-primary" id="epm-delete-confirm"><?php echo esc_html__( 'Move to trash', 'expert-price-manager' ); ?></button>
					<button type="button" class="button" id="epm-delete-cancel"><?php echo esc_html__( 'Cancel', 'expert-price-manager' ); ?></button>
				</p>
			</div>
		</div>
		<div id="epm-admin-message" style="display:none;"></div>

		<div class="epm-table-scroll">
			<table class="widefat striped epm-resizable-table" id="epm-admin-table">
				<colgroup>
					<col data-col-key="name" style="width:280px">
					<col data-col-key="sku" style="width:130px">
					<col data-col-key="regular" style="width:120px">
					<col data-col-key="sale" style="width:120px">
					<col data-col-key="manage" style="width:105px">
					<col data-col-key="stock" style="width:90px">
					<col data-col-key="instock" style="width:90px">
					<col data-col-key="single" style="width:100px">
					<col data-col-key="actions" style="width:100px">
				</colgroup>
				<thead><tr>
					<th data-col-key="name"><?php echo esc_html__( 'Product name', 'expert-price-manager' ); ?><span class="epm-resizer"></span></th>
					<th data-col-key="sku">SKU<span class="epm-resizer"></span></th>
					<th data-col-key="regular"><?php echo esc_html__( 'Regular price', 'expert-price-manager' ); ?><span class="epm-resizer"></span></th>
					<th data-col-key="sale"><?php echo esc_html__( 'Sale price', 'expert-price-manager' ); ?><span class="epm-resizer"></span></th>
					<th data-col-key="manage"><?php echo esc_html__( 'Manage stock', 'expert-price-manager' ); ?><span class="epm-resizer"></span></th>
					<th data-col-key="stock"><?php echo esc_html__( 'Stock quantity', 'expert-price-manager' ); ?><span class="epm-resizer"></span></th>
					<th data-col-key="instock"><?php echo esc_html__( 'In stock', 'expert-price-manager' ); ?><span class="epm-resizer"></span></th>
					<th data-col-key="single" title="<?php echo esc_attr__( 'Limit purchases to 1 item per order', 'expert-price-manager' ); ?>"><?php echo esc_html__( 'Sold individually', 'expert-price-manager' ); ?><span class="epm-resizer"></span></th>
					<th data-col-key="actions"><?php echo esc_html__( 'Actions', 'expert-price-manager' ); ?></th>
				</tr></thead>
				<tbody id="epm-admin-table-body"><tr><td colspan="9"><?php echo esc_html__( 'Loading...', 'expert-price-manager' ); ?></td></tr></tbody>
			</table>
		</div>
		<div id="epm-admin-pagination"></div>
		<?php epm_render_footer(); ?>
	</div>
	<?php
}

