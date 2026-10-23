<?php
/** Free Elementor adapter. Loaded only after Elementor's widget registry is ready. @package Ashbi_Testimonials */
if ( ! defined( 'ABSPATH' ) ) { exit; }
class ABT_Elementor_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'ashbi-testimonials'; }
	public function get_title() { return __( 'Ashbi Reviews', 'ashbi-testimonials' ); }
	public function get_icon() { return 'eicon-testimonial'; }
	public function get_categories() { return array( 'general' ); }
	public function get_style_depends() { return array( 'abt-frontend' ); }
	public function get_script_depends() { return array( 'abt-frontend' ); }
	protected function register_controls() {
		$this->start_controls_section( 'reviews', array( 'label' => __( 'Reviews', 'ashbi-testimonials' ) ) );
		$this->add_control( 'kind', array( 'label' => __( 'Display', 'ashbi-testimonials' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'testimonials', 'options' => array( 'testimonials' => __( 'Testimonials', 'ashbi-testimonials' ), 'rating' => __( 'Rating summary', 'ashbi-testimonials' ), 'review-form' => __( 'Review form', 'ashbi-testimonials' ) ) ) );
		$this->add_control( 'collection', array( 'label' => __( 'Collection slug or ID', 'ashbi-testimonials' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '' ) );
		$this->add_control( 'product', array( 'label' => __( 'Product/page ID (or current)', 'ashbi-testimonials' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '' ) );
		$this->add_control( 'layout', array( 'label' => __( 'Layout', 'ashbi-testimonials' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'grid', 'options' => ABT_Data::layouts(), 'condition' => array( 'kind' => 'testimonials' ) ) );
		$this->add_control( 'columns', array( 'label' => __( 'Columns', 'ashbi-testimonials' ), 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 3, 'min' => 1, 'max' => 4, 'condition' => array( 'kind' => 'testimonials' ) ) );
		$this->add_control( 'limit', array( 'label' => __( 'Reviews per page', 'ashbi-testimonials' ), 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 9, 'min' => 1, 'max' => 100, 'condition' => array( 'kind' => 'testimonials' ) ) );
		$this->add_control( 'summary', array( 'label' => __( 'Rating summary', 'ashbi-testimonials' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => '1', 'default' => '1', 'condition' => array( 'kind' => 'testimonials' ) ) );
		$this->add_control( 'load_more', array( 'label' => __( 'Load more reviews', 'ashbi-testimonials' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => '1', 'default' => '', 'condition' => array( 'kind' => 'testimonials' ) ) );
		$this->add_control( 'theme', array( 'label' => __( 'Theme', 'ashbi-testimonials' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'light', 'options' => array( 'light' => __( 'Light', 'ashbi-testimonials' ), 'dark' => __( 'Dark', 'ashbi-testimonials' ), 'auto' => __( 'Automatic', 'ashbi-testimonials' ) ) ) );
		$this->add_control( 'accent', array( 'label' => __( 'Accent colour', 'ashbi-testimonials' ), 'type' => \Elementor\Controls_Manager::COLOR ) );
		$this->end_controls_section();
	}
	protected function render() {
		$settings = $this->get_settings_for_display();
		$kind = $settings['kind'] ?? 'testimonials';
		foreach ( array( 'summary', 'load_more' ) as $key ) { $settings[ $key ] = '1' === ( $settings[ $key ] ?? '' ) ? '1' : '0'; }
		$html = ABT_Editors::render( $kind, $settings );
		if ( ! $html && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			$html = '<p>' . esc_html__( 'Choose a collection to display its approved reviews.', 'ashbi-testimonials' ) . '</p>';
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Canonical renderers escape all user content.
	}
}
