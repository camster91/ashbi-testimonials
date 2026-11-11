<?php
/**
 * Database smoke test. Run inside a disposable WordPress site:
 *   wp eval-file wp-content/plugins/ashbi-testimonials/tests/wordpress-smoke.php
 *
 * @package Ashbi_Testimonials
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'ASHBTE_Data' ) ) {
	fwrite( STDERR, "Run with wp eval-file on a site where the plugin is active.\n" );
	exit( 1 );
}

// wp eval-file runs this file inside a function, so the counter lives in $GLOBALS.
$GLOBALS['ashbte_checks'] = 0;
function ashbte_assert( $condition, $message ) {
	++$GLOBALS['ashbte_checks'];
	if ( ! $condition ) {
		throw new RuntimeException( 'FAILED: ' . $message );
	}
}

$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
wp_set_current_user( $admins[0]->ID );

$data = ASHBTE_Data::instance();

/* ---- Collections ---- */
$col = $data->save_collection( array( 'name' => 'CI Software', 'type' => 'software', 'layout' => 'slider', 'accent' => '#e11d48' ) );
ashbte_assert( is_array( $col ) && 'ci-software' === $col['slug'], 'collection is created with a slug' );
ashbte_assert( 'software' === $col['type'] && 'slider' === $col['layout'] && '#e11d48' === $col['accent'], 'collection settings persist' );
ashbte_assert( is_wp_error( $data->save_collection( array( 'name' => '' ) ) ), 'unnamed collection is rejected' );
$bad = $data->save_collection( array( 'name' => 'Odd', 'type' => 'nope', 'layout' => 'nope', 'accent' => 'red' ) );
ashbte_assert( 'general' === $bad['type'] && 'grid' === $bad['layout'] && '' === $bad['accent'], 'invalid collection settings fall back' );
ashbte_assert( $data->find_collection( 'ci-software' )->term_id === $data->find_collection( $col['id'] )->term_id, 'collections resolve by slug and ID' );

/* ---- Testimonials ---- */
$page = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'CI Product', 'post_status' => 'publish' ) );
$ratings = array( 5, 5, 4, 3, 5, 2, 4, 5, 1, 4 );
$ids     = array();
foreach ( range( 0, 29 ) as $i ) {
	$ids[] = $data->save(
		array(
			'origin'      => 'direct',
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
ashbte_assert( 30 === count( array_filter( $ids, 'is_int' ) ), 'thirty testimonials are saved' );
ashbte_assert( is_wp_error( $data->save( array( 'author' => '', 'content' => 'x' ) ) ), 'missing author is rejected' );
ashbte_assert( is_wp_error( $data->save( array( 'author' => 'X' ) ) ), 'empty text without rating is rejected' );

$clamped = $data->get( $data->save( array( 'author' => 'Clamp', 'rating' => 99, 'content' => '<script>x()</script><b>ok</b>', 'status' => 'draft' ) ) );
ashbte_assert( 5 === $clamped['rating'], 'rating is clamped to five' );
ashbte_assert( false === strpos( $clamped['content'], '<script' ) && false !== strpos( $clamped['content'], '<b>ok</b>' ), 'content keeps safe HTML only' );

$one = $data->get( $ids[3] );
ashbte_assert( 3 === $one['rating'] && 'Reviewer 3' === $one['author'] && array( $col['id'] ) === $one['collections'], 'testimonial reads back' );
ashbte_assert( '2026-03-04' === $one['date'], 'custom date is kept' );

/* ---- Queries ---- */
$q = $data->query( array( 'collection' => $col['id'], 'per_page' => 10 ) );
ashbte_assert( 29 === $q['total'] && 3 === $q['pages'] && 10 === count( $q['items'] ), 'published query paginates' );
ashbte_assert( 30 === $data->query( array( 'collection' => $col['id'], 'status' => 'any' ) )['total'], 'any status includes pending' );
ashbte_assert( 5 === $data->query( array( 'collection' => $col['id'], 'orderby' => 'rating' ) )['items'][0]['rating'], 'rating order puts five stars first' );
ashbte_assert( 1 === $data->query( array( 'collection' => $col['id'], 'orderby' => 'rating', 'order' => 'ASC' ) )['items'][0]['rating'], 'ascending rating order' );
ashbte_assert( 1 === $data->query( array( 'collection' => $col['id'], 'orderby' => 'featured' ) )['items'][0]['featured'], 'featured order puts featured first' );
foreach ( $data->query( array( 'collection' => $col['id'], 'min_rating' => 4, 'per_page' => 100 ) )['items'] as $item ) {
	ashbte_assert( $item['rating'] >= 4, 'min_rating filters' );
}
ashbte_assert( 10 === $data->query( array( 'product_id' => $page ) )['total'], 'product filter' );
ashbte_assert( 15 === $data->query( array( 'subject' => 'Pro plan' ) )['total'], 'subject filter' );
ashbte_assert( 3 === $data->query( array( 'featured' => true ) )['total'], 'featured filter' );
ashbte_assert( 1 === $data->query( array( 'search' => 'Reviewer 17' ) )['total'], 'search' );

/* ---- Stats ---- */
$stats    = $data->stats( array( 'collection' => $col['id'] ) );
$expected = array();
foreach ( range( 0, 28 ) as $i ) {
	$expected[] = $ratings[ $i % 10 ];
}
ashbte_assert( 29 === $stats['count'] && 29 === $stats['rated'], 'stats count published only' );
ashbte_assert( abs( $stats['average'] - round( array_sum( $expected ) / 29, 2 ) ) < 0.001, 'stats average is correct' );
ashbte_assert( 29 === array_sum( $stats['breakdown'] ), 'breakdown sums to count' );
ashbte_assert( 10 === $data->stats( array( 'product_id' => $page ) )['count'], 'product stats' );
$data->save( array( 'author' => 'Late', 'content' => 'x', 'rating' => 5, 'collections' => array( $col['id'] ) ) );
ashbte_assert( 30 === $data->stats( array( 'collection' => $col['id'] ) )['count'], 'stats cache is invalidated on save' );

/* ---- Trash ---- */
wp_trash_post( $ids[0] );
ashbte_assert( 1 === $data->query( array( 'collection' => $col['id'], 'status' => 'trash' ) )['total'], 'trash is queryable' );
wp_untrash_post( $ids[0] );
ashbte_assert( 'publish' === get_post_status( $ids[0] ), 'untrash restores the previous status' );

/* ---- Shortcodes ---- */
$html = do_shortcode( '[ashbte_testimonials collection="ci-software" layout="grid" limit="5" summary="1" load_more="1" show="avatar,rating,product,date"]' );
ashbte_assert( 5 === substr_count( $html, '<figure class="ashbte-card' ), 'shortcode renders the limit' );
ashbte_assert( false !== strpos( $html, 'ashbte-summary' ) && false !== strpos( $html, 'data-ashbte-more' ), 'summary and load more render' );
ashbte_assert( false === strpos( $html, '@example.test' ), 'private email is never rendered' );
ashbte_assert( false !== strpos( $html, '--ashbte-accent:#e11d48' ), 'collection accent applies' );
ashbte_assert( false !== strpos( do_shortcode( '[ashbte_testimonials collection="ci-software"]' ), 'data-ashbte-slider' ), 'collection default layout applies' );
foreach ( array_keys( ASHBTE_Data::layouts() ) as $layout ) {
	ashbte_assert( false !== strpos( do_shortcode( '[ashbte_testimonials collection="ci-software" layout="' . $layout . '"]' ), 'ashbte--' . $layout ), "layout {$layout} renders" );
}
ashbte_assert( false !== strpos( do_shortcode( '[ashbte_testimonials collection="missing"]' ), 'ashbte-notice' ), 'editors see a notice for a missing collection' );
ashbte_assert( false !== strpos( do_shortcode( '[ashbte_rating collection="ci-software"]' ), 'ashbte-summary__bars' ), 'rating summary renders' );
ashbte_assert( false !== strpos( do_shortcode( '[ashbte_rating collection="ci-software" style="inline"]' ), 'ashbte-inline-rating' ), 'inline rating renders' );
ashbte_assert( false !== strpos( do_shortcode( '[ashbte_form collection="ci-software"]' ), 'name="ashbte_token"' ), 'form renders with a token' );

ashbte_assert( is_wp_error( $data->save( array( 'author' => '<img src=x onerror=alert(1)>', 'content' => 'x' ) ) ), 'a name that is only markup is rejected' );
$xss = $data->save( array( 'author' => 'Tom "T" <b>&</b> Co', 'headline' => '"><svg onload=1>Great', 'content' => 'x', 'rating' => 4, 'collections' => array( $col['id'] ) ) );
ashbte_assert( is_int( $xss ), 'special-character testimonial saves' );
$out = do_shortcode( '[ashbte_testimonials ids="' . $xss . '" show="headline"]' );
ashbte_assert( false === strpos( $out, '<svg' ) && false === strpos( $out, '"T"' ) && false !== strpos( $out, '&quot;T&quot;' ), 'author and headline are escaped' );

/* Load-more page 2 uses the same filters. */
$parsed                  = ASHBTE_Shortcode::instance()->parse( array( 'collection' => 'ci-software', 'limit' => '10' ) );
$parsed['query']['page'] = 2;
ashbte_assert( 10 === count( $data->query( $parsed['query'] )['items'] ), 'load more page two' );

/* Structured data only for products and software, one entity per item. */
do_shortcode( '[ashbte_testimonials summary="1" collection="ci-software" product="' . $page . '"]' );
do_shortcode( '[ashbte_rating product="' . $page . '"]' );
ob_start();
ASHBTE_Render::instance()->print_schema();
$schema = ob_get_clean();
ashbte_assert( 1 === substr_count( $schema, 'CI Product' ) && false !== strpos( $schema, 'SoftwareApplication' ), 'structured data is emitted once per item' );

/* Logged-out visitors never see notices. */
wp_set_current_user( 0 );
ashbte_assert( '' === do_shortcode( '[ashbte_testimonials collection="missing"]' ), 'visitors see nothing for a missing collection' );

/* ---- Collection delete ---- */
wp_set_current_user( $admins[0]->ID );
$data->delete_collection( $bad['id'] );
ashbte_assert( null === $data->find_collection( $bad['id'] ), 'collection deletes' );

fwrite( STDOUT, 'OK: ' . $GLOBALS['ashbte_checks'] . " checks passed\n" );
