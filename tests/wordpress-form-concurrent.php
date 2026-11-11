<?php
/** Multi-process fixture. Only execute in the disposable CI WordPress environment. */
if ( ! defined( 'ABSPATH' ) || '1' !== getenv( 'ASHBTE_DISPOSABLE_TEST' ) ) {
	exit( 1 );
}
$mode = $args[0] ?? '';
$data = ASHBTE_Data::instance();
if ( 'setup' === $mode ) {
	$col = $data->save_collection( array( 'name' => 'Concurrent submissions ' . wp_generate_uuid4() ) );
	update_option( 'ashbte_concurrent_test_collection', $col['id'], false );
	update_option( ASHBTE_SETTINGS_OPTION, array( 'notify_email' => '' ), false );
	$collection = $col['id'];
	delete_transient( 'ashbte_rate_' . substr( wp_hash( '192.0.2.' . ( 10 + $collection % 240 ) ), 0, 16 ) );
	return;
}
$collection = (int) get_option( 'ashbte_concurrent_test_collection' );
if ( ! $collection ) {
	throw new RuntimeException( 'Missing concurrent submission fixture' );
}
if ( 'worker' === $mode ) {
	$target = $collection . ':0';
	$time = time();
	$_SERVER['REMOTE_ADDR'] = '192.0.2.' . ( 10 + $collection % 240 );
	$_POST = array( 'ashbte_nonce' => wp_create_nonce( ASHBTE_Render::NONCE ), 'ashbte_target' => $target, 'ashbte_token' => $time . '.' . substr( wp_hash( 'ashbte_form|' . $time . '|' . $target ), 0, 20 ), 'author' => 'Concurrent reviewer', 'email' => 'concurrent@example.test', 'content' => 'Concurrent review ' . (int) ( $args[1] ?? 0 ), 'rating' => '5', 'consent' => '1' );
	add_filter( 'wp_redirect', static function ( $url ) {
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		if ( ! in_array( $query['ashbte_status'] ?? '', array( 'thanks', 'busy', 'limit' ), true ) ) {
			throw new RuntimeException( 'Unexpected concurrent submission outcome' );
		}
		return $url;
	} );
	ASHBTE_Form::instance()->handle();
}
if ( 'check' === $mode ) {
	$result = $data->query( array( 'collection' => $collection, 'status' => 'pending' ) );
	$sent = (int) get_transient( 'ashbte_rate_' . substr( wp_hash( '192.0.2.' . ( 10 + $collection % 240 ) ), 0, 16 ) );
	if ( $result['total'] < 1 || $result['total'] > 5 || $result['total'] !== $sent ) {
		throw new RuntimeException( 'Concurrent rate/save mismatch: ' . $result['total'] . ' saved, ' . $sent . ' counted' );
	}
	delete_option( 'ashbte_concurrent_test_collection' );
	fwrite( STDOUT, "OK: ten parallel requests respected the rate/save boundary\n" );
}
