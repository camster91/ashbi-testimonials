<?php
/** Opt-in scheduler fixture: intercept all email. */
if ( ! defined( 'ABSPATH' ) || ! class_exists( 'WooCommerce' ) ) { exit( 1 ); }
$check = static function( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( 'Requests: ' . $message ); } };
$check( class_exists( 'ABT_Requests' ), 'request workflow exists' );
$accept = true; $mail = array(); $intercept = static function( $return, $args ) use ( &$mail, &$accept ) { $mail[] = $args; return $accept; }; add_filter( 'pre_wp_mail', $intercept, 10, 2 );
$user = wp_create_user( 'request_' . wp_generate_password( 8, false ), wp_generate_password(), 'request-' . wp_generate_password( 8, false ) . '@example.test' ); wp_set_current_user( $user );
$product = new WC_Product_Simple(); $product->set_name( 'Reminder mug' ); $product->set_regular_price( '10' ); $product->set_status( 'publish' ); $pid = $product->save();
$order = wc_create_order( array( 'customer_id' => $user ) ); $order->add_product( $product, 1 ); $order->set_billing_email( get_userdata( $user )->user_email ); $order->calculate_totals(); $order->set_date_paid( time() ); $order->set_status( 'completed' ); $order->save(); $id = $order->get_id();
$col = ABT_Data::instance()->save_collection( array( 'name' => 'Reminder collection' ) );
update_option( ABT_SETTINGS_OPTION, array( 'woo_tab' => '1', 'woo_collection' => (string) $col['id'] ) );
$check( is_wp_error( ABT_Requests::consent( $id ) ), 'default off prevents opt-in/scheduling' );
update_option( ABT_SETTINGS_OPTION, array( 'woo_tab' => '1', 'woo_collection' => (string) $col['id'], 'review_requests' => '1' ) );
wp_set_current_user( 0 ); $check( is_wp_error( ABT_Requests::consent( $id ) ), 'guests cannot guess an order consent' ); wp_set_current_user( $user );
$check( true === ABT_Requests::consent( $id ), 'owner can request one reminder' );
ABT_Requests::consent( $id );
$check( (bool) wp_next_scheduled( ABT_Requests::HOOK, array( $id, $user ) ), 'one stable event is scheduled' );
$state = get_option( ABT_Requests::key( $id, $user ) ); $state['due'] = time() - 1; update_option( ABT_Requests::key( $id, $user ), $state, false );
ABT_Requests::send( $id, $user ); ABT_Requests::send( $id, $user );
$check( 1 === count( $mail ), 'replayed delivery sends at most one message' );
$check( false !== strpos( $mail[0]['message'], get_permalink( $pid ) ) && false === strpos( $mail[0]['message'], 'rating=5' ), 'neutral invitation links to the product, without rating gating' );
$second = wc_create_order( array( 'customer_id' => $user ) ); $second->add_product( $product, 1 ); $second->set_billing_email( get_userdata( $user )->user_email ); $second->set_date_paid( time() ); $second->set_status( 'completed' ); $second->save();
ABT_Requests::consent( $second->get_id() ); $second->set_status( 'cancelled' ); $second->save(); ABT_Requests::send( $second->get_id(), $user );
$check( 1 === count( $mail ), 'cancellation suppresses delivery' );
$third = wc_create_order( array( 'customer_id' => $user ) ); $third->add_product( $product, 1 ); $third->set_billing_email( get_userdata( $user )->user_email ); $third->set_date_paid( time() ); $third->set_status( 'completed' ); $third->save();
ABT_Requests::consent( $third->get_id() ); ABT_Requests::cancel( $third->get_id(), $user ); ABT_Requests::send( $third->get_id(), $user );
$check( 1 === count( $mail ) && ! wp_next_scheduled( ABT_Requests::HOOK, array( $third->get_id(), $user ) ), 'customer cancellation removes pending delivery' );
$make = static function() use ( $user, $product ) {
 $o = wc_create_order( array( 'customer_id' => $user ) ); $o->add_product( $product, 1 ); $o->set_billing_email( get_userdata( $user )->user_email ); $o->set_date_paid( time() ); $o->set_status( 'completed' ); $o->save();
 ABT_Requests::consent( $o->get_id() ); $key = ABT_Requests::key( $o->get_id(), $user ); $state = get_option( $key ); $state['due'] = time() - 1; update_option( $key, $state, false ); return $o;
};
$failed = $make(); $accept = false; ABT_Requests::send( $failed->get_id(), $user ); ABT_Requests::send( $failed->get_id(), $user ); $accept = true;
$check( 2 === count( $mail ) && 'mail_failed' === get_option( ABT_Requests::key( $failed->get_id(), $user ) )['state'], 'failed mail is terminal and is never blindly resent' );
$disabled = $make(); update_option( ABT_SETTINGS_OPTION, array() ); ABT_Requests::send( $disabled->get_id(), $user );
$check( 2 === count( $mail ), 'disabling configuration suppresses an already queued reminder' );
update_option( ABT_SETTINGS_OPTION, array( 'woo_tab' => '1', 'woo_collection' => (string) $col['id'], 'review_requests' => '1' ) );
$reviewed = $make(); ABT_Data::instance()->save( array( 'author' => 'Already reviewed', 'email' => get_userdata( $user )->user_email, 'rating' => 4, 'product_id' => $pid, 'status' => 'pending' ) ); ABT_Requests::send( $reviewed->get_id(), $user );
$check( 2 === count( $mail ), 'existing pending review suppresses reminders' );
$privacy = ABT_Requests::export( get_userdata( $user )->user_email );
$check( ! empty( $privacy['data'] ), 'request state participates in account-scoped privacy export' );
$erased = ABT_Requests::erase( get_userdata( $user )->user_email );
$check( $erased['items_removed'] && ! get_option( ABT_Requests::key( $id, $user ) ), 'privacy erasure removes request state and claims' );
remove_filter( 'pre_wp_mail', $intercept, 10 );
fwrite( STDOUT, "OK: opt-in scheduling, idempotent delivery and cancellation passed with intercepted mail\n" );
