<?php
/** Native block rendering contract on disposable WordPress. */
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
$check = static function ( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( 'Editors: ' . $message ); } };
$registry = WP_Block_Type_Registry::get_instance();
foreach ( array( 'ashbi/testimonials', 'ashbi/rating', 'ashbi/review-form' ) as $name ) {
	$check( $registry->is_registered( $name ), $name . ' registered' );
}
$data = ABT_Data::instance();
$col = $data->save_collection( array( 'name' => 'Editor fixtures' ) );
$id = $data->save( array( 'author' => 'Editor public review', 'content' => 'Public editor text', 'rating' => 5, 'email' => 'SECRET-EDITOR@example.test', 'collections' => array( $col['id'] ) ) );
$attrs = array( 'collection' => (string) $col['id'], 'layout' => 'list', 'summary' => '1', 'schema' => '0' );
$html = render_block( array( 'blockName' => 'ashbi/testimonials', 'attrs' => $attrs, 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
$check( false !== strpos( $html, 'Editor public review' ) && false !== strpos( $html, 'abt-summary' ) && false === strpos( $html, 'SECRET-EDITOR' ), 'native block renders public review and summary without email' );
$check( '' === ABT_Editors::render( 'testimonials', array() ), 'unconfigured block never exposes all reviews by default' );
$check( '' === ABT_Editors::render( 'testimonials', array( 'product' => '  ' ) ), 'blank product scope does not expose all reviews' );
$check( '' === ABT_Editors::render( 'testimonials', array( 'product' => 'invalid' ) ), 'invalid product scope does not expose all reviews' );
$check( '' === ABT_Editors::render( 'review-form', array( 'product' => 'current' ) ), 'a product alone cannot configure a form' );
$shortcodes = ABT_Shortcode::instance();
$check( '' === $shortcodes->rating( array( 'product' => '-1' ) ), 'invalid product scope cannot fall back to the aggregate across all reviews' );
$check( is_wp_error( $shortcodes->public_page( '{"product":"invalid"}', '2' ) ), 'pagination rejects invalid product scope' );
$check( is_wp_error( $shortcodes->public_page( '{"collection":"abt-no-such-collection"}', '2' ) ), 'pagination rejects unresolved collection scope' );
$rating = ABT_Editors::render( 'rating', $attrs );
$check( false !== strpos( $rating, 'abt-summary' ), 'rating renderer reuses the canonical summary' );
$form = ABT_Editors::render( 'review-form', $attrs );
$check( false !== strpos( $form, 'name="abt_target" value="' . $col['id'] . ':0"' ), 'form renderer binds to the selected collection' );
$check( ! wp_script_is( 'abt-block-editor', 'enqueued' ), 'editor script is not enqueued on the frontend' );

$admin = ABT_Admin::instance();
$block = '<!-- wp:ashbi/review-form {"collection":"' . $col['id'] . '"} /-->';
$page_id = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Review link lifecycle', 'post_content' => $block, 'post_status' => 'draft' ), true );
$check( is_int( $page_id ) && $page_id > 0, 'form page fixture created' );
$check( '' === $admin->review_form_page_url( $col['id'] ), 'draft form page has no share link' );
wp_update_post( array( 'ID' => $page_id, 'post_status' => 'publish' ) );
$check( get_permalink( $page_id ) === $admin->review_form_page_url( $col['id'] ), 'published form page is shareable' );
wp_update_post( array( 'ID' => $page_id, 'post_password' => 'test-password' ) );
$check( '' === $admin->review_form_page_url( $col['id'] ), 'password-protected page is excluded' );
wp_update_post( array( 'ID' => $page_id, 'post_status' => 'draft', 'post_password' => '' ) );
$check( '' === $admin->review_form_page_url( $col['id'] ), 'unpublishing the page removes its share link' );
wp_update_post( array( 'ID' => $page_id, 'post_status' => 'publish', 'post_content' => '[ashbi_testimonial_form collection="' . $col['id'] . '"]' ) );
$check( get_permalink( $page_id ) === $admin->review_form_page_url( $col['id'] ), 'published Classic Editor form is shareable' );
wp_update_post( array( 'ID' => $page_id, 'post_content' => 'The form has been removed.' ) );
$check( '' === $admin->review_form_page_url( $col['id'] ), 'removing the form removes its share link' );
$check( '' === get_post_meta( $page_id, '_abt_review_form_collections', true ), 'removing the form clears the page index' );

fwrite( STDOUT, "OK: native editor registration/rendering passed\n" );
