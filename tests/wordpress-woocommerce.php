<?php
/**
 * WooCommerce integration test. Run on a disposable site with WooCommerce active:
 *   wp eval-file wp-content/plugins/ashbi-testimonials/tests/wordpress-woocommerce.php
 *
 * @package Ashbi_Testimonials
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'ABT_Data' ) || ! class_exists( 'WooCommerce' ) ) {
	fwrite( STDERR, "Run with wp eval-file on a site with this plugin and WooCommerce active.\n" );
	exit( 1 );
}

// wp eval-file runs this file inside a function, so the counter lives in $GLOBALS.
$GLOBALS['abt_checks'] = 0;
function abt_assert( $condition, $message ) {
	++$GLOBALS['abt_checks'];
	if ( ! $condition ) {
		throw new RuntimeException( 'FAILED: ' . $message );
	}
}

/** Rebuild the singleton so it re-reads settings, as it would on the next request. */
function abt_reload_woocommerce() {
	remove_all_filters( 'woocommerce_product_tabs' );
	remove_filter( 'woocommerce_structured_data_product', array( ABT_WooCommerce::instance(), 'schema' ), 20 );
	$prop = new ReflectionProperty( 'ABT_WooCommerce', 'instance' );
	$prop->setAccessible( true );
	$prop->setValue( null, null );
	return ABT_WooCommerce::instance();
}

$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
wp_set_current_user( $admins[0]->ID );

abt_assert( ABT_WooCommerce::active(), 'WooCommerce is detected' );

$product = new WC_Product_Simple();
$product->set_name( 'CI Mug' );
$product->set_regular_price( '12' );
$product->set_status( 'publish' );
$product_id = $product->save();
abt_assert( $product_id > 0, 'product is created' );

/* Off by default. */
update_option( ABT_SETTINGS_OPTION, array() );
abt_reload_woocommerce();
abt_assert( false === has_filter( 'woocommerce_product_tabs' ), 'tab is off by default' );

/* On: no reviews and no form means no empty tab. */
update_option( ABT_SETTINGS_OPTION, array( 'woo_tab' => '1' ) );
$woo = abt_reload_woocommerce();
abt_assert( false !== has_filter( 'woocommerce_product_tabs' ), 'tab filter registers when enabled' );

$GLOBALS['post'] = get_post( $product_id );
setup_postdata( $GLOBALS['post'] );
abt_assert( ! isset( $woo->tab( array() )['abt_testimonials'] ), 'no tab without reviews or a form' );

/* Reviews attached to the product add a counted tab. */
$col = ABT_Data::instance()->save_collection( array( 'name' => 'Mug reviews', 'type' => 'product' ) );
foreach ( array( 5, 4, 3 ) as $i => $rating ) {
	ABT_Data::instance()->save( array( 'author' => 'Buyer ' . $i, 'content' => 'Nice mug ' . $i, 'rating' => $rating, 'product_id' => $product_id, 'collections' => array( $col['id'] ) ) );
}
ABT_Data::instance()->save( array( 'author' => 'Other', 'content' => 'Different product', 'rating' => 1, 'collections' => array( $col['id'] ) ) );

$tabs = $woo->tab( array() );
abt_assert( isset( $tabs['abt_testimonials'] ), 'tab appears with reviews' );
abt_assert( false !== strpos( $tabs['abt_testimonials']['title'], '3' ), 'tab title shows the product review count' );

ob_start();
$woo->tab_content();
$html = ob_get_clean();
abt_assert( 3 === substr_count( $html, '<figure class="abt-card' ), 'tab shows only this product’s reviews' );
abt_assert( false === strpos( $html, 'Different product' ), 'other products’ reviews stay out' );
abt_assert( false !== strpos( $html, 'abt-summary' ) && false !== strpos( $html, '4.0' ), 'tab shows the product rating summary' );
abt_assert( false === strpos( $html, 'abt-form' ), 'no form unless a collection is chosen' );

/* A chosen collection adds the review form, bound to this product. */
update_option( ABT_SETTINGS_OPTION, array( 'woo_tab' => '1', 'woo_collection' => (string) $col['id'] ) );
$woo = abt_reload_woocommerce();
ob_start();
$woo->tab_content();
$html = ob_get_clean();
abt_assert( false !== strpos( $html, 'name="abt_target" value="' . $col['id'] . ':' . $product_id . '"' ), 'form saves to the chosen collection and product' );

/* A product with no reviews still gets a tab when the form is enabled. */
$empty = new WC_Product_Simple();
$empty->set_name( 'CI Empty' );
$empty->set_status( 'publish' );
$GLOBALS['post'] = get_post( $empty->save() );
setup_postdata( $GLOBALS['post'] );
abt_assert( isset( $woo->tab( array() )['abt_testimonials'] ), 'form-only tab appears for products without reviews' );
wp_reset_postdata();

/* Structured data for a product shortcode outside product pages names the product. */
do_shortcode( '[ashbi_testimonials collection="mug-reviews" product="' . $product_id . '"]' );
ob_start();
ABT_Render::instance()->print_schema();
$schema = ob_get_clean();
abt_assert( false !== strpos( $schema, '"Product"' ) && false !== strpos( $schema, 'CI Mug' ), 'product structured data names the product' );


/* Ashbi ratings merge into the existing WooCommerce Product entity. */
$native_schema = new WC_Structured_Data();
$native_schema->generate_product_data( $product );
$nodes = $native_schema->get_data();
$node = end( $nodes );
abt_assert( isset( $node['aggregateRating'] ) && 3 === (int) $node['aggregateRating']['reviewCount'] && 4.0 === (float) $node['aggregateRating']['ratingValue'], 'Ashbi-only product reviews are included in WooCommerce schema' );
$product->set_rating_counts( array( 5 => 2 ) );
$product->set_average_rating( '5' );
$product->set_review_count( 2 );
$product->save();
$combined_schema = new WC_Structured_Data();
$combined_schema->generate_product_data( $product );
$nodes = $combined_schema->get_data();
$node = end( $nodes );
abt_assert( 5 === (int) $node['aggregateRating']['reviewCount'] && 4.4 === (float) $node['aggregateRating']['ratingValue'], 'native and Ashbi ratings are combined with a weighted average' );
update_option( ABT_SETTINGS_OPTION, array( 'schema' => '0', 'woo_tab' => '1' ) );
$disabled_schema = new WC_Structured_Data();
$disabled_schema->generate_product_data( $product );
$nodes = $disabled_schema->get_data();
$node = end( $nodes );
abt_assert( 2 === (int) $node['aggregateRating']['reviewCount'], 'schema opt-out leaves native WooCommerce ratings unchanged' );

fwrite( STDOUT, 'OK: ' . $GLOBALS['abt_checks'] . " WooCommerce checks passed\n" );
