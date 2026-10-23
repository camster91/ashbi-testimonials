<?php
/** Standalone avatar regression: php -d disable_functions=mb_strtoupper tests/avatar-without-mbstring.php */
define( 'ABSPATH', __DIR__ . '/' );

// Only the WordPress boundaries used by the avatar renderer are needed here.
function add_action( ...$args ) {}
function esc_html( $value ) {
	return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}
require dirname( __DIR__ ) . '/includes/class-abt-render.php';

if ( function_exists( 'mb_strtoupper' ) ) {
	throw new RuntimeException( 'Run with -d disable_functions=mb_strtoupper.' );
}
if ( ! function_exists( 'mb_substr' ) ) {
	// WordPress supplies this fallback when mbstring is absent.
	function mb_substr( $value, $start, $length ) {
		return implode( '', array_slice( preg_split( '//u', $value, -1, PREG_SPLIT_NO_EMPTY ), $start, $length ) );
	}
}
$avatar = new ReflectionMethod( ABT_Render::class, 'avatar' );
$html = $avatar->invoke( ABT_Render::instance(), array( 'author' => 'alice example', 'avatar' => '' ) );
if ( false === strpos( $html, '>AE</span>' ) ) {
	throw new RuntimeException( 'Avatar initials did not render.' );
}
fwrite( STDOUT, "OK: initials render without mb_strtoupper\n" );
