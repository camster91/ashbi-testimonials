<?php
/** Explicitly opted-in, at-most-once order review reminders.
 * @package Ashbi_Testimonials
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
class ASHBTE_Requests {
	const HOOK = 'ashbte_review_request';
	const BATCH = 20;
	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'erasers' ) );
		add_action( self::HOOK, array( __CLASS__, 'send' ), 10, 2 );
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'controls' ) );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'changed' ), 10, 4 );
		add_action( 'admin_post_ashbte_request', array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_ashbte_request_stop', array( __CLASS__, 'stop' ) );
		add_action( 'admin_post_nopriv_ashbte_request_stop', array( __CLASS__, 'stop' ) );
	}
	public static function exporters( $list ) {
		$list['ashbte-review-requests'] = array( 'exporter_friendly_name' => __( 'Review reminders', 'ashbi-testimonials' ), 'callback' => array( __CLASS__, 'export' ) ); return $list;
	}
	public static function erasers( $list ) {
		$list['ashbte-review-requests'] = array( 'eraser_friendly_name' => __( 'Review reminders', 'ashbi-testimonials' ), 'callback' => array( __CLASS__, 'erase' ) ); return $list;
	}
	public static function export( $email, $page = 1 ) { return self::privacy( $email, $page ); }
	public static function erase( $email, $page = 1 ) {
		$result = self::privacy( $email, $page, true );
		$user = get_user_by( 'email', $email );
		$retained = $user && (bool) get_user_meta( $user->ID, self::optout_key(), true );
		return array( 'items_removed' => $result['items_removed'], 'items_retained' => $retained, 'messages' => $retained ? array( __( 'A minimal account suppression preference is retained to prevent future reminders. WooCommerce orders are handled by WooCommerce privacy tools.', 'ashbi-testimonials' ) ) : array(), 'done' => $result['done'] );
	}
	public static function key( $id, $user ) { return 'ashbte_request_' . absint( $user ) . '_' . absint( $id ); }
	private static function optout_key() { return '_ashbte_request_optout_' . get_current_blog_id(); }
	private static function enabled() {
		$s = ashbte_settings();
		return '1' === $s['review_requests'] && '1' === $s['woo_tab'] && ASHBTE_Data::instance()->find_collection( $s['woo_collection'] );
	}
	private static function product( $order ) {
		if ( ! $order instanceof WC_Order || ! $order->has_status( 'completed' ) || ! $order->is_paid() || ! $order->get_date_paid() || $order->get_refunds() ) { return null; }
		foreach ( $order->get_items() as $id => $line ) {
			$product = $line->get_product();
			if ( $product && $product->is_type( 'variation' ) ) { $product = wc_get_product( $product->get_parent_id() ); }
			if ( $product && $line->get_quantity() + $order->get_qty_refunded_for_item( $id ) > 0 && is_post_publicly_viewable( $product->get_id() ) && ! post_password_required( $product->get_id() ) ) { return $product; }
		}
		return null;
	}
	/** Domain operation; route also verifies the customer nonce. */
	public static function consent( $id ) {
		$user = get_current_user_id();
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $id ) : null;
		if ( ! self::enabled() || ! $user || ! $order instanceof WC_Order || (int) $order->get_customer_id() !== $user || ! self::product( $order ) || strtolower( $order->get_billing_email() ) !== strtolower( wp_get_current_user()->user_email ) ) { return new WP_Error( 'ashbte_request', __( 'This order is not eligible for a review reminder.', 'ashbi-testimonials' ) ); }
		$key = self::key( $id, $user );
		if ( get_option( $key ) || get_option( $key . '_claim' ) ) { return true; }
		$state = array( 'user_id' => $user, 'order_id' => (int) $id, 'consented_at' => time(), 'suppression_generation' => (string) get_user_meta( $user, self::optout_key(), true ), 'due' => time() + 7 * DAY_IN_SECONDS, 'attempts' => 0, 'state' => 'queued' );
		if ( ! ASHBTE_Atomic::insert( $key, $state ) ) { return true; }
		if ( ! wp_schedule_single_event( $state['due'], self::HOOK, array( (int) $id, $user ) ) ) { $failed = $state; $failed['state'] = 'schedule_failed'; ASHBTE_Atomic::replace( $key, $state, $failed ); return new WP_Error( 'ashbte_schedule', __( 'The reminder could not be scheduled. Contact the site owner.', 'ashbi-testimonials' ) ); }
		return true;
	}
	public static function cancel( $id, $user, $all = false ) {
		$key = self::key( $id, $user );
		global $wpdb;
		$state = maybe_unserialize( $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Terminal cancellation must read current state, not a stale worker cache.
		if ( is_array( $state ) ) {
			do_action( 'ashbte_before_request_cancel', $id, $user );
			$state['state'] = 'suppressed';
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s", maybe_serialize( $state ), $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Terminal update overrides a concurrent queued retry but cannot recreate an erased record.
			wp_cache_delete( $key, 'options' ); wp_cache_delete( 'notoptions', 'options' );
		}
		wp_clear_scheduled_hook( self::HOOK, array( (int) $id, (int) $user ) );
		if ( $all ) { update_user_meta( $user, self::optout_key(), wp_generate_uuid4() ); }
	}
	public static function changed( $id, $from, $to, $order ) {
		if ( 'completed' !== $to && $order instanceof WC_Order ) { self::cancel( $id, $order->get_customer_id() ); }
	}
	/** The atomic claim must still match both order consent and account suppression. */
	private static function claim( $key, array $expected ) {
		global $wpdb;
		$claim = $key . '_claim';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Atomic consent/suppression claim requires a conditional insert, never a cached option API.
		$result = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload)
			SELECT %s, %s, 'no' FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s
			AND COALESCE((SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s ORDER BY umeta_id ASC LIMIT 1), '') = %s",
			$claim, (string) time(), $key, maybe_serialize( $expected ), $expected['user_id'], self::optout_key(), $expected['suppression_generation'] ?? '' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic consent/suppression comparison cannot use cached options or separate reads.
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
		wp_cache_delete( $claim, 'options' ); wp_cache_delete( 'notoptions', 'options' );
		return 1 === $result;
	}
	/** Queue replay is safe; never retry an ambiguous mail attempt. */
	public static function send( $id, $user ) {
		$key = self::key( $id, $user );
		$state = get_option( $key );
		if ( ! is_array( $state ) || 'queued' !== $state['state'] || time() < $state['due'] || get_option( $key . '_claim' ) ) { return; }
		if ( ! apply_filters( 'ashbte_request_order_api_available', function_exists( 'wc_get_order' ) ) ) {
			$retry = $state; ++$retry['attempts']; $retry['due'] = time() + HOUR_IN_SECONDS;
			if ( $retry['attempts'] >= 3 ) { $retry['state'] = 'unavailable'; }
			do_action( 'ashbte_before_request_retry', $id, $user );
			if ( ! ASHBTE_Atomic::replace( $key, $state, $retry ) ) { return; }
			if ( 'queued' === $retry['state'] && ! wp_schedule_single_event( $retry['due'], self::HOOK, array( (int) $id, (int) $user ) ) ) {
				$failed = $retry; $failed['state'] = 'unavailable'; ASHBTE_Atomic::replace( $key, $retry, $failed );
			}
			return;
		}
		$order = wc_get_order( $id );
		$account = get_userdata( $user );
		$product = self::product( $order );
		$generation = (string) get_user_meta( $user, self::optout_key(), true );
		if ( ! self::enabled() || ! $account || ( $generation && $generation !== ( $state['suppression_generation'] ?? '' ) ) || ! $product || (int) $order->get_customer_id() !== (int) $user || strtolower( $order->get_billing_email() ) !== strtolower( $account->user_email ) ) { self::cancel( $id, $user ); return; }
		$reviews = get_posts( array( 'post_type' => ASHBTE_Data::POST_TYPE, 'post_status' => ASHBTE_Data::STATUSES, 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => true, 'meta_query' => array( array( 'key' => ASHBTE_Data::META['email'], 'value' => $account->user_email ), array( 'key' => ASHBTE_Data::META['product_id'], 'value' => $product->get_id() ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded check before an explicitly opted-in reminder.
		if ( $reviews ) { self::cancel( $id, $user ); return; }
		do_action( 'ashbte_before_request_claim', $id, $user );
		if ( ! self::claim( $key, $state ) ) { return; }
		$sending = $state; $sending['state'] = 'sending';
		if ( ! ASHBTE_Atomic::replace( $key, $state, $sending ) ) { return; }
		$state = $sending;
		$stop = add_query_arg( array( 'action' => 'ashbte_request_stop', 'order' => (int) $id, 'customer' => (int) $user, 'token' => wp_hash( 'ashbte_stop|' . ( is_multisite() ? get_current_blog_id() . '|' : '' ) . $id . '|' . $user ) ), admin_url( 'admin-post.php' ) );
		/* translators: 1: product name, 2: review link, 3: unsubscribe confirmation link */
		$message = sprintf( __( "You requested one review reminder for %1\$s. Share your honest experience, positive or negative:\n\n%2\$s\n\nStop review reminders (confirmation required):\n%3\$s", 'ashbi-testimonials' ), wp_strip_all_tags( $product->get_name() ), $product->get_permalink(), $stop );
		$state['state'] = wp_mail( $account->user_email, __( 'Share your experience', 'ashbi-testimonials' ), $message ) ? 'accepted' : 'mail_failed';
		$state['attempted_at'] = time(); ASHBTE_Atomic::replace( $key, $sending, $state );
		wp_clear_scheduled_hook( self::HOOK, array( (int) $id, (int) $user ) );
	}
	public static function controls( $order ) {
		if ( ! self::enabled() || ! is_user_logged_in() || ! $order instanceof WC_Order || (int) $order->get_customer_id() !== get_current_user_id() || ! self::product( $order ) ) { return; }
		$state = get_option( self::key( $order->get_id(), get_current_user_id() ) );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ashbte_request" /><input type="hidden" name="order" value="<?php echo (int) $order->get_id(); ?>" />
			<?php wp_nonce_field( 'ashbte_request', 'ashbte_request_nonce' ); ?>
			<?php if ( ! $state ) : ?>
				<p><?php esc_html_e( 'Optional: receive one email in seven days asking for your honest product review. No account details or review text are shared with Ashbi.', 'ashbi-testimonials' ); ?></p>
				<button type="submit" name="operation" value="consent"><?php esc_html_e( 'Email me one review reminder', 'ashbi-testimonials' ); ?></button>
			<?php elseif ( 'queued' === $state['state'] && ! get_option( self::key( $order->get_id(), get_current_user_id() ) . '_claim' ) ) : ?>
				<button type="submit" name="operation" value="cancel"><?php esc_html_e( 'Cancel my review reminder', 'ashbi-testimonials' ); ?></button>
			<?php else : ?>
				<p><?php esc_html_e( 'No further review reminder is scheduled for this order.', 'ashbi-testimonials' ); ?></p>
			<?php endif; ?>
		</form>
		<?php
	}
	public static function handle() {
		check_admin_referer( 'ashbte_request', 'ashbte_request_nonce' );
		$id = isset( $_POST['order'] ) && is_string( $_POST['order'] ) ? absint( $_POST['order'] ) : 0;
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $id ) : null;
		if ( ! is_user_logged_in() || ! $order instanceof WC_Order || (int) $order->get_customer_id() !== get_current_user_id() ) { wp_die( esc_html__( 'This order is not available.', 'ashbi-testimonials' ), '', array( 'response' => 403 ) ); }
		$operation = isset( $_POST['operation'] ) && is_string( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
		if ( 'cancel' === $operation ) { self::cancel( $id, get_current_user_id() ); } elseif ( 'consent' === $operation ) { $result = self::consent( $id ); if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); } }
		wp_safe_redirect( $order->get_view_order_url() ); exit;
	}
	/** A GET never changes preferences: mail scanners see only a generic confirmation. */
	public static function stop() {
		nocache_headers(); header( 'Referrer-Policy: no-referrer' );
		$method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' === $method ) {
			// The confirmation form carries a nonce; verify it before reading anything else.
			check_admin_referer( 'ashbte_request_stop', 'ashbte_stop_nonce' );
			$id = isset( $_POST['order'] ) && is_string( $_POST['order'] ) ? absint( $_POST['order'] ) : 0;
			$user = isset( $_POST['customer'] ) && is_string( $_POST['customer'] ) ? absint( $_POST['customer'] ) : 0;
			$token = isset( $_POST['token'] ) && is_string( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
			if ( ! self::valid_stop( $id, $user, $token ) ) { wp_die( esc_html__( 'This confirmation link is invalid.', 'ashbi-testimonials' ) ); }
			self::cancel( $id, $user, true );
			wp_die( esc_html__( 'Review reminders are stopped. You can explicitly request a new reminder from your account in future.', 'ashbi-testimonials' ) );
		}
		/*
		 * GET is the link from the reminder email. It cannot carry a nonce: the recipient is
		 * usually signed out and the link must outlive a nonce. The link is authenticated by
		 * its HMAC signature instead, and GET only shows a confirmation form. The state change
		 * happens on POST above, which requires the nonce printed in that form.
		 */
		$id = isset( $_GET['order'] ) && is_string( $_GET['order'] ) ? absint( $_GET['order'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Signed email link; read-only confirmation page.
		$user = isset( $_GET['customer'] ) && is_string( $_GET['customer'] ) ? absint( $_GET['customer'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Signed email link; read-only confirmation page.
		$token = isset( $_GET['token'] ) && is_string( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Signed email link; read-only confirmation page.
		if ( ! self::valid_stop( $id, $user, $token ) ) { wp_die( esc_html__( 'This confirmation link is invalid.', 'ashbi-testimonials' ) ); }
		$html = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="ashbte_request_stop" /><input type="hidden" name="order" value="' . $id . '" /><input type="hidden" name="customer" value="' . $user . '" /><input type="hidden" name="token" value="' . esc_attr( $token ) . '" />' . wp_nonce_field( 'ashbte_request_stop', 'ashbte_stop_nonce', false, false ) . '<button type="submit">' . esc_html__( 'Stop review reminders', 'ashbi-testimonials' ) . '</button></form>';
		wp_die( $html, esc_html__( 'Review reminder preferences', 'ashbi-testimonials' ), array( 'response' => 200 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every dynamic form value escaped above.
	}
	private static function valid_stop( $id, $user, $token ) {
		return $id && $user && hash_equals( wp_hash( 'ashbte_stop|' . ( is_multisite() ? get_current_blog_id() . '|' : '' ) . $id . '|' . $user ), $token );
	}
	/** Private batch scoped to a verified account email, used by native privacy tools. */
	public static function privacy( $email, $page = 1, $erase = false ) {
		global $wpdb;
		$user = get_user_by( 'email', $email );
		if ( ! $user ) { return array( 'data' => array(), 'done' => true, 'items_removed' => false ); }
		$prefix = self::key( 0, $user->ID ); $prefix = substr( $prefix, 0, -1 );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s ORDER BY option_name LIMIT %d OFFSET %d", $wpdb->esc_like( $prefix ) . '%', '%' . $wpdb->esc_like( '_claim' ), self::BATCH, $erase ? 0 : ( max( 1, (int) $page ) - 1 ) * self::BATCH ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded indexed prefix over plugin-owned private records for verified privacy requests.
		$out = array();
		if ( ! $erase && 1 === (int) $page && get_user_meta( $user->ID, self::optout_key(), true ) ) {
			$out[] = array( 'group_id' => 'ashbte-review-requests', 'group_label' => __( 'Review reminders', 'ashbi-testimonials' ), 'item_id' => 'ashbte-reminder-preference-' . $user->ID, 'data' => array( array( 'name' => __( 'Account suppression preference', 'ashbi-testimonials' ), 'value' => __( 'Review reminders stopped unless explicitly requested again.', 'ashbi-testimonials' ) ) ) );
		}
		foreach ( $rows as $row ) {
			$state = maybe_unserialize( $row->option_value );
			if ( ! is_array( $state ) || (int) ( $state['user_id'] ?? 0 ) !== (int) $user->ID ) { continue; }
			if ( $erase ) { self::cancel( $state['order_id'], $user->ID, true ); delete_option( $row->option_name ); delete_option( $row->option_name . '_claim' ); }
			else { $out[] = array( 'group_id' => 'ashbte-review-requests', 'group_label' => __( 'Review reminders', 'ashbi-testimonials' ), 'item_id' => $row->option_name, 'data' => array( array( 'name' => __( 'Reminder state and consent', 'ashbi-testimonials' ), 'value' => wp_json_encode( $state ) ) ) ); }
		}
		return array( 'data' => $out, 'done' => count( $rows ) < self::BATCH, 'items_removed' => $erase && (bool) $rows );
	}
}
