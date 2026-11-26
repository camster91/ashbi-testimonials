<?php
/** Reusable design preset portability/security fixtures. */
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
$check = static function ( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( 'Presets: ' . $message ); } };
$check( class_exists( 'ASHBTE_Presets' ), 'preset provider is registered' );
class ASHBTE_Preset_Denied extends RuntimeException {}
$die = static fn() => static function ( $message ) { throw new ASHBTE_Preset_Denied( (string) $message ); };
$actor = wp_insert_user( array( 'user_login' => 'ashbte_preset_editor', 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
$old_user = get_current_user_id();
wp_set_current_user( $actor );
add_filter( 'wp_die_handler', $die, 9999 );
$denied = false;
try { ASHBTE_Presets::handle(); } catch ( ASHBTE_Preset_Denied $error ) { $denied = false !== strpos( $error->getMessage(), 'permission' ); }
remove_filter( 'wp_die_handler', $die, 9999 );
wp_set_current_user( $old_user );
$check( $denied, 'editors cannot manage administrator design transfers' );
$before = get_option( 'ashbte_design_presets', array() );
$valid = array( 'version' => 1, 'presets' => array( 'clean' => array( 'name' => 'Clean editorial', 'layout' => 'grid', 'columns' => '2', 'theme' => 'light', 'accent' => '#123456' ) ) );
try {
	$check( true === ASHBTE_Presets::import_json( wp_json_encode( $valid ) ), 'portable preset imports' );
	$attrs = ASHBTE_Presets::apply( array( 'preset' => 'clean', 'layout' => 'list' ) );
	$check( 'grid' === $attrs['layout'] && '2' === $attrs['columns'] && '#123456' === $attrs['accent'], 'saved preset applies consistently' );
	$json = ASHBTE_Presets::export_json();
	delete_option( 'ashbte_design_presets' );
	$check( true === ASHBTE_Presets::import_json( $json ) && 'Clean editorial' === ASHBTE_Presets::all()['clean']['name'], 'export restores on another clean site' );
	foreach ( array( array( 'version' => 999, 'presets' => array() ), array( 'version' => 1, 'presets' => array( 'evil' => array( 'name' => 'Evil', 'layout' => 'grid', 'css' => 'body{display:none}' ) ) ), array( 'version' => 1, 'presets' => array(), 'notify_email' => 'injected@example.test' ) ) as $bad ) {
		$check( is_wp_error( ASHBTE_Presets::import_json( wp_json_encode( $bad ) ) ), 'unknown versions and executable/unrelated fields fail safely' );
		$check( isset( ASHBTE_Presets::all()['clean'] ), 'invalid import cannot destroy saved presets' );
	}
	$check( is_wp_error( ASHBTE_Presets::import_json( str_repeat( 'x', 65537 ) ) ), 'oversized transfer is rejected' );
	$col = ASHBTE_Data::instance()->save_collection( array( 'name' => 'Preset fixture' ) );
	ASHBTE_Data::instance()->save( array( 'author' => 'Preset reviewer', 'rating' => 5, 'collections' => array( $col['id'] ) ) );
	$html = ASHBTE_Editors::render( 'testimonials', array( 'collection' => (string) $col['id'], 'preset' => 'clean' ) );
	$check( false !== strpos( $html, 'ashbte--grid' ) && false !== strpos( $html, '--ashbte-cols:2' ) && false !== strpos( $html, '#123456' ), 'native adapters use the saved design' );
	$check( false === strpos( $html, 'data-ashbte-look' ), 'presets without a look keep the classic cards' );
	$look_html = ASHBTE_Editors::render( 'testimonials', array( 'collection' => (string) $col['id'], 'look' => 'bold' ) );
	$check( false !== strpos( $look_html, 'data-ashbte-look="bold"' ), 'look attribute reaches the wrapper' );
	$bad_look = ASHBTE_Editors::render( 'testimonials', array( 'collection' => (string) $col['id'], 'look' => 'neon"><script>' ) );
	$check( false === strpos( $bad_look, 'data-ashbte-look' ) && false === strpos( $bad_look, '<script>' ), 'unknown looks are ignored' );
	$with_look = array( 'version' => 1, 'presets' => array( 'glassy' => array( 'name' => 'Glassy', 'layout' => 'grid', 'columns' => '3', 'theme' => 'dark', 'look' => 'glass', 'accent' => '' ) ) );
	$check( true === ASHBTE_Presets::import_json( wp_json_encode( $with_look ) ) && 'glass' === ASHBTE_Presets::all()['glassy']['look'], 'presets can save a look' );
	$check( is_wp_error( ASHBTE_Presets::import_json( wp_json_encode( array( 'version' => 1, 'presets' => array( 'x' => array( 'name' => 'X', 'look' => 'neon' ) ) ) ) ) ), 'unknown looks in presets are rejected' );
} finally { update_option( 'ashbte_design_presets', $before, false ); }
fwrite( STDOUT, "OK: preset validation, portability and rendering passed\n" );
