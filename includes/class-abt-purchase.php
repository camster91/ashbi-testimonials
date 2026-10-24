<?php
/** Private, authenticated WooCommerce purchase evidence.
 * @package Ashbi_Testimonials
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
class ABT_Purchase {
	const META = '_abt_purchase_evidence';
	public static function init() {
		add_action( 'wp_ajax_abt_purchase_nonce', array( __CLASS__, 'nonce' ) );
		add_action( 'abt_submitted', array( __CLASS__, 'capture' ) );
	}
	/** Same-origin authenticated read: never cached in a public form. */
	public static function nonce() {
		nocache_headers();
		$id = isset( $_POST['product_id'] ) && is_string( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This endpoint issues a user-bound nonce; it changes no state.
		if ( ! is_user_logged_in() || ! function_exists( 'wc_get_product' ) || ! $id || ! is_post_publicly_viewable( $id ) || post_password_required( $id ) || ! wc_get_product( $id ) ) { wp_send_json_error( null, 400 ); }
		wp_send_json_success( array( 'nonce' => wp_create_nonce( 'abt_purchase_' . $id ) ) );
	}
	private static function valid_line( $order, $line_id, $product_id ) {
		if ( ! $order instanceof WC_Order || ! $order->is_paid() || ! $order->get_date_paid() ) { return false; }
		$line = $order->get_item( $line_id );
		return $line instanceof WC_Order_Item_Product && in_array( $product_id, array( (int) $line->get_product_id(), (int) $line->get_variation_id() ), true ) && $line->get_quantity() + $order->get_qty_refunded_for_item( $line_id ) > 0;
	}
	/** Called only after a fresh public submission. Raw posted order/customer IDs are ignored. */
	public static function capture( $id ) {
		if ( ! function_exists( 'wc_get_orders' ) || ! is_user_logged_in() ) { return; }
		$item = ABT_Data::instance()->get( $id );
		$user = wp_get_current_user();
		$nonce = isset( $_POST['abt_purchase_nonce'] ) && is_string( $_POST['abt_purchase_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['abt_purchase_nonce'] ) ) : '';
		if ( ! $item || ! $item['product_id'] || ! wp_verify_nonce( $nonce, 'abt_purchase_' . $item['product_id'] ) || strtolower( $user->user_email ) !== strtolower( $item['email'] ) ) { return; }
		$orders = wc_get_orders( array( 'customer_id' => $user->ID, 'status' => wc_get_is_paid_statuses(), 'limit' => 50, 'orderby' => 'date', 'order' => 'DESC' ) );
		foreach ( $orders as $order ) {
			if ( (int) $order->get_customer_id() !== (int) $user->ID ) { continue; }
			foreach ( $order->get_items() as $line_id => $line ) {
				if ( self::valid_line( $order, $line_id, $item['product_id'] ) ) {
					update_post_meta( $id, self::META, array( 'order_id' => $order->get_id(), 'customer_id' => (int) $user->ID, 'product_id' => $item['product_id'], 'line_id' => (int) $line_id ) );
					return;
				}
			}
		}
	}
	/** Do not cache eligibility across requests: refunds, edits and deletion revoke it. */
	public static function verified( array $item ) {
		if ( ! function_exists( 'wc_get_order' ) ) { return false; }
		$proof = get_post_meta( $item['id'], self::META, true );
		if ( ! is_array( $proof ) || empty( $proof['order_id'] ) || empty( $proof['customer_id'] ) || empty( $proof['line_id'] ) || (int) ( $proof['product_id'] ?? 0 ) !== $item['product_id'] ) { return false; }
		$user = get_userdata( (int) $proof['customer_id'] );
		$order = wc_get_order( (int) $proof['order_id'] );
		return $user && strtolower( $user->user_email ) === strtolower( $item['email'] ) && $order instanceof WC_Order && (int) $order->get_customer_id() === (int) $user->ID && self::valid_line( $order, (int) $proof['line_id'], $item['product_id'] );
	}
}
