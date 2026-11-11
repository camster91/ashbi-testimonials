<?php
/**
 * One-time migration from the pre-1.2 "abt" storage names to the "ashbte" prefix.
 *
 * Releases before 1.2.0 stored data under a three-letter prefix. This renames the
 * plugin's own rows in place (no content is deleted) and rewrites the plugin's
 * shortcodes and blocks in saved content so existing sites keep working.
 *
 * @package Ashbi_Testimonials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASHBTE_Migration {

	/** Legacy storage prefix used by 1.0.0 – 1.1.2. Only read here, never registered. */
	const LEGACY = 'abt';

	/** Lock option so two PHP workers cannot migrate at the same time. */
	const LOCK = 'ashbte_migration_lock';

	/** Legacy shortcode/block names => current names, applied in saved content. */
	const CONTENT = array(
		'[ashbi_testimonial_form'        => '[ashbte_form',
		'[ashbi_testimonials'            => '[ashbte_testimonials',
		'[ashbi_rating'                  => '[ashbte_rating',
		'<!-- wp:ashbi/testimonials'     => '<!-- wp:ashbte/testimonials',
		'<!-- wp:ashbi/rating'           => '<!-- wp:ashbte/rating',
		'<!-- wp:ashbi/review-form'      => '<!-- wp:ashbte/review-form',
		'<!-- /wp:ashbi/testimonials'    => '<!-- /wp:ashbte/testimonials',
		'<!-- /wp:ashbi/rating'          => '<!-- /wp:ashbte/rating',
		'<!-- /wp:ashbi/review-form'     => '<!-- /wp:ashbte/review-form',
	);

	/** Whether this site still has data stored under the legacy names. */
	public static function needed() {
		global $wpdb;
		$names = array( self::LEGACY . '_plugin_version', self::LEGACY . '_settings' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cheap indexed lookup; runs only until the migration completes.
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name IN (%s, %s)", $names[0], $names[1] ) );
	}

	/** Run once per site when legacy data is present. Safe to call on every load. */
	public static function maybe_run() {
		if ( ! self::needed() ) {
			return false;
		}
		$owner = ( time() + 300 ) . ':' . wp_generate_uuid4();
		if ( ! ASHBTE_Atomic::insert( self::LOCK, $owner ) ) {
			$held = get_option( self::LOCK );
			if ( ! is_string( $held ) || (int) $held >= time() ) {
				return false;
			}
			delete_option( self::LOCK );
			if ( ! ASHBTE_Atomic::insert( self::LOCK, $owner ) ) {
				return false;
			}
		}
		try {
			self::run();
		} finally {
			delete_option( self::LOCK );
		}
		return true;
	}

	private static function run() {
		global $wpdb;
		$old = self::LEGACY;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time rename of plugin-owned rows; caches are flushed below.

		// Testimonials and collections.
		$wpdb->update( $wpdb->posts, array( 'post_type' => ASHBTE_Data::POST_TYPE ), array( 'post_type' => $old . '_testimonial' ) );
		$wpdb->update( $wpdb->term_taxonomy, array( 'taxonomy' => ASHBTE_Data::TAXONOMY ), array( 'taxonomy' => $old . '_collection' ) );

		// Testimonial post meta (_abt_*), including purchase evidence and review form page index.
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = CONCAT(%s, SUBSTRING(meta_key, %d)) WHERE meta_key LIKE %s", '_ashbte_', strlen( '_' . $old . '_' ) + 1, $wpdb->esc_like( '_' . $old . '_' ) . '%' ) );

		// Collection term meta.
		foreach ( array( 'type', 'layout', 'accent', 'fields' ) as $key ) {
			$wpdb->update( $wpdb->termmeta, array( 'meta_key' => 'ashbte_' . $key ), array( 'meta_key' => $old . '_' . $key ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-time rename.
		}

		// Review reminder opt-out preferences.
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->usermeta} SET meta_key = CONCAT(%s, SUBSTRING(meta_key, %d)) WHERE meta_key LIKE %s", '_ashbte_request_optout_', strlen( '_' . $old . '_request_optout_' ) + 1, $wpdb->esc_like( '_' . $old . '_request_optout_' ) . '%' ) );

		// Single options. When a current value already exists (for example after a temporary
		// rollback to 1.1.x re-created empty legacy defaults), the current value wins.
		foreach ( array( 'settings', 'cache_version', 'design_presets' ) as $key ) {
			$legacy  = $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s", $old . '_' . $key ) );
			$current = $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s", 'ashbte_' . $key ) );
			if ( $legacy && $current ) {
				$wpdb->delete( $wpdb->options, array( 'option_id' => (int) $legacy ) );
			} elseif ( $legacy ) {
				$wpdb->update( $wpdb->options, array( 'option_name' => 'ashbte_' . $key ), array( 'option_id' => (int) $legacy ) );
			}
		}

		// Reminder records, their claims and import receipts.
		foreach ( array( 'request_', 'import_' ) as $group ) {
			$wpdb->query( $wpdb->prepare( "UPDATE IGNORE {$wpdb->options} SET option_name = CONCAT(%s, SUBSTRING(option_name, %d)) WHERE option_name LIKE %s", 'ashbte_' . $group, strlen( $old . '_' . $group ) + 1, $wpdb->esc_like( $old . '_' . $group ) . '%' ) );
		}

		// Short-lived data is safe to drop: rating caches, rate limits and submission locks.
		foreach ( array( $old . '_submit_lock_', '_transient_' . $old . '_', '_transient_timeout_' . $old . '_' ) as $prefix ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $prefix ) . '%' ) );
		}

		// Saved shortcodes and blocks in posts, revisions and Elementor data.
		foreach ( self::CONTENT as $from => $to ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s", $from, $to, '%' . $wpdb->esc_like( $from ) . '%' ) );
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_key = %s AND meta_value LIKE %s", $from, $to, '_elementor_data', '%' . $wpdb->esc_like( $from ) . '%' ) );
		}
		$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_elementor_element_cache' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Elementor rebuilds it.

		$wpdb->delete( $wpdb->options, array( 'option_name' => $old . '_plugin_version' ) );

		// phpcs:enable

		wp_cache_flush();

		// Serialized widget settings must be rewritten through PHP to keep string lengths valid.
		foreach ( array( 'widget_text', 'widget_block', 'widget_custom_html' ) as $option ) {
			$value = get_option( $option );
			if ( is_array( $value ) ) {
				$updated = self::replace_deep( $value );
				if ( $updated !== $value ) {
					update_option( $option, $updated );
				}
			}
		}

		// Pending review reminders keep their original due time and arguments.
		$cron = _get_cron_array();
		foreach ( is_array( $cron ) ? $cron : array() as $timestamp => $hooks ) {
			foreach ( $hooks[ $old . '_review_request' ] ?? array() as $event ) {
				$args = (array) ( $event['args'] ?? array() );
				wp_unschedule_event( (int) $timestamp, $old . '_review_request', $args );
				if ( ! wp_next_scheduled( ASHBTE_Requests::HOOK, $args ) ) {
					wp_schedule_single_event( (int) $timestamp, ASHBTE_Requests::HOOK, $args );
				}
			}
		}

		update_option( ASHBTE_CACHE_OPTION, wp_generate_uuid4() );
	}

	/** Apply the content renames to every string inside a widget settings array. */
	private static function replace_deep( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( __CLASS__, 'replace_deep' ), $value );
		}
		return is_string( $value ) ? strtr( $value, self::CONTENT ) : $value;
	}
}
