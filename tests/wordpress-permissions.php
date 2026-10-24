<?php
/** Permission regressions; run with wp eval-file on disposable WordPress. */
if ( ! defined( 'ABSPATH' ) || ! class_exists( 'ABT_Admin' ) ) {
	exit( 1 );
}
if ( ! defined( 'DOING_AJAX' ) ) {
	define( 'DOING_AJAX', true );
}
class ABT_Permissions_Response extends RuntimeException {}
$die_handler = static function () {
	return static function ( $message = '' ) {
		throw new ABT_Permissions_Response( is_wp_error( $message ) ? $message->get_error_message() : (string) $message );
	};
};
add_filter( 'wp_die_ajax_handler', $die_handler );
$check = static function ( $ok, $message ) {
	if ( ! $ok ) {
		throw new RuntimeException( 'Permissions: ' . $message );
	}
};
$admin = ABT_Admin::instance();
$call = static function ( $object, $method, $post = array(), $nonce = 'valid' ) {
	$old_post = $_POST;
	$old_request = $_REQUEST;
	$_POST = $post;
	if ( 'missing' !== $nonce ) {
		$_POST['nonce'] = 'valid' === $nonce ? wp_create_nonce( 'abt_admin' ) : 'invalid';
	}
	$_REQUEST = $_POST;
	$message = '';
	ob_start();
	try {
		$object->$method();
	} catch ( ABT_Permissions_Response $end ) {
		$message = $end->getMessage();
	} finally {
		$output = ob_get_clean();
		$_POST = $old_post;
		$_REQUEST = $old_request;
	}
	return array( 'json' => json_decode( $output, true ), 'message' => $message, 'output' => $output );
};
$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
$administrator = (int) $admins[0]->ID;
$users = array( 'visitor' => 0 );
foreach ( array( 'subscriber', 'author', 'editor' ) as $role ) {
	$id = wp_insert_user( array( 'user_login' => 'abt_permissions_' . $role, 'user_pass' => wp_generate_password(), 'role' => $role ) );
	$check( ! is_wp_error( $id ), 'create ' . $role . ' fixture' );
	$users[ $role ] = $id;
}
wp_set_current_user( $administrator );
$data = ABT_Data::instance();
$review = $data->save( array( 'author' => 'Permission reviewer', 'content' => 'Public permission fixture', 'email' => 'PRIVATE-EMAIL@example.test', 'rating' => 5 ) );
$pending = $data->save( array( 'author' => 'Pending identity', 'content' => 'PRIVATE PENDING REVIEW TEXT', 'rating' => 1, 'status' => 'pending' ) );
$post = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Original unrelated page', 'post_status' => 'publish' ) );
$methods = array( 'ajax_list', 'ajax_get', 'ajax_save', 'ajax_delete', 'ajax_bulk', 'ajax_collection_save', 'ajax_collection_delete', 'ajax_search_posts', 'ajax_import', 'ajax_preview' );
foreach ( array( 'visitor', 'subscriber', 'author' ) as $role ) {
	wp_set_current_user( $users[ $role ] );
	foreach ( $methods as $method ) {
		$result = $call( $admin, $method, array( 'id' => $review ) );
		$check( isset( $result['json']['success'] ) && false === $result['json']['success'], $role . ' rejected by ' . $method );
	}
	foreach ( array( 'export', 'save_settings' ) as $method ) {
		$result = $call( $admin, $method );
		$check( false !== strpos( $result['message'], 'permission' ), $role . ' rejected by ' . $method );
	}
}
wp_set_current_user( $administrator );
foreach ( array( 'missing', 'invalid' ) as $nonce ) {
	foreach ( $methods as $method ) {
		$result = $call( $admin, $method, array( 'id' => $review ), $nonce );
		$check( '-1' === $result['message'], $nonce . ' nonce rejected by ' . $method );
	}
}
wp_set_current_user( $users['editor'] );
$result = $call( $admin, 'ajax_get', array( 'id' => $review ) );
$check( true === $result['json']['success'] && 'Permission reviewer' === $result['json']['data']['author'], 'editor can read reviews' );
$result = $call( $admin, 'ajax_save', array( 'item' => wp_json_encode( array( 'id' => $review, 'author' => 'Editor update', 'rating' => 4 ) ) ) );
$check( true === $result['json']['success'] && 'Editor update' === get_post( $review )->post_title, 'editor can save reviews' );
$result = $call( $admin, 'save_settings' );
$check( false !== strpos( $result['message'], 'permission' ), 'editor cannot change administrator settings' );
$result = $call( $admin, 'ajax_save', array( 'item' => wp_json_encode( array( 'id' => $post, 'author' => 'Injected title', 'rating' => 5 ) ) ) );
$check( false === $result['json']['success'] && 'Original unrelated page' === get_post( $post )->post_title, 'review save cannot mutate another post type' );
$call( $admin, 'ajax_delete', array( 'ids' => wp_json_encode( array( $post ) ) ) );
$check( 'publish' === get_post_status( $post ), 'review trash cannot mutate another post type' );
$public_second = $data->save( array( 'author' => 'Second public reviewer', 'content' => 'Second public permission text', 'email' => 'PRIVATE-EMAIL@example.test', 'rating' => 5 ) );
wp_set_current_user( 0 );
$result = $call( ABT_Shortcode::instance(), 'ajax_load_more', array( 'page' => '2', 'atts' => wp_json_encode( array( 'ids' => "$review,$pending,$public_second", 'limit' => 1, 'show' => 'email,content' ) ) ) );
$check( true === $result['json']['success'] && false !== strpos( $result['json']['data']['html'], 'Second public permission text' ) && false === strpos( $result['output'], 'PRIVATE PENDING REVIEW TEXT' ) && false === strpos( $result['output'], 'PRIVATE-EMAIL' ), 'anonymous load-more excludes unpublished text and email' );

$bad_public = ABT_Shortcode::instance()->public_page( wp_json_encode( array( 'status' => 'pending', 'post_status' => 'any' ) ), '2' );
$check( is_wp_error( $bad_public ), 'public status injection is rejected before querying' );

// A custom testimonial capability does not grant access to private pages.
wp_set_current_user( $administrator );
$picker_ids = array();
foreach ( array( 'publish', 'private', 'draft', 'password' ) as $visibility ) {
	$picker_ids[ $visibility ] = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'ABT picker permission ' . $visibility, 'post_status' => 'password' === $visibility ? 'publish' : $visibility, 'post_password' => 'password' === $visibility ? 'secret' : '', 'post_author' => $administrator ) );
}
$custom_cap = static fn() => 'read';
add_filter( 'abt_capability', $custom_cap );
wp_set_current_user( $users['subscriber'] );
$result = $call( $admin, 'ajax_search_posts', array( 'q' => 'ABT picker permission' ) );
$found = array_map( 'intval', array_column( $result['json']['data'], 'id' ) );
$check( in_array( $picker_ids['publish'], $found, true ), 'custom testimonial manager can find public pages' );
foreach ( array( 'private', 'draft', 'password' ) as $visibility ) {
	$check( ! in_array( $picker_ids[ $visibility ], $found, true ), 'custom testimonial manager cannot discover ' . $visibility . ' page titles' );
}
wp_set_current_user( $administrator );
$result = $call( $admin, 'ajax_search_posts', array( 'q' => 'ABT picker permission' ) );
$check( 4 === count( $result['json']['data'] ), 'administrators retain private picker entries' );
remove_filter( 'abt_capability', $custom_cap );
remove_filter( 'wp_die_ajax_handler', $die_handler );
fwrite( STDOUT, "OK: management permission regressions passed\n" );

$abt_diag_admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0];
wp_set_current_user( 0 );
if ( ABT_Admin::diagnostics() ) { throw new RuntimeException( 'Anonymous diagnostics exposed.' ); }
$abt_diag_editor = wp_create_user( 'diag_' . wp_generate_password( 8, false ), wp_generate_password(), 'diag-' . wp_generate_password( 8, false ) . '@example.test' );
$abt_diag_user = new WP_User( $abt_diag_editor ); $abt_diag_user->set_role( 'editor' ); wp_set_current_user( $abt_diag_editor );
if ( ABT_Admin::diagnostics() ) { throw new RuntimeException( 'Editor accessed administrator diagnostics.' ); }
wp_set_current_user( $abt_diag_admin->ID );
$abt_diag = ABT_Admin::diagnostics();
if ( ABT_VERSION !== $abt_diag['plugin'] || false !== strpos( wp_json_encode( $abt_diag ), 'http' ) || false !== strpos( wp_json_encode( $abt_diag ), '@' ) ) { throw new RuntimeException( 'Diagnostics contains unexpected data.' ); }
fwrite( STDOUT, "OK: diagnostics are minimal and administrator-only\n" );
