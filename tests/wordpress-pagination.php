<?php
/** Stable ties and bounded read-only load-more requests. */
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
$check = static function( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( 'Pagination: ' . $message ); } };
$data = ABT_Data::instance(); $shortcode = ABT_Shortcode::instance();
foreach ( array( 'rating', 'featured' ) as $sort ) {
 $args = $data->query_args( array( 'orderby' => $sort ) );
 $check( isset( $args['orderby']['ID'] ), $sort . ' ties use a stable ID order' );
}
$check( method_exists( $shortcode, 'public_page' ), 'public request validator exists' );
foreach ( array( array( array(), '2' ), array( '{}', array() ), array( '{}', '999999999999999' ), array( '{}', '2x' ), array( '{bad', '2' ), array( '[]', '2' ), array( '{"orderby":"random"}', '2' ), array( '{"orderby":" random "}', '2' ), array( '{"product":" current "}', '2' ), array( '{"limit":100}', '1000' ), array( '{"ids":"' . implode( ',', range( 1, 101 ) ) . '"}', '2' ), array( '{"collection":[]}', '2' ), array( '{"product":"current"}', '2' ) ) as $input ) {
 $check( is_wp_error( $shortcode->public_page( $input[0], $input[1] ) ), 'malformed/unbounded/context-dependent request is rejected' );
}
$valid = $shortcode->public_page( '{"limit":"20","orderby":"rating"}', '2' );
$check( ! is_wp_error( $valid ) && 'publish' === $valid['query']['status'] && 2 === $valid['query']['page'], 'valid bounded request remains published-only' );
$quoted = $shortcode->public_page( wp_json_encode( array( 'label' => 'A "quoted" label' ) ), '2' );
$check( ! is_wp_error( $quoted ), 'unslashed JSON retains escaped label characters' );
$col = $data->save_collection( array( 'name' => 'Stable page ties' ) ); $ids = array();
foreach ( range( 1, 6 ) as $i ) { $ids[] = $data->save( array( 'author' => 'Tied reviewer ' . $i, 'rating' => 5, 'featured' => 1, 'date' => '2025-01-01', 'collections' => array( $col['id'] ) ) ); }
foreach ( array( 'date', 'rating', 'featured' ) as $sort ) {
 $seen = array(); foreach ( range( 1, 3 ) as $page ) { $r = $data->query( array( 'collection' => $col['id'], 'orderby' => $sort, 'per_page' => 2, 'page' => $page ) ); $seen = array_merge( $seen, array_column( $r['items'], 'id' ) ); }
 $check( 6 === count( array_unique( $seen ) ) && ! array_diff( $ids, $seen ), $sort . ' pages do not duplicate or skip tied reviews' );
}
fwrite( STDOUT, "OK: deterministic tie ordering and bounded public pagination passed\n" );
