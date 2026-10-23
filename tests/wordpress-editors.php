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
$rating = ABT_Editors::render( 'rating', $attrs );
$check( false !== strpos( $rating, 'abt-summary' ), 'rating renderer reuses the canonical summary' );
$form = ABT_Editors::render( 'review-form', $attrs );
$check( false !== strpos( $form, 'name="abt_target" value="' . $col['id'] . ':0"' ), 'form renderer binds to the selected collection' );
$check( ! wp_script_is( 'abt-block-editor', 'enqueued' ), 'editor script is not enqueued on the frontend' );
fwrite( STDOUT, "OK: native editor registration/rendering passed\n" );
