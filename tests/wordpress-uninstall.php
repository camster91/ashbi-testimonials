<?php
/**
 * Uninstall test. Destructive: only for a disposable site.
 *   ABT_DISPOSABLE_TEST=1 wp eval-file wp-content/plugins/ashbi-testimonials/tests/wordpress-uninstall.php
 *
 * Checks default retention, site-specific cleanup and complete opt-in cleanup.
 *
 * @package Ashbi_Testimonials
 */

if ( ! defined( 'ABSPATH' ) || '1' !== getenv( 'ABT_DISPOSABLE_TEST' ) ) {
	fwrite( STDERR, "Set ABT_DISPOSABLE_TEST=1 and run with wp eval-file on a disposable site.\n" );
	exit( 1 );
}

$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'blog_id' => get_main_site_id() ) );
wp_set_current_user( $admins ? $admins[0]->ID : 1 );
if ( is_multisite() ) {
	grant_super_admin( get_current_user_id() );
	if ( count( get_sites() ) < 2 ) {
		wpmu_create_blog( wp_parse_url( home_url(), PHP_URL_HOST ), '/second/', 'Second', get_current_user_id() );
	}
}

$sites = is_multisite() ? get_sites( array( 'fields' => 'ids' ) ) : array( 0 );

$seed = static function () {
	ABT_Data::instance()->register();
	$col = ABT_Data::instance()->save_collection( array( 'name' => 'Uninstall me', 'type' => 'store' ) );
	ABT_Data::instance()->save( array( 'author' => 'A', 'content' => 'b', 'rating' => 5, 'collections' => array( $col['id'] ) ) );
	ABT_Data::instance()->stats( array( 'collection' => $col['id'] ) );
	update_option( ABT_SETTINGS_OPTION, array( 'schema' => '0' ) );
};
$state = static function () {
	global $wpdb;
	return array(
		'posts'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'abt_testimonial' ) ),
		'meta'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE '\\_abt\\_%'" ),
		'terms'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", 'abt_collection' ) ),
		'options'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'abt\\_%' OR option_name LIKE '\\_transient\\_abt\\_%' OR option_name LIKE '\\_transient\\_timeout\\_abt\\_%'" ),
	);
};

foreach ( $sites as $site ) {
	if ( $site ) {
		switch_to_blog( $site );
	}
	$seed();
	$before = $state();
	if ( ! $before['posts'] || ! $before['terms'] || ! $before['options'] ) {
		throw new RuntimeException( 'FAILED: seeding site ' . $site );
	}
	if ( $site ) {
		restore_current_blog();
	}
}

define( 'WP_UNINSTALL_PLUGIN', 'ashbi-testimonials/ashbi-testimonials.php' );
include ABT_PLUGIN_DIR . 'uninstall.php';

// Existing sites without a deletion preference must retain all plugin data.
foreach ( $sites as $site ) {
	if ( $site ) {
		switch_to_blog( $site );
	}
	$kept = $state();
	if ( ! $kept['posts'] || ! $kept['meta'] || ! $kept['terms'] || ! $kept['options'] ) {
		throw new RuntimeException( 'FAILED: default uninstall removed data on site ' . $site );
	}
	$settings = get_option( ABT_SETTINGS_OPTION, array() );
	$settings['delete_on_uninstall'] = $site === $sites[0] ? '1' : '0';
	update_option( ABT_SETTINGS_OPTION, $settings );
	if ( $site ) {
		restore_current_blog();
	}
}
include ABT_PLUGIN_DIR . 'uninstall.php';

// One site's opt-in must not authorize deleting another site's data.
foreach ( $sites as $site ) {
	if ( $site ) {
		switch_to_blog( $site );
	}
	$actual = $state();
	if ( $site === $sites[0] ? (bool) array_sum( $actual ) : ( ! $actual['posts'] || ! $actual['meta'] || ! $actual['terms'] || ! $actual['options'] ) ) {
		throw new RuntimeException( 'FAILED: site-specific cleanup preference on site ' . $site );
	}
	update_option( ABT_SETTINGS_OPTION, array( 'delete_on_uninstall' => '1' ) );
	if ( $site ) {
		restore_current_blog();
	}
}
include ABT_PLUGIN_DIR . 'uninstall.php';

foreach ( $sites as $site ) {
	if ( $site ) {
		switch_to_blog( $site );
	}
	wp_cache_flush();
	foreach ( $state() as $what => $count ) {
		if ( 0 !== $count ) {
			throw new RuntimeException( "FAILED: {$count} {$what} left on site {$site}" );
		}
	}
	if ( $site ) {
		restore_current_blog();
	}
}

fwrite( STDOUT, 'OK: uninstall retained by default and explicitly removed data on ' . count( $sites ) . " site(s)\n" );
