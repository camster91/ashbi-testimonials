<?php
/**
 * Database smoke test. Run inside a disposable WordPress site:
 *   wp eval-file wp-content/plugins/ashbi-testimonials/tests/wordpress-smoke.php
 *
 * @package Ashbi_Testimonials
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'ABT_Data' ) ) {
	fwrite( STDERR, "Run with wp eval-file on a site where the plugin is active.\n" );
	exit( 1 );
}

// wp eval-file runs this file inside a function, so the counter lives in $GLOBALS.
$GLOBALS['abt_checks'] = 0;
function abt_assert( $condition, $message ) {
	++$GLOBALS['abt_checks'];
	if ( ! $condition ) {
		throw new RuntimeException( 'FAILED: ' . $message );
	}
}

$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
wp_set_current_user( $admins[0]->ID );

$data = ABT_Data::instance();

/* ---- Collections ---- */
$col = $data->save_collection( array( 'name' => 'CI Software', 'type' => 'software', 'layout' => 'slider', 'accent' => '#e11d48' ) );
abt_assert( is_array( $col ) && 'ci-software' === $col['slug'], 'collection is created with a slug' );
abt_assert( 'software' === $col['type'] && 'slider' === $col['layout'] && '#e11d48' === $col['accent'], 'collection settings persist' );
abt_assert( is_wp_error( $data->save_collection( array( 'name' => '' ) ) ), 'unnamed collection is rejected' );
$bad = $data->save_collection( array( 'name' => 'Odd', 'type' => 'nope', 'layout' => 'nope', 'accent' => 'red' ) );
abt_assert( 'general' === $bad['type'] && 'grid' === $bad['layout'] && '' === $bad['accent'], 'invalid collection settings fall back' );
abt_assert( $data->find_collection( 'ci-software' )->term_id === $data->find_collection( $col['id'] )->term_id, 'collections resolve by slug and ID' );

/* ---- Testimonials ---- */
$page = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'CI Product', 'post_status' => 'publish' ) );
$ratings = array( 5, 5, 4, 3, 5, 2, 4, 5, 1, 4 );
$ids     = array();
foreach ( range( 0, 29 ) as $i ) {
	$ids[] = $data->save(
		array(
			'author'      => 'Reviewer ' . $i,
			'content'     => 'Review body ' . $i,
			'rating'      => $ratings[ $i % 10 ],
			'email'       => 'private' . $i . '@example.test',
			'product_id'  => 0 === $i % 3 ? $page : 0,
			'subject'     => 0 === $i % 2 ? 'Pro plan' : 'Basic plan',
			'featured'    => 0 === $i % 10 ? 1 : 0,
			'verified'    => $i % 2,
			'status'      => 29 === $i ? 'pending' : 'publish',
			'date'        => sprintf( '2026-03-%02d', 1 + $i % 28 ),
			'collections' => array( $col['id'] ),
		)
	);
}
abt_assert( 30 === count( array_filter( $ids, 'is_int' ) ), 'thirty testimonials are saved' );
abt_assert( is_wp_error( $data->save( array( 'author' => '', 'content' => 'x' ) ) ), 'missing author is rejected' );
abt_assert( is_wp_error( $data->save( array( 'author' => 'X' ) ) ), 'empty text without rating is rejected' );

$clamped = $data->get( $data->save( array( 'author' => 'Clamp', 'rating' => 99, 'content' => '<script>x()</script><b>ok</b>', 'status' => 'draft' ) ) );
abt_assert( 5 === $clamped['rating'], 'rating is clamped to five' );
abt_assert( false === strpos( $clamped['content'], '<script' ) && false !== strpos( $clamped['content'], '<b>ok</b>' ), 'content keeps safe HTML only' );

$one = $data->get( $ids[3] );
abt_assert( 3 === $one['rating'] && 'Reviewer 3' === $one['author'] && array( $col['id'] ) === $one['collections'], 'testimonial reads back' );
abt_assert( '2026-03-04' === $one['date'], 'custom date is kept' );

/* ---- Queries ---- */
$q = $data->query( array( 'collection' => $col['id'], 'per_page' => 10 ) );
abt_assert( 29 === $q['total'] && 3 === $q['pages'] && 10 === count( $q['items'] ), 'published query paginates' );
abt_assert( 30 === $data->query( array( 'collection' => $col['id'], 'status' => 'any' ) )['total'], 'any status includes pending' );
abt_assert( 5 === $data->query( array( 'collection' => $col['id'], 'orderby' => 'rating' ) )['items'][0]['rating'], 'rating order puts five stars first' );
abt_assert( 1 === $data->query( array( 'collection' => $col['id'], 'orderby' => 'rating', 'order' => 'ASC' ) )['items'][0]['rating'], 'ascending rating order' );
abt_assert( 1 === $data->query( array( 'collection' => $col['id'], 'orderby' => 'featured' ) )['items'][0]['featured'], 'featured order puts featured first' );
foreach ( $data->query( array( 'collection' => $col['id'], 'min_rating' => 4, 'per_page' => 100 ) )['items'] as $item ) {
	abt_assert( $item['rating'] >= 4, 'min_rating filters' );
}
abt_assert( 10 === $data->query( array( 'product_id' => $page ) )['total'], 'product filter' );
abt_assert( 15 === $data->query( array( 'subject' => 'Pro plan' ) )['total'], 'subject filter' );
abt_assert( 3 === $data->query( array( 'featured' => true ) )['total'], 'featured filter' );
abt_assert( 1 === $data->query( array( 'search' => 'Reviewer 17' ) )['total'], 'search' );

/* ---- Stats ---- */
$stats    = $data->stats( array( 'collection' => $col['id'] ) );
$expected = array();
foreach ( range( 0, 28 ) as $i ) {
	$expected[] = $ratings[ $i % 10 ];
}
abt_assert( 29 === $stats['count'] && 29 === $stats['rated'], 'stats count published only' );
abt_assert( abs( $stats['average'] - round( array_sum( $expected ) / 29, 2 ) ) < 0.001, 'stats average is correct' );
abt_assert( 29 === array_sum( $stats['breakdown'] ), 'breakdown sums to count' );
abt_assert( 10 === $data->stats( array( 'product_id' => $page ) )['count'], 'product stats' );
$data->save( array( 'author' => 'Late', 'content' => 'x', 'rating' => 5, 'collections' => array( $col['id'] ) ) );
abt_assert( 30 === $data->stats( array( 'collection' => $col['id'] ) )['count'], 'stats cache is invalidated on save' );

/* ---- Trash ---- */
wp_trash_post( $ids[0] );
abt_assert( 1 === $data->query( array( 'collection' => $col['id'], 'status' => 'trash' ) )['total'], 'trash is queryable' );
wp_untrash_post( $ids[0] );
abt_assert( 'publish' === get_post_status( $ids[0] ), 'untrash restores the previous status' );

/* ---- Shortcodes ---- */
$html = do_shortcode( '[ashbi_testimonials collection="ci-software" layout="grid" limit="5" summary="1" load_more="1" show="avatar,rating,product,date"]' );
abt_assert( 5 === substr_count( $html, '<figure class="abt-card' ), 'shortcode renders the limit' );
abt_assert( false !== strpos( $html, 'abt-summary' ) && false !== strpos( $html, 'data-abt-more' ), 'summary and load more render' );
abt_assert( false === strpos( $html, '@example.test' ), 'private email is never rendered' );
abt_assert( false !== strpos( $html, '--abt-accent:#e11d48' ), 'collection accent applies' );
abt_assert( false !== strpos( do_shortcode( '[ashbi_testimonials collection="ci-software"]' ), 'data-abt-slider' ), 'collection default layout applies' );
foreach ( array_keys( ABT_Data::layouts() ) as $layout ) {
	abt_assert( false !== strpos( do_shortcode( '[ashbi_testimonials collection="ci-software" layout="' . $layout . '"]' ), 'abt--' . $layout ), "layout {$layout} renders" );
}
abt_assert( false !== strpos( do_shortcode( '[ashbi_testimonials collection="missing"]' ), 'abt-notice' ), 'editors see a notice for a missing collection' );
abt_assert( false !== strpos( do_shortcode( '[ashbi_rating collection="ci-software"]' ), 'abt-summary__bars' ), 'rating summary renders' );
abt_assert( false !== strpos( do_shortcode( '[ashbi_rating collection="ci-software" style="inline"]' ), 'abt-inline-rating' ), 'inline rating renders' );
abt_assert( false !== strpos( do_shortcode( '[ashbi_testimonial_form collection="ci-software"]' ), 'name="abt_token"' ), 'form renders with a token' );

abt_assert( is_wp_error( $data->save( array( 'author' => '<img src=x onerror=alert(1)>', 'content' => 'x' ) ) ), 'a name that is only markup is rejected' );
$xss = $data->save( array( 'author' => 'Tom "T" <b>&</b> Co', 'headline' => '"><svg onload=1>Great', 'content' => 'x', 'rating' => 4, 'collections' => array( $col['id'] ) ) );
abt_assert( is_int( $xss ), 'special-character testimonial saves' );
$out = do_shortcode( '[ashbi_testimonials ids="' . $xss . '" show="headline"]' );
abt_assert( false === strpos( $out, '<svg' ) && false === strpos( $out, '"T"' ) && false !== strpos( $out, '&quot;T&quot;' ), 'author and headline are escaped' );

/* Load-more page 2 uses the same filters. */
$parsed                  = ABT_Shortcode::instance()->parse( array( 'collection' => 'ci-software', 'limit' => '10' ) );
$parsed['query']['page'] = 2;
abt_assert( 10 === count( $data->query( $parsed['query'] )['items'] ), 'load more page two' );

/* Structured data only for products and software, one entity per item. */
do_shortcode( '[ashbi_testimonials collection="ci-software" product="' . $page . '"]' );
do_shortcode( '[ashbi_rating product="' . $page . '"]' );
ob_start();
ABT_Render::instance()->print_schema();
$schema = ob_get_clean();
abt_assert( 1 === substr_count( $schema, 'CI Product' ) && false !== strpos( $schema, 'SoftwareApplication' ), 'structured data is emitted once per item' );

/* Logged-out visitors never see notices. */
wp_set_current_user( 0 );
abt_assert( '' === do_shortcode( '[ashbi_testimonials collection="missing"]' ), 'visitors see nothing for a missing collection' );

/* ---- Collection delete ---- */
wp_set_current_user( $admins[0]->ID );
$data->delete_collection( $bad['id'] );
abt_assert( null === $data->find_collection( $bad['id'] ), 'collection deletes' );

fwrite( STDOUT, 'OK: ' . $GLOBALS['abt_checks'] . " checks passed\n" );
