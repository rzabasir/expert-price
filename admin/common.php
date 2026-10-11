<?php
/**
 * Pieces shared by the plugin screens (loaded only on those screens).
 *
 * @package ExpertPriceManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Branded header shared by the plugin screens.
 *
 * @param string $title    Screen title.
 * @param string $subtitle Short description.
 */
function epm_render_hero( $title, $subtitle ) {
	?>
	<h2 class="screen-reader-text epm-notice-anchor"><?php echo esc_html( $title ); ?></h2>
	<div class="epm-hero">
		<img class="epm-hero-logo" decoding="async" width="52" height="56" src="<?php echo esc_url( EPM_PLUGIN_URL . 'assets/img/hilawin-mark.png' ); ?>" alt="Hilawin">
		<div class="epm-hero-text">
			<h1><?php echo esc_html( $title ); ?></h1>
			<p><?php echo esc_html( $subtitle ); ?></p>
		</div>
	</div>
	<?php
}

/** Credit footer shared by the plugin screens. */
function epm_render_footer() {
	?>
	<div class="epm-footer">
		<span>
			<?php
			printf(
				/* translators: %s: developer name. */
				esc_html__( 'Developed by %s — Hilawin Software Development Group', 'expert-price-manager' ),
				'<strong>' . esc_html__( 'Mohammadreza Basir', 'expert-price-manager' ) . '</strong>'
			);
			?>
		</span>
		<span class="epm-footer-brand">
			<img src="<?php echo esc_url( EPM_PLUGIN_URL . 'assets/img/hilawin-mark.png' ); ?>" alt="" height="22" loading="lazy" decoding="async">
			<img src="<?php echo esc_url( EPM_PLUGIN_URL . 'assets/img/hilawin-wordmark.png' ); ?>" alt="Hilawin" height="9" loading="lazy" decoding="async">
		</span>
	</div>
	<?php
}

add_action( 'in_admin_header', 'epm_hide_foreign_notices', 1 );
/**
 * Keep our screens clean: other plugins' notices and promotions are not printed on the plugin's own pages.
 */
function epm_hide_foreign_notices() {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check.
	if ( 'epm-price-products' !== $page && 'epm-changes' !== $page ) {
		return;
	}
	remove_all_actions( 'admin_notices' );
	remove_all_actions( 'all_admin_notices' );
	remove_all_actions( 'user_admin_notices' );
}
