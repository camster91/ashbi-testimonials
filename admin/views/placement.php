<?php
/**
 * Admin: collection placement options.
 *
 * @package Ashbi_Testimonials
 * @var array $collection Collection data.
 * @var bool  $abt_is_all Whether the screen includes all collections.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$abt_collection_attr = $abt_is_all ? '' : ' collection="' . ( is_numeric( $collection['slug'] ) ? (string) $collection['id'] : $collection['slug'] ) . '"';
$abt_placements = array(
	array(
		'title' => __( 'Display testimonials', 'ashbi-testimonials' ),
		'copy'  => __( 'Copy testimonials shortcode', 'ashbi-testimonials' ),
		'text'  => __( 'Show published testimonials on a page.', 'ashbi-testimonials' ),
		'code'  => '[ashbi_testimonials' . $abt_collection_attr . ']',
	),
	array(
		'title' => __( 'Show a rating summary', 'ashbi-testimonials' ),
		'copy'  => __( 'Copy rating shortcode', 'ashbi-testimonials' ),
		'text'  => __( 'Show an average rating and a count of published ratings.', 'ashbi-testimonials' ),
		'code'  => '[ashbi_rating' . $abt_collection_attr . ']',
	),
);
if ( ! $abt_is_all ) {
	$abt_placements[] = array(
		'title' => __( 'Collect new reviews', 'ashbi-testimonials' ),
		'copy'  => __( 'Copy review form shortcode', 'ashbi-testimonials' ),
		'text'  => __( 'Add a form to your site. Submissions wait for approval before they appear publicly.', 'ashbi-testimonials' ),
		'code'  => '[ashbi_testimonial_form' . $abt_collection_attr . ']',
		'link'  => ABT_Admin::instance()->review_form_page_url( $collection['id'] ),
		'page'  => current_user_can( 'edit_pages' ) ? add_query_arg(
			array( 'post_type' => 'page', 'abt_review_form_collection' => (int) $collection['id'] ),
			admin_url( 'post-new.php' )
		) : '',
	);
}
?>
<dialog class="abt-dialog" id="abt-placement-dialog" aria-labelledby="abt-placement-title">
	<div class="abt-dialog__form">
		<header class="abt-dialog__head">
			<h2 id="abt-placement-title"><?php esc_html_e( 'Place on site', 'ashbi-testimonials' ); ?></h2>
			<button type="button" class="abt-icon-btn" data-abt-close aria-label="<?php esc_attr_e( 'Close', 'ashbi-testimonials' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
		</header>
		<div class="abt-dialog__body">
			<p><?php esc_html_e( 'In the WordPress editor, search for Testimonials, Rating Summary or Review Form and choose a collection. In Elementor, add the Ashbi Reviews widget.', 'ashbi-testimonials' ); ?></p>
			<p><?php esc_html_e( 'You can also copy a shortcode below. Paste it into a Shortcode block, or into the content in the Classic Editor.', 'ashbi-testimonials' ); ?></p>
			<?php foreach ( $abt_placements as $abt_placement ) : ?>
				<section class="abt-placement-option">
					<h3><?php echo esc_html( $abt_placement['title'] ); ?></h3>
					<p><?php echo esc_html( $abt_placement['text'] ); ?></p>
					<?php if ( ! empty( $abt_placement['link'] ) ) : ?>
						<p>
							<button type="button" class="button button-primary" data-abt-copy="<?php echo esc_attr( $abt_placement['link'] ); ?>"><?php esc_html_e( 'Copy review link', 'ashbi-testimonials' ); ?></button>
							<a class="button" href="<?php echo esc_url( $abt_placement['link'] ); ?>"><?php esc_html_e( 'Open review form', 'ashbi-testimonials' ); ?></a>
						</p>
					<?php elseif ( ! empty( $abt_placement['page'] ) ) : ?>
						<p><a class="button button-primary" href="<?php echo esc_url( $abt_placement['page'] ); ?>"><?php esc_html_e( 'Create a review form page', 'ashbi-testimonials' ); ?></a></p>
						<p><?php esc_html_e( 'The new page opens in the editor. Add a title and review it before publishing.', 'ashbi-testimonials' ); ?></p>
					<?php endif; ?>
					<div class="abt-placement-option__code">
						<code><?php echo esc_html( $abt_placement['code'] ); ?></code>
						<button type="button" class="button" data-abt-copy="<?php echo esc_attr( $abt_placement['code'] ); ?>"><?php echo esc_html( $abt_placement['copy'] ); ?></button>
					</div>
				</section>
			<?php endforeach; ?>
			<?php if ( $abt_is_all ) : ?>
				<p><?php esc_html_e( 'To collect reviews, open a specific collection and copy its form shortcode.', 'ashbi-testimonials' ); ?></p>
			<?php endif; ?>
		</div>
		<footer class="abt-dialog__foot">
			<button type="button" class="button" data-abt-close><?php esc_html_e( 'Done', 'ashbi-testimonials' ); ?></button>
			<button type="button" class="button button-primary" data-abt-open="abt-builder-dialog"><?php esc_html_e( 'Customize display', 'ashbi-testimonials' ); ?></button>
		</footer>
	</div>
</dialog>
