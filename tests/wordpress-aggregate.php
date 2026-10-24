<?php
/** Production summary filters agree with a fixed fictional review set. */
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
$check = static function( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( 'Aggregate: ' . $message ); } };
$data = ABT_Data::instance();
$a = $data->save_collection( array( 'name' => 'Aggregate A' ) ); $b = $data->save_collection( array( 'name' => 'Aggregate B' ) );
$product = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Aggregate product', 'post_status' => 'publish' ) );
$ids = array();
foreach ( range( 1, 8 ) as $i ) { $ids[ $i ] = $data->save( array( 'author' => 'Aggregate reviewer ' . $i, 'content' => 'Fictional aggregate test.', 'rating' => $i <= 5 ? $i : 0, 'product_id' => $i <= 3 ? $product : 0, 'subject' => $i <= 2 ? 'Plan' : '', 'featured' => $i <= 2, 'verified' => $i <= 3, 'origin' => 4 === $i ? 'imported' : 'direct', 'source' => 4 === $i ? 'External' : 'Website', 'status' => 8 === $i ? 'pending' : 'publish', 'collections' => 1 === $i ? array( $a['id'], $b['id'] ) : array( $a['id'] ) ) ); }
$cases = array(
 array( array( 'collection' => array( $a['id'], $b['id'] ) ), 7, 5, 3.0, 1 ),
 array( array( 'collection' => $b['id'] ), 1, 1, 1.0, 0 ),
 array( array( 'collection' => $a['id'], 'product_id' => $product ), 3, 3, 2.0, 0 ),
 array( array( 'collection' => $a['id'], 'subject' => 'Plan' ), 2, 2, 1.5, 0 ),
 array( array( 'collection' => $a['id'], 'featured' => true ), 2, 2, 1.5, 0 ),
 array( array( 'collection' => $a['id'], 'verified' => true ), 3, 3, 2.0, 0 ),
 array( array( 'collection' => $a['id'], 'min_rating' => 4 ), 2, 2, 4.5, 1 ),
 array( array( 'collection' => $a['id'], 'rating' => 4 ), 1, 1, 4.0, 1 ),
 array( array( 'ids' => array( $ids[1], $ids[5], $ids[8] ) ), 2, 2, 3.0, 0 ),
);
foreach ( $cases as $i => $case ) { $r = $data->stats( $case[0] ); $check( $r['count'] === $case[1] && $r['rated'] === $case[2] && (float) $r['average'] === $case[3] && $r['ineligible'] === $case[4], 'all count/rating/provenance expectations for case ' . $i . ': ' . wp_json_encode( $r ) ); }
fwrite( STDOUT, "OK: scoped aggregate filters, overlapping collections and unrated/provenance counts passed\n" );

// A worker can retain the pre-change option value while another worker publishes.
$stale_generation = get_option( ABT_CACHE_OPTION, 1 );
$data->flush();
$data->stats( array( 'collection' => $a['id'] ) );
$data->save( array( 'author' => 'Concurrent publication', 'rating' => 5, 'origin' => 'direct', 'collections' => array( $a['id'] ) ) );
$stale_worker = static fn() => $stale_generation;
add_filter( 'pre_option_' . ABT_CACHE_OPTION, $stale_worker );
$data->flush();
remove_filter( 'pre_option_' . ABT_CACHE_OPTION, $stale_worker );
$check( 8 === $data->stats( array( 'collection' => $a['id'] ) )['count'], 'a stale worker cannot reuse a cached generation from before a concurrent publication' );
fwrite( STDOUT, "OK: stale-worker cache invalidation cannot reuse an earlier summary\n" );
