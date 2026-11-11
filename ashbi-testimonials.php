<?php
/**
 * Plugin Name: Ashbi Testimonials
 * Plugin URI:  https://github.com/camster91/ashbi-testimonials
 * Description: Collect, organise and display testimonials and reviews for your store, products, software or services with a clean editor and simple shortcodes.
 * Version:     1.2.0
 * Author:      Cameron Ashley
 * Author URI:  https://github.com/camster91
 * License:     GPL-2.0-or-later
 * Text Domain: ashbi-testimonials
 * Requires at least: 6.6
 * Requires PHP: 8.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ASHBTE_VERSION', '1.2.0' );
define( 'ASHBTE_PLUGIN_FILE', __FILE__ );
define( 'ASHBTE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ASHBTE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ASHBTE_VERSION_OPTION', 'ashbte_plugin_version' );
define( 'ASHBTE_SETTINGS_OPTION', 'ashbte_settings' );
define( 'ASHBTE_CACHE_OPTION', 'ashbte_cache_version' );

require_once ASHBTE_PLUGIN_DIR . 'includes/class-ashbte-atomic.php';
require_once ASHBTE_PLUGIN_DIR . 'includes/class-ashbte-fields.php';
require_once ASHBTE_PLUGIN_DIR . 'includes/class-ashbte-data.php';
require_once ASHBTE_PLUGIN_DIR . 'includes/class-ashbte-render.php';
require_once ASHBTE_PLUGIN_DIR . 'includes/class-ashbte-shortcode.php';
require_once ASHBTE_PLUGIN_DIR . 'includes/class-ashbte-form.php';
require_once ASHBTE_PLUGIN_DIR . 'includes/class-ashbte-editors.php';
require_once ASHBTE_PLUGIN_DIR . 'includes/class-ashbte-presets.php';
require_once ASHBTE_PLUGIN_DIR . 'includes/class-ashbte-privacy.php';
require_once ASHBTE_PLUGIN_DIR . 'includes/class-ashbte-admin.php';
require_once ASHBTE_PLUGIN_DIR . 'includes/class-ashbte-woocommerce.php';
require_once ASHBTE_PLUGIN_DIR . 'includes/class-ashbte-purchase.php';
require_once ASHBTE_PLUGIN_DIR . 'includes/class-ashbte-requests.php';
require_once ASHBTE_PLUGIN_DIR . 'includes/class-ashbte-migration.php';

/* ==================================================================
   Helpers
   ================================================================== */

/** Capability required to manage testimonials. Editors by default. */
function ashbte_capability() {
	return (string) apply_filters( 'ashbte_capability', 'edit_others_posts' );
}

/** Plugin-wide settings merged with defaults. */
function ashbte_settings() {
	$defaults = array(
		'schema'         => '1',
		'woo_tab'        => '0',
		'review_requests' => '0',
		'woo_collection' => '0',
		'notify_email'   => '',
		'accent'         => '#2457ff',
		'date_format'    => '',
		'delete_on_uninstall' => '0',
	);
	$stored = get_option( ASHBTE_SETTINGS_OPTION, array() );
	return array_merge( $defaults, is_array( $stored ) ? $stored : array() );
}

/* ==================================================================
   Bootstrap
   ================================================================== */

function ashbte_init() {
	// Migrate legacy data before any component reads or writes options.
	ashbte_maybe_upgrade();
	ASHBTE_Data::instance();
	ASHBTE_Render::instance();
	ASHBTE_Shortcode::instance();
	ASHBTE_Form::instance();
	ASHBTE_Editors::init();
	add_action( 'save_post_page', array( ASHBTE_Admin::class, 'index_review_form_page' ), 10, 2 );
	ASHBTE_Presets::init();
	ASHBTE_Purchase::init();
	ASHBTE_Requests::init();
	ASHBTE_Privacy::instance();
	ASHBTE_WooCommerce::instance();

	if ( is_admin() ) {
		ASHBTE_Admin::instance();
	}
}
add_action( 'plugins_loaded', 'ashbte_init' );

/* ==================================================================
   Activation
   ================================================================== */

function ashbte_activate() {
	ASHBTE_Migration::maybe_run();
	ASHBTE_Data::instance()->register();
	add_option( ASHBTE_SETTINGS_OPTION, array(), '', false );
	add_option( ASHBTE_CACHE_OPTION, 1 );
	update_option( ASHBTE_VERSION_OPTION, ASHBTE_VERSION );
}
register_activation_hook( __FILE__, 'ashbte_activate' );

function ashbte_deactivate() {
	// Stored testimonials remain available after reactivation.
}
register_deactivation_hook( __FILE__, 'ashbte_deactivate' );

function ashbte_maybe_upgrade() {
	// Sites upgraded from 1.0.0 – 1.1.2 rename their stored data once.
	ASHBTE_Migration::maybe_run();

	if ( ASHBTE_VERSION === get_option( ASHBTE_VERSION_OPTION ) ) {
		return;
	}

	/*
	 * Future migrations should operate on documented meta keys, remain
	 * idempotent and never delete content an administrator created.
	 */

	update_option( ASHBTE_VERSION_OPTION, ASHBTE_VERSION );
}
