<?php
/**
 * Optional WooCommerce product tab. Off by default; nothing loads without WooCommerce.
 *
 * @package Ashbi_Testimonials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABT_WooCommerce {

	/**
	 * @var ABT_WooCommerce|null
	 */
	private static $instance = null;

	/**
	 * @return ABT_WooCommerce
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		if ( class_exists( 'WooCommerce' ) && '1' === abt_settings()['woo_tab'] ) {
			add_filter( 'woocommerce_product_tabs', array( $this, 'tab' ), 25 );
		}
	}

	public static function active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * @param array $tabs Product tabs.
	 * @return array
	 */
	public function tab( $tabs ) {
		$product_id = (int) get_the_ID();
		$stats      = ABT_Data::instance()->stats( array( 'product_id' => $product_id ) );
		$form       = (int) abt_settings()['woo_collection'];
		if ( ! $stats['count'] && ! $form ) {
			return $tabs;
		}

		$tabs['abt_testimonials'] = array(
			/* translators: %s: number of reviews */
			'title'    => $stats['count'] ? sprintf( __( 'Testimonials (%s)', 'ashbi-testimonials' ), number_format_i18n( $stats['count'] ) ) : __( 'Testimonials', 'ashbi-testimonials' ),
			'priority' => 35,
			'callback' => array( $this, 'tab_content' ),
		);
		return $tabs;
	}

	public function tab_content() {
		$product_id = (int) get_the_ID();
		echo do_shortcode( '[ashbi_testimonials product="' . $product_id . '" layout="list" columns="1" summary="1" load_more="1" limit="5" show="avatar,rating,headline,date,verified,source"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output is escaped internally.

		$form = ABT_Data::instance()->find_collection( (int) abt_settings()['woo_collection'] );
		if ( $form ) {
			echo do_shortcode( '[ashbi_testimonial_form collection="' . (int) $form->term_id . '" product="' . $product_id . '"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output is escaped internally.
		}
	}
}
