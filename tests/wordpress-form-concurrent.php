<?php
/** Multi-process fixture. Only execute in the disposable CI WordPress environment. */
if ( ! defined( 'ABSPATH' ) || '1' !== getenv( 'ABT_DISPOSABLE_TEST' ) ) {
	exit( 1 );
}
$mode = $args[0] ?? '';
$data = ABT_Data::instance();
if ( 'setup' === $mode ) {
	$col = $data->save_collection( array( 'name' => 'Concurrent submissions' ) );
	update_option( 'abt_concurrent_test_collection', $col['id'], false );
	update_option( ABT_SETTINGS_OPTION, array( 'notify_email' => '' ), false );
	delete_transient( 'abt_rate_' . substr( wp_hash( '192.0.2.210' ), 0, 16 ) );
	return;
}
$collection = (int) get_option( 'abt_concurrent_test_collection' );
if ( ! $collection ) {
	throw new RuntimeException( 'Missing concurrent submission fixture' );
}
if ( 'worker' === $mode ) {
	$target = $collection . ':0';
	$time = time();
	$_SERVER['REMOTE_ADDR'] = '192.0.2.210';
	$_POST = array( 'abt_target' => $target, 'abt_token' => $time . '.' . substr( wp_hash( 'abt_form|' . $time . '|' . $target ), 0, 20 ), 'author' => 'Concurrent reviewer', 'email' => 'concurrent@example.test', 'content' => 'Concurrent review ' . (int) ( $args[1] ?? 0 ), 'rating' => '5', 'consent' => '1' );
	add_filter( 'wp_redirect', static function ( $url ) {
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		if ( ! in_array( $query['abt_status'] ?? '', array( 'thanks', 'busy', 'limit' ), true ) ) {
			throw new RuntimeException( 'Unexpected concurrent submission outcome' );
		}
		return $url;
	} );
	ABT_Form::instance()->handle();
}
if ( 'check' === $mode ) {
	$result = $data->query( array( 'collection' => $collection, 'status' => 'pending' ) );
	$sent = (int) get_transient( 'abt_rate_' . substr( wp_hash( '192.0.2.210' ), 0, 16 ) );
	if ( $result['total'] < 1 || $result['total'] > 5 || $result['total'] !== $sent ) {
		throw new RuntimeException( 'Concurrent rate/save mismatch: ' . $result['total'] . ' saved, ' . $sent . ' counted' );
	}
	delete_option( 'abt_concurrent_test_collection' );
	fwrite( STDOUT, "OK: ten parallel requests respected the rate/save boundary\n" );
}
