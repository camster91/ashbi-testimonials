<?php
/** Disposable WordPress integration regression for public form input/retries. */
if ( ! defined( 'ABSPATH' ) || ! class_exists( 'ABT_Form' ) ) {
	exit( 1 );
}
class ABT_Form_Test_Redirect extends RuntimeException {}
$redirect = static function ( $url ) {
	parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
	throw new ABT_Form_Test_Redirect( $query['abt_status'] ?? 'missing' );
};
add_filter( 'wp_redirect', $redirect, 1 );
$check = static function ( $ok, $message ) {
	if ( ! $ok ) {
		throw new RuntimeException( 'Form safety: ' . $message );
	}
};
$old_settings = get_option( ABT_SETTINGS_OPTION );
update_option( ABT_SETTINGS_OPTION, array( 'notify_email' => '' ) );
$data = ABT_Data::instance();
$col = $data->save_collection( array( 'name' => 'Safe submissions' ) );
$target = $col['id'] . ':0';
$time = time();
$base = array( 'abt_target' => $target, 'abt_token' => $time . '.' . substr( wp_hash( 'abt_form|' . $time . '|' . $target ), 0, 20 ), 'author' => 'Safety reviewer', 'email' => 'safe@example.test', 'content' => 'A valid personal review.', 'rating' => '5', 'consent' => '1' );
$send = static function ( $fields, $ip = '192.0.2.201' ) use ( $base ) {
	$_POST = array_merge( $base, $fields );
	$_SERVER['REMOTE_ADDR'] = $ip;
	try {
		ABT_Form::instance()->handle();
	} catch ( ABT_Form_Test_Redirect $result ) {
		return $result->getMessage();
	}
	throw new RuntimeException( 'Missing form response' );
};
try {
	foreach ( array( 'abt_form', 'abt_target', 'abt_token', 'author', 'email', 'content', 'rating', 'consent', 'company' ) as $field ) {
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
} finally {
	remove_filter( 'wp_redirect', $redirect, 1 );
	update_option( ABT_SETTINGS_OPTION, $old_settings );
	$_POST = array();
}
fwrite( STDOUT, "OK: public form validation and retries passed\n" );
