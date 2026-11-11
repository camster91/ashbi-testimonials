<?php
/**
 * WooCommerce integration test. Run on a disposable site with WooCommerce active:
 *   wp eval-file wp-content/plugins/ashbi-testimonials/tests/wordpress-woocommerce.php
 *
 * @package Ashbi_Testimonials
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'ASHBTE_Data' ) || ! class_exists( 'WooCommerce' ) ) {
	fwrite( STDERR, "Run with wp eval-file on a site with this plugin and WooCommerce active.\n" );
	exit( 1 );
}

// wp eval-file runs this file inside a function, so the counter lives in $GLOBALS.
$GLOBALS['ashbte_checks'] = 0;
function ashbte_assert( $condition, $message ) {
	++$GLOBALS['ashbte_checks'];
	if ( ! $condition ) {
		throw new RuntimeException( 'FAILED: ' . $message );
	}
}

/** Rebuild the singleton so it re-reads settings, as it would on the next request. */
function ashbte_reload_woocommerce() {
	remove_all_filters( 'woocommerce_product_tabs' );
	remove_filter( 'woocommerce_structured_data_product', array( ASHBTE_WooCommerce::instance(), 'schema' ), 20 );
	$prop = new ReflectionProperty( 'ASHBTE_WooCommerce', 'instance' );
	$prop->setAccessible( true );
	$prop->setValue( null, null );
	return ASHBTE_WooCommerce::instance();
}

$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
wp_set_current_user( $admins[0]->ID );

ashbte_assert( ASHBTE_WooCommerce::active(), 'WooCommerce is detected' );

$product = new WC_Product_Simple();
$product->set_name( 'CI Mug' );
$product->set_regular_price( '12' );
$product->set_status( 'publish' );
$product_id = $product->save();
ashbte_assert( $product_id > 0, 'product is created' );

/* Off by default. */
update_option( ASHBTE_SETTINGS_OPTION, array() );
ashbte_reload_woocommerce();
ashbte_assert( false === has_filter( 'woocommerce_product_tabs' ), 'tab is off by default' );

/* On: no reviews and no form means no empty tab. */
update_option( ASHBTE_SETTINGS_OPTION, array( 'woo_tab' => '1' ) );
$woo = ashbte_reload_woocommerce();
ashbte_assert( false !== has_filter( 'woocommerce_product_tabs' ), 'tab filter registers when enabled' );

$GLOBALS['post'] = get_post( $product_id );
setup_postdata( $GLOBALS['post'] );
ashbte_assert( ! isset( $woo->tab( array() )['ashbte_testimonials'] ), 'no tab without reviews or a form' );

/* Reviews attached to the product add a counted tab. */
$col = ASHBTE_Data::instance()->save_collection( array( 'name' => 'Mug reviews', 'type' => 'product' ) );
foreach ( array( 5, 4, 3 ) as $i => $rating ) {
	ASHBTE_Data::instance()->save( array( 'origin' => 'direct', 'author' => 'Buyer ' . $i, 'content' => 'Nice mug ' . $i, 'rating' => $rating, 'product_id' => $product_id, 'collections' => array( $col['id'] ) ) );
}
ASHBTE_Data::instance()->save( array( 'author' => 'Other', 'content' => 'Different product', 'rating' => 1, 'collections' => array( $col['id'] ) ) );

$tabs = $woo->tab( array() );
ashbte_assert( isset( $tabs['ashbte_testimonials'] ), 'tab appears with reviews' );
ashbte_assert( false !== strpos( $tabs['ashbte_testimonials']['title'], '3' ), 'tab title shows the product review count' );

ob_start();
$woo->tab_content();
$html = ob_get_clean();
ashbte_assert( 3 === substr_count( $html, '<figure class="ashbte-card' ), 'tab shows only this product’s reviews' );
ashbte_assert( false === strpos( $html, 'Different product' ), 'other products’ reviews stay out' );
ashbte_assert( false !== strpos( $html, 'ashbte-summary' ) && false !== strpos( $html, '4.0' ), 'tab shows the product rating summary' );
ashbte_assert( false === strpos( $html, 'ashbte-form' ), 'no form unless a collection is chosen' );

/* A chosen collection adds the review form, bound to this product. */
update_option( ASHBTE_SETTINGS_OPTION, array( 'woo_tab' => '1', 'woo_collection' => (string) $col['id'] ) );
$woo = ashbte_reload_woocommerce();
ob_start();
$woo->tab_content();
$html = ob_get_clean();
ashbte_assert( false !== strpos( $html, 'name="ashbte_target" value="' . $col['id'] . ':' . $product_id . '"' ), 'form saves to the chosen collection and product' );

/* A product with no reviews still gets a tab when the form is enabled. */
$empty = new WC_Product_Simple();
$empty->set_name( 'CI Empty' );
$empty->set_status( 'publish' );
$GLOBALS['post'] = get_post( $empty->save() );
setup_postdata( $GLOBALS['post'] );
ashbte_assert( isset( $woo->tab( array() )['ashbte_testimonials'] ), 'form-only tab appears for products without reviews' );
wp_reset_postdata();

/* Structured data for a product shortcode outside product pages names the product. */
do_shortcode( '[ashbte_testimonials summary="1" collection="mug-reviews" product="' . $product_id . '"]' );
ob_start();
ASHBTE_Render::instance()->print_schema();
$schema = ob_get_clean();
ashbte_assert( false !== strpos( $schema, '"Product"' ) && false !== strpos( $schema, 'CI Mug' ), 'product structured data names the product' );


/* Ashbi ratings merge into the existing WooCommerce Product entity. */
$native_schema = new WC_Structured_Data();
$native_schema->generate_product_data( $product );
$nodes = $native_schema->get_data();
$node = end( $nodes );
ashbte_assert( isset( $node['aggregateRating'] ) && 3 === (int) $node['aggregateRating']['reviewCount'] && 4.0 === (float) $node['aggregateRating']['ratingValue'], 'Ashbi-only product reviews are included in WooCommerce schema' );
$product->set_rating_counts( array( 5 => 2 ) );
$product->set_average_rating( '5' );
$product->set_review_count( 2 );
$product->save();
$combined_schema = new WC_Structured_Data();
$combined_schema->generate_product_data( $product );
$nodes = $combined_schema->get_data();
$node = end( $nodes );
ashbte_assert( 5 === (int) $node['aggregateRating']['reviewCount'] && 4.4 === (float) $node['aggregateRating']['ratingValue'], 'native and Ashbi ratings are combined with a weighted average' );
$external = ASHBTE_Data::instance()->save( array( 'author' => 'External review', 'rating' => 1, 'origin' => 'imported', 'source' => 'Google', 'product_id' => $product_id, 'collections' => array( $col['id'] ) ) );
$external_schema = new WC_Structured_Data();
$external_schema->generate_product_data( $product );
$nodes = $external_schema->get_data();
$node = end( $nodes );
ashbte_assert( 2 === (int) $node['aggregateRating']['reviewCount'], 'mixed external reviews leave native WooCommerce aggregate unchanged' );
wp_delete_post( $external, true );
update_option( ASHBTE_SETTINGS_OPTION, array( 'schema' => '0', 'woo_tab' => '1' ) );
$disabled_schema = new WC_Structured_Data();
$disabled_schema->generate_product_data( $product );
$nodes = $disabled_schema->get_data();
$node = end( $nodes );
ashbte_assert( 2 === (int) $node['aggregateRating']['reviewCount'], 'schema opt-out leaves native WooCommerce ratings unchanged' );

fwrite( STDOUT, 'OK: ' . $GLOBALS['ashbte_checks'] . " WooCommerce checks passed\n" );
