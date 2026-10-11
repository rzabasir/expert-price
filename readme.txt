=== Expert Price & Stock Manager ===
Contributors: hilawin
Tags: woocommerce, price, stock, bulk edit, export
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.1.14
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fast, table-based management of WooCommerce prices, stock and SKUs, with custom XLSX export and Rank Math redirects when deleting products.

== Description ==

Expert Price & Stock Manager adds one page to the WordPress admin where shop managers can edit the SKU, regular price, sale price, stock quantity and stock status of many products quickly, without opening each product.

**Features**

* One table for all products, with search by product name **or product URL/slug**, category and brand filters, and sorting.
* Inline editing of SKU, regular price, sale price, stock quantity and "in stock" status; variable products open a box with all their variations.
* Persian/Arabic digits and thousand separators are accepted in price fields.
* Safe rules: prices cannot be accidentally emptied, the sale price must be lower than the regular price, products that do not manage stock are not forced into stock management.
* **Custom XLSX export**: choose and reorder columns (including SEO fields from Rank Math and any custom meta key such as ACF fields), filter by status, stock, sale, missing price or SKU, include variations, and save column templates. Large catalogs are exported in batches with a progress bar.
* **Delete with redirect**: when deleting a product you are asked whether to create a redirect. Choose 410 (gone), 301 to the product category, or 301 to a custom URL. The redirect is created in Rank Math first, then the product is deleted permanently. Without a redirect the product is only moved to the trash.
* A shortcut link next to every product opens the Rank Math "Add redirection" screen with the product URL prefilled.
* Uses the WooCommerce product CRUD API, is compatible with High-Performance Order Storage, and stores nothing of its own for prices and stock.

Access is limited to users with the `manage_woocommerce` capability (shop managers and administrators).

**Requirements**

* WooCommerce 7.0 or newer.
* Rank Math SEO with the Redirections module enabled (only needed for the redirect features).
* The PHP ZipArchive extension (only needed for the XLSX export).

**Translations**

The plugin is translation ready. A Persian (fa_IR) translation is included.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it from the Plugins screen.
2. Activate the plugin through the "Plugins" screen. WooCommerce must be active.
3. Open "Price Manager" in the admin menu.

== Frequently Asked Questions ==

= Are the redirects removed if I delete this plugin? =

No. Redirects are stored by Rank Math, not by this plugin, and stay in place. Product prices and stock are saved on the WooCommerce products themselves and are not touched either.

= Why is the "redirect" option disabled in the delete dialog? =

The Rank Math plugin or its Redirections module is not active. You can still move the product to the trash without a redirect.

= Which exports formats are supported? =

XLSX (Excel). Text cells are stored as plain strings, so values starting with = or + are never executed as formulas.

= Can I search by product link? =

Yes. Paste a full product URL or only its slug into the search box and the matching product is found; plain text searches product titles and slugs.

== Screenshots ==

1. The price manager table with inline editing.
2. The XLSX export panel with custom columns and templates.
3. The delete dialog with redirect options.

== Changelog ==

= 1.1.14 =
* Tweak: action buttons are 10% smaller with a gap between them.

= 1.1.13 =
* Performance: lazy loading - screens, AJAX handlers and the toolbar shortcut load only when needed; order stock tracking now also runs on front-end checkouts; no background polling in hidden tabs; lazy images.
* Tweak: "last updated" fits on one line.

= 1.1.12 =
* Tweak: the "last updated" box shows who made the change again.

= 1.1.11 =
* New: loading animation (progress bar, shimmering placeholder rows, row fade-in, spinner for variations).

= 1.1.10 =
* Tweak: centered table headings and cells; the "last updated" line no longer shows the user name.

= 1.1.9 =
* Tweak: the "last updated" box has exactly 5px padding on every side.

= 1.1.8 =
* New: stock status filter (all / in stock / out of stock) on the pricing table; also applied to the export.

= 1.1.7 =
* Fix: removed the duplicate page title above the header banner.

= 1.1.6 =
* New: items-per-page selector (default 20, remembered per browser).

= 1.1.5 =
* New: every variation row now has its own View and Edit links.

= 1.1.4 =
* Maintenance release.

= 1.1.3 =
* New: keeps the theme's "show last modified" product fields in sync (Jalali date) after every change made through the plugin.

= 1.1.2 =
* Improved: when another plugin or security rule redirects an AJAX request, the plugin now shows which plugin and URL caused it instead of a generic error.

= 1.1.1 =
* Fixed: toolbar icon size.
* Fixed: notices and promotions from other plugins no longer appear inside the plugin screens.

= 1.1.0 =
* New: Changes dashboard (who changed prices and stock, when) with 32-day retention and automatic cleanup.
* New: Manage stock and Sold individually controls for each product.
* New: Live update of the "last updated" line, including stock changes caused by orders.
* New: Hilawin branded interface, menu icon and quick access from the top toolbar.
* Improved: column resizing now works in RTL languages; assets are cache-busted automatically.

= 1.0.0 =
* Initial public release.

== Upgrade Notice ==

= 1.1.14 =
* Tweak: action buttons are 10% smaller with a gap between them.

= 1.1.13 =
* Performance: lazy loading - screens, AJAX handlers and the toolbar shortcut load only when needed; order stock tracking now also runs on front-end checkouts; no background polling in hidden tabs; lazy images.
* Tweak: "last updated" fits on one line.

= 1.1.12 =
* Tweak: the "last updated" box shows who made the change again.

= 1.1.11 =
* New: loading animation (progress bar, shimmering placeholder rows, row fade-in, spinner for variations).

= 1.1.10 =
* Tweak: centered table headings and cells; the "last updated" line no longer shows the user name.

= 1.1.9 =
* Tweak: the "last updated" box has exactly 5px padding on every side.

= 1.1.8 =
* New: stock status filter (all / in stock / out of stock) on the pricing table; also applied to the export.

= 1.1.7 =
Adds View and Edit links to each variation.

= 1.1.4 =
Maintenance release.

= 1.1.3 =
Syncs the theme's last-modified display with changes made here.

= 1.1.2 =
Better error reporting for blocked or redirected requests.

= 1.1.1 =
Interface fixes (toolbar icon, notices).

= 1.1.0 =
Adds the changes dashboard. A small database table is created automatically on first load.

= 1.0.0 =
Initial release.= 1.1.7 =
* New: items-per-page selector (default 20, remembered per browser).

= 1.1.14 =
* Tweak: action buttons are 10% smaller with a gap between them.

= 1.1.13 =
* Performance: lazy loading - screens, AJAX handlers and the toolbar shortcut load only when needed; order stock tracking now also runs on front-end checkouts; no background polling in hidden tabs; lazy images.
* Tweak: "last updated" fits on one line.

= 1.1.12 =
* Tweak: the "last updated" box shows who made the change again.

= 1.1.11 =
* New: loading animation (progress bar, shimmering placeholder rows, row fade-in, spinner for variations).

= 1.1.10 =
* Tweak: centered table headings and cells; the "last updated" line no longer shows the user name.

= 1.1.9 =
* Tweak: the "last updated" box has exactly 5px padding on every side.

= 1.1.8 =
* New: stock status filter (all / in stock / out of stock) on the pricing table; also applied to the export.

= 1.1.7 =
* Fix: removed the duplicate page title above the header banner.


