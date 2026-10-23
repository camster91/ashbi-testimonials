<?php
/**
 * Admin: one collection (or all testimonials) with list, editor, shortcode builder and import.
 *
 * @package Ashbi_Testimonials
 *
 * @var array $collection Collection data; id -1 means all testimonials.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$abt_is_all = -1 === $collection['id'];
$abt_type   = ABT_Data::types()[ $collection['type'] ];
$abt_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'any'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only.
$abt_export = wp_nonce_url( admin_url( 'admin-post.php?action=abt_export&collection=' . max( 0, $collection['id'] ) ), 'abt_export' );
$abt_sources = array( 'Google', 'Trustpilot', 'G2', 'Capterra', 'Facebook', 'Yelp', 'Amazon', 'App Store', 'Google Play', 'Product Hunt', 'Clutch', 'LinkedIn', 'Email', 'Website', 'Survey' );
?>
<div class="wrap abt-wrap abt-collection" data-abt-app data-collection="<?php echo (int) $collection['id']; ?>" data-slug="<?php echo esc_attr( $collection['slug'] ); ?>" data-status="<?php echo esc_attr( $abt_status ); ?>">
	<a class="abt-back" href="<?php echo esc_url( admin_url( 'admin.php?page=' . ABT_Admin::SLUG ) ); ?>"><span aria-hidden="true">&larr;</span> <?php esc_html_e( 'All collections', 'ashbi-testimonials' ); ?></a>

	<header class="abt-dash-header">
		<div class="abt-logo-title">
			<span class="abt-ccard__icon abt-ccard__icon--lg dashicons dashicons-<?php echo esc_attr( $abt_is_all ? 'format-quote' : $abt_type['icon'] ); ?>" aria-hidden="true"></span>
			<div>
				<h1 data-abt-collection-name><?php echo esc_html( $collection['name'] ); ?></h1>
				<p class="abt-admin-intro">
					<?php if ( $abt_is_all ) : ?>
						<?php esc_html_e( 'Every testimonial across all collections.', 'ashbi-testimonials' ); ?>
					<?php else : ?>
						<span class="abt-badge"><?php echo esc_html( $abt_type['label'] ); ?></span>
						<code class="abt-inline-code">[ashbi_testimonials collection="<?php echo esc_html( $collection['slug'] ); ?>"]</code>
					<?php endif; ?>
				</p>
			</div>
		</div>
		<div class="abt-dash-header__actions">
			<?php if ( ! $abt_is_all ) : ?>
				<button type="button" class="button" data-abt-edit-collection='<?php echo esc_attr( wp_json_encode( $collection ) ); ?>'><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Collection settings', 'ashbi-testimonials' ); ?></span></button>
			<?php endif; ?>
			<details class="abt-menu">
				<summary class="button"><?php esc_html_e( 'Import / export', 'ashbi-testimonials' ); ?></summary>
				<div class="abt-menu__list">
					<button type="button" data-abt-open="abt-import-dialog"><span class="dashicons dashicons-upload" aria-hidden="true"></span><?php esc_html_e( 'Import CSV', 'ashbi-testimonials' ); ?></button>
					<a href="<?php echo esc_url( $abt_export ); ?>"><span class="dashicons dashicons-download" aria-hidden="true"></span><?php esc_html_e( 'Export CSV', 'ashbi-testimonials' ); ?></a>
				</div>
			</details>
			<button type="button" class="button" data-abt-open="abt-builder-dialog"><span class="dashicons dashicons-shortcode" aria-hidden="true"></span> <?php esc_html_e( 'Get shortcode', 'ashbi-testimonials' ); ?></button>
			<button type="button" class="button button-primary" data-abt-add>
				<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
				<?php esc_html_e( 'Add testimonial', 'ashbi-testimonials' ); ?>
			</button>
		</div>
	</header>

	<div class="abt-status" id="abt-status" role="status" aria-live="polite" tabindex="-1"></div>

	<div class="abt-overview abt-overview--compact" data-abt-stats></div>

	<div class="abt-panel abt-list-panel">
		<div class="abt-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Filter by status', 'ashbi-testimonials' ); ?>">
			<?php
			$abt_tabs = array(
				'any'     => __( 'All', 'ashbi-testimonials' ),
				'publish' => __( 'Published', 'ashbi-testimonials' ),
				'pending' => __( 'Pending', 'ashbi-testimonials' ),
				'draft'   => __( 'Drafts', 'ashbi-testimonials' ),
				'trash'   => __( 'Trash', 'ashbi-testimonials' ),
			);
			foreach ( $abt_tabs as $abt_key => $abt_label ) :
				?>
				<button type="button" role="tab" class="abt-tab" data-abt-tab="<?php echo esc_attr( $abt_key ); ?>" aria-selected="<?php echo $abt_status === $abt_key ? 'true' : 'false'; ?>"><?php echo esc_html( $abt_label ); ?> <span class="abt-tab__count" data-abt-count="<?php echo esc_attr( $abt_key ); ?>"></span></button>
			<?php endforeach; ?>
		</div>

		<div class="abt-toolbar">
			<label class="abt-search">
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Search testimonials', 'ashbi-testimonials' ); ?></span>
				<input type="search" data-abt-search placeholder="<?php esc_attr_e( 'Search names and text…', 'ashbi-testimonials' ); ?>" />
			</label>
			<label>
				<span class="screen-reader-text"><?php esc_html_e( 'Rating', 'ashbi-testimonials' ); ?></span>
				<select data-abt-rating>
					<option value="0"><?php esc_html_e( 'Any rating', 'ashbi-testimonials' ); ?></option>
					<?php for ( $abt_i = 5; $abt_i >= 1; $abt_i-- ) : ?>
						<option value="<?php echo (int) $abt_i; ?>"><?php echo esc_html( str_repeat( '★', $abt_i ) . str_repeat( '☆', 5 - $abt_i ) ); ?></option>
					<?php endfor; ?>
				</select>
			</label>
			<label>
				<span class="screen-reader-text"><?php esc_html_e( 'Sort', 'ashbi-testimonials' ); ?></span>
				<select data-abt-sort>
					<option value="date:desc"><?php esc_html_e( 'Newest first', 'ashbi-testimonials' ); ?></option>
					<option value="date:asc"><?php esc_html_e( 'Oldest first', 'ashbi-testimonials' ); ?></option>
					<option value="rating:desc"><?php esc_html_e( 'Highest rated', 'ashbi-testimonials' ); ?></option>
					<option value="rating:asc"><?php esc_html_e( 'Lowest rated', 'ashbi-testimonials' ); ?></option>
					<option value="featured:desc"><?php esc_html_e( 'Featured first', 'ashbi-testimonials' ); ?></option>
				</select>
			</label>
			<label class="abt-product-filter">
				<span class="screen-reader-text"><?php esc_html_e( 'Filter by product ID or label', 'ashbi-testimonials' ); ?></span>
				<input type="search" data-abt-product-filter placeholder="<?php esc_attr_e( 'Product ID or label', 'ashbi-testimonials' ); ?>" />
			</label>
		</div>

		<div class="abt-bulk" data-abt-bulk hidden>
			<label class="abt-inline-check"><input type="checkbox" data-abt-select-all /> <span data-abt-selected-count></span></label>
			<button type="button" class="button button-small" data-abt-bulk-action="status:publish"><?php esc_html_e( 'Approve', 'ashbi-testimonials' ); ?></button>
			<button type="button" class="button button-small" data-abt-bulk-action="status:pending"><?php esc_html_e( 'Unpublish', 'ashbi-testimonials' ); ?></button>
			<button type="button" class="button button-small" data-abt-bulk-action="featured:1"><?php esc_html_e( 'Feature', 'ashbi-testimonials' ); ?></button>
			<button type="button" class="button button-small" data-abt-bulk-action="featured:0"><?php esc_html_e( 'Unfeature', 'ashbi-testimonials' ); ?></button>
			<label>
				<span class="screen-reader-text"><?php esc_html_e( 'Add to collection', 'ashbi-testimonials' ); ?></span>
				<select data-abt-bulk-collection>
					<option value=""><?php esc_html_e( 'Add to collection…', 'ashbi-testimonials' ); ?></option>
				</select>
			</label>
			<button type="button" class="button button-small abt-danger" data-abt-bulk-delete><?php esc_html_e( 'Move to trash', 'ashbi-testimonials' ); ?></button>
			<button type="button" class="button button-small" data-abt-trash-only data-abt-bulk-action="restore:1"><?php esc_html_e( 'Restore', 'ashbi-testimonials' ); ?></button>
			<button type="button" class="button button-small abt-danger" data-abt-trash-only data-abt-bulk-purge><?php esc_html_e( 'Delete permanently', 'ashbi-testimonials' ); ?></button>
		</div>

		<div class="abt-rows" data-abt-rows aria-live="polite" aria-busy="true"></div>
		<nav class="abt-pager" data-abt-pager aria-label="<?php esc_attr_e( 'Pagination', 'ashbi-testimonials' ); ?>"></nav>
	</div>

	<!-- Editor -->
	<dialog class="abt-dialog abt-drawer" id="abt-editor" aria-labelledby="abt-editor-title">
		<form method="dialog" class="abt-dialog__form" data-abt-editor-form novalidate>
			<header class="abt-dialog__head">
				<h2 id="abt-editor-title" data-new="<?php esc_attr_e( 'Add testimonial', 'ashbi-testimonials' ); ?>" data-edit="<?php esc_attr_e( 'Edit testimonial', 'ashbi-testimonials' ); ?>"><?php esc_html_e( 'Add testimonial', 'ashbi-testimonials' ); ?></h2>
				<button type="button" class="abt-icon-btn" data-abt-close aria-label="<?php esc_attr_e( 'Close', 'ashbi-testimonials' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
			</header>
			<div class="abt-dialog__body">
				<input type="hidden" name="id" value="0" />
				<div class="abt-form-error" data-abt-editor-error role="alert" hidden></div>

				<section class="abt-group">
					<h3><?php esc_html_e( 'Review', 'ashbi-testimonials' ); ?></h3>
					<fieldset class="abt-field">
						<legend><?php esc_html_e( 'Rating', 'ashbi-testimonials' ); ?></legend>
						<div class="abt-star-input" data-abt-star-input>
							<?php for ( $abt_i = 1; $abt_i <= 5; $abt_i++ ) : ?>
								<button type="button" data-value="<?php echo (int) $abt_i; ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: stars */ _n( '%d star', '%d stars', $abt_i, 'ashbi-testimonials' ), $abt_i ) ); ?>">★</button>
							<?php endfor; ?>
							<button type="button" class="button-link abt-star-input__clear" data-value="0"><?php esc_html_e( 'No rating', 'ashbi-testimonials' ); ?></button>
							<input type="hidden" name="rating" value="5" />
						</div>
					</fieldset>
					<div class="abt-field">
						<label for="abt-e-headline"><?php esc_html_e( 'Headline', 'ashbi-testimonials' ); ?> <span class="abt-optional"><?php esc_html_e( 'optional', 'ashbi-testimonials' ); ?></span></label>
						<input type="text" id="abt-e-headline" name="headline" class="widefat" placeholder="<?php esc_attr_e( 'e.g. Saved us hours every week', 'ashbi-testimonials' ); ?>" />
					</div>
					<div class="abt-field">
						<label for="abt-e-content"><?php esc_html_e( 'Testimonial', 'ashbi-testimonials' ); ?></label>
						<textarea id="abt-e-content" name="content" rows="6" class="widefat"></textarea>
					</div>
				</section>

				<section class="abt-group">
					<h3><?php esc_html_e( 'Reviewer', 'ashbi-testimonials' ); ?></h3>
					<div class="abt-avatar-field">
						<span class="abt-avatar-field__preview" data-abt-avatar-preview></span>
						<div>
							<button type="button" class="button button-small" data-abt-avatar-pick><?php esc_html_e( 'Choose photo', 'ashbi-testimonials' ); ?></button>
							<button type="button" class="button-link" data-abt-avatar-remove hidden><?php esc_html_e( 'Remove', 'ashbi-testimonials' ); ?></button>
							<input type="hidden" name="avatar_id" value="0" />
							<input type="url" name="avatar_url" class="abt-avatar-field__url" placeholder="<?php esc_attr_e( 'or paste an image URL', 'ashbi-testimonials' ); ?>" />
						</div>
					</div>
					<div class="abt-field-row">
						<div class="abt-field">
							<label for="abt-e-author"><?php esc_html_e( 'Name', 'ashbi-testimonials' ); ?></label>
							<input type="text" id="abt-e-author" name="author" class="widefat" required />
						</div>
						<div class="abt-field">
							<label for="abt-e-email"><?php esc_html_e( 'Email', 'ashbi-testimonials' ); ?> <span class="abt-optional"><?php esc_html_e( 'never shown', 'ashbi-testimonials' ); ?></span></label>
							<input type="email" id="abt-e-email" name="email" class="widefat" />
						</div>
					</div>
					<div class="abt-field-row">
						<div class="abt-field">
							<label for="abt-e-role"><?php esc_html_e( 'Job title', 'ashbi-testimonials' ); ?></label>
							<input type="text" id="abt-e-role" name="role" class="widefat" />
						</div>
						<div class="abt-field">
							<label for="abt-e-company"><?php esc_html_e( 'Company', 'ashbi-testimonials' ); ?></label>
							<input type="text" id="abt-e-company" name="company" class="widefat" />
						</div>
					</div>
					<div class="abt-field">
						<label for="abt-e-location"><?php esc_html_e( 'Location', 'ashbi-testimonials' ); ?></label>
						<input type="text" id="abt-e-location" name="location" class="widefat" />
					</div>
				</section>

				<section class="abt-group">
					<h3><?php esc_html_e( 'What is being reviewed', 'ashbi-testimonials' ); ?></h3>
					<div class="abt-field">
						<label for="abt-e-product-search"><?php echo esc_html( ABT_WooCommerce::active() ? __( 'Product', 'ashbi-testimonials' ) : __( 'Product or page', 'ashbi-testimonials' ) ); ?></label>
						<div class="abt-picker" data-abt-picker>
							<input type="hidden" name="product_id" value="0" />
							<span class="abt-chip" data-abt-picker-chip hidden><span data-abt-picker-label></span><button type="button" class="abt-chip__x" data-abt-picker-clear aria-label="<?php esc_attr_e( 'Remove product', 'ashbi-testimonials' ); ?>">&times;</button></span>
							<input type="search" id="abt-e-product-search" class="widefat" data-abt-picker-input autocomplete="off" placeholder="<?php esc_attr_e( 'Search by name or ID…', 'ashbi-testimonials' ); ?>" />
							<ul class="abt-picker__results" data-abt-picker-results role="listbox" hidden></ul>
						</div>
					</div>
					<div class="abt-field">
						<label for="abt-e-subject"><?php esc_html_e( 'Label, plan or SKU', 'ashbi-testimonials' ); ?> <span class="abt-optional"><?php esc_html_e( 'optional', 'ashbi-testimonials' ); ?></span></label>
						<input type="text" id="abt-e-subject" name="subject" class="widefat" placeholder="<?php esc_attr_e( 'e.g. Pro plan, SKU-1042, Kitchen remodel', 'ashbi-testimonials' ); ?>" />
						<p class="description"><?php esc_html_e( 'Group reviews without a page. Show them with subject="…".', 'ashbi-testimonials' ); ?></p>
					</div>
				</section>

				<section class="abt-group">
					<h3><?php esc_html_e( 'Source', 'ashbi-testimonials' ); ?></h3>
					<div class="abt-field-row">
						<div class="abt-field">
							<label for="abt-e-source"><?php esc_html_e( 'Where it came from', 'ashbi-testimonials' ); ?></label>
							<input type="text" id="abt-e-source" name="source" class="widefat" list="abt-sources" />
							<datalist id="abt-sources">
								<?php foreach ( $abt_sources as $abt_source ) : ?>
									<option value="<?php echo esc_attr( $abt_source ); ?>"></option>
								<?php endforeach; ?>
							</datalist>
						</div>
						<div class="abt-field">
							<label for="abt-e-source-url"><?php esc_html_e( 'Link to original', 'ashbi-testimonials' ); ?></label>
							<input type="url" id="abt-e-source-url" name="source_url" class="widefat" />
						</div>
					</div>
				</section>

				<section class="abt-group">
					<h3><?php esc_html_e( 'Publishing', 'ashbi-testimonials' ); ?></h3>
					<div class="abt-field-row">
						<div class="abt-field">
							<label for="abt-e-status"><?php esc_html_e( 'Status', 'ashbi-testimonials' ); ?></label>
							<select id="abt-e-status" name="status">
								<option value="publish"><?php esc_html_e( 'Published', 'ashbi-testimonials' ); ?></option>
								<option value="pending"><?php esc_html_e( 'Pending approval', 'ashbi-testimonials' ); ?></option>
								<option value="draft"><?php esc_html_e( 'Draft', 'ashbi-testimonials' ); ?></option>
							</select>
						</div>
						<div class="abt-field">
							<label for="abt-e-date"><?php esc_html_e( 'Date', 'ashbi-testimonials' ); ?></label>
							<input type="date" id="abt-e-date" name="date" />
						</div>
					</div>
					<div class="abt-toggles">
						<label class="abt-toggle"><input type="checkbox" name="verified" value="1" /> <span><?php esc_html_e( 'Verified customer', 'ashbi-testimonials' ); ?></span></label>
						<label class="abt-toggle"><input type="checkbox" name="featured" value="1" /> <span><?php esc_html_e( 'Featured', 'ashbi-testimonials' ); ?></span></label>
					</div>
					<fieldset class="abt-field">
						<legend><?php esc_html_e( 'Collections', 'ashbi-testimonials' ); ?></legend>
						<div class="abt-checklist" data-abt-collections></div>
					</fieldset>
				</section>
			</div>
			<footer class="abt-dialog__foot">
				<button type="button" class="button-link abt-danger-link" data-abt-editor-delete hidden><?php esc_html_e( 'Move to trash', 'ashbi-testimonials' ); ?></button>
				<span class="abt-spacer"></span>
				<button type="button" class="button" data-abt-close><?php esc_html_e( 'Cancel', 'ashbi-testimonials' ); ?></button>
				<button type="button" class="button" data-abt-save-new><?php esc_html_e( 'Save & add another', 'ashbi-testimonials' ); ?></button>
				<button type="submit" class="button button-primary" value="save"><?php esc_html_e( 'Save', 'ashbi-testimonials' ); ?></button>
			</footer>
		</form>
	</dialog>

	<!-- Shortcode builder -->
	<dialog class="abt-dialog abt-dialog--wide" id="abt-builder-dialog" aria-labelledby="abt-builder-title">
		<form method="dialog" class="abt-dialog__form" data-abt-builder>
			<header class="abt-dialog__head">
				<h2 id="abt-builder-title"><?php esc_html_e( 'Shortcode builder', 'ashbi-testimonials' ); ?></h2>
				<button type="button" class="abt-icon-btn" data-abt-close aria-label="<?php esc_attr_e( 'Close', 'ashbi-testimonials' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
			</header>
			<div class="abt-builder">
				<div class="abt-builder__controls">
					<div class="abt-field">
						<label for="abt-b-layout"><?php esc_html_e( 'Layout', 'ashbi-testimonials' ); ?></label>
						<div class="abt-layout-picker" id="abt-b-layout">
							<?php foreach ( ABT_Data::layouts() as $abt_key => $abt_label ) : ?>
								<label class="abt-layout-opt"><input type="radio" name="layout" value="<?php echo esc_attr( $abt_key ); ?>" <?php checked( $collection['layout'], $abt_key ); ?> /><span class="abt-layout-opt__art abt-layout-opt__art--<?php echo esc_attr( $abt_key ); ?>" aria-hidden="true"><i></i><i></i><i></i></span><span><?php echo esc_html( $abt_label ); ?></span></label>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="abt-field-row">
						<div class="abt-field"><label for="abt-b-columns"><?php esc_html_e( 'Columns', 'ashbi-testimonials' ); ?></label><select id="abt-b-columns" name="columns"><option>1</option><option>2</option><option selected>3</option><option>4</option></select></div>
						<div class="abt-field"><label for="abt-b-limit"><?php esc_html_e( 'Show', 'ashbi-testimonials' ); ?></label><input type="number" id="abt-b-limit" name="limit" min="1" max="100" value="9" /></div>
					</div>
					<div class="abt-field-row">
						<div class="abt-field">
							<label for="abt-b-orderby"><?php esc_html_e( 'Order', 'ashbi-testimonials' ); ?></label>
							<select id="abt-b-orderby" name="orderby">
								<option value="date"><?php esc_html_e( 'Newest', 'ashbi-testimonials' ); ?></option>
								<option value="rating"><?php esc_html_e( 'Highest rated', 'ashbi-testimonials' ); ?></option>
								<option value="featured"><?php esc_html_e( 'Featured first', 'ashbi-testimonials' ); ?></option>
								<option value="random"><?php esc_html_e( 'Random', 'ashbi-testimonials' ); ?></option>
							</select>
						</div>
						<div class="abt-field">
							<label for="abt-b-min"><?php esc_html_e( 'Minimum rating', 'ashbi-testimonials' ); ?></label>
							<select id="abt-b-min" name="min_rating"><option value="0"><?php esc_html_e( 'Any', 'ashbi-testimonials' ); ?></option><option value="3">3+</option><option value="4">4+</option><option value="5">5</option></select>
						</div>
					</div>
					<div class="abt-field-row">
						<div class="abt-field">
							<label for="abt-b-theme"><?php esc_html_e( 'Theme', 'ashbi-testimonials' ); ?></label>
							<select id="abt-b-theme" name="theme"><option value="light"><?php esc_html_e( 'Light', 'ashbi-testimonials' ); ?></option><option value="dark"><?php esc_html_e( 'Dark', 'ashbi-testimonials' ); ?></option><option value="auto"><?php esc_html_e( 'Match visitor', 'ashbi-testimonials' ); ?></option></select>
						</div>
						<div class="abt-field">
							<label for="abt-b-words"><?php esc_html_e( 'Shorten to words', 'ashbi-testimonials' ); ?></label>
							<input type="number" id="abt-b-words" name="words" min="0" max="500" value="0" />
						</div>
					</div>
					<fieldset class="abt-field">
						<legend><?php esc_html_e( 'Card details', 'ashbi-testimonials' ); ?></legend>
						<div class="abt-checklist abt-checklist--inline" data-abt-builder-parts></div>
					</fieldset>
					<div class="abt-toggles">
						<label class="abt-toggle"><input type="checkbox" name="summary" value="1" /> <span><?php esc_html_e( 'Rating summary', 'ashbi-testimonials' ); ?></span></label>
						<label class="abt-toggle"><input type="checkbox" name="load_more" value="1" /> <span><?php esc_html_e( 'Load more button', 'ashbi-testimonials' ); ?></span></label>
						<label class="abt-toggle"><input type="checkbox" name="featured" value="1" /> <span><?php esc_html_e( 'Featured only', 'ashbi-testimonials' ); ?></span></label>
						<label class="abt-toggle"><input type="checkbox" name="verified" value="1" /> <span><?php esc_html_e( 'Verified only', 'ashbi-testimonials' ); ?></span></label>
						<label class="abt-toggle"><input type="checkbox" name="product_current" value="1" /> <span><?php esc_html_e( 'Only the product being viewed', 'ashbi-testimonials' ); ?></span></label>
					</div>
				</div>
				<div class="abt-builder__output">
					<div class="abt-builder__code">
						<code data-abt-builder-code></code>
						<button type="button" class="button button-primary" data-abt-builder-copy><?php esc_html_e( 'Copy', 'ashbi-testimonials' ); ?></button>
					</div>
					<p class="description" data-abt-builder-note hidden><?php esc_html_e( 'Preview shows every product; on a product page only its own reviews appear.', 'ashbi-testimonials' ); ?></p>
					<div class="abt-builder__preview" data-abt-builder-preview></div>
				</div>
			</div>
		</form>
	</dialog>

	<!-- Import -->
	<dialog class="abt-dialog" id="abt-import-dialog" aria-labelledby="abt-import-title">
		<form method="dialog" class="abt-dialog__form" data-abt-import>
			<header class="abt-dialog__head">
				<h2 id="abt-import-title"><?php esc_html_e( 'Import from CSV', 'ashbi-testimonials' ); ?></h2>
				<button type="button" class="abt-icon-btn" data-abt-close aria-label="<?php esc_attr_e( 'Close', 'ashbi-testimonials' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
			</header>
			<div class="abt-dialog__body">
				<p><?php esc_html_e( 'The first row must be column headings. Only author and content (or rating) are required. Large files are imported in batches, so thousands of rows are fine.', 'ashbi-testimonials' ); ?></p>
				<p class="abt-columns"><?php echo esc_html( implode( ', ', ABT_Admin::CSV_COLUMNS ) ); ?></p>
				<p class="description"><?php esc_html_e( 'Also accepted: name, review, text, title, stars, product, sku. Tip: export first to get a ready-made template.', 'ashbi-testimonials' ); ?></p>
				<div class="abt-field">
					<label for="abt-i-file"><?php esc_html_e( 'CSV file', 'ashbi-testimonials' ); ?></label>
					<input type="file" id="abt-i-file" accept=".csv,text/csv" required />
				</div>
				<?php if ( $abt_is_all ) : ?>
					<div class="abt-field">
						<label for="abt-i-collection"><?php esc_html_e( 'Add to collection', 'ashbi-testimonials' ); ?></label>
						<select id="abt-i-collection" data-abt-import-collection></select>
					</div>
				<?php endif; ?>
				<div class="abt-progress" data-abt-import-progress hidden><span></span></div>
				<p data-abt-import-result role="status"></p>
			</div>
			<footer class="abt-dialog__foot">
				<span class="abt-spacer"></span>
				<button type="button" class="button" data-abt-close><?php esc_html_e( 'Close', 'ashbi-testimonials' ); ?></button>
				<button type="submit" class="button button-primary" value="import"><?php esc_html_e( 'Import', 'ashbi-testimonials' ); ?></button>
			</footer>
		</form>
	</dialog>

	<?php include ABT_PLUGIN_DIR . 'admin/views/collection-modal.php'; ?>
</div>
