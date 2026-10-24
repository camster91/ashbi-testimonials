<?php
/**
 * Shortcodes and theme helpers.
 *
 *   [ashbi_testimonials collection="store-reviews" layout="slider"]
 *   [ashbi_testimonials product="current" summary="1" load_more="1"]
 *   [ashbi_rating collection="acme-crm"]
 *
 * @package Ashbi_Testimonials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABT_Shortcode {

	/**
	 * @var ABT_Shortcode|null
	 */
	private static $instance = null;

	/**
	 * @return ABT_Shortcode
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'ashbi_testimonials', array( $this, 'testimonials' ) );
		add_shortcode( 'ashbi_rating', array( $this, 'rating' ) );
		add_action( 'wp_ajax_abt_load_more', array( $this, 'ajax_load_more' ) );
		add_action( 'wp_ajax_nopriv_abt_load_more', array( $this, 'ajax_load_more' ) );
	}

	/** Every supported attribute and its default. */
	public static function defaults() {
		return array(
			'preset'      => '',
			'collection'  => '',
			'product'     => '',
			'subject'     => '',
			'ids'         => '',
			'layout'      => '',
			'columns'     => '3',
			'limit'       => '9',
			'orderby'     => 'date',
			'order'       => 'desc',
			'min_rating'  => '0',
			'featured'    => '0',
			'verified'    => '0',
			'show'        => ABT_Render::DEFAULT_PARTS,
			'words'       => '0',
			'summary'     => '0',
			'load_more'   => '0',
			'theme'       => 'light',
			'accent'      => '',
			'heading'     => 'h3',
			'schema'      => '1',
			'schema_name' => '',
			'class'       => '',
			'label'       => '',
		);
	}

	/**
	 * Turn raw attributes into query filters and render options.
	 *
	 * @param array $atts Raw shortcode attributes.
	 * @return array{query: array, render: array, collections: WP_Term[], atts: array}
	 */
	public function parse( $atts ) {
		$atts = ABT_Presets::apply( is_array( $atts ) ? $atts : array() );
		$atts = shortcode_atts( self::defaults(), is_array( $atts ) ? $atts : array(), 'ashbi_testimonials' );
		$atts = array_map( static fn( $v ) => is_scalar( $v ) ? trim( (string) $v ) : '', $atts );
		$data = ABT_Data::instance();

		$collections = array();
		foreach ( array_filter( array_map( 'trim', array_slice( explode( ',', $atts['collection'] ), 0, 20 ) ) ) as $ref ) {
			$term = $data->find_collection( $ref );
			if ( $term ) {
				$collections[] = $term;
			}
		}

		$product_id = 0;
		if ( 'current' === $atts['product'] ) {
			$product_id = (int) get_queried_object_id() ?: (int) get_the_ID();
		} elseif ( is_numeric( $atts['product'] ) ) {
			$product_id = absint( $atts['product'] );
		}
		// Normalise so load-more requests never depend on the page context.
		$atts['product'] = $product_id ? (string) $product_id : '';

		$first  = $collections ? $data->collection_to_array( $collections[0] ) : null;
		$layout = sanitize_key( $atts['layout'] ?: ( $first['layout'] ?? 'grid' ) );
		if ( ! isset( ABT_Data::layouts()[ $layout ] ) ) {
			$layout = 'grid';
		}
		$orderby = in_array( $atts['orderby'], array( 'date', 'rating', 'featured', 'random' ), true ) ? $atts['orderby'] : 'date';
		$ids     = array_filter( array_map( 'absint', array_slice( explode( ',', $atts['ids'] ), 0, 100 ) ) );
		if ( $ids && 'date' === $orderby ) {
			$orderby = 'ids';
		}
		$limit = max( 1, min( 100, (int) $atts['limit'] ) );
		if ( 'spotlight' === $layout && '9' === $atts['limit'] ) {
			$limit = 1;
		}

		$query = array(
			'collection' => array_map( static fn( $t ) => (int) $t->term_id, $collections ),
			'product_id' => $product_id,
			'subject'    => sanitize_text_field( $atts['subject'] ),
			'ids'        => $ids,
			'min_rating' => max( 0, min( 5, (int) $atts['min_rating'] ) ),
			'featured'   => '1' === $atts['featured'],
			'verified'   => '1' === $atts['verified'],
			'orderby'    => $orderby,
			'order'      => 'asc' === strtolower( $atts['order'] ) ? 'ASC' : 'DESC',
			'per_page'   => $limit,
			'status'     => 'publish',
		);

		$parts = array_values( array_intersect( array_map( 'trim', explode( ',', strtolower( $atts['show'] ) ) ), ABT_Render::PARTS ) );
		$accent = (string) ( sanitize_hex_color( $atts['accent'] ) ?: ( $first['accent'] ?? '' ) ?: ( sanitize_hex_color( abt_settings()['accent'] ) ?: '' ) );

		$render = array(
			'layout'  => $layout,
			'columns' => max( 1, min( 4, (int) $atts['columns'] ) ),
			'theme'   => in_array( $atts['theme'], array( 'light', 'dark', 'auto' ), true ) ? $atts['theme'] : 'light',
			'accent'  => $accent,
			'class'   => implode( ' ', array_map( 'sanitize_html_class', array_filter( explode( ' ', $atts['class'] ) ) ) ),
			'label'   => '' !== $atts['label'] ? sanitize_text_field( $atts['label'] ) : ( $first['name'] ?? __( 'Testimonials', 'ashbi-testimonials' ) ),
			'parts'   => $parts,
			'words'   => max( 0, (int) $atts['words'] ),
			'heading' => sanitize_key( $atts['heading'] ),
		);

		return array( 'query' => $query, 'render' => $render, 'collections' => $collections, 'atts' => $atts );
	}

	/**
	 * [ashbi_testimonials]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function testimonials( $atts ) {
		$parsed = $this->parse( $atts );
		$atts   = $parsed['atts'];
		$data   = ABT_Data::instance();
		$render = ABT_Render::instance();

		if ( '' !== $atts['collection'] && ! $parsed['collections'] ) {
			return current_user_can( abt_capability() ) ? '<p class="abt-notice">' . esc_html__( 'Ashbi Testimonials: that collection was not found. Check the shortcode.', 'ashbi-testimonials' ) . '</p>' : '';
		}

		$result = $data->query( $parsed['query'] );
		if ( ! $result['items'] ) {
			return '';
		}

		$render->enqueue();
		$cards = array_map( fn( $item ) => $render->card( $item, $parsed['render'] ), $result['items'] );

		$stats = null;
		$before = '';
		if ( '1' === $atts['summary'] ) {
			$stats  = $data->stats( $parsed['query'] );
			$before = $render->summary( $stats );
		}

		$more = array();
		if ( '1' === $atts['load_more'] && 'random' !== $parsed['query']['orderby'] && $result['pages'] > 1 && ! in_array( $parsed['render']['layout'], array( 'slider', 'spotlight' ), true ) ) {
			$more = array( 'atts' => $atts, 'page' => 2 );
		}

		if ( '0' !== $atts['schema'] && '1' === $atts['summary'] ) {
			$this->schema( $parsed, $stats ?? $data->stats( $parsed['query'] ), $result['items'] );
		}

		return $render->layout( $cards, array_merge( $parsed['render'], array( 'before' => $before, 'more' => $more ) ) );
	}

	/**
	 * [ashbi_rating] — average stars, count and breakdown.
	 *
	 * @param array $atts Shortcode attributes; accepts the same filters plus breakdown="0".
	 * @return string
	 */
	public function rating( $atts ) {
		$atts      = is_array( $atts ) ? $atts : array();
		$breakdown = ! isset( $atts['breakdown'] ) || '0' !== (string) $atts['breakdown'];
		$compact   = isset( $atts['style'] ) && 'inline' === $atts['style'];
		unset( $atts['breakdown'], $atts['style'] );

		$parsed = $this->parse( $atts );
		if ( '' !== $parsed['atts']['collection'] && ! $parsed['collections'] ) {
			return current_user_can( abt_capability() ) ? '<p class="abt-notice">' . esc_html__( 'Ashbi Testimonials: that collection was not found. Check the shortcode.', 'ashbi-testimonials' ) . '</p>' : '';
		}
		$stats  = ABT_Data::instance()->stats( $parsed['query'] );
		if ( ! $stats['rated'] ) {
			return '';
		}
		$render = ABT_Render::instance();
		$render->enqueue();

		if ( '0' !== $parsed['atts']['schema'] ) {
			$this->schema( $parsed, $stats, array() );
		}

		$style = $parsed['render']['accent'] ? ' style="' . esc_attr( '--abt-accent:' . $parsed['render']['accent'] ) . '"' : '';
		if ( $compact ) {
			return sprintf(
				'<span class="abt abt-inline-rating"%s>%s <strong>%s</strong> <span>(%s)</span></span>',
				$style,
				$render->stars( $stats['average'], 'sm' ),
				esc_html( number_format_i18n( $stats['average'], 1 ) ),
				esc_html( number_format_i18n( $stats['rated'] ) )
			);
		}
		return '<div class="abt abt--theme-' . esc_attr( $parsed['render']['theme'] ) . '"' . $style . '>' . $render->summary( $stats, $breakdown ) . '</div>';
	}

	/** Queue structured data for products and software only. */
	private function schema( array $parsed, array $stats, array $items ) {
		if ( ( $stats['ineligible'] ?? $stats['rated'] ) || $parsed['query']['ids'] || $parsed['query']['subject'] || $parsed['query']['min_rating'] || $parsed['query']['featured'] || $parsed['query']['verified'] || 'random' === $parsed['query']['orderby'] ) {
			return;
		}
		$first = $parsed['collections'] ? ABT_Data::instance()->collection_to_array( $parsed['collections'][0] ) : null;
		$type  = ABT_Data::types()[ $first['type'] ?? 'general' ]['schema'];
		$name  = $parsed['atts']['schema_name'];
		$url   = '';

		if ( $parsed['query']['product_id'] ) {
			// Structured data is public even when an editor is previewing a page.
			if ( ! is_post_publicly_viewable( $parsed['query']['product_id'] ) || post_password_required( $parsed['query']['product_id'] ) ) {
				return;
			}
			$type = $type ?: 'Product';
			$name = $name ?: get_the_title( $parsed['query']['product_id'] );
			$url  = (string) get_permalink( $parsed['query']['product_id'] );
		} elseif ( 'Product' === $type || count( $parsed['collections'] ) > 1 ) {
			// A collection spanning many products is not one product.
			return;
		} elseif ( '' === $name && $first ) {
			$name = $first['name'];
		}

		if ( $type ) {
			ABT_Render::instance()->queue_schema( $type, sanitize_text_field( (string) $name ), $stats, $items, $url );
		}
	}

	/**
	 * Next page of cards for the Load more button. Read-only public data, so no
	 * nonce: a nonce would break on cached pages. Status is always forced to
	 * published and private fields such as email are never rendered.
	 */
	/** Validate a public request without allowing expensive unbounded offsets or random pagination. */
	public function public_page( $raw, $page ) {
		$error = new WP_Error( 'abt_page', __( 'This review page request is invalid or exceeds the public browsing limit.', 'ashbi-testimonials' ) );
		if ( ! is_string( $raw ) || strlen( $raw ) > 8192 || ! is_string( $page ) || ! preg_match( '/^[0-9]{1,4}$/D', $page ) || (int) $page < 2 || (int) $page > 1000 ) { return $error; }
		$atts = json_decode( wp_unslash( $raw ), true );
		if ( ! is_array( $atts ) || ! preg_match( '/^\s*\{/', $raw ) || array_diff( array_keys( $atts ), array_keys( self::defaults() ) ) ) { return $error; }
		foreach ( $atts as $value ) { if ( ! is_scalar( $value ) || strlen( (string) $value ) > 2000 ) { return $error; } }
		if ( count( explode( ',', (string) ( $atts['ids'] ?? '' ) ) ) > 100 || count( explode( ',', (string) ( $atts['collection'] ?? '' ) ) ) > 20 || 'random' === ( $atts['orderby'] ?? '' ) || 'current' === ( $atts['product'] ?? '' ) ) { return $error; }
		$parsed = $this->parse( $atts );
		if ( (int) $page * $parsed['query']['per_page'] > 10000 ) { return $error; }
		$parsed['query']['page'] = (int) $page;
		return $parsed;
	}

	public function ajax_load_more() {
		$parsed = $this->public_page( $_POST['atts'] ?? null, $_POST['page'] ?? '2' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- Read-only published endpoint; strict validation below before any query.
		if ( is_wp_error( $parsed ) ) { wp_send_json_error( array( 'message' => $parsed->get_error_message() ), 400 ); }
		$page = $parsed['query']['page'];
		$result = ABT_Data::instance()->query( $parsed['query'] );
		$render = ABT_Render::instance();

		wp_send_json_success(
			array(
				'html'    => implode( '', array_map( fn( $item ) => $render->card( $item, $parsed['render'] ), $result['items'] ) ),
				'hasMore' => $page < min( $result['pages'], 1000, (int) floor( 10000 / $parsed['query']['per_page'] ) ),
				'page'    => $page + 1,
			)
		);
	}
}

/**
 * Theme helper: return testimonials HTML using shortcode attributes.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function abt_get_testimonials( $atts = array() ) {
	return ABT_Shortcode::instance()->testimonials( $atts );
}

/**
 * Theme helper: echo testimonials.
 *
 * @param array $atts Shortcode attributes.
 */
function abt_render_testimonials( $atts = array() ) {
	echo abt_get_testimonials( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

