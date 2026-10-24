<?php
/** Atomic inserts for plugin-owned leases and at-most-once claims.
 * @package Ashbi_Testimonials
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
class ABT_Atomic {
	/** Core add_option() can upsert after a stale notoptions cache; never use it as a mutex. */
	public static function insert( $key, $value ) {
		global $wpdb;
		if ( ! is_string( $key ) || 0 !== strpos( $key, 'abt_' ) || strlen( $key ) > 191 ) { return false; }
		$result = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $key, maybe_serialize( $value ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Unique-index insert without upsert is required for cross-worker mutual exclusion.
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		return 1 === $result;
	}
}
