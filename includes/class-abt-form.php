<?php
/**
 * Public submission form: [ashbi_testimonial_form collection="store-reviews"].
 * Submissions are always held as pending until approved in the editor.
 *
 * @package Ashbi_Testimonials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABT_Form {

	const MAX_PER_HOUR = 5;
	const FIELD_LIMITS = array( 'author' => 120, 'email' => 190, 'role' => 120, 'company' => 120, 'location' => 120, 'headline' => 160, 'content' => 5000, 'rating' => 1, 'consent' => 1, 'abt_form' => 100, 'abt_target' => 40, 'abt_token' => 128, 'abt_website' => 200 );

	/**
	 * @var ABT_Form|null
	 */
	private static $instance = null;

	/**
	 * @return ABT_Form
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'ashbi_testimonial_form', array( $this, 'shortcode' ) );
		add_action( 'admin_post_abt_submit', array( $this, 'handle' ) );
		add_action( 'admin_post_nopriv_abt_submit', array( $this, 'handle' ) );
	}

	/**
	 * Signed render token, bound to the collection and product. It has no time
	 * expiry because page caches may retain forms indefinitely. It is public
	 * proof of form provenance, not permission to publish or edit a review.
	 */
	private function token( $time, $target ) {
		return $time . '.' . substr( wp_hash( 'abt_form|' . $time . '|' . $target ), 0, 20 );
	}

	/**
	 * @param array $atts collection, product, rating, title, button.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'collection' => '',
				'product'    => '',
				'rating'     => '1',
				'fields'     => 'headline,role,company',
				'title'      => __( 'Leave a review', 'ashbi-testimonials' ),
				'button'     => __( 'Submit review', 'ashbi-testimonials' ),
			),
			$atts,
			'ashbi_testimonial_form'
		);

		$term = ABT_Data::instance()->find_collection( (string) $atts['collection'] );
		if ( ! $term ) {
			return current_user_can( abt_capability() ) ? '<p class="abt-notice">' . esc_html__( 'Ashbi Testimonials: add a valid collection to the form shortcode.', 'ashbi-testimonials' ) . '</p>' : '';
		}

		$product_id = 'current' === $atts['product'] ? (int) get_the_ID() : absint( $atts['product'] );
		$target     = $term->term_id . ':' . $product_id;
		$fields     = array_map( 'trim', explode( ',', (string) $atts['fields'] ) );
		$form_id    = 'abt-form-' . $term->term_id . ( $product_id ? '-' . $product_id : '' );
		ABT_Render::instance()->enqueue();

		$status = isset( $_GET['abt_status'], $_GET['abt_form'] ) && $form_id === sanitize_key( wp_unslash( $_GET['abt_form'] ) ) ? sanitize_key( wp_unslash( $_GET['abt_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only status flag.

		ob_start();
		?>
		<div class="abt abt-form-wrap" id="<?php echo esc_attr( $form_id ); ?>">
			<?php if ( 'thanks' === $status ) : ?>
				<p class="abt-form__notice abt-form__notice--ok" role="status"><?php esc_html_e( 'Thank you! Your review was received and will appear once approved.', 'ashbi-testimonials' ); ?></p>
			<?php elseif ( $status ) : ?>
				<p class="abt-form__notice abt-form__notice--error" role="alert"><?php echo esc_html( $this->message( $status ) ); ?></p>
			<?php endif; ?>
			<form class="abt-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php if ( '' !== $atts['title'] ) : ?>
					<h3 class="abt-form__title"><?php echo esc_html( $atts['title'] ); ?></h3>
				<?php endif; ?>
				<input type="hidden" name="action" value="abt_submit" />
				<input type="hidden" name="abt_target" value="<?php echo esc_attr( $target ); ?>" />
				<input type="hidden" name="abt_form" value="<?php echo esc_attr( $form_id ); ?>" />
				<input type="hidden" name="abt_token" value="<?php echo esc_attr( $this->token( time(), $target ) ); ?>" />
				<div class="abt-form__hp" aria-hidden="true"><label>Website <input type="text" name="abt_website" tabindex="-1" autocomplete="off" /></label></div>

				<?php if ( '0' !== $atts['rating'] ) : ?>
					<fieldset class="abt-form__rating">
						<legend><?php esc_html_e( 'Your rating', 'ashbi-testimonials' ); ?></legend>
						<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
							<input type="radio" id="<?php echo esc_attr( $form_id . '-r' . $i ); ?>" name="rating" value="<?php echo (int) $i; ?>" <?php checked( 5, $i ); ?> />
							<label for="<?php echo esc_attr( $form_id . '-r' . $i ); ?>" title="<?php echo esc_attr( sprintf( /* translators: %d: stars */ _n( '%d star', '%d stars', $i, 'ashbi-testimonials' ), $i ) ); ?>"><span class="screen-reader-text"><?php echo esc_html( sprintf( /* translators: %d: stars */ _n( '%d star', '%d stars', $i, 'ashbi-testimonials' ), $i ) ); ?></span></label>
						<?php endfor; ?>
					</fieldset>
				<?php endif; ?>

				<div class="abt-form__row">
					<p><label><?php esc_html_e( 'Name', 'ashbi-testimonials' ); ?> <span aria-hidden="true">*</span><input type="text" name="author" required maxlength="120" autocomplete="name" /></label></p>
					<p><label><?php esc_html_e( 'Email (not published)', 'ashbi-testimonials' ); ?> <span aria-hidden="true">*</span><input type="email" name="email" required maxlength="190" autocomplete="email" /></label></p>
				</div>
				<?php if ( in_array( 'role', $fields, true ) || in_array( 'company', $fields, true ) ) : ?>
					<div class="abt-form__row">
						<?php if ( in_array( 'role', $fields, true ) ) : ?>
							<p><label><?php esc_html_e( 'Job title', 'ashbi-testimonials' ); ?><input type="text" name="role" maxlength="120" autocomplete="organization-title" /></label></p>
						<?php endif; ?>
						<?php if ( in_array( 'company', $fields, true ) ) : ?>
							<p><label><?php esc_html_e( 'Company', 'ashbi-testimonials' ); ?><input type="text" name="company" maxlength="120" autocomplete="organization" /></label></p>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<?php if ( in_array( 'location', $fields, true ) ) : ?>
					<p><label><?php esc_html_e( 'Location', 'ashbi-testimonials' ); ?><input type="text" name="location" maxlength="120" /></label></p>
				<?php endif; ?>
				<?php if ( in_array( 'headline', $fields, true ) ) : ?>
					<p><label><?php esc_html_e( 'Headline', 'ashbi-testimonials' ); ?><input type="text" name="headline" maxlength="160" /></label></p>
				<?php endif; ?>
				<p><label><?php esc_html_e( 'Your review', 'ashbi-testimonials' ); ?> <span aria-hidden="true">*</span><textarea name="content" rows="5" required maxlength="5000"></textarea></label></p>
				<p class="abt-form__consent"><label><input type="checkbox" name="consent" value="1" required /> <?php esc_html_e( 'I agree that my name and review may be published on this site.', 'ashbi-testimonials' ); ?></label></p>
				<p><button type="submit" class="abt-form__submit"><?php echo esc_html( $atts['button'] ); ?></button></p>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private function message( $code ) {
		$messages = array(
			'invalid' => __( 'Please fill in your name, email and review, and accept the consent box.', 'ashbi-testimonials' ),
			'expired' => __( 'The form expired. Please reload the page and try again.', 'ashbi-testimonials' ),
			'limit'   => __( 'Too many reviews were sent from your connection. Please try again later.', 'ashbi-testimonials' ),
			'busy'    => __( 'Another review is being processed from your connection. Please try again in a moment.', 'ashbi-testimonials' ),
			'error'   => __( 'Your review could not be saved. Please try again.', 'ashbi-testimonials' ),
		);
		return $messages[ $code ] ?? $messages['error'];
	}

	/** Remove only our own lease, including when another worker has replaced an expired lease. */
	private function release( $key, $owner ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, $owner ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic compare-and-delete for a cross-worker lease.
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}

	/** Serialize rate check, exact retries and save across PHP workers using a unique option key. */
	private function persist( array $fields, $ip_key ) {
		$key = 'abt_submit_lock_' . substr( $ip_key, -16 );
		$owner = ( time() + 30 ) . ':' . wp_generate_uuid4();
		$acquired = add_option( $key, $owner, '', false );
		if ( ! $acquired ) {
			$previous = get_option( $key );
			if ( is_string( $previous ) && (int) $previous < time() ) {
				$this->release( $key, $previous );
				$acquired = add_option( $key, $owner, '', false );
			}
		}
		if ( ! $acquired ) {
			return new WP_Error( 'busy' );
		}
		try {
			$retry_key = 'abt_retry_' . wp_hash( $ip_key . wp_json_encode( $fields ) );
			$existing = (int) get_transient( $retry_key );
			if ( $existing && ABT_Data::POST_TYPE === get_post_type( $existing ) ) {
				return array( 'id' => $existing, 'duplicate' => true );
			}
			// Transients may have been cached before obtaining the lease in this worker.
			wp_cache_delete( '_transient_' . $ip_key, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
			$sent = (int) get_transient( $ip_key );
			if ( $sent >= self::MAX_PER_HOUR ) {
				return new WP_Error( 'limit' );
			}
			$id = ABT_Data::instance()->save( $fields );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			set_transient( $ip_key, $sent + 1, HOUR_IN_SECONDS );
			set_transient( $retry_key, $id, HOUR_IN_SECONDS );
			return array( 'id' => $id, 'duplicate' => false );
		} finally {
			$this->release( $key, $owner );
		}
	}

	/** Process a submission and redirect back to the form. */
	public function handle() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Anonymous form verified by a signed target-bound token below.
		$form_id = isset( $_POST['abt_form'] ) && is_string( $_POST['abt_form'] ) ? sanitize_key( wp_unslash( $_POST['abt_form'] ) ) : '';
		$back    = wp_get_referer() ?: home_url( '/' );
		$back    = remove_query_arg( array( 'abt_status', 'abt_form' ), $back );
		$go      = static function ( $status ) use ( $back, $form_id ) {
			wp_safe_redirect( add_query_arg( array( 'abt_status' => $status, 'abt_form' => $form_id ), $back ) . '#' . $form_id );
			exit;
		};

		// Browser maxlength is advisory; validate type and character limits on the server.
		foreach ( self::FIELD_LIMITS as $field => $limit ) {
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}
			$value = $_POST[ $field ];
			if ( ! is_string( $value ) || strlen( $value ) > $limit * 4 || wp_check_invalid_utf8( $value ) !== $value ) {
				$go( 'invalid' );
			}
			$value = wp_unslash( $value );
			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : preg_match_all( '/./us', $value );
			if ( $length > $limit ) {
				$go( 'invalid' );
			}
		}

		// Bots fill the hidden field; pretend success so they learn nothing.
		if ( ! empty( $_POST['abt_website'] ) ) {
			$go( 'thanks' );
		}

		$target = isset( $_POST['abt_target'] ) ? sanitize_text_field( wp_unslash( $_POST['abt_target'] ) ) : '';
		$token  = isset( $_POST['abt_token'] ) ? sanitize_text_field( wp_unslash( $_POST['abt_token'] ) ) : '';
		$time   = (int) strtok( $token, '.' );
		if ( ! preg_match( '/^\d+:\d+$/', $target ) || ! hash_equals( $this->token( $time, $target ), $token ) ) {
			$go( 'expired' );
		}

		$ip_key = 'abt_rate_' . substr( wp_hash( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ), 0, 16 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Hashed only, never stored raw.

		list( $term_id, $product_id ) = array_map( 'intval', explode( ':', $target ) );
		$term = get_term( $term_id, ABT_Data::TAXONOMY );
		if ( ! $term instanceof WP_Term ) {
			$go( 'error' );
		}

		$fields = array();
		foreach ( array( 'author', 'email', 'role', 'company', 'location', 'headline', 'content', 'rating' ) as $key ) {
			$fields[ $key ] = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised by ABT_Data::sanitize().
		}
		if ( '1' !== ( $_POST['consent'] ?? '' ) || ! preg_match( '/^[0-5]?$/', $fields['rating'] ) || '' === trim( (string) $fields['author'] ) || ! is_email( (string) $fields['email'] ) || '' === trim( (string) $fields['content'] ) ) {
			$go( 'invalid' );
		}
		// phpcs:enable

		$fields['content']     = wp_strip_all_tags( (string) $fields['content'] );
		$fields['status']      = 'pending';
		$fields['product_id']  = $product_id;
		$fields['collections'] = array( $term_id );
		$fields['source']      = __( 'Website', 'ashbi-testimonials' );

		$result = $this->persist( $fields, $ip_key );
		if ( is_wp_error( $result ) ) {
			$go( in_array( $result->get_error_code(), array( 'limit', 'busy' ), true ) ? $result->get_error_code() : 'error' );
		}
		if ( $result['duplicate'] ) {
			$go( 'thanks' );
		}
		$id = $result['id'];

		$notify = abt_settings()['notify_email'];
		if ( $notify && is_email( $notify ) ) {
			wp_mail(
				$notify,
				/* translators: %s: collection name */
				sprintf( __( 'New review waiting for approval: %s', 'ashbi-testimonials' ), $term->name ),
				sprintf(
					"%s\n\n%s\n\n%s",
					sanitize_text_field( (string) $fields['author'] ),
					$fields['content'],
					admin_url( 'admin.php?page=ashbi-testimonials&collection=' . $term_id . '&status=pending' )
				)
			);
		}

		do_action( 'abt_submitted', $id, $term_id );
		$go( 'thanks' );
	}
}

