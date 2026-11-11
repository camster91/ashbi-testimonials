<?php
/**
 * Real v1.0.0 data through an in-place update to the ashbte-prefixed release,
 * a binary rollback and a reinstall. Seed runs under v1.0.0 (legacy names).
 */
if ( ! defined( 'ABSPATH' ) || ( '1' !== getenv( 'ASHBTE_DISPOSABLE_TEST' ) && '1' !== getenv( 'ABT_DISPOSABLE_TEST' ) ) ) { exit( 1 ); }
$phase   = getenv( 'ASHBTE_UPGRADE_PHASE' );
$fixture_option = 'ashbte_upgrade_fixture_v1';
$legacy  = class_exists( 'ABT_Data' );
$data_class = $legacy ? 'ABT_Data' : 'ASHBTE_Data';
$taxonomy   = $legacy ? 'abt_collection' : 'ashbte_collection';
$settings_option = $legacy ? 'abt_settings' : 'ashbte_settings';
$fail = static function ( $message ) { throw new RuntimeException( 'Upgrade: ' . $message ); };

// Meta and option names are compared without their storage prefix.
$normalize = static function ( $meta ) {
	$out = array();
	foreach ( (array) $meta as $key => $value ) {
		$out[ preg_replace( '/^(_?)(abt|ashbte)_/', '$1', (string) $key ) ] = $value;
	}
	ksort( $out );
	return $out;
};
$snapshot = static function ( $ids, $collections ) use ( $normalize, $taxonomy, $settings_option, $fail ) {
	$out = array( 'reviews' => array(), 'collections' => array(), 'settings' => get_option( $settings_option ) );
	foreach ( $ids as $id ) {
		$p = get_post( $id );
		if ( ! $p ) { $fail( 'a fixture review was removed' ); }
		$terms = wp_get_object_terms( $id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $terms ) ) { $fail( 'collection assignments are unreadable' ); }
		sort( $terms );
		$out['reviews'][ $id ] = array( 'title' => $p->post_title, 'content' => $p->post_content, 'status' => $p->post_status, 'date' => $p->post_date, 'meta' => $normalize( get_post_meta( $id ) ), 'collections' => $terms, 'revisions' => count( wp_get_post_revisions( $id ) ) );
	}
	foreach ( $collections as $id ) {
		$t = get_term( $id, $taxonomy );
		if ( ! $t instanceof WP_Term ) { $fail( 'a fixture collection was removed' ); }
		$out['collections'][ $id ] = array( 'name' => $t->name, 'slug' => $t->slug, 'meta' => $normalize( get_term_meta( $id ) ) );
	}
	return $out;
};

if ( 'seed' === $phase ) {
	if ( ! $legacy || '1.0.0' !== ABT_VERSION ) { $fail( 'seed must run with the actual v1.0.0 plugin loaded' ); }
	$data = ABT_Data::instance();
	$a = $data->save_collection( array( 'name' => 'Upgrade product reviews', 'type' => 'product', 'layout' => 'list', 'accent' => '#234567' ) );
	$b = $data->save_collection( array( 'name' => 'Upgrade service reviews', 'type' => 'service' ) );
	update_option( 'abt_settings', array( 'accent' => '#234567', 'schema' => '0', 'woo_tab' => '0', 'notify_email' => 'upgrade@example.test', 'date_format' => 'Y-m-d' ), false );
	$ids = array();
	foreach ( array( 'publish', 'pending', 'draft', 'trash' ) as $status ) {
		$id = $data->save( array( 'author' => 'Upgrade ' . $status, 'content' => '<p>Original paragraphs.</p><p>Safe <strong>formatting</strong>.</p>', 'email' => $status . '@example.test', 'rating' => 4, 'featured' => 1, 'verified' => 1, 'company' => 'Fictional Studio', 'date' => '2025-05-01', 'status' => 'trash' === $status ? 'publish' : $status, 'collections' => array( $a['id'], $b['id'] ) ) );
		if ( is_wp_error( $id ) ) { $fail( $id->get_error_message() ); }
		wp_save_post_revision( $id );
		if ( 'trash' === $status ) { wp_trash_post( $id ); }
		$ids[] = $id;
	}
	// Saved content using the legacy shortcodes and blocks.
	$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Upgrade placements', 'post_content' => '[ashbi_testimonials collection="' . $a['id'] . '"] [ashbi_rating collection="' . $a['id'] . '"] [ashbi_testimonial_form collection="' . $a['id'] . '"] <!-- wp:ashbi/testimonials {"collection":"' . $a['id'] . '"} /--> <!-- wp:ashbi/review-form {"collection":"' . $a['id'] . '"} /--> <!-- wp:ashbi-mega-menu/menu /-->' ) );
	update_option( 'widget_text', array( 2 => array( 'title' => 'Reviews', 'text' => '[ashbi_rating collection="' . $a['id'] . '"]' ), '_multiwidget' => 1 ) );
	wp_schedule_single_event( time() + WEEK_IN_SECONDS, 'abt_review_request', array( 123, 45 ) );
	update_option( $fixture_option, array( 'ids' => $ids, 'collections' => array( $a['id'], $b['id'] ), 'page' => $page, 'snapshot' => $snapshot( $ids, array( $a['id'], $b['id'] ) ) ), false );
} else {
	$fixture = get_option( $fixture_option );
	if ( ! is_array( $fixture ) ) { $fail( 'missing fixture' ); }
	if ( 'rollback' === $phase ) {
		// 1.1.x cannot read the migrated names; the rows must still be present for the reinstall.
		global $wpdb;
		$kept = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'ashbte_testimonial' ) );
		if ( count( $fixture['ids'] ) !== $kept ) { $fail( 'rollback lost migrated reviews' ); }
	} else {
		if ( $legacy ) { $fail( 'check must run with the candidate plugin loaded' ); }
		$now = $snapshot( $fixture['ids'], $fixture['collections'] );
		if ( $now !== $fixture['snapshot'] ) { $fail( 'reviews, metadata, revisions, assignments, collections or settings changed' ); }
		$content = get_post_field( 'post_content', $fixture['page'] );
		foreach ( array( '[ashbte_testimonials ', '[ashbte_rating ', '[ashbte_form ', '<!-- wp:ashbte/testimonials ', '<!-- wp:ashbte/review-form ', '<!-- wp:ashbi-mega-menu/menu /-->' ) as $needle ) {
			if ( false === strpos( $content, $needle ) ) { $fail( 'saved content was not migrated: ' . $needle ); }
		}
		if ( preg_match( '/ashbi_(testimonials|rating|testimonial_form)|wp:ashbi\/(testimonials|rating|review-form)/', $content ) ) { $fail( 'legacy placements remain in content' ); }
		$output = do_shortcode( $content );
		if ( false !== strpos( $output, '[ashbte_' ) || false === strpos( $output, 'Upgrade publish' ) ) { $fail( 'migrated shortcodes do not render' ); }
		$widget = get_option( 'widget_text' );
		if ( 0 !== strpos( (string) ( $widget[2]['text'] ?? '' ), '[ashbte_rating ' ) ) { $fail( 'widget shortcode was not migrated' ); }
		if ( ! wp_next_scheduled( 'ashbte_review_request', array( 123, 45 ) ) || wp_next_scheduled( 'abt_review_request', array( 123, 45 ) ) ) { $fail( 'pending reminder was not moved to the new hook' ); }
		foreach ( array( 'abt_settings', 'abt_plugin_version' ) as $legacy_option ) {
			if ( false !== get_option( $legacy_option ) ) { $fail( 'legacy option remains: ' . $legacy_option ); }
		}
		if ( ASHBTE_VERSION !== get_option( ASHBTE_VERSION_OPTION ) ) { $fail( 'version was not recorded' ); }
	}
}
fwrite( STDOUT, 'OK: real v1.0.0 upgrade fixture ' . $phase . ' under plugin ' . ( $legacy ? ABT_VERSION : ASHBTE_VERSION ) . "\n" );
