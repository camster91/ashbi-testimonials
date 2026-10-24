<?php
/** Deterministic MySQL benchmark. Never run on an existing site.
 * ABT_DISPOSABLE_TEST=1 wp eval-file .../tests/wordpress-benchmark.php
 */
if ( ! defined( 'ABSPATH' ) || '1' !== getenv( 'ABT_DISPOSABLE_TEST' ) ) { exit( 1 ); }
global $wpdb;
if ( defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ) { throw new RuntimeException( 'MySQL/MariaDB evidence is required; SQLite is not interchangeable.' ); }
if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", ABT_Data::POST_TYPE ) ) ) { throw new RuntimeException( 'Benchmark requires a fresh disposable site without testimonials.' ); }
register_post_type( 'abt_bench_product', array( 'public' => true, 'supports' => array( 'title' ) ) );
$data = ABT_Data::instance(); $cols = array(); $products = array();
foreach ( range( 1, 10 ) as $i ) { $c = $data->save_collection( array( 'name' => 'Synthetic benchmark collection ' . $i ) ); $term = get_term( $c['id'], ABT_Data::TAXONOMY ); $cols[] = $term; }
foreach ( range( 1, 100 ) as $i ) { $products[] = wp_insert_post( array( 'post_type' => 'abt_bench_product', 'post_title' => 'Synthetic benchmark product ' . $i, 'post_status' => 'publish' ) ); }
$start_id = (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts}" ) + 100;
$seeded = 0; $results = array();
$seed = static function( $target ) use ( &$seeded, $start_id, $cols, $products, $wpdb ) {
 for ( $from = $seeded; $from < $target; $from += 500 ) {
  $posts = array(); $meta = array(); $relationships = array();
  for ( $i = $from; $i < min( $from + 500, $target ); ++$i ) {
   $id = $start_id + $i; $date = gmdate( 'Y-m-d H:i:s', gmmktime( 0, 0, 0, 1, 1, 2025 ) + ( $i % 365 ) * DAY_IN_SECONDS );
   $posts[] = $wpdb->prepare( '(%d,%s,%s,%s,%s,%s,%s,%s,%s)', $id, 'Synthetic benchmark reviewer ' . $i, 'Fictional benchmark feedback about a synthetic project. No real customer data.', 'publish', ABT_Data::POST_TYPE, $date, $date, $date, $date );
   $values = array( 'custom_fields' => 'a:0:{}', 'rating' => 1 + $i % 5, 'product_id' => $products[ $i % 100 ], 'origin' => 'direct', 'source' => 'Website', 'verified' => $i % 2, 'featured' => 0 === $i % 10 ? 1 : 0, 'email' => 'synthetic-' . $i . '@example.test' );
   foreach ( ABT_Data::META as $field => $key ) { $meta[] = $wpdb->prepare( '(%d,%s,%s)', $id, $key, $values[ $field ] ?? '' ); }
   $relationships[] = $wpdb->prepare( '(%d,%d,0)', $id, $cols[ $i % 10 ]->term_taxonomy_id );
  }
  if ( false === $wpdb->query( "INSERT INTO {$wpdb->posts} (ID,post_title,post_content,post_status,post_type,post_date,post_date_gmt,post_modified,post_modified_gmt) VALUES " . implode( ',', $posts ) ) || false === $wpdb->query( "INSERT INTO {$wpdb->postmeta} (post_id,meta_key,meta_value) VALUES " . implode( ',', $meta ) ) || false === $wpdb->query( "INSERT INTO {$wpdb->term_relationships} (object_id,term_taxonomy_id,term_order) VALUES " . implode( ',', $relationships ) ) ) { throw new RuntimeException( 'Synthetic seed failed: ' . $wpdb->last_error ); }
 }
 $seeded = $target; wp_cache_flush(); wp_update_term_count_now( array_map( static fn( $t ) => $t->term_taxonomy_id, $cols ), ABT_Data::TAXONOMY ); ABT_Data::instance()->flush();
};
$percentile = static function( $a, $p ) { sort( $a ); return $a[ max( 0, (int) ceil( count( $a ) * $p ) - 1 ) ]; };
$cases = array(
 'collection_date' => static fn() => $data->query( array( 'collection' => $cols[0]->term_id, 'per_page' => 20 ) ),
 'product_date' => static fn() => $data->query( array( 'product_id' => $products[0], 'per_page' => 20 ) ),
 'rating_sort' => static fn() => $data->query( array( 'collection' => $cols[0]->term_id, 'orderby' => 'rating', 'per_page' => 20 ) ),
 'featured_sort' => static fn() => $data->query( array( 'orderby' => 'featured', 'per_page' => 20 ) ),
 'search' => static fn() => $data->query( array( 'search' => 'synthetic project', 'per_page' => 20 ) ),
 'page_100' => static fn() => $data->query( array( 'page' => 100, 'per_page' => 20 ) ),
 'stats_global' => static fn() => $data->stats( array() ),
 'stats_collection' => static fn() => $data->stats( array( 'collection' => $cols[0]->term_id ) ),
);
foreach ( array( 1000, 10000, 100000 ) as $volume ) {
 $seed( $volume );
 foreach ( $cases as $name => $run ) {
  foreach ( array( 'wp_cold', 'same_process_warm' ) as $cache ) {
   $times = array(); $queries = array(); $peaks = array();
   if ( 'same_process_warm' === $cache ) { $run(); }
   foreach ( range( 1, 20 ) as $sample ) {
    if ( 'wp_cold' === $cache ) { $data->flush(); wp_cache_flush(); }
    if ( function_exists( 'memory_reset_peak_usage' ) ) { memory_reset_peak_usage(); }
    $q = get_num_queries(); $t = hrtime( true ); $value = $run();
    $times[] = round( ( hrtime( true ) - $t ) / 1e6, 3 ); $queries[] = get_num_queries() - $q; $peaks[] = memory_get_peak_usage( true );
    if ( ! is_array( $value ) ) { throw new RuntimeException( 'Benchmark workload failed.' ); }
   }
   $results[] = array( 'reviews' => $volume, 'case' => $name, 'cache' => $cache, 'samples' => 20, 'p50_ms' => $percentile( $times, .5 ), 'p95_ms' => $percentile( $times, .95 ), 'queries_p50' => $percentile( $queries, .5 ), 'queries_p95' => $percentile( $queries, .95 ), 'peak_bytes' => max( $peaks ) );
   fwrite( STDOUT, 'ABT_BENCH_RESULT=' . wp_json_encode( end( $results ) ) . "\n" );
  }
 }
}
$environment = array( 'php' => PHP_VERSION, 'wordpress' => get_bloginfo( 'version' ), 'database' => $wpdb->get_var( 'SELECT VERSION()' ), 'plugin' => ABT_VERSION, 'revision' => getenv( 'ABT_BENCH_REVISION' ), 'os' => PHP_OS, 'external_object_cache' => wp_using_ext_object_cache(), 'limitations' => 'Synthetic GitHub runner; DB buffer/filesystem caches not reset; warm cache is same PHP process; not shared-hosting, concurrent traffic or million-install evidence. Values measure production PHP APIs, not total page/Core Web Vitals.' );
fwrite( STDOUT, 'ABT_BENCH_ENV=' . wp_json_encode( $environment ) . "\n" );
