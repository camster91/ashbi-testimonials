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
<dialog class="ashbte-dialog" id="ashbte-collection-dialog" aria-labelledby="ashbte-collection-dialog-title">
	<form method="dialog" class="ashbte-dialog__form" data-ashbte-collection-form>
		<header class="ashbte-dialog__head">
			<h2 id="ashbte-collection-dialog-title" data-new="<?php esc_attr_e( 'New collection', 'ashbi-testimonials' ); ?>" data-edit="<?php esc_attr_e( 'Collection settings', 'ashbi-testimonials' ); ?>"><?php esc_html_e( 'New collection', 'ashbi-testimonials' ); ?></h2>
			<button type="button" class="ashbte-icon-btn" data-ashbte-close aria-label="<?php esc_attr_e( 'Close', 'ashbi-testimonials' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
		</header>
		<div class="ashbte-dialog__body">
			<input type="hidden" name="id" value="0" />
			<div class="ashbte-field">
				<label for="ashbte-c-name"><?php esc_html_e( 'Name', 'ashbi-testimonials' ); ?></label>
				<input type="text" id="ashbte-c-name" name="name" required class="widefat" placeholder="<?php esc_attr_e( 'e.g. Store reviews, Acme CRM, Coffee beans', 'ashbi-testimonials' ); ?>" />
			</div>
			<fieldset class="ashbte-field">
				<legend><?php esc_html_e( 'Type', 'ashbi-testimonials' ); ?></legend>
				<div class="ashbte-type-picker">
					<?php foreach ( ASHBTE_Data::types() as $ashbte_key => $ashbte_type ) : ?>
						<label class="ashbte-type">
							<input type="radio" name="type" value="<?php echo esc_attr( $ashbte_key ); ?>" <?php checked( 'general', $ashbte_key ); ?> />
							<span class="dashicons dashicons-<?php echo esc_attr( $ashbte_type['icon'] ); ?>" aria-hidden="true"></span>
							<span><?php echo esc_html( $ashbte_type['label'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>
			<div class="ashbte-field-row">
				<div class="ashbte-field">
					<label for="ashbte-c-layout"><?php esc_html_e( 'Default layout', 'ashbi-testimonials' ); ?></label>
					<select id="ashbte-c-layout" name="layout">
						<?php foreach ( ASHBTE_Data::layouts() as $ashbte_key => $ashbte_label ) : ?>
							<option value="<?php echo esc_attr( $ashbte_key ); ?>"><?php echo esc_html( $ashbte_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="ashbte-field">
					<label for="ashbte-c-accent"><?php esc_html_e( 'Accent colour', 'ashbi-testimonials' ); ?></label>
					<span class="ashbte-color">
						<input type="color" id="ashbte-c-accent" name="accent" value="<?php echo esc_attr( ashbte_settings()['accent'] ); ?>" />
						<label class="ashbte-inline-check"><input type="checkbox" name="accent_default" checked /> <?php esc_html_e( 'Use site default', 'ashbi-testimonials' ); ?></label>
					</span>
				</div>
			</div>
			<fieldset class="ashbte-field">
				<legend><?php esc_html_e( 'Custom review fields', 'ashbi-testimonials' ); ?></legend>
				<p class="description"><?php esc_html_e( 'Add up to ten questions. Public answers appear beside approved reviews. Making a field private hides its previous answers.', 'ashbi-testimonials' ); ?></p>
				<div data-ashbte-field-definitions></div>
				<button type="button" class="button" data-ashbte-add-field><?php esc_html_e( 'Add field', 'ashbi-testimonials' ); ?></button>
			</fieldset>
			<div class="ashbte-field" data-ashbte-edit-only hidden>
				<label for="ashbte-c-slug"><?php esc_html_e( 'Shortcode slug', 'ashbi-testimonials' ); ?></label>
				<input type="text" id="ashbte-c-slug" name="slug" class="widefat" />
				<p class="description"><?php esc_html_e( 'Changing this breaks shortcodes that use the old slug.', 'ashbi-testimonials' ); ?></p>
			</div>
		</div>
		<footer class="ashbte-dialog__foot">
			<button type="button" class="button-link ashbte-danger-link" data-ashbte-delete-collection data-ashbte-edit-only hidden><?php esc_html_e( 'Delete collection', 'ashbi-testimonials' ); ?></button>
			<span class="ashbte-spacer"></span>
			<button type="button" class="button" data-ashbte-close><?php esc_html_e( 'Cancel', 'ashbi-testimonials' ); ?></button>
			<button type="submit" class="button button-primary" value="save"><?php esc_html_e( 'Save collection', 'ashbi-testimonials' ); ?></button>
		</footer>
	</form>
</dialog>
