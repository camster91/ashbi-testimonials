<?php
/** Native editing adapters around the canonical shortcode renderers. @package Ashbi_Testimonials */
if ( ! defined( 'ABSPATH' ) ) { exit; }
class ABT_Editors {
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_settings' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'elementor' ) );
	}
	public static function register() {
		ABT_Render::instance()->register_assets();
		wp_register_script( 'abt-block-editor', ABT_PLUGIN_URL . 'assets/js/blocks.js', array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ), ABT_VERSION, true );
		wp_set_script_translations( 'abt-block-editor', 'ashbi-testimonials', ABT_PLUGIN_DIR . 'languages' );
		foreach ( array( 'testimonials', 'rating', 'review-form' ) as $kind ) {
			$path = ABT_PLUGIN_DIR . 'blocks/' . $kind;
			register_block_type( $path, array( 'render_callback' => static function ( $attrs, $content, $block ) use ( $kind ) {
				if ( 'current' === ( $attrs['product'] ?? '' ) && ! empty( $block->context['postId'] ) ) {
					$attrs['product'] = (string) $block->context['postId'];
				}
				$html = self::render( $kind, $attrs );
				return $html ? '<div ' . get_block_wrapper_attributes() . '>' . $html . '</div>' : '';
			} ) );
		}
	}
	public static function editor_settings() {
		$blocks = array();
		foreach ( array( 'testimonials', 'rating', 'review-form' ) as $kind ) {
			$path = ABT_PLUGIN_DIR . 'blocks/' . $kind . '/block.json';
			$blocks[] = json_decode( file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local bundled metadata only.
		}
		$collections = array();
		if ( current_user_can( abt_capability() ) ) {
			$terms = get_terms( array( 'taxonomy' => ABT_Data::TAXONOMY, 'hide_empty' => false, 'orderby' => 'name' ) );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$collections[] = array(
						'label' => $term->name,
						'value' => (string) $term->term_id,
						'slug'  => $term->slug,
					);
				}
			}
		}
		$presets = array();
		foreach ( ABT_Presets::all() as $key => $preset ) { $name = $preset['name']; unset( $preset['name'] ); $presets[] = array( 'label' => $name, 'value' => $key, 'settings' => $preset ); }
		wp_localize_script( 'abt-block-editor', 'abtBlocks', array( 'blocks' => $blocks, 'collections' => $collections, 'presets' => $presets ) );
	}
	public static function render( $kind, array $attrs ) {
		$attrs = array_map( static fn( $value ) => trim( (string) $value ), array_filter( $attrs, 'is_scalar' ) );
		if ( 'review-form' === $kind && empty( $attrs['collection'] ) ) { return ''; }
		if ( empty( $attrs['collection'] ) && empty( $attrs['product'] ) ) { return ''; }
		if ( 'review-form' === $kind ) { return ABT_Form::instance()->shortcode( $attrs ); }
		if ( 'rating' === $kind ) { return ABT_Shortcode::instance()->rating( $attrs ); }
		return ABT_Shortcode::instance()->testimonials( $attrs );
	}
	public static function elementor( $manager ) {
		if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) { return; }
		require_once ABT_PLUGIN_DIR . 'includes/class-abt-elementor-widget.php';
		$manager->register( new ABT_Elementor_Widget() );
	}
}
