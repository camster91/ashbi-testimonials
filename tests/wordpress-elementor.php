<?php
/** Real free Elementor widget integration on disposable WordPress. */
if ( ! defined( 'ABSPATH' ) || ! class_exists( '\\Elementor\\Plugin' ) ) { exit( 1 ); }
$check = static function ( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( 'Elementor: ' . $message ); } };
$manager = \Elementor\Plugin::$instance->widgets_manager;
$check( null !== $manager->get_widget_types( 'ashbi-testimonials' ), 'free widget is registered' );
$col = ASHBTE_Data::instance()->save_collection( array( 'name' => 'Elementor fixture' ) );
ASHBTE_Data::instance()->save( array( 'author' => 'Elementor reviewer', 'content' => 'Public Elementor review text', 'email' => 'SECRET-ELEMENTOR@example.test', 'rating' => 5, 'collections' => array( $col['id'] ) ) );
foreach ( array( 'testimonials' => 'Public Elementor review text', 'rating' => 'ashbte-summary', 'review-form' => 'name="ashbte_target"' ) as $kind => $needle ) {
	$widget = new ASHBTE_Elementor_Widget( array( 'id' => 'abtfixture', 'elType' => 'widget', 'widgetType' => 'ashbi-testimonials', 'settings' => array( 'kind' => $kind, 'collection' => (string) $col['id'], 'layout' => 'list', 'summary' => '1', 'schema' => '0' ) ), array() );
	ob_start(); $widget->render_content(); $html = ob_get_clean();
	$check( false !== strpos( $html, $needle ) && false === strpos( $html, 'SECRET-ELEMENTOR' ), 'public-safe ' . $kind . ' rendering' );
}
fwrite( STDOUT, "OK: free Elementor widget registration/rendering passed\n" );
