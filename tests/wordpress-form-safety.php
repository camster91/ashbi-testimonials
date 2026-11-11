<?php
/** Disposable WordPress integration regression for public form input/retries. */
if ( ! defined( 'ABSPATH' ) || ! class_exists( 'ASHBTE_Form' ) ) {
	exit( 1 );
}
class ASHBTE_Form_Test_Redirect extends RuntimeException {}
$redirect = static function ( $url ) {
	parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
	throw new ASHBTE_Form_Test_Redirect( $query['ashbte_status'] ?? 'missing' );
};
add_filter( 'wp_redirect', $redirect, 1 );
$check = static function ( $ok, $message ) {
	if ( ! $ok ) {
		throw new RuntimeException( 'Form safety: ' . $message );
	}
};
$old_settings = get_option( ASHBTE_SETTINGS_OPTION );
update_option( ASHBTE_SETTINGS_OPTION, array( 'notify_email' => '' ) );
$data = ASHBTE_Data::instance();
$col = $data->save_collection( array( 'name' => 'Safe submissions' ) );
$target = $col['id'] . ':0';
$time = time();
$base = array( 'ashbte_nonce' => wp_create_nonce( ASHBTE_Render::NONCE ), 'ashbte_target' => $target, 'ashbte_token' => $time . '.' . substr( wp_hash( 'ashbte_form|' . $time . '|' . $target ), 0, 20 ), 'author' => 'Safety reviewer', 'email' => 'safe@example.test', 'content' => 'A valid personal review.', 'rating' => '5', 'consent' => '1' );
$send = static function ( $fields, $ip = '192.0.2.201' ) use ( $base ) {
	$_POST = array_merge( $base, $fields );
	$_SERVER['REMOTE_ADDR'] = $ip;
	try {
		ASHBTE_Form::instance()->handle();
	} catch ( ASHBTE_Form_Test_Redirect $result ) {
		return $result->getMessage();
	}
	throw new RuntimeException( 'Missing form response' );
};
try {
	$check( 'expired' === $send( array( 'ashbte_nonce' => '' ) ), 'missing nonce is rejected' );
	$check( 'expired' === $send( array( 'ashbte_nonce' => 'not-a-nonce' ) ), 'invalid nonce is rejected' );
	$check( 'expired' === $send( array( 'ashbte_nonce' => wp_create_nonce( 'ashbte_other_action' ) ) ), 'nonce for another action is rejected' );
	foreach ( array( 'ashbte_form', 'ashbte_target', 'ashbte_token', 'author', 'email', 'content', 'rating', 'consent', 'company' ) as $field ) {
		$check( 'invalid' === $send( array( $field => array( 'malformed' ) ) ), 'array rejected for ' . $field );
	}
	foreach ( array( 'author' => 120, 'email' => 190, 'role' => 120, 'company' => 120, 'location' => 120, 'headline' => 160, 'content' => 5000 ) as $field => $limit ) {
		$check( 'invalid' === $send( array( $field => str_repeat( 'x', $limit + 1 ) ) ), 'server enforces length of ' . $field );
	}
	foreach ( array( '6', '-1', '2.5', 'five' ) as $rating ) {
		$check( 'invalid' === $send( array( 'rating' => $rating ) ), 'invalid rating rejected' );
	}
	$check( 'invalid' === $send( array( 'consent' => 'false' ) ), 'consent requires exact opt-in' );
	$check( 0 === $data->query( array( 'collection' => $col['id'], 'status' => 'pending' ) )['total'], 'invalid input never creates reviews' );
	$check( 'thanks' === $send( array() ), 'valid review is accepted immediately' );
	$check( 'thanks' === $send( array() ), 'exact retry receives success' );
	$check( 1 === $data->query( array( 'collection' => $col['id'], 'status' => 'pending' ) )['total'], 'exact retry does not duplicate the review' );
	foreach ( range( 2, 5 ) as $i ) {
		$check( 'thanks' === $send( array( 'content' => 'Distinct review ' . $i ) ), 'new reviews within rate budget are accepted' );
	}
	$check( 'limit' === $send( array( 'content' => 'Sixth new review' ) ), 'sixth new review is rejected' );
	$check( 'thanks' === $send( array() ), 'retry stays successful after rate budget is exhausted' );
	$check( 5 === $data->query( array( 'collection' => $col['id'], 'status' => 'pending' ) )['total'], 'only five distinct reviews were stored' );
	$ip = '192.0.2.202';
	$lock = 'ashbte_submit_lock_' . substr( wp_hash( $ip ), 0, 16 );
	add_option( $lock, ( time() + 30 ) . ':another-worker', '', false );
	// A second worker may have cached absence before another worker acquired the lease.
	wp_cache_set( 'notoptions', array( $lock => true ), 'options' );
	$check( 'busy' === $send( array( 'content' => 'Contended review' ), $ip ), 'contended request gives an honest retry response' );
	delete_option( $lock );
	add_option( $lock, ( time() - 60 ) . ':expired-worker', '', false );
	$check( 'thanks' === $send( array( 'content' => 'Recovered review' ), $ip ), 'expired lease recovers' );
	$check( false === get_option( $lock ), 'lease is removed after saving' );
	$block_save = static fn() => true;
	add_filter( 'wp_insert_post_empty_content', $block_save );
	$check( 'error' === $send( array( 'content' => 'Temporarily failed review' ), $ip ), 'save failure is reported without success' );
	remove_filter( 'wp_insert_post_empty_content', $block_save );
	$check( false === get_option( $lock ), 'save failure releases its lease' );
	$check( 'thanks' === $send( array( 'content' => 'Temporarily failed review' ), $ip ), 'failed save can be retried successfully' );
} finally {
	remove_filter( 'wp_redirect', $redirect, 1 );
	update_option( ASHBTE_SETTINGS_OPTION, $old_settings );
	$_POST = array();
}
fwrite( STDOUT, "OK: public form validation and retries passed\n" );
