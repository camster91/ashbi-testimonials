<?php
/** Genuine order-backed badge regression; disposable WooCommerce only. */
if ( ! defined( 'ABSPATH' ) || ! class_exists( 'WooCommerce' ) ) { exit( 1 ); }
$check = static function( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( 'Purchase: ' . $message ); } };
$check( class_exists( 'ABT_Purchase' ), 'purchase evidence provider exists' );
add_filter( 'pre_wp_mail', '__return_true' );
$user = wp_create_user( 'abt_buyer_' . wp_generate_password( 8, false ), wp_generate_password(), 'buyer-' . wp_generate_password( 8, false ) . '@example.test' );
wp_set_current_user( $user );
$account = get_userdata( $user );
$product = new WC_Product_Simple(); $product->set_name( 'Evidence mug' ); $product->set_regular_price( '12' ); $product->set_status( 'publish' ); $product_id = $product->save();
$order = wc_create_order( array( 'customer_id' => $user ) );
$line_id = $order->add_product( $product, 1 ); $order->set_billing_email( $account->user_email ); $order->calculate_totals(); $order->set_status( 'completed' ); $order->save();
$col = ABT_Data::instance()->save_collection( array( 'name' => 'Evidence collection' ) );
$id = ABT_Data::instance()->save( array( 'author' => 'Buyer', 'email' => $account->user_email, 'rating' => 5, 'verified' => 1, 'product_id' => $product_id, 'collections' => array( $col['id'] ) ) );
$check( ! ABT_Purchase::verified( ABT_Data::instance()->get( $id ) ), 'manual verified flag cannot create purchase evidence' );
$_POST = array(); ABT_Purchase::capture( $id );
$check( ! ABT_Purchase::verified( ABT_Data::instance()->get( $id ) ), 'missing per-user nonce cannot create evidence' );
$_POST['abt_purchase_nonce'] = wp_create_nonce( 'abt_purchase_' . $product_id ); ABT_Purchase::capture( $id );
$check( ABT_Purchase::verified( ABT_Data::instance()->get( $id ) ), 'owned paid product order verifies authenticated submission' );
$html = ABT_Render::instance()->card( ABT_Data::instance()->get( $id ) );
$check( false !== strpos( $html, 'Verified purchase' ) && false === strpos( $html, 'order_id' ), 'badge reveals no order details' );
$forged = ABT_Data::instance()->save( array( 'author' => 'Other', 'email' => 'claimed@example.test', 'rating' => 5, 'product_id' => $product_id ) );
ABT_Purchase::capture( $forged );
$check( ! ABT_Purchase::verified( ABT_Data::instance()->get( $forged ) ), 'a claimed email cannot borrow the account purchase' );
wp_set_current_user( 0 ); ABT_Purchase::capture( $forged );
$check( ! ABT_Purchase::verified( ABT_Data::instance()->get( $forged ) ), 'anonymous submission cannot claim order evidence' );
$refund = wc_create_refund( array( 'order_id' => $order->get_id(), 'amount' => 12, 'reason' => 'Disposable test', 'line_items' => array( $line_id => array( 'qty' => 1, 'refund_total' => 12 ) ) ) );
$check( ! is_wp_error( $refund ) && ! ABT_Purchase::verified( ABT_Data::instance()->get( $id ) ), 'refunded product revokes the badge' );
wp_set_current_user( $user );
$export = ABT_Privacy::instance()->export( $account->user_email, 1 );
$check( false !== strpos( wp_json_encode( $export ), 'Purchase evidence' ), 'private evidence participates in privacy export' );
$_POST = array(); remove_filter( 'pre_wp_mail', '__return_true' );
fwrite( STDOUT, "OK: authenticated purchase evidence and refund/privacy regressions passed\n" );
