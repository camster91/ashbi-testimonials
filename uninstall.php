<?php
/**
 * Fired when the plugin is deleted. Removes testimonials, collections and settings.
 *
 * @package Ashbi_Testimonials
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! current_user_can( 'activate_plugins' ) ) {
	return;
}

$abt_cleanup_site = static function () {
	global $wpdb;

	// Posts with their meta, revisions and term relationships.
	$abt_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time uninstall enumeration.
		$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'abt_testimonial' )
	);
	foreach ( $abt_ids as $abt_id ) {
		wp_delete_post( (int) $abt_id, true );
	}

	// Collections. The taxonomy is not registered during uninstall, so remove its rows directly.
	$abt_terms = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time uninstall enumeration.
		$wpdb->prepare( "SELECT term_id, term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", 'abt_collection' )
	);
	foreach ( $abt_terms as $abt_term ) {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup of plugin-owned rows.
		$wpdb->delete( $wpdb->term_relationships, array( 'term_taxonomy_id' => (int) $abt_term->term_taxonomy_id ) );
		$wpdb->delete( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => (int) $abt_term->term_taxonomy_id ) );
		$wpdb->delete( $wpdb->termmeta, array( 'term_id' => (int) $abt_term->term_id ) );
		$wpdb->delete( $wpdb->terms, array( 'term_id' => (int) $abt_term->term_id ) );
		// phpcs:enable
	}

	foreach ( array( 'abt_settings', 'abt_plugin_version', 'abt_cache_version' ) as $abt_key ) {
		delete_option( $abt_key );
	}

	// Cached rating summaries and submission rate limits use hashed keys.
	foreach ( array( '_transient_abt_', '_transient_timeout_abt_' ) as $abt_prefix ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $abt_prefix ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transient keys are unpredictable.
	}
};

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $abt_site_id ) {
		switch_to_blog( $abt_site_id );
		$abt_cleanup_site();
		restore_current_blog();
	}
} else {
	$abt_cleanup_site();
}
