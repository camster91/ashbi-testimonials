<?php
/**
 * Admin: collection placement options.
 *
 * @package Ashbi_Testimonials
 * @var array $collection Collection data.
 * @var bool  $ashbte_is_all Whether the screen includes all collections.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ashbte_collection_attr = $ashbte_is_all ? '' : ' collection="' . ( is_numeric( $collection['slug'] ) ? (string) $collection['id'] : $collection['slug'] ) . '"';
$ashbte_placements = array(
	array(
		'title' => __( 'Display testimonials', 'ashbi-testimonials' ),
		'copy'  => __( 'Copy testimonials shortcode', 'ashbi-testimonials' ),
		'text'  => __( 'Show published testimonials on a page.', 'ashbi-testimonials' ),
		'code'  => '[ashbte_testimonials' . $ashbte_collection_attr . ']',
	),
	array(
		'title' => __( 'Show a rating summary', 'ashbi-testimonials' ),
		'copy'  => __( 'Copy rating shortcode', 'ashbi-testimonials' ),
		'text'  => __( 'Show an average rating and a count of published ratings.', 'ashbi-testimonials' ),
		'code'  => '[ashbte_rating' . $ashbte_collection_attr . ']',
	),
);
if ( ! $ashbte_is_all ) {
	$ashbte_placements[] = array(
		'title' => __( 'Collect new reviews', 'ashbi-testimonials' ),
		'copy'  => __( 'Copy review form shortcode', 'ashbi-testimonials' ),
		'text'  => __( 'Add a form to your site. Submissions wait for approval before they appear publicly.', 'ashbi-testimonials' ),
		'code'  => '[ashbte_form' . $ashbte_collection_attr . ']',
		'link'  => ASHBTE_Admin::instance()->review_form_page_url( $collection['id'] ),
		'page'  => current_user_can( 'edit_pages' ) ? ASHBTE_Admin::url( array( 'post_type' => 'page', 'ashbte_review_form_collection' => (int) $collection['id'] ), 'post-new.php' ) : '',
	);
}
?>
<dialog class="ashbte-dialog" id="ashbte-placement-dialog" aria-labelledby="ashbte-placement-title">
	<div class="ashbte-dialog__form">
		<header class="ashbte-dialog__head">
			<h2 id="ashbte-placement-title"><?php esc_html_e( 'Place on site', 'ashbi-testimonials' ); ?></h2>
			<button type="button" class="ashbte-icon-btn" data-ashbte-close aria-label="<?php esc_attr_e( 'Close', 'ashbi-testimonials' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
		</header>
		<div class="ashbte-dialog__body">
			<p><?php esc_html_e( 'In the WordPress editor, search for Testimonials, Rating Summary or Review Form and choose a collection. In Elementor, add the Ashbi Reviews widget.', 'ashbi-testimonials' ); ?></p>
			<p><?php esc_html_e( 'You can also copy a shortcode below. Paste it into a Shortcode block, or into the content in the Classic Editor.', 'ashbi-testimonials' ); ?></p>
			<?php foreach ( $ashbte_placements as $ashbte_placement ) : ?>
				<section class="ashbte-placement-option">
					<h3><?php echo esc_html( $ashbte_placement['title'] ); ?></h3>
					<p><?php echo esc_html( $ashbte_placement['text'] ); ?></p>
					<?php if ( ! empty( $ashbte_placement['link'] ) ) : ?>
						<p>
							<button type="button" class="button button-primary" data-ashbte-copy="<?php echo esc_attr( $ashbte_placement['link'] ); ?>"><?php esc_html_e( 'Copy review link', 'ashbi-testimonials' ); ?></button>
							<a class="button" href="<?php echo esc_url( $ashbte_placement['link'] ); ?>"><?php esc_html_e( 'Open review form', 'ashbi-testimonials' ); ?></a>
						</p>
					<?php endif; ?>
					<?php if ( ! empty( $ashbte_placement['page'] ) ) : ?>
						<p><a class="button<?php echo empty( $ashbte_placement['link'] ) ? ' button-primary' : ''; ?>" href="<?php echo esc_url( $ashbte_placement['page'] ); ?>"><?php esc_html_e( 'Create a review form page', 'ashbi-testimonials' ); ?></a></p>
						<p><?php esc_html_e( 'The new page opens in the editor. Add a title and review it before publishing.', 'ashbi-testimonials' ); ?></p>
					<?php endif; ?>
					<div class="ashbte-placement-option__code">
						<code><?php echo esc_html( $ashbte_placement['code'] ); ?></code>
						<button type="button" class="button" data-ashbte-copy="<?php echo esc_attr( $ashbte_placement['code'] ); ?>"><?php echo esc_html( $ashbte_placement['copy'] ); ?></button>
					</div>
				</section>
			<?php endforeach; ?>
			<?php if ( $ashbte_is_all ) : ?>
				<p><?php esc_html_e( 'To collect reviews, open a specific collection and copy its form shortcode.', 'ashbi-testimonials' ); ?></p>
			<?php endif; ?>
		</div>
		<footer class="ashbte-dialog__foot">
			<button type="button" class="button" data-ashbte-close><?php esc_html_e( 'Done', 'ashbi-testimonials' ); ?></button>
			<button type="button" class="button button-primary" data-ashbte-open="ashbte-builder-dialog"><?php esc_html_e( 'Customize display', 'ashbi-testimonials' ); ?></button>
		</footer>
	</div>
</dialog>
