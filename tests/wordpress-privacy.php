<?php
/** Native privacy integration tests. Run on disposable WordPress with wp eval-file. */
if ( ! defined( 'ABSPATH' ) || ! class_exists( 'ABT_Data' ) ) {
	exit( 1 );
}
$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
$erasers = apply_filters( 'wp_privacy_personal_data_erasers', array() );
$check = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( 'Privacy: ' . $message );
	}
};
$check( isset( $exporters['ashbi-testimonials'], $erasers['ashbi-testimonials'] ), 'exporter and eraser are registered' );
$export = $exporters['ashbi-testimonials']['callback'];
$erase = $erasers['ashbi-testimonials']['callback'];
$data = ABT_Data::instance();
$other_site = 0;
$other_site_review = 0;
if ( is_multisite() ) {
	$current_site = get_current_blog_id();
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $site_id ) {
		if ( (int) $site_id !== $current_site ) {
			$other_site = (int) $site_id;
			break;
		}
	}
	if ( ! $other_site ) {
		$other_site = wpmu_create_blog( wp_parse_url( home_url(), PHP_URL_HOST ), '/privacy-sibling/', 'Privacy sibling', 1 );
	}
	$check( is_numeric( $other_site ) && $other_site > 0, 'sibling site fixture created' );
	switch_to_blog( $other_site );
	$other_site_review = $data->save( array( 'author' => 'SIBLING PRIVATE REVIEWER', 'content' => 'SIBLING PRIVATE TEXT', 'email' => 'privacy@example.test', 'rating' => 5 ) );
	restore_current_blog();
}
$col = $data->save_collection( array( 'name' => 'Privacy fixtures' ) );
$ids = array();
foreach ( range( 0, 104 ) as $i ) {
	$id = $data->save( array( 'author' => 'Private Reviewer ' . $i, 'content' => 'Personal text ' . $i, 'email' => 'privacy@example.test', 'company' => 'Personal company', 'rating' => 4, 'status' => array( 'publish', 'pending', 'draft' )[ $i % 3 ], 'collections' => array( $col['id'] ) ) );
	if ( 0 === $i % 7 ) {
		wp_trash_post( $id );
	}
	$ids[] = $id;
}
// Revisions contain personal text too and must disappear with their parent.
$revision = _wp_put_post_revision( $ids[1] );
$check( is_int( $revision ) && $revision > 0, 'personal revision fixture created' );
wp_update_post( array( 'ID' => $revision, 'post_content' => 'Historical personal review text' ) );
$other = $data->save( array( 'author' => 'Unrelated reviewer', 'content' => 'UNRELATED PRIVATE TEXT', 'email' => 'other@example.test', 'rating' => 1, 'collections' => array( $col['id'] ) ) );
$unrelated = wp_insert_post( array( 'post_type' => 'post', 'post_title' => 'Unrelated post', 'post_status' => 'draft' ) );
update_post_meta( $unrelated, '_abt_email', 'privacy@example.test' );
$first = call_user_func( $export, 'privacy@example.test', 1 );
$second = call_user_func( $export, 'privacy@example.test', 2 );
$check( 100 === count( $first['data'] ) && ! $first['done'] && 5 === count( $second['data'] ) && $second['done'], 'exports paginate through all review states' );
$all = array_merge( $first['data'], $second['data'] );
$check( 105 === count( array_unique( array_column( $all, 'item_id' ) ) ), 'export pages have no duplicates or missing items' );
$json = wp_json_encode( $all );
$check( false !== strpos( $json, 'Historical personal review text' ) && false !== strpos( $json, 'Personal company' ) && false !== strpos( $json, 'Personal text' ) && false === strpos( $json, 'UNRELATED PRIVATE TEXT' ) && false === strpos( $json, 'SIBLING PRIVATE TEXT' ), 'export includes personal fields and isolates reviewers' );
$check( array() === call_user_func( $export, '', 1 )['data'], 'empty email never exports unassigned reviews' );
$check( call_user_func( $erase, 'invalid-email', 1 )['done'] && get_post( $ids[0] ), 'invalid email cannot erase reviews' );
$before = $data->stats( array( 'collection' => $col['id'] ) );
$result = call_user_func( $erase, 'privacy@example.test', 1 );
$check( $result['items_removed'] && ! $result['done'], 'first erase batch is bounded' );
$result = call_user_func( $erase, 'privacy@example.test', 2 );
$check( $result['items_removed'] && $result['done'], 'second erase batch does not skip records after deletion' );
foreach ( $ids as $id ) {
	$check( null === get_post( $id ) && array() === get_post_meta( $id ), 'review and its metadata are removed' );
}
$check( null === get_post( $revision ), 'personal review revisions are removed' );
$check( get_post( $other ) && get_post( $unrelated ), 'other reviewers and post types survive' );
$after = $data->stats( array( 'collection' => $col['id'] ) );
$check( $before['count'] > $after['count'] && 1 === $after['count'], 'erasure invalidates published rating caches' );
if ( $other_site ) {
	switch_to_blog( $other_site );
	$check( null !== get_post( $other_site_review ), 'erasure never touches another site with the same reviewer email' );
	wp_delete_post( $other_site_review, true );
	restore_current_blog();
}
$repeat = call_user_func( $erase, 'privacy@example.test', 3 );
$check( ! $repeat['items_removed'] && $repeat['done'], 'erasure retries are idempotent' );
$blocked = $data->save( array( 'author' => 'Blocked', 'email' => 'blocked@example.test', 'rating' => 3 ) );
$block = static function ( $delete, $post ) use ( $blocked ) {
	return $post->ID === $blocked ? false : $delete;
};
add_filter( 'pre_delete_post', $block, 10, 2 );
$failed = call_user_func( $erase, 'blocked@example.test', 1 );
remove_filter( 'pre_delete_post', $block, 10 );
$check( $failed['items_retained'] && $failed['done'] && $failed['messages'] && get_post( $blocked ), 'deletion failure is explicit and cannot cause an infinite loop' );
call_user_func( $erase, 'blocked@example.test', 2 );
$attachment = wp_insert_attachment( array( 'post_title' => 'Shared personal media', 'post_status' => 'inherit' ) );
$media_review = $data->save( array( 'author' => 'Media reviewer', 'email' => 'media@example.test', 'rating' => 5, 'avatar_id' => $attachment ) );
$media_result = call_user_func( $erase, 'media@example.test', 1 );
$check( ! get_post( $media_review ) && get_post( $attachment ) && $media_result['items_retained'] && $media_result['messages'], 'shared media is preserved and disclosed' );
wp_delete_attachment( $attachment, true );
// This deliberately foreign _abt_email fixture must not pollute later uninstall counts.
wp_delete_post( $unrelated, true );
fwrite( STDOUT, "OK: WordPress privacy export and erasure passed\n" );
