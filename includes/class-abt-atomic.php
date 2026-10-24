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
	/** Never resurrect an erased or cancelled record after an in-flight operation. */
	public static function replace( $key, array $expected, array $next ) {
		global $wpdb;
		$result = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", maybe_serialize( $next ), $key, maybe_serialize( $expected ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Compare-and-set preserves concurrent suppression/erasure.
		wp_cache_delete( $key, 'options' ); wp_cache_delete( 'notoptions', 'options' );
		return 1 === $result;
	}
}
