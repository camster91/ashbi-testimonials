<?php
/** Schema provenance/visibility regressions on disposable WordPress. */
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
$check = static function ( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( 'Schema eligibility: ' . $message ); } };
$data = ABT_Data::instance();
$col = $data->save_collection( array( 'name' => 'Eligible product', 'type' => 'product' ) );
$product = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Eligible product', 'post_status' => 'publish' ) );
$id = $data->save( array( 'author' => 'Direct reviewer', 'content' => 'Original direct review.', 'rating' => 5, 'origin' => 'direct', 'source' => 'Website', 'product_id' => $product, 'collections' => array( $col['id'] ) ) );
$render_schema = static function ( $extra = '' ) use ( $col, $product ) {
	$prop = new ReflectionProperty( 'ABT_Render', 'schema' );
	$prop->setValue( ABT_Render::instance(), array() );
	do_shortcode( '[ashbi_testimonials collection="' . $col['id'] . '" product="' . $product . '" summary="1" ' . $extra . ']' );
	ob_start(); ABT_Render::instance()->print_schema(); return ob_get_clean();
};
$check( false !== strpos( $render_schema(), 'AggregateRating' ), 'direct originals with visible summary have product markup' );
foreach ( array( 'summary="0"', 'featured="1"', 'verified="1"', 'min_rating="4"', 'ids="' . $id . '"', 'orderby="random"' ) as $curated ) {
	$check( '' === $render_schema( $curated ), 'hidden/curated aggregates are excluded: ' . $curated );
}
foreach ( array( 'manual', 'imported', '' ) as $origin ) {
	$other = $data->save( array( 'author' => 'Unknown origin', 'rating' => 1, 'origin' => $origin, 'product_id' => $product, 'collections' => array( $col['id'] ) ) );
	$check( '' === $render_schema(), 'mixed provenance does not become an aggregate: ' . $origin );
	wp_delete_post( $other, true );
}
foreach ( array( array( 'source' => 'Google' ), array( 'source_url' => 'https://reviews.example.test/external' ) ) as $external ) {
	$other = $data->save( array_merge( array( 'author' => 'External origin', 'rating' => 1, 'origin' => 'direct', 'product_id' => $product, 'collections' => array( $col['id'] ) ), $external ) );
	$check( '' === $render_schema(), 'external source cannot be disguised by direct flag' );
	wp_delete_post( $other, true );
}
$data->save( array( 'author' => 'Updated direct reviewer', 'content' => 'Still original.', 'rating' => 5, 'source' => 'Website', 'product_id' => $product, 'collections' => array( $col['id'] ) ), $id );
$check( 'direct' === $data->get( $id )['origin'], 'ordinary editing preserves existing provenance' );
$csv_id = ABT_Admin::instance()->import_row( array( 'author' => 'CSV reviewer', 'rating' => 5, 'origin' => 'direct' ) );
$check( 'imported' === $data->get( $csv_id )['origin'], 'CSV cannot self-assert direct collection' );
fwrite( STDOUT, "OK: provenance and visible aggregate schema checks passed\n" );
