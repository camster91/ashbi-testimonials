<?php
/**
 * Fired when the plugin is deleted. Cleanup requires each site's explicit opt-in.
 *
 * @package Ashbi_Testimonials
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! current_user_can( 'activate_plugins' ) ) {
	return;
}

$ashbte_cleanup_site = static function () {
	global $wpdb;
	wp_unschedule_hook( 'ashbte_review_request' );
	$ashbte_settings = get_option( 'ashbte_settings', array() );
	if ( ! is_array( $ashbte_settings ) || '1' !== ( $ashbte_settings['delete_on_uninstall'] ?? '0' ) ) {
		return;
	}

	delete_metadata( 'user', 0, '_ashbte_request_optout_' . get_current_blog_id(), '', true );
	delete_post_meta_by_key( '_ashbte_review_form_collections' );

	// Posts with their meta, revisions and term relationships.
	$ashbte_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time uninstall enumeration.
		$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'ashbte_testimonial' )
	);
	foreach ( $ashbte_ids as $ashbte_id ) {
		wp_delete_post( (int) $ashbte_id, true );
	}

	// Collections. The taxonomy is not registered during uninstall, so remove its rows directly.
	$ashbte_terms = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time uninstall enumeration.
		$wpdb->prepare( "SELECT term_id, term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", 'ashbte_collection' )
	);
	foreach ( $ashbte_terms as $ashbte_term ) {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup of plugin-owned rows.
		$wpdb->delete( $wpdb->term_relationships, array( 'term_taxonomy_id' => (int) $ashbte_term->term_taxonomy_id ) );
		$wpdb->delete( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => (int) $ashbte_term->term_taxonomy_id ) );
		$wpdb->delete( $wpdb->termmeta, array( 'term_id' => (int) $ashbte_term->term_id ) );
		$wpdb->delete( $wpdb->terms, array( 'term_id' => (int) $ashbte_term->term_id ) );
		// phpcs:enable
	}

	foreach ( array( 'ashbte_settings', 'ashbte_plugin_version', 'ashbte_cache_version', 'ashbte_design_presets' ) as $ashbte_key ) {
		delete_option( $ashbte_key );
	}

	// Cached rating summaries and submission rate limits use hashed keys.
	foreach ( array( 'ashbte_request_', 'ashbte_submit_lock_', 'ashbte_import_', '_transient_ashbte_', '_transient_timeout_ashbte_' ) as $ashbte_prefix ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $ashbte_prefix ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transient keys are unpredictable.
	}
};

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $ashbte_site_id ) {
		switch_to_blog( $ashbte_site_id );
		$ashbte_cleanup_site();
		restore_current_blog();
	}
} else {
	$ashbte_cleanup_site();
}
