<?php
/**
 * Admin: collection dashboard.
 *
 * @package Ashbi_Testimonials
 *
 * @var array[] $collections Collections with stats.
 * @var array   $all_stats   Stats across every collection.
 * @var int     $pending     Pending testimonials across the site.
 * @var array   $settings    Plugin settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$abt_types = ABT_Data::types();
$abt_render = ABT_Render::instance();
?>
<div class="wrap abt-wrap abt-dashboard">
	<header class="abt-dash-header">
		<div class="abt-logo-title">
			<img src="<?php echo esc_url( ABT_PLUGIN_URL . 'assets/brand/icon.svg' ); ?>" width="44" height="44" alt="" />
			<div>
				<h1><?php esc_html_e( 'Ashbi Testimonials', 'ashbi-testimonials' ); ?></h1>
				<p class="abt-admin-intro"><?php esc_html_e( 'Collect, organise and display reviews anywhere with a shortcode.', 'ashbi-testimonials' ); ?></p>
			</div>
		</div>
		<?php if ( $collections ) : ?>
			<div class="abt-dash-header__actions">
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . ABT_Admin::SLUG . '&collection=all' ) ); ?>"><?php esc_html_e( 'All testimonials', 'ashbi-testimonials' ); ?></a>
				<button type="button" class="button button-primary" data-abt-new-collection>
					<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					<?php esc_html_e( 'New collection', 'ashbi-testimonials' ); ?>
				</button>
			</div>
		<?php endif; ?>
	</header>

	<?php if ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag. ?>
		<div class="abt-status is-visible" role="status"><?php esc_html_e( 'Settings saved.', 'ashbi-testimonials' ); ?></div>
	<?php endif; ?>
	<div class="abt-status" id="abt-status" role="status" aria-live="polite" tabindex="-1"></div>

	<?php if ( ! $collections ) : ?>
		<section class="abt-welcome" aria-labelledby="abt-welcome-title">
			<h2 id="abt-welcome-title"><?php esc_html_e( 'What would you like to collect?', 'ashbi-testimonials' ); ?></h2>
			<p><?php esc_html_e( 'Pick a starting point. You can add as many collections as you need: one for your store, one per product line, or one for every app you sell.', 'ashbi-testimonials' ); ?></p>
			<div class="abt-welcome__choices">
				<?php foreach ( $abt_types as $abt_key => $abt_type ) : ?>
					<button type="button" class="abt-choice" data-abt-new-collection="<?php echo esc_attr( $abt_key ); ?>">
						<span class="abt-choice__icon dashicons dashicons-<?php echo esc_attr( $abt_type['icon'] ); ?>" aria-hidden="true"></span>
						<strong><?php echo esc_html( $abt_type['label'] ); ?></strong>
						<span><?php echo esc_html( $abt_type['hint'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
			<ol class="abt-how">
				<li><span aria-hidden="true">1</span><?php esc_html_e( 'Create a collection', 'ashbi-testimonials' ); ?></li>
				<li><span aria-hidden="true">2</span><?php esc_html_e( 'Add, import or collect reviews', 'ashbi-testimonials' ); ?></li>
				<li><span aria-hidden="true">3</span><?php esc_html_e( 'Paste the shortcode on any page', 'ashbi-testimonials' ); ?></li>
			</ol>
		</section>
	<?php else : ?>
		<div class="abt-overview">
			<div class="abt-stat">
				<span class="abt-stat__label"><?php esc_html_e( 'Published', 'ashbi-testimonials' ); ?></span>
				<strong class="abt-stat__value"><?php echo esc_html( number_format_i18n( $all_stats['count'] ) ); ?></strong>
			</div>
			<div class="abt-stat">
				<span class="abt-stat__label"><?php esc_html_e( 'Average rating', 'ashbi-testimonials' ); ?></span>
				<strong class="abt-stat__value"><?php echo $all_stats['rated'] ? esc_html( number_format_i18n( $all_stats['average'], 1 ) ) : '—'; ?></strong>
				<?php echo $all_stats['rated'] ? $abt_render->stars( $all_stats['average'], 'sm' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in stars(). ?>
			</div>
			<a class="abt-stat<?php echo $pending ? ' abt-stat--alert' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . ABT_Admin::SLUG . '&collection=all&status=pending' ) ); ?>">
				<span class="abt-stat__label"><?php esc_html_e( 'Waiting for approval', 'ashbi-testimonials' ); ?></span>
				<strong class="abt-stat__value"><?php echo esc_html( number_format_i18n( $pending ) ); ?></strong>
			</a>
			<div class="abt-stat">
				<span class="abt-stat__label"><?php esc_html_e( 'Collections', 'ashbi-testimonials' ); ?></span>
				<strong class="abt-stat__value"><?php echo esc_html( number_format_i18n( count( $collections ) ) ); ?></strong>
			</div>
		</div>

		<div class="abt-section-head">
			<h2><?php esc_html_e( 'Your collections', 'ashbi-testimonials' ); ?></h2>
			<label class="abt-filter-cards">
				<span class="screen-reader-text"><?php esc_html_e( 'Filter collections', 'ashbi-testimonials' ); ?></span>
				<input type="search" placeholder="<?php esc_attr_e( 'Filter collections…', 'ashbi-testimonials' ); ?>" data-abt-filter-cards />
			</label>
		</div>

		<div class="abt-cards">
			<?php foreach ( $collections as $abt_c ) : ?>
				<?php $abt_shortcode = '[ashbi_testimonials collection="' . $abt_c['slug'] . '"]'; ?>
				<article class="abt-ccard" data-abt-card-name="<?php echo esc_attr( strtolower( $abt_c['name'] ) ); ?>">
					<a class="abt-ccard__link" href="<?php echo esc_url( admin_url( 'admin.php?page=' . ABT_Admin::SLUG . '&collection=' . $abt_c['id'] ) ); ?>">
						<span class="abt-ccard__icon dashicons dashicons-<?php echo esc_attr( $abt_types[ $abt_c['type'] ]['icon'] ); ?>" aria-hidden="true"></span>
						<span class="abt-ccard__body">
							<h3 class="abt-ccard__title"><?php echo esc_html( $abt_c['name'] ); ?></h3>
							<span class="abt-badge"><?php echo esc_html( $abt_types[ $abt_c['type'] ]['label'] ); ?></span>
							<?php if ( $abt_c['pending'] ) : ?>
								<span class="abt-badge abt-badge--pending">
									<?php
									/* translators: %s: number of pending reviews */
									echo esc_html( sprintf( __( '%s pending', 'ashbi-testimonials' ), number_format_i18n( $abt_c['pending'] ) ) );
									?>
								</span>
							<?php endif; ?>
						</span>
					</a>
					<div class="abt-ccard__stats">
						<span>
							<?php
							/* translators: %s: number of reviews */
							echo esc_html( sprintf( _n( '%s published', '%s published', $abt_c['stats']['count'], 'ashbi-testimonials' ), number_format_i18n( $abt_c['stats']['count'] ) ) );
							?>
						</span>
						<?php if ( $abt_c['stats']['rated'] ) : ?>
							<span class="abt-ccard__rating"><?php echo $abt_render->stars( $abt_c['stats']['average'], 'sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in stars(). ?> <?php echo esc_html( number_format_i18n( $abt_c['stats']['average'], 1 ) ); ?></span>
						<?php endif; ?>
					</div>
					<div class="abt-ccard__code">
						<code><?php echo esc_html( $abt_shortcode ); ?></code>
						<button type="button" class="button button-small" data-abt-copy="<?php echo esc_attr( $abt_shortcode ); ?>"><?php esc_html_e( 'Copy', 'ashbi-testimonials' ); ?></button>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<details class="abt-panel abt-settings"<?php echo isset( $_GET['settings-updated'] ) ? ' open' : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag. ?>>
		<summary><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span> <?php esc_html_e( 'Settings', 'ashbi-testimonials' ); ?></summary>
		<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="abt-settings__form">
				<input type="hidden" name="action" value="abt_save_settings" />
				<?php wp_nonce_field( 'abt_save_settings' ); ?>
				<div class="abt-field">
					<label for="abt-accent"><?php esc_html_e( 'Default accent colour', 'ashbi-testimonials' ); ?></label>
					<input type="color" id="abt-accent" name="accent" value="<?php echo esc_attr( $settings['accent'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Stars, links and buttons. Each collection or shortcode can override it.', 'ashbi-testimonials' ); ?></p>
				</div>
				<div class="abt-field">
					<label for="abt-notify"><?php esc_html_e( 'Email me new submissions', 'ashbi-testimonials' ); ?></label>
					<input type="email" id="abt-notify" name="notify_email" class="regular-text" value="<?php echo esc_attr( $settings['notify_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Leave blank for no emails. Submissions always wait for your approval.', 'ashbi-testimonials' ); ?></p>
				</div>
				<div class="abt-field">
					<label for="abt-date-format"><?php esc_html_e( 'Date format', 'ashbi-testimonials' ); ?></label>
					<input type="text" id="abt-date-format" name="date_format" value="<?php echo esc_attr( $settings['date_format'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'date_format' ) ); ?>" />
				</div>
				<div class="abt-field abt-field--check">
					<label><input type="checkbox" name="schema" value="1" <?php checked( '1', $settings['schema'] ); ?> /> <?php esc_html_e( 'Add review structured data for products and software', 'ashbi-testimonials' ); ?></label>
					<p class="description"><?php esc_html_e( 'Helps search engines show star ratings. Never added for your own business, which search engines ignore or penalise.', 'ashbi-testimonials' ); ?></p>
				</div>
				<?php if ( ABT_WooCommerce::active() ) : ?>
					<div class="abt-field abt-field--check">
						<label><input type="checkbox" name="woo_tab" value="1" <?php checked( '1', $settings['woo_tab'] ); ?> /> <?php esc_html_e( 'Show a Testimonials tab on WooCommerce products', 'ashbi-testimonials' ); ?></label>
					</div>
					<div class="abt-field">
						<label for="abt-woo-collection"><?php esc_html_e( 'Product tab review form saves to', 'ashbi-testimonials' ); ?></label>
						<select id="abt-woo-collection" name="woo_collection">
							<option value="0"><?php esc_html_e( 'No form', 'ashbi-testimonials' ); ?></option>
							<?php foreach ( $collections as $abt_c ) : ?>
								<option value="<?php echo (int) $abt_c['id']; ?>" <?php selected( (int) $settings['woo_collection'], $abt_c['id'] ); ?>><?php echo esc_html( $abt_c['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>
				<div class="abt-field abt-field--check">
					<label><input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( '1', $settings['delete_on_uninstall'] ); ?> /> <?php esc_html_e( 'Permanently delete this site’s plugin data when deleting the plugin', 'ashbi-testimonials' ); ?></label>
					<p class="description"><?php esc_html_e( 'Off by default. Deleting the plugin will otherwise keep your reviews, collections and settings for reinstallation. When enabled, deletion removes them permanently. Export a backup first. Media Library files are kept.', 'ashbi-testimonials' ); ?></p>
				</div>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'ashbi-testimonials' ); ?></button></p>
			</form>
		<?php else : ?>
			<p><?php esc_html_e( 'Ask an administrator to change plugin settings.', 'ashbi-testimonials' ); ?></p>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Shortcode reference', 'ashbi-testimonials' ); ?></h3>
		<table class="widefat striped abt-ref">
			<tbody>
				<tr><td><code>[ashbi_testimonials collection="slug"]</code></td><td><?php esc_html_e( 'Show a collection. Use commas for several.', 'ashbi-testimonials' ); ?></td></tr>
				<tr><td><code>[ashbi_testimonials product="current"]</code></td><td><?php esc_html_e( 'Reviews attached to the page or product being viewed. Or use a post ID.', 'ashbi-testimonials' ); ?></td></tr>
				<tr><td><code>layout="grid|masonry|slider|list|spotlight|compact"</code></td><td><?php esc_html_e( 'How cards are arranged. columns="1-4".', 'ashbi-testimonials' ); ?></td></tr>
				<tr><td><code>limit="9" load_more="1"</code></td><td><?php esc_html_e( 'How many to show, with a Load more button for big collections.', 'ashbi-testimonials' ); ?></td></tr>
				<tr><td><code>orderby="date|rating|featured|random" min_rating="4" featured="1" verified="1"</code></td><td><?php esc_html_e( 'Sort and filter.', 'ashbi-testimonials' ); ?></td></tr>
				<tr><td><code>show="avatar,rating,headline,role,company,date,source,product,verified,location"</code></td><td><?php esc_html_e( 'Choose which details appear on each card.', 'ashbi-testimonials' ); ?></td></tr>
				<tr><td><code>summary="1" words="40" theme="dark" accent="#e11d48"</code></td><td><?php esc_html_e( 'Rating summary header, shortened text with Read more, dark cards and colour.', 'ashbi-testimonials' ); ?></td></tr>
				<tr><td><code>[ashbi_rating collection="slug" style="inline"]</code></td><td><?php esc_html_e( 'Average rating badge or full breakdown (breakdown="0" to hide bars).', 'ashbi-testimonials' ); ?></td></tr>
				<tr><td><code>[ashbi_testimonial_form collection="slug" product="current"]</code></td><td><?php esc_html_e( 'Let visitors submit a review. Held as pending until you approve it.', 'ashbi-testimonials' ); ?></td></tr>
			</tbody>
		</table>
	</details>

	<?php include ABT_PLUGIN_DIR . 'admin/views/presets.php'; ?>
	<?php include ABT_PLUGIN_DIR . 'admin/views/collection-modal.php'; ?>
</div>
