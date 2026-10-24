<?php
/**
 * Plugin Name: Ashbi Testimonials
 * Plugin URI:  https://github.com/camster91/ashbi-testimonials
 * Description: Collect, organise and display testimonials and reviews for your store, products, software or services with a clean editor and simple shortcodes.
 * Version:     1.0.0
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

define( 'ABT_VERSION', '1.0.0' );
define( 'ABT_PLUGIN_FILE', __FILE__ );
define( 'ABT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ABT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ABT_VERSION_OPTION', 'abt_plugin_version' );
define( 'ABT_SETTINGS_OPTION', 'abt_settings' );
define( 'ABT_CACHE_OPTION', 'abt_cache_version' );

require_once ABT_PLUGIN_DIR . 'includes/class-abt-fields.php';
require_once ABT_PLUGIN_DIR . 'includes/class-abt-data.php';
require_once ABT_PLUGIN_DIR . 'includes/class-abt-render.php';
require_once ABT_PLUGIN_DIR . 'includes/class-abt-shortcode.php';
require_once ABT_PLUGIN_DIR . 'includes/class-abt-form.php';
require_once ABT_PLUGIN_DIR . 'includes/class-abt-editors.php';
require_once ABT_PLUGIN_DIR . 'includes/class-abt-presets.php';
require_once ABT_PLUGIN_DIR . 'includes/class-abt-privacy.php';
require_once ABT_PLUGIN_DIR . 'includes/class-abt-admin.php';
require_once ABT_PLUGIN_DIR . 'includes/class-abt-woocommerce.php';
require_once ABT_PLUGIN_DIR . 'includes/class-abt-purchase.php';

/* ==================================================================
   Helpers
   ================================================================== */

/** Capability required to manage testimonials. Editors by default. */
function abt_capability() {
	return (string) apply_filters( 'abt_capability', 'edit_others_posts' );
}

/** Plugin-wide settings merged with defaults. */
function abt_settings() {
	$defaults = array(
		'schema'         => '1',
		'woo_tab'        => '0',
		'woo_collection' => '0',
		'notify_email'   => '',
		'accent'         => '#2457ff',
		'date_format'    => '',
		'delete_on_uninstall' => '0',
	);
	$stored = get_option( ABT_SETTINGS_OPTION, array() );
	return array_merge( $defaults, is_array( $stored ) ? $stored : array() );
}

/* ==================================================================
   Bootstrap
   ================================================================== */

function abt_init() {
	ABT_Data::instance();
	ABT_Render::instance();
	ABT_Shortcode::instance();
	ABT_Form::instance();
	ABT_Editors::init();
	ABT_Presets::init();
	ABT_Purchase::init();
	ABT_Privacy::instance();
	ABT_WooCommerce::instance();
	abt_maybe_upgrade();

	if ( is_admin() ) {
		ABT_Admin::instance();
	}
}
add_action( 'plugins_loaded', 'abt_init' );

/* ==================================================================
   Activation
   ================================================================== */

function abt_activate() {
	ABT_Data::instance()->register();
	add_option( ABT_SETTINGS_OPTION, array(), '', false );
	add_option( ABT_CACHE_OPTION, 1 );
	update_option( ABT_VERSION_OPTION, ABT_VERSION );
}
register_activation_hook( __FILE__, 'abt_activate' );

function abt_deactivate() {
	// Stored testimonials remain available after reactivation.
}
register_deactivation_hook( __FILE__, 'abt_deactivate' );

function abt_maybe_upgrade() {
	if ( ABT_VERSION === get_option( ABT_VERSION_OPTION ) ) {
		return;
	}

	/*
	 * Future migrations should operate on documented meta keys, remain
	 * idempotent and never delete content an administrator created.
	 */

	update_option( ABT_VERSION_OPTION, ABT_VERSION );
}
