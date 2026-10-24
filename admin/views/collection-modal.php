<?php
/**
 * Admin: create / edit collection dialog.
 *
 * @package Ashbi_Testimonials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<dialog class="abt-dialog" id="abt-collection-dialog" aria-labelledby="abt-collection-dialog-title">
	<form method="dialog" class="abt-dialog__form" data-abt-collection-form>
		<header class="abt-dialog__head">
			<h2 id="abt-collection-dialog-title" data-new="<?php esc_attr_e( 'New collection', 'ashbi-testimonials' ); ?>" data-edit="<?php esc_attr_e( 'Collection settings', 'ashbi-testimonials' ); ?>"><?php esc_html_e( 'New collection', 'ashbi-testimonials' ); ?></h2>
			<button type="button" class="abt-icon-btn" data-abt-close aria-label="<?php esc_attr_e( 'Close', 'ashbi-testimonials' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
		</header>
		<div class="abt-dialog__body">
			<input type="hidden" name="id" value="0" />
			<div class="abt-field">
				<label for="abt-c-name"><?php esc_html_e( 'Name', 'ashbi-testimonials' ); ?></label>
				<input type="text" id="abt-c-name" name="name" required class="widefat" placeholder="<?php esc_attr_e( 'e.g. Store reviews, Acme CRM, Coffee beans', 'ashbi-testimonials' ); ?>" />
			</div>
			<fieldset class="abt-field">
				<legend><?php esc_html_e( 'Type', 'ashbi-testimonials' ); ?></legend>
				<div class="abt-type-picker">
					<?php foreach ( ABT_Data::types() as $abt_key => $abt_type ) : ?>
						<label class="abt-type">
							<input type="radio" name="type" value="<?php echo esc_attr( $abt_key ); ?>" <?php checked( 'general', $abt_key ); ?> />
							<span class="dashicons dashicons-<?php echo esc_attr( $abt_type['icon'] ); ?>" aria-hidden="true"></span>
							<span><?php echo esc_html( $abt_type['label'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>
			<div class="abt-field-row">
				<div class="abt-field">
					<label for="abt-c-layout"><?php esc_html_e( 'Default layout', 'ashbi-testimonials' ); ?></label>
					<select id="abt-c-layout" name="layout">
						<?php foreach ( ABT_Data::layouts() as $abt_key => $abt_label ) : ?>
							<option value="<?php echo esc_attr( $abt_key ); ?>"><?php echo esc_html( $abt_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="abt-field">
					<label for="abt-c-accent"><?php esc_html_e( 'Accent colour', 'ashbi-testimonials' ); ?></label>
					<span class="abt-color">
						<input type="color" id="abt-c-accent" name="accent" value="<?php echo esc_attr( abt_settings()['accent'] ); ?>" />
						<label class="abt-inline-check"><input type="checkbox" name="accent_default" checked /> <?php esc_html_e( 'Use site default', 'ashbi-testimonials' ); ?></label>
					</span>
				</div>
			</div>
			<fieldset class="abt-field">
				<legend><?php esc_html_e( 'Custom review fields', 'ashbi-testimonials' ); ?></legend>
				<p class="description"><?php esc_html_e( 'Add up to ten questions. Public answers appear beside approved reviews. Making a field private hides its previous answers.', 'ashbi-testimonials' ); ?></p>
				<div data-abt-field-definitions></div>
				<button type="button" class="button" data-abt-add-field><?php esc_html_e( 'Add field', 'ashbi-testimonials' ); ?></button>
			</fieldset>
			<div class="abt-field" data-abt-edit-only hidden>
				<label for="abt-c-slug"><?php esc_html_e( 'Shortcode slug', 'ashbi-testimonials' ); ?></label>
				<input type="text" id="abt-c-slug" name="slug" class="widefat" />
				<p class="description"><?php esc_html_e( 'Changing this breaks shortcodes that use the old slug.', 'ashbi-testimonials' ); ?></p>
			</div>
		</div>
		<footer class="abt-dialog__foot">
			<button type="button" class="button-link abt-danger-link" data-abt-delete-collection data-abt-edit-only hidden><?php esc_html_e( 'Delete collection', 'ashbi-testimonials' ); ?></button>
			<span class="abt-spacer"></span>
			<button type="button" class="button" data-abt-close><?php esc_html_e( 'Cancel', 'ashbi-testimonials' ); ?></button>
			<button type="submit" class="button button-primary" value="save"><?php esc_html_e( 'Save collection', 'ashbi-testimonials' ); ?></button>
		</footer>
	</form>
</dialog>
