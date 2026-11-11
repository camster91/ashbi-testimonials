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

$ashbte_is_all = -1 === $collection['id'];
$ashbte_type   = ASHBTE_Data::types()[ $collection['type'] ];
$ashbte_status = ASHBTE_Admin::nav_arg( 'status' );
if ( ! in_array( $ashbte_status, array( 'any', 'publish', 'pending', 'draft', 'trash' ), true ) ) {
	$ashbte_status = 'any';
}
$ashbte_export = wp_nonce_url( admin_url( 'admin-post.php?action=ashbte_export&collection=' . max( 0, $collection['id'] ) ), 'ashbte_export' );
$ashbte_sources = array( 'Google', 'Trustpilot', 'G2', 'Capterra', 'Facebook', 'Yelp', 'Amazon', 'App Store', 'Google Play', 'Product Hunt', 'Clutch', 'LinkedIn', 'Email', 'Website', 'Survey' );
?>
<div class="wrap ashbte-wrap ashbte-collection" data-ashbte-app data-collection="<?php echo (int) $collection['id']; ?>" data-slug="<?php echo esc_attr( ( is_numeric( $collection['slug'] ) ? (string) $collection['id'] : $collection['slug'] ) ); ?>" data-status="<?php echo esc_attr( $ashbte_status ); ?>">
	<a class="ashbte-back" href="<?php echo esc_url( admin_url( 'admin.php?page=' . ASHBTE_Admin::SLUG ) ); ?>"><span aria-hidden="true">&larr;</span> <?php esc_html_e( 'All collections', 'ashbi-testimonials' ); ?></a>

	<header class="ashbte-dash-header">
		<div class="ashbte-logo-title">
			<span class="ashbte-ccard__icon ashbte-ccard__icon--lg dashicons dashicons-<?php echo esc_attr( $ashbte_is_all ? 'format-quote' : $ashbte_type['icon'] ); ?>" aria-hidden="true"></span>
			<div>
				<h1 data-ashbte-collection-name><?php echo esc_html( $collection['name'] ); ?></h1>
				<p class="ashbte-admin-intro">
					<?php if ( $ashbte_is_all ) : ?>
						<?php esc_html_e( 'Every testimonial across all collections.', 'ashbi-testimonials' ); ?>
					<?php else : ?>
						<span class="ashbte-badge"><?php echo esc_html( $ashbte_type['label'] ); ?></span>
						<?php esc_html_e( 'Manage reviews and choose how they appear on your site.', 'ashbi-testimonials' ); ?>
					<?php endif; ?>
				</p>
			</div>
		</div>
		<div class="ashbte-dash-header__actions">
			<?php if ( ! $ashbte_is_all ) : ?>
				<button type="button" class="button" data-ashbte-edit-collection='<?php echo esc_attr( wp_json_encode( $collection ) ); ?>'><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span><?php esc_html_e( 'Collection settings', 'ashbi-testimonials' ); ?></button>
			<?php endif; ?>
			<details class="ashbte-menu">
				<summary class="button"><?php esc_html_e( 'Import / export', 'ashbi-testimonials' ); ?></summary>
				<div class="ashbte-menu__list">
					<button type="button" data-ashbte-open="ashbte-import-dialog"><span class="dashicons dashicons-upload" aria-hidden="true"></span><?php esc_html_e( 'Import CSV', 'ashbi-testimonials' ); ?></button>
					<a download href="<?php echo esc_url( $ashbte_export ); ?>"><span class="dashicons dashicons-download" aria-hidden="true"></span><?php esc_html_e( 'Export CSV', 'ashbi-testimonials' ); ?></a>
				</div>
			</details>
			<button type="button" class="button" data-ashbte-open="ashbte-placement-dialog"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span> <?php esc_html_e( 'Place on site', 'ashbi-testimonials' ); ?></button>
			<button type="button" class="button button-primary" data-ashbte-add>
				<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
				<?php esc_html_e( 'Add testimonial', 'ashbi-testimonials' ); ?>
			</button>
		</div>
	</header>
	<hr class="wp-header-end" />

	<div class="ashbte-status" id="ashbte-status" role="status" aria-live="polite" tabindex="-1"></div>

	<div class="ashbte-overview ashbte-overview--compact" data-ashbte-stats></div>

	<div class="ashbte-panel ashbte-list-panel">
		<div class="ashbte-tabs" role="group" aria-label="<?php esc_attr_e( 'Filter by status', 'ashbi-testimonials' ); ?>">
			<?php
			$ashbte_tabs = array(
				'any'     => __( 'All', 'ashbi-testimonials' ),
				'publish' => __( 'Published', 'ashbi-testimonials' ),
				'pending' => __( 'Pending', 'ashbi-testimonials' ),
				'draft'   => __( 'Drafts', 'ashbi-testimonials' ),
				'trash'   => __( 'Trash', 'ashbi-testimonials' ),
			);
			foreach ( $ashbte_tabs as $ashbte_key => $ashbte_label ) :
				?>
				<button type="button" class="ashbte-tab" data-ashbte-tab="<?php echo esc_attr( $ashbte_key ); ?>" aria-pressed="<?php echo $ashbte_status === $ashbte_key ? 'true' : 'false'; ?>"><?php echo esc_html( $ashbte_label ); ?> <span class="ashbte-tab__count" data-ashbte-count="<?php echo esc_attr( $ashbte_key ); ?>"></span></button>
			<?php endforeach; ?>
		</div>

		<div class="ashbte-toolbar">
			<label class="ashbte-search">
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Search testimonials', 'ashbi-testimonials' ); ?></span>
				<input type="search" data-ashbte-search placeholder="<?php esc_attr_e( 'Search names and text…', 'ashbi-testimonials' ); ?>" />
			</label>
			<label>
				<span class="screen-reader-text"><?php esc_html_e( 'Rating', 'ashbi-testimonials' ); ?></span>
				<select data-ashbte-rating>
					<option value="0"><?php esc_html_e( 'Any rating', 'ashbi-testimonials' ); ?></option>
					<?php for ( $ashbte_i = 5; $ashbte_i >= 1; $ashbte_i-- ) : ?>
						<option value="<?php echo (int) $ashbte_i; ?>"><?php echo esc_html( str_repeat( '★', $ashbte_i ) . str_repeat( '☆', 5 - $ashbte_i ) ); ?></option>
					<?php endfor; ?>
				</select>
			</label>
			<label>
				<span class="screen-reader-text"><?php esc_html_e( 'Sort', 'ashbi-testimonials' ); ?></span>
				<select data-ashbte-sort>
					<option value="date:desc"><?php esc_html_e( 'Newest first', 'ashbi-testimonials' ); ?></option>
					<option value="date:asc"><?php esc_html_e( 'Oldest first', 'ashbi-testimonials' ); ?></option>
					<option value="rating:desc"><?php esc_html_e( 'Highest rated', 'ashbi-testimonials' ); ?></option>
					<option value="rating:asc"><?php esc_html_e( 'Lowest rated', 'ashbi-testimonials' ); ?></option>
					<option value="featured:desc"><?php esc_html_e( 'Featured first', 'ashbi-testimonials' ); ?></option>
				</select>
			</label>
			<label class="ashbte-product-filter">
				<span class="screen-reader-text"><?php esc_html_e( 'Filter by product ID or label', 'ashbi-testimonials' ); ?></span>
				<input type="search" data-ashbte-product-filter placeholder="<?php esc_attr_e( 'Product ID or label', 'ashbi-testimonials' ); ?>" />
			</label>
		</div>

		<div class="ashbte-bulk" data-ashbte-bulk hidden>
			<label class="ashbte-inline-check"><input type="checkbox" data-ashbte-select-all /> <span data-ashbte-selected-count></span></label>
			<button type="button" class="button button-small" data-ashbte-bulk-action="status:publish"><?php esc_html_e( 'Approve', 'ashbi-testimonials' ); ?></button>
			<button type="button" class="button button-small" data-ashbte-bulk-action="status:pending"><?php esc_html_e( 'Unpublish', 'ashbi-testimonials' ); ?></button>
			<button type="button" class="button button-small" data-ashbte-bulk-action="featured:1"><?php esc_html_e( 'Feature', 'ashbi-testimonials' ); ?></button>
			<button type="button" class="button button-small" data-ashbte-bulk-action="featured:0"><?php esc_html_e( 'Unfeature', 'ashbi-testimonials' ); ?></button>
			<label>
				<span class="screen-reader-text"><?php esc_html_e( 'Add to collection', 'ashbi-testimonials' ); ?></span>
				<select data-ashbte-bulk-collection>
					<option value=""><?php esc_html_e( 'Add to collection…', 'ashbi-testimonials' ); ?></option>
				</select>
			</label>
			<button type="button" class="button button-small ashbte-danger" data-ashbte-bulk-delete><?php esc_html_e( 'Move to trash', 'ashbi-testimonials' ); ?></button>
			<button type="button" class="button button-small" data-ashbte-trash-only data-ashbte-bulk-action="restore:1"><?php esc_html_e( 'Restore', 'ashbi-testimonials' ); ?></button>
			<button type="button" class="button button-small ashbte-danger" data-ashbte-trash-only data-ashbte-bulk-purge><?php esc_html_e( 'Delete permanently', 'ashbi-testimonials' ); ?></button>
		</div>

		<div class="ashbte-rows" data-ashbte-rows aria-live="polite" aria-busy="true">
			<p class="ashbte-empty"><?php esc_html_e( 'Loading testimonials…', 'ashbi-testimonials' ); ?></p>
		</div>
		<nav class="ashbte-pager" data-ashbte-pager aria-label="<?php esc_attr_e( 'Pagination', 'ashbi-testimonials' ); ?>"></nav>
	</div>

	<!-- Editor -->
	<dialog class="ashbte-dialog ashbte-drawer" id="ashbte-editor" aria-labelledby="ashbte-editor-title">
		<form method="dialog" class="ashbte-dialog__form" data-ashbte-editor-form novalidate>
			<header class="ashbte-dialog__head">
				<h2 id="ashbte-editor-title" data-new="<?php esc_attr_e( 'Add testimonial', 'ashbi-testimonials' ); ?>" data-edit="<?php esc_attr_e( 'Edit testimonial', 'ashbi-testimonials' ); ?>"><?php esc_html_e( 'Add testimonial', 'ashbi-testimonials' ); ?></h2>
				<button type="button" class="ashbte-icon-btn" data-ashbte-close aria-label="<?php esc_attr_e( 'Close', 'ashbi-testimonials' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
			</header>
			<div class="ashbte-dialog__body">
				<input type="hidden" name="id" value="0" />
				<div class="ashbte-form-error" data-ashbte-editor-error role="alert" hidden></div>

				<section class="ashbte-group">
					<h3><?php esc_html_e( 'Review', 'ashbi-testimonials' ); ?></h3>
					<div class="ashbte-field">
						<label for="ashbte-e-author"><?php esc_html_e( 'Name', 'ashbi-testimonials' ); ?></label>
						<input type="text" id="ashbte-e-author" name="author" class="widefat" required />
					</div>
					<fieldset class="ashbte-field">
						<legend><?php esc_html_e( 'Rating', 'ashbi-testimonials' ); ?></legend>
						<div class="ashbte-star-input" data-ashbte-star-input>
							<?php for ( $ashbte_i = 1; $ashbte_i <= 5; $ashbte_i++ ) : ?>
								<button type="button" data-value="<?php echo (int) $ashbte_i; ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: stars */ _n( '%d star', '%d stars', $ashbte_i, 'ashbi-testimonials' ), $ashbte_i ) ); ?>">★</button>
							<?php endfor; ?>
							<button type="button" class="button-link ashbte-star-input__clear" data-value="0"><?php esc_html_e( 'No rating', 'ashbi-testimonials' ); ?></button>
							<input type="hidden" name="rating" value="5" />
						</div>
					</fieldset>
					<div class="ashbte-field">
						<label for="ashbte-e-content"><?php esc_html_e( 'Testimonial', 'ashbi-testimonials' ); ?></label>
						<textarea id="ashbte-e-content" name="content" rows="6" class="widefat"></textarea>
					</div>
				</section>

				<details class="ashbte-group" data-ashbte-review-details>
					<summary><?php esc_html_e( 'Optional review details', 'ashbi-testimonials' ); ?></summary>
					<section class="ashbte-group">
						<h3><?php esc_html_e( 'Reviewer', 'ashbi-testimonials' ); ?></h3>
						<div class="ashbte-field">
							<label for="ashbte-e-headline"><?php esc_html_e( 'Headline', 'ashbi-testimonials' ); ?> <span class="ashbte-optional"><?php esc_html_e( 'optional', 'ashbi-testimonials' ); ?></span></label>
							<input type="text" id="ashbte-e-headline" name="headline" class="widefat" placeholder="<?php esc_attr_e( 'e.g. Saved us hours every week', 'ashbi-testimonials' ); ?>" />
						</div>
						<div class="ashbte-avatar-field">
							<span class="ashbte-avatar-field__preview" data-ashbte-avatar-preview></span>
							<div>
								<button type="button" class="button button-small" data-ashbte-avatar-pick><?php esc_html_e( 'Choose photo', 'ashbi-testimonials' ); ?></button>
								<button type="button" class="button-link" data-ashbte-avatar-remove hidden><?php esc_html_e( 'Remove', 'ashbi-testimonials' ); ?></button>
								<input type="hidden" name="avatar_id" value="0" />
								<input type="url" name="avatar_url" class="ashbte-avatar-field__url" placeholder="<?php esc_attr_e( 'or paste an image URL', 'ashbi-testimonials' ); ?>" />
							</div>
						</div>
						<div class="ashbte-field-row">
							<div class="ashbte-field">
								<label for="ashbte-e-email"><?php esc_html_e( 'Email', 'ashbi-testimonials' ); ?> <span class="ashbte-optional"><?php esc_html_e( 'never shown', 'ashbi-testimonials' ); ?></span></label>
								<input type="email" id="ashbte-e-email" name="email" class="widefat" />
							</div>
						</div>
						<div class="ashbte-field-row">
							<div class="ashbte-field">
								<label for="ashbte-e-role"><?php esc_html_e( 'Job title', 'ashbi-testimonials' ); ?></label>
								<input type="text" id="ashbte-e-role" name="role" class="widefat" />
							</div>
							<div class="ashbte-field">
								<label for="ashbte-e-company"><?php esc_html_e( 'Company', 'ashbi-testimonials' ); ?></label>
								<input type="text" id="ashbte-e-company" name="company" class="widefat" />
							</div>
						</div>
						<div class="ashbte-field">
							<label for="ashbte-e-location"><?php esc_html_e( 'Location', 'ashbi-testimonials' ); ?></label>
							<input type="text" id="ashbte-e-location" name="location" class="widefat" />
						</div>
					</section>

					<section class="ashbte-group">
						<h3><?php esc_html_e( 'What is being reviewed', 'ashbi-testimonials' ); ?></h3>
						<div class="ashbte-field">
							<label for="ashbte-e-product-search"><?php echo esc_html( ASHBTE_WooCommerce::active() ? __( 'Product', 'ashbi-testimonials' ) : __( 'Product or page', 'ashbi-testimonials' ) ); ?></label>
							<div class="ashbte-picker" data-ashbte-picker>
								<input type="hidden" name="product_id" value="0" />
								<span class="ashbte-chip" data-ashbte-picker-chip hidden><span data-ashbte-picker-label></span><button type="button" class="ashbte-chip__x" data-ashbte-picker-clear aria-label="<?php esc_attr_e( 'Remove product', 'ashbi-testimonials' ); ?>">&times;</button></span>
								<input type="search" id="ashbte-e-product-search" class="widefat" data-ashbte-picker-input autocomplete="off" placeholder="<?php esc_attr_e( 'Search by name or ID…', 'ashbi-testimonials' ); ?>" />
								<ul class="ashbte-picker__results" data-ashbte-picker-results role="listbox" hidden></ul>
							</div>
						</div>
						<div class="ashbte-field">
							<label for="ashbte-e-subject"><?php esc_html_e( 'Label, plan or SKU', 'ashbi-testimonials' ); ?> <span class="ashbte-optional"><?php esc_html_e( 'optional', 'ashbi-testimonials' ); ?></span></label>
							<input type="text" id="ashbte-e-subject" name="subject" class="widefat" placeholder="<?php esc_attr_e( 'e.g. Pro plan, SKU-1042, Kitchen remodel', 'ashbi-testimonials' ); ?>" />
							<p class="description"><?php esc_html_e( 'Group reviews without a page. Show them with subject="…".', 'ashbi-testimonials' ); ?></p>
						</div>
					</section>

					<section class="ashbte-group">
						<h3><?php esc_html_e( 'Source', 'ashbi-testimonials' ); ?></h3>
						<div class="ashbte-field-row">
							<div class="ashbte-field">
								<label for="ashbte-e-source"><?php esc_html_e( 'Where it came from', 'ashbi-testimonials' ); ?></label>
								<input type="text" id="ashbte-e-source" name="source" class="widefat" list="ashbte-sources" />
								<datalist id="ashbte-sources">
									<?php foreach ( $ashbte_sources as $ashbte_source ) : ?>
										<option value="<?php echo esc_attr( $ashbte_source ); ?>"></option>
									<?php endforeach; ?>
								</datalist>
							</div>
							<div class="ashbte-field">
								<label for="ashbte-e-source-url"><?php esc_html_e( 'Link to original', 'ashbi-testimonials' ); ?></label>
								<input type="url" id="ashbte-e-source-url" name="source_url" class="widefat" />
							</div>
						</div>
					</section>

				</details>

				<section class="ashbte-group">
					<h3><?php esc_html_e( 'Publishing', 'ashbi-testimonials' ); ?></h3>
					<div class="ashbte-field-row">
						<div class="ashbte-field">
							<label for="ashbte-e-status"><?php esc_html_e( 'Status', 'ashbi-testimonials' ); ?></label>
							<select id="ashbte-e-status" name="status">
								<option value="publish"><?php esc_html_e( 'Published', 'ashbi-testimonials' ); ?></option>
								<option value="pending"><?php esc_html_e( 'Pending approval', 'ashbi-testimonials' ); ?></option>
								<option value="draft"><?php esc_html_e( 'Draft', 'ashbi-testimonials' ); ?></option>
							</select>
						</div>
						<div class="ashbte-field">
							<label for="ashbte-e-date"><?php esc_html_e( 'Date', 'ashbi-testimonials' ); ?></label>
							<input type="date" id="ashbte-e-date" name="date" />
						</div>
					</div>
					<div class="ashbte-field">
						<label for="ashbte-e-origin"><?php esc_html_e( 'Review provenance', 'ashbi-testimonials' ); ?></label>
						<select id="ashbte-e-origin" name="origin">
							<option value="manual"><?php esc_html_e( 'Manually entered / not confirmed', 'ashbi-testimonials' ); ?></option>
							<option value="direct"><?php esc_html_e( 'Collected directly from the reviewer', 'ashbi-testimonials' ); ?></option>
							<option value="imported"><?php esc_html_e( 'Imported from another source', 'ashbi-testimonials' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Confirm direct collection only for original reviews you received yourself. External source links and platform ratings remain excluded from review schema.', 'ashbi-testimonials' ); ?></p>
					</div>
					<div class="ashbte-toggles">
						<label class="ashbte-toggle"><input type="checkbox" name="verified" value="1" /> <span><?php esc_html_e( 'Manually checked', 'ashbi-testimonials' ); ?></span></label>
						<label class="ashbte-toggle"><input type="checkbox" name="featured" value="1" /> <span><?php esc_html_e( 'Featured', 'ashbi-testimonials' ); ?></span></label>
					</div>
					<fieldset class="ashbte-field">
						<legend><?php esc_html_e( 'Collections', 'ashbi-testimonials' ); ?></legend>
						<div class="ashbte-checklist" data-ashbte-collections></div>
						<div data-ashbte-custom-values></div>
					</fieldset>
				</section>
			</div>
			<footer class="ashbte-dialog__foot">
				<button type="button" class="button-link ashbte-danger-link" data-ashbte-editor-delete hidden><?php esc_html_e( 'Move to trash', 'ashbi-testimonials' ); ?></button>
				<span class="ashbte-spacer"></span>
				<button type="button" class="button" data-ashbte-close><?php esc_html_e( 'Cancel', 'ashbi-testimonials' ); ?></button>
				<button type="button" class="button" data-ashbte-save-new><?php esc_html_e( 'Save & add another', 'ashbi-testimonials' ); ?></button>
				<button type="submit" class="button button-primary" value="save"><?php esc_html_e( 'Publish testimonial', 'ashbi-testimonials' ); ?></button>
			</footer>
		</form>
	</dialog>

	<?php include ASHBTE_PLUGIN_DIR . 'admin/views/placement.php'; ?>

	<!-- Shortcode builder -->
	<dialog class="ashbte-dialog ashbte-dialog--wide" id="ashbte-builder-dialog" aria-labelledby="ashbte-builder-title">
		<form method="dialog" class="ashbte-dialog__form" data-ashbte-builder>
			<header class="ashbte-dialog__head">
				<h2 id="ashbte-builder-title"><?php esc_html_e( 'Customize display', 'ashbi-testimonials' ); ?></h2>
				<button type="button" class="ashbte-icon-btn" data-ashbte-close aria-label="<?php esc_attr_e( 'Close', 'ashbi-testimonials' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
			</header>
			<div class="ashbte-builder">
				<div class="ashbte-builder__controls">
					<fieldset class="ashbte-field">
						<legend><?php esc_html_e( 'Layout', 'ashbi-testimonials' ); ?></legend>
						<div class="ashbte-layout-picker">
							<?php foreach ( ASHBTE_Data::layouts() as $ashbte_key => $ashbte_label ) : ?>
								<label class="ashbte-layout-opt"><input type="radio" name="layout" value="<?php echo esc_attr( $ashbte_key ); ?>" <?php checked( $collection['layout'], $ashbte_key ); ?> /><span class="ashbte-layout-opt__art ashbte-layout-opt__art--<?php echo esc_attr( $ashbte_key ); ?>" aria-hidden="true"><i></i><i></i><i></i></span><span><?php echo esc_html( $ashbte_label ); ?></span></label>
							<?php endforeach; ?>
						</div>
					</fieldset>
					<div class="ashbte-field-row">
						<div class="ashbte-field"><label for="ashbte-b-columns"><?php esc_html_e( 'Columns', 'ashbi-testimonials' ); ?></label><select id="ashbte-b-columns" name="columns"><option>1</option><option>2</option><option selected>3</option><option>4</option></select></div>
						<div class="ashbte-field"><label for="ashbte-b-limit"><?php esc_html_e( 'Show', 'ashbi-testimonials' ); ?></label><input type="number" id="ashbte-b-limit" name="limit" min="1" max="100" value="9" /></div>
					</div>
					<details class="ashbte-builder__advanced">
						<summary><?php esc_html_e( 'More display options', 'ashbi-testimonials' ); ?></summary>
						<div class="ashbte-field-row">
							<div class="ashbte-field">
								<label for="ashbte-b-orderby"><?php esc_html_e( 'Order', 'ashbi-testimonials' ); ?></label>
								<select id="ashbte-b-orderby" name="orderby">
									<option value="date"><?php esc_html_e( 'Newest', 'ashbi-testimonials' ); ?></option>
									<option value="rating"><?php esc_html_e( 'Highest rated', 'ashbi-testimonials' ); ?></option>
									<option value="featured"><?php esc_html_e( 'Featured first', 'ashbi-testimonials' ); ?></option>
									<option value="random"><?php esc_html_e( 'Random', 'ashbi-testimonials' ); ?></option>
								</select>
							</div>
							<div class="ashbte-field">
								<label for="ashbte-b-min"><?php esc_html_e( 'Minimum rating', 'ashbi-testimonials' ); ?></label>
								<select id="ashbte-b-min" name="min_rating"><option value="0"><?php esc_html_e( 'Any', 'ashbi-testimonials' ); ?></option><option value="3">3+</option><option value="4">4+</option><option value="5">5</option></select>
							</div>
						</div>
						<div class="ashbte-field-row">
							<div class="ashbte-field">
								<label for="ashbte-b-theme"><?php esc_html_e( 'Theme', 'ashbi-testimonials' ); ?></label>
								<select id="ashbte-b-theme" name="theme"><option value="light"><?php esc_html_e( 'Light', 'ashbi-testimonials' ); ?></option><option value="dark"><?php esc_html_e( 'Dark', 'ashbi-testimonials' ); ?></option><option value="auto"><?php esc_html_e( 'Match visitor', 'ashbi-testimonials' ); ?></option></select>
							</div>
							<div class="ashbte-field">
								<label for="ashbte-b-words"><?php esc_html_e( 'Shorten to words', 'ashbi-testimonials' ); ?></label>
								<input type="number" id="ashbte-b-words" name="words" min="0" max="500" value="0" />
							</div>
						</div>
						<fieldset class="ashbte-field">
							<legend><?php esc_html_e( 'Card details', 'ashbi-testimonials' ); ?></legend>
							<div class="ashbte-checklist ashbte-checklist--inline" data-ashbte-builder-parts></div>
						</fieldset>
						<div class="ashbte-toggles">
							<label class="ashbte-toggle"><input type="checkbox" name="summary" value="1" /> <span><?php esc_html_e( 'Rating summary', 'ashbi-testimonials' ); ?></span></label>
							<label class="ashbte-toggle"><input type="checkbox" name="load_more" value="1" /> <span><?php esc_html_e( 'Load more button', 'ashbi-testimonials' ); ?></span></label>
							<label class="ashbte-toggle"><input type="checkbox" name="featured" value="1" /> <span><?php esc_html_e( 'Featured only', 'ashbi-testimonials' ); ?></span></label>
							<label class="ashbte-toggle"><input type="checkbox" name="verified" value="1" /> <span><?php esc_html_e( 'Manually checked only', 'ashbi-testimonials' ); ?></span></label>
							<label class="ashbte-toggle"><input type="checkbox" name="product_current" value="1" /> <span><?php esc_html_e( 'Only the product being viewed', 'ashbi-testimonials' ); ?></span></label>
						</div>
					</details>
				</div>
				<div class="ashbte-builder__output">
					<div class="ashbte-builder__code">
						<code data-ashbte-builder-code></code>
						<button type="button" class="button button-primary" data-ashbte-builder-copy><?php esc_html_e( 'Copy shortcode', 'ashbi-testimonials' ); ?></button>
					</div>
					<p class="description" data-ashbte-builder-note hidden><?php esc_html_e( 'Preview shows every product; on a product page only its own reviews appear.', 'ashbi-testimonials' ); ?></p>
					<div class="ashbte-builder__preview" data-ashbte-builder-preview aria-live="polite"></div>
				</div>
			</div>
		</form>
	</dialog>

	<!-- Import -->
	<dialog class="ashbte-dialog" id="ashbte-import-dialog" aria-labelledby="ashbte-import-title">
		<form method="dialog" class="ashbte-dialog__form" data-ashbte-import>
			<header class="ashbte-dialog__head">
				<h2 id="ashbte-import-title"><?php esc_html_e( 'Import from CSV', 'ashbi-testimonials' ); ?></h2>
				<button type="button" class="ashbte-icon-btn" data-ashbte-close aria-label="<?php esc_attr_e( 'Close', 'ashbi-testimonials' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
			</header>
			<div class="ashbte-dialog__body">
				<p><?php esc_html_e( 'The first row must be column headings. Only author and content (or rating) are required. Large files are imported in batches, so thousands of rows are fine.', 'ashbi-testimonials' ); ?></p>
				<p class="ashbte-columns"><?php echo esc_html( implode( ', ', ASHBTE_Admin::CSV_COLUMNS ) ); ?></p>
				<p class="description"><?php esc_html_e( 'Also accepted: name, review, text, title, stars, product, sku. Tip: export first to get a ready-made template.', 'ashbi-testimonials' ); ?></p>
				<div class="ashbte-field">
					<label for="ashbte-i-file"><?php esc_html_e( 'CSV file', 'ashbi-testimonials' ); ?></label>
					<input type="file" id="ashbte-i-file" accept=".csv,text/csv" required />
				</div>
				<?php if ( $ashbte_is_all ) : ?>
					<div class="ashbte-field">
						<label for="ashbte-i-collection"><?php esc_html_e( 'Add to collection', 'ashbi-testimonials' ); ?></label>
						<select id="ashbte-i-collection" data-ashbte-import-collection></select>
					</div>
				<?php endif; ?>
				<div class="ashbte-progress" data-ashbte-import-progress hidden><span></span></div>
				<p data-ashbte-import-result role="status"></p>
			</div>
			<footer class="ashbte-dialog__foot">
				<span class="ashbte-spacer"></span>
				<button type="button" class="button" data-ashbte-close><?php esc_html_e( 'Close', 'ashbi-testimonials' ); ?></button>
				<button type="submit" class="button button-primary" value="import"><?php esc_html_e( 'Import', 'ashbi-testimonials' ); ?></button>
			</footer>
		</form>
	</dialog>

	<?php include ASHBTE_PLUGIN_DIR . 'admin/views/collection-modal.php'; ?>
</div>
