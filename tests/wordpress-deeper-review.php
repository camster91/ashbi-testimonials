<?php
/** Run with wp eval-file on disposable WordPress. */
if ( ! defined( 'ABSPATH' ) || ! class_exists( 'ABT_Data' ) ) {
	exit( 1 );
}
$failures = array();
$check = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};
$run = static function ( $name, $test ) use ( &$failures ) {
	try {
		$test();
	} catch ( Throwable $error ) {
		$failures[] = $name . ': ' . $error->getMessage();
	}
};
$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
wp_set_current_user( $admins[0]->ID );
$data = ABT_Data::instance();
$run( 'review dates', static function () use ( $data, $check ) {
	$id = $data->save( array( 'author' => 'Today', 'rating' => 5, 'date' => current_time( 'Y-m-d' ) ) );
	$check( ! is_wp_error( $id ) && 'publish' === get_post_status( $id ), 'today is published immediately' );
	$check( '00:00:00' === substr( get_post( $id )->post_date, -8 ), 'date-only reviews use midnight, including before noon' );
	$future = $data->save( array( 'author' => 'Future', 'rating' => 5, 'date' => '2099-01-01' ) );
	$check( is_wp_error( $future ), 'future dates produce an actionable error rather than hidden scheduled reviews' );
	$invalid = $data->save( array( 'author' => 'Invalid date', 'rating' => 5, 'date' => '2026-02-31' ) );
	$check( is_wp_error( $invalid ), 'impossible dates are rejected' );
} );
$run( 'filtered statistics', static function () use ( $data, $check ) {
	$col = $data->save_collection( array( 'name' => 'Filtered stats' ) );
	$ids = array();
	foreach ( array( 5, 1, 3 ) as $rating ) {
		$ids[] = $data->save( array( 'author' => 'Stats ' . $rating, 'rating' => $rating, 'featured' => 5 === $rating, 'verified' => 5 === $rating, 'collections' => array( $col['id'] ) ) );
	}
	$base = array( 'collection' => $col['id'] );
	$all = $data->stats( $base );
	$check( 3 === $all['rated'], 'unfiltered stats include all three reviews' );
	foreach ( array( array( 'ids' => array( $ids[0] ) ), array( 'featured' => true ), array( 'verified' => true ), array( 'rating' => 5 ) ) as $filter ) {
		$stats = $data->stats( array_merge( $base, $filter ) );
		$check( 1 === $stats['rated'] && 5.0 === (float) $stats['average'], 'IDs, featured, verified and exact rating have isolated correct stats' );
	}
	$stats = $data->stats( array_merge( $base, array( 'min_rating' => 3 ) ) );
	$check( 2 === $stats['rated'] && 4.0 === (float) $stats['average'], 'minimum rating filters statistics' );
	$stats = $data->stats( array_merge( $base, array( 'ids' => array( $ids[1] ), 'verified' => true ) ) );
	$check( 0 === $stats['rated'], 'filters combine and empty results are not cached as unfiltered results' );
	$html = do_shortcode( '[ashbi_rating ids="' . $ids[0] . '" style="inline"]' );
	$check( false !== strpos( $html, '>5.0</strong>' ) && false !== strpos( $html, '(1)' ), 'rating shortcode respects IDs' );
} );
$run( 'CSV collection restore', static function () use ( $data, $check ) {
	$admin = ABT_Admin::instance();
	$a = $data->save_collection( array( 'name' => 'Restore A', 'slug' => 'restore-a', 'type' => 'software', 'layout' => 'slider', 'accent' => '#123456' ) );
	$b = $data->save_collection( array( 'name' => 'Restore B', 'slug' => 'restore-b' ) );
	$id = $data->save( array( 'author' => 'CSV restore', 'content' => "<p>One</p><p>Two</p>", 'rating' => 4, 'collections' => array( $a['id'], $b['id'] ) ) );
	$stream = fopen( 'php://temp', 'r+' );
	fputcsv( $stream, $admin->export_row( $data->get( $id ) ), ',', '"', '' );
	rewind( $stream );
	$row = array_combine( ABT_Admin::CSV_COLUMNS, fgetcsv( $stream, 0, ',', '"', '' ) );
	fclose( $stream );
	$data->delete_collection( $a['id'] );
	$data->delete_collection( $b['id'] );
	$restored_id = $admin->import_row( $row );
	$check( ! is_wp_error( $restored_id ), 'exported CSV imports after collections are deleted' );
	$restored = $data->get( $restored_id );
	$check( 2 === count( $restored['collections'] ), 'both collection assignments are restored' );
	$term = get_term_by( 'slug', 'restore-a', ABT_Data::TAXONOMY );
	$settings = $data->collection_to_array( $term );
	$check( 'software' === $settings['type'] && 'slider' === $settings['layout'] && '#123456' === $settings['accent'], 'collection settings survive a CSV round trip' );
	$check( $restored['content'] === $row['content'], 'review content survives the round trip' );
	$legacy = $admin->import_row( array( 'name' => 'Legacy CSV', 'review' => 'Old headings still work', 'stars' => 5 ), $term->term_id );
	$check( ! is_wp_error( $legacy ) && array( $term->term_id ) === $data->get( $legacy )['collections'], 'old CSV files and explicit collection selection remain supported' );
} );
if ( $failures ) {
	throw new RuntimeException( "Deeper review regressions:\n" . implode( "\n", $failures ) );
}
fwrite( STDOUT, "OK: deeper review regressions passed\n" );
