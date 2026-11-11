<?php
/** Genuine order-backed badge regression; disposable WooCommerce only. */
if ( ! defined( 'ABSPATH' ) || ! class_exists( 'WooCommerce' ) ) { exit( 1 ); }
$check = static function( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( 'Purchase: ' . $message ); } };
$check( class_exists( 'ASHBTE_Purchase' ), 'purchase evidence provider exists' );
add_filter( 'pre_wp_mail', '__return_true' );
$user = wp_create_user( 'ashbte_buyer_' . wp_generate_password( 8, false ), wp_generate_password(), 'buyer-' . wp_generate_password( 8, false ) . '@example.test' );
wp_set_current_user( $user );
$account = get_userdata( $user );
$product = new WC_Product_Simple(); $product->set_name( 'Evidence mug' ); $product->set_regular_price( '12' ); $product->set_status( 'publish' ); $product_id = $product->save();
$order = wc_create_order( array( 'customer_id' => $user ) );
$line_id = $order->add_product( $product, 1 ); $order->set_billing_email( $account->user_email ); $order->calculate_totals(); $order->set_date_paid( time() ); $order->set_status( 'completed' ); $order->save();
$col = ASHBTE_Data::instance()->save_collection( array( 'name' => 'Evidence collection' ) );
$id = ASHBTE_Data::instance()->save( array( 'author' => 'Buyer', 'email' => $account->user_email, 'rating' => 5, 'verified' => 1, 'product_id' => $product_id, 'collections' => array( $col['id'] ) ) );
$check( ! ASHBTE_Purchase::verified( ASHBTE_Data::instance()->get( $id ) ), 'manual verified flag cannot create purchase evidence' );
$_POST = array(); ASHBTE_Purchase::capture( $id );
$check( ! ASHBTE_Purchase::verified( ASHBTE_Data::instance()->get( $id ) ), 'missing per-user nonce cannot create evidence' );
$_POST['ashbte_purchase_nonce'] = wp_create_nonce( 'ashbte_purchase_' . $product_id ); ASHBTE_Purchase::capture( $id );
$check( ASHBTE_Purchase::verified( ASHBTE_Data::instance()->get( $id ) ), 'owned paid product order verifies authenticated submission' );
$html = ASHBTE_Render::instance()->card( ASHBTE_Data::instance()->get( $id ) );
$check( false !== strpos( $html, 'Verified purchase' ) && false === strpos( $html, 'order_id' ), 'badge reveals no order details' );
$forged = ASHBTE_Data::instance()->save( array( 'author' => 'Other', 'email' => 'claimed@example.test', 'rating' => 5, 'product_id' => $product_id ) );
ASHBTE_Purchase::capture( $forged );
$check( ! ASHBTE_Purchase::verified( ASHBTE_Data::instance()->get( $forged ) ), 'a claimed email cannot borrow the account purchase' );
$variable = new WC_Product_Variable(); $variable->set_name( 'Purchased variable item' ); $variable->set_status( 'publish' ); $variable_id = $variable->save();
$variation = new WC_Product_Variation(); $variation->set_parent_id( $variable_id ); $variation->set_status( 'publish' ); $variation->set_regular_price( '15' ); $variation->save();
$variable_order = wc_create_order( array( 'customer_id' => $user ) ); $variable_order->add_product( $variation, 1 ); $variable_order->set_billing_email( $account->user_email ); $variable_order->set_date_paid( time() ); $variable_order->set_status( 'completed' ); $variable_order->save();
$variable_review = ASHBTE_Data::instance()->save( array( 'author' => 'Variation buyer', 'email' => $account->user_email, 'rating' => 5, 'product_id' => $variable_id ) );
$_POST['ashbte_purchase_nonce'] = wp_create_nonce( 'ashbte_purchase_' . $variable_id ); ASHBTE_Purchase::capture( $variable_review );
$check( ASHBTE_Purchase::verified( ASHBTE_Data::instance()->get( $variable_review ) ), 'purchased variation verifies its public parent product review' );
wp_set_current_user( 0 ); ASHBTE_Purchase::capture( $forged );
$check( ! ASHBTE_Purchase::verified( ASHBTE_Data::instance()->get( $forged ) ), 'anonymous submission cannot claim order evidence' );
$refund = wc_create_refund( array( 'order_id' => $order->get_id(), 'amount' => 12, 'reason' => 'Disposable test', 'line_items' => array( $line_id => array( 'qty' => 1, 'refund_total' => 12 ) ) ) );
$check( ! is_wp_error( $refund ) && ! ASHBTE_Purchase::verified( ASHBTE_Data::instance()->get( $id ) ), 'refunded product revokes the badge' );
wp_set_current_user( $user );
$export = ASHBTE_Privacy::instance()->export( $account->user_email, 1 );
$check( false !== strpos( wp_json_encode( $export ), 'Purchase evidence' ), 'private evidence participates in privacy export' );
$_POST = array(); remove_filter( 'pre_wp_mail', '__return_true' );
fwrite( STDOUT, "OK: authenticated purchase evidence and refund/privacy regressions passed\n" );
