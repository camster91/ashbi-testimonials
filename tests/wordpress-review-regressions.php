<?php
/** Regression checks for the v1.0.0 release review. Run on disposable WordPress. */
if ( ! defined( 'ABSPATH' ) || ! class_exists( 'ABT_Data' ) ) {
	exit( 1 );
}

$failures = array();
$check = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};
$data = ABT_Data::instance();
$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
wp_set_current_user( $admins[0]->ID );
$collection = $data->save_collection( array( 'name' => 'Review regressions', 'type' => 'product' ) );
$review = $data->save( array( 'author' => 'Alice Example', 'content' => 'A helpful product.', 'rating' => 5, 'collections' => array( $collection['id'] ) ) );

// Avatar initials continue to render in WordPress.
try {
	$html = do_shortcode( '[ashbi_testimonials ids="' . $review . '"]' );
	$check( false !== strpos( $html, '>AE</span>' ), 'initials render in WordPress' );
} catch ( Throwable $error ) {
	$failures[] = 'initials render in WordPress: ' . $error->getMessage();
}

foreach ( array( 'draft', 'private', 'password', 'publish' ) as $visibility ) {
	$title = 'Confidential product ' . $visibility;
	$product = wp_insert_post( array( 'post_type' => 'page', 'post_title' => $title, 'post_status' => 'password' === $visibility ? 'publish' : $visibility, 'post_password' => 'password' === $visibility ? 'secret' : '' ) );
	$item = $data->save( array( 'author' => 'Reviewer', 'content' => 'Useful.', 'rating' => 5, 'product_id' => $product, 'collections' => array( $collection['id'] ) ) );
	$check( false !== strpos( $data->get( $item )['product_title'], $title ), 'administrators retain product details for ' . $visibility );
	wp_set_current_user( 0 );
	$html = do_shortcode( '[ashbi_testimonials ids="' . $item . '" product="' . $product . '" show="product" schema="1"]' );
	ob_start();
	ABT_Render::instance()->print_schema();
	$schema = ob_get_clean();
	if ( 'publish' === $visibility ) {
		$check( false !== strpos( $html, $title ) && false !== strpos( $schema, $title ), 'public product title and schema remain available' );
	} else {
		$check( false === strpos( $html . $schema, $title ), 'public output hides ' . $visibility . ' product titles' );
	}
	wp_set_current_user( $admins[0]->ID );
}

wp_set_current_user( 0 );
$check( '' === do_shortcode( '[ashbi_rating collection="missing-review-regression"]' ), 'invalid rating collection renders nothing for visitors' );
$check( '' !== do_shortcode( '[ashbi_rating collection="' . $collection['slug'] . '"]' ), 'valid rating collection still renders' );
wp_set_current_user( $admins[0]->ID );
$check( false !== strpos( do_shortcode( '[ashbi_rating collection="missing-review-regression"]' ), 'abt-notice' ), 'invalid rating collection explains the problem to editors' );

// Intercept redirects so the real public submission handler can run without exiting.
class ABT_Regression_Redirect extends RuntimeException {}
$redirect = static function ( $location ) {
	throw new ABT_Regression_Redirect( $location );
};
$old_post = $_POST;
$old_server = $_SERVER;
$old_settings = get_option( ABT_SETTINGS_OPTION );
update_option( ABT_SETTINGS_OPTION, array( 'notify_email' => '' ) );
wp_set_current_user( 0 );
add_filter( 'wp_redirect', $redirect, 1 );
$rate_key = 'abt_rate_' . substr( wp_hash( '192.0.2.123' ), 0, 16 );
delete_transient( $rate_key );
try {
	$target = $collection['id'] . ':0';
	$time = time() - 3 * DAY_IN_SECONDS;
	$token = $time . '.' . substr( wp_hash( 'abt_form|' . $time . '|' . $target ), 0, 20 );
	$_SERVER['REMOTE_ADDR'] = '192.0.2.123';
	$_POST = array( 'abt_target' => $target, 'abt_token' => $token, 'abt_form' => 'abt-form-' . $collection['id'], 'author' => 'Cached form reviewer', 'email' => 'reviewer@example.test', 'content' => 'Sent from an old cached page.', 'rating' => '5', 'consent' => '1' );
	foreach ( array( false, true ) as $tampered ) {
		if ( $tampered ) {
			$_POST['abt_target'] = $collection['id'] . ':12345';
		}
		try {
			ABT_Form::instance()->handle();
		} catch ( ABT_Regression_Redirect $result ) {
			$check( false !== strpos( $result->getMessage(), 'abt_status=' . ( $tampered ? 'expired' : 'thanks' ) ), $tampered ? 'tampered target is rejected' : 'old cached form is accepted' );
		}
	}
	$check( 1 === $data->query( array( 'search' => 'Cached form reviewer', 'status' => 'pending' ) )['total'], 'cached submission is saved once and held for moderation' );
	$check( 0 === $data->query( array( 'search' => 'Cached form reviewer' ) )['total'], 'cached submission is never auto-published' );
} finally {
	remove_filter( 'wp_redirect', $redirect, 1 );
	$_POST = $old_post;
	$_SERVER = $old_server;
	update_option( ABT_SETTINGS_OPTION, $old_settings );
	delete_transient( $rate_key );
}

if ( $failures ) {
	throw new RuntimeException( "Review regressions:\n" . implode( "\n", $failures ) );
}
fwrite( STDOUT, "OK: release review regressions passed\n" );
