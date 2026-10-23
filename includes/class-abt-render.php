<?php
/**
 * Frontend markup: testimonial cards, layouts, rating summaries and structured data.
 *
 * @package Ashbi_Testimonials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABT_Render {

	/** Card parts that the `show` attribute can toggle. */
	const PARTS = array( 'avatar', 'rating', 'headline', 'role', 'company', 'date', 'source', 'product', 'verified', 'location' );

	const DEFAULT_PARTS = 'avatar,rating,headline,role,company,source,verified';

	/**
	 * @var ABT_Render|null
	 */
	private static $instance = null;

	/** Structured data queued for output in the footer, keyed to avoid duplicates. */
	private $schema = array();

	/**
	 * @return ABT_Render
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'wp_footer', array( $this, 'print_schema' ) );
	}

	public function register_assets() {
		wp_register_style( 'abt-frontend', ABT_PLUGIN_URL . 'assets/css/frontend.css', array(), ABT_VERSION );
		wp_register_script( 'abt-frontend', ABT_PLUGIN_URL . 'assets/js/frontend.js', array(), ABT_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_localize_script(
			'abt-frontend',
			'abtFrontend',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'i18n'    => array(
					'more'    => __( 'Read more', 'ashbi-testimonials' ),
					'less'    => __( 'Show less', 'ashbi-testimonials' ),
					'loading' => __( 'Loading…', 'ashbi-testimonials' ),
					'error'   => __( 'Could not load more reviews. Try again.', 'ashbi-testimonials' ),
				),
			)
		);
	}

	public function enqueue() {
		if ( ! wp_style_is( 'abt-frontend', 'registered' ) ) {
			$this->register_assets();
		}
		wp_enqueue_style( 'abt-frontend' );
		wp_enqueue_script( 'abt-frontend' );
	}

	/* ==================================================================
	   Pieces
	   ================================================================== */

	/**
	 * Accessible star rating. Supports fractional averages.
	 *
	 * @param float  $rating Rating 0-5.
	 * @param string $size   sm|md|lg.
	 * @return string
	 */
	public function stars( $rating, $size = 'md' ) {
		$rating = (float) max( 0, min( 5, (float) $rating ) );
		$star   = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2.6l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.5l-5.9 3.1 1.2-6.5L2.5 9.5l6.6-.9z"/></svg>';
		$row    = str_repeat( $star, 5 );
		/* translators: %s: star rating such as 4.5 */
		$label = sprintf( __( 'Rated %s out of 5', 'ashbi-testimonials' ), number_format_i18n( $rating, floor( $rating ) === $rating ? 0 : 1 ) );

		return sprintf(
			'<span class="abt-stars abt-stars--%1$s" role="img" aria-label="%2$s"><span class="abt-stars__base">%3$s</span><span class="abt-stars__fill" style="width:%4$s%%">%3$s</span></span>',
			esc_attr( sanitize_key( $size ) ),
			esc_attr( $label ),
			$row,
			esc_attr( (string) round( $rating / 5 * 100, 2 ) )
		);
	}

	/** Image avatar, or coloured initials when none is set. */
	private function avatar( array $item ) {
		if ( ! empty( $item['avatar'] ) ) {
			return sprintf( '<img class="abt-avatar" src="%s" alt="" width="48" height="48" loading="lazy" decoding="async" />', esc_url( $item['avatar'] ) );
		}
		$words    = preg_split( '/\s+/u', trim( (string) $item['author'] ) );
		$initials = '';
		foreach ( array_slice( array_filter( (array) $words ), 0, 2 ) as $word ) {
			$initial   = mb_substr( $word, 0, 1 );
			$initials .= function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $initial ) : strtoupper( $initial );
		}
		$hue = hexdec( substr( md5( (string) $item['author'] ), 0, 2 ) ) * 360 / 255;
		return sprintf( '<span class="abt-avatar abt-avatar--initials" style="--abt-hue:%d" aria-hidden="true">%s</span>', (int) $hue, esc_html( $initials ?: '?' ) );
	}

	/**
	 * One testimonial card.
	 *
	 * @param array $item  Testimonial from ABT_Data::get().
	 * @param array $opts  parts (array), words (int), heading (h2-h6|p).
	 * @return string
	 */
	public function card( array $item, array $opts = array() ) {
		$parts   = array_flip( $opts['parts'] ?? explode( ',', self::DEFAULT_PARTS ) );
		$words   = (int) ( $opts['words'] ?? 0 );
		$heading = in_array( $opts['heading'] ?? 'h3', array( 'h2', 'h3', 'h4', 'h5', 'h6', 'p' ), true ) ? $opts['heading'] : 'h3';
		$show    = static fn( $part ) => isset( $parts[ $part ] );

		$classes = array( 'abt-card' );
		if ( $item['featured'] ) {
			$classes[] = 'abt-card--featured';
		}

		$html = '<figure class="' . esc_attr( implode( ' ', $classes ) ) . '">';

		if ( $show( 'rating' ) && $item['rating'] ) {
			$html .= $this->stars( $item['rating'], 'sm' );
		}
		if ( $show( 'headline' ) && '' !== $item['headline'] ) {
			$html .= sprintf( '<%1$s class="abt-card__headline">%2$s</%1$s>', $heading, esc_html( $item['headline'] ) );
		}

		$content = (string) $item['content'];
		if ( '' !== trim( $content ) ) {
			$full = wpautop( wp_kses_post( $content ) );
			$html .= '<blockquote class="abt-card__quote">';
			$plain = wp_strip_all_tags( $content );
			if ( $words > 0 && str_word_count( $plain ) > $words ) {
				$html .= '<div class="abt-card__excerpt"><p>' . esc_html( wp_trim_words( $plain, $words, '…' ) ) . '</p></div>';
				$html .= '<div class="abt-card__full" hidden>' . $full . '</div>';
				$html .= '<button type="button" class="abt-card__more" aria-expanded="false">' . esc_html__( 'Read more', 'ashbi-testimonials' ) . '</button>';
			} else {
				$html .= $full;
			}
			$html .= '</blockquote>';
		}

		if ( $show( 'product' ) && $item['product_title'] ) {
			$html .= sprintf(
				'<p class="abt-card__product">%s <a href="%s">%s</a></p>',
				esc_html__( 'Reviewed:', 'ashbi-testimonials' ),
				esc_url( $item['product_url'] ),
				esc_html( $item['product_title'] )
			);
		} elseif ( $show( 'product' ) && '' !== $item['subject'] ) {
			$html .= '<p class="abt-card__product">' . esc_html__( 'Reviewed:', 'ashbi-testimonials' ) . ' ' . esc_html( $item['subject'] ) . '</p>';
		}

		$html .= '<figcaption class="abt-card__author">';
		if ( $show( 'avatar' ) ) {
			$html .= $this->avatar( $item );
		}
		$html .= '<span class="abt-card__who"><cite class="abt-card__name">' . esc_html( $item['author'] ) . '</cite>';
		if ( $show( 'verified' ) && $item['verified'] ) {
			$html .= '<span class="abt-badge-verified"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg>' . esc_html__( 'Verified', 'ashbi-testimonials' ) . '</span>';
		}

		$meta = array();
		if ( $show( 'role' ) && '' !== $item['role'] ) {
			$meta[] = esc_html( $item['role'] );
		}
		if ( $show( 'company' ) && '' !== $item['company'] ) {
			$meta[] = esc_html( $item['company'] );
		}
		if ( $show( 'location' ) && '' !== $item['location'] ) {
			$meta[] = esc_html( $item['location'] );
		}
		if ( $meta ) {
			$html .= '<span class="abt-card__meta">' . implode( ' · ', $meta ) . '</span>';
		}

		$foot = array();
		if ( $show( 'date' ) ) {
			$format = abt_settings()['date_format'] ?: get_option( 'date_format' );
			$foot[] = sprintf( '<time datetime="%s">%s</time>', esc_attr( $item['date'] ), esc_html( wp_date( $format, $item['time'] ) ) );
		}
		if ( $show( 'source' ) && '' !== $item['source'] ) {
			$label = esc_html( sprintf( /* translators: %s: review source such as Google */ __( 'via %s', 'ashbi-testimonials' ), $item['source'] ) );
			$foot[] = $item['source_url'] ? sprintf( '<a href="%s" rel="nofollow noopener" target="_blank">%s</a>', esc_url( $item['source_url'] ), $label ) : $label;
		}
		if ( $foot ) {
			$html .= '<span class="abt-card__foot">' . implode( ' · ', $foot ) . '</span>';
		}
		$html .= '</span></figcaption></figure>';

		/**
		 * Filter the HTML of a single testimonial card.
		 *
		 * @param string $html Card HTML.
		 * @param array  $item Testimonial data.
		 * @param array  $opts Render options.
		 */
		return (string) apply_filters( 'abt_card_html', $html, $item, $opts );
	}

	/**
	 * Rating summary: average, stars, count and optional breakdown bars.
	 *
	 * @param array $stats     From ABT_Data::stats().
	 * @param bool  $breakdown Show per-star bars.
	 * @return string
	 */
	public function summary( array $stats, $breakdown = true ) {
		if ( ! $stats['rated'] ) {
			return '';
		}
		$html  = '<div class="abt-summary">';
		$html .= '<div class="abt-summary__score"><span class="abt-summary__avg">' . esc_html( number_format_i18n( $stats['average'], 1 ) ) . '</span>';
		$html .= $this->stars( $stats['average'], 'md' );
		$html .= '<span class="abt-summary__count">' . esc_html(
			sprintf(
				/* translators: %s: number of reviews */
				_n( 'Based on %s review', 'Based on %s reviews', $stats['rated'], 'ashbi-testimonials' ),
				number_format_i18n( $stats['rated'] )
			)
		) . '</span></div>';

		if ( $breakdown ) {
			$html .= '<ul class="abt-summary__bars">';
			foreach ( $stats['breakdown'] as $star => $n ) {
				$pct   = $stats['rated'] ? round( $n / $stats['rated'] * 100 ) : 0;
				$html .= sprintf(
					'<li><span class="abt-summary__label">%1$s</span><span class="abt-summary__bar" aria-hidden="true"><span style="width:%2$d%%"></span></span><span class="abt-summary__pct">%3$s</span></li>',
					/* translators: %d: number of stars */
					esc_html( sprintf( _n( '%d star', '%d stars', $star, 'ashbi-testimonials' ), $star ) ),
					(int) $pct,
					esc_html( number_format_i18n( $n ) )
				);
			}
			$html .= '</ul>';
		}
		return $html . '</div>';
	}

	/**
	 * Wrap cards in a layout container.
	 *
	 * @param string[] $cards  Card HTML.
	 * @param array    $layout layout, columns, theme, accent, class, label, data (load-more state).
	 * @return string
	 */
	public function layout( array $cards, array $layout ) {
		$type    = $layout['layout'];
		$classes = array( 'abt', 'abt--' . $type, 'abt--theme-' . $layout['theme'] );
		if ( '' !== $layout['class'] ) {
			$classes[] = $layout['class'];
		}
		$style = '--abt-cols:' . (int) $layout['columns'] . ';';
		if ( $layout['accent'] ) {
			$style .= '--abt-accent:' . $layout['accent'] . ';';
		}

		$html = sprintf(
			'<section class="%s" style="%s" aria-label="%s"%s>',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( $style ),
			esc_attr( $layout['label'] ),
			'slider' === $type ? ' data-abt-slider' : ''
		);
		$html .= $layout['before'];

		if ( 'slider' === $type ) {
			$html .= '<div class="abt-slider__track" tabindex="0">' . implode( '', array_map( static fn( $c ) => '<div class="abt-slider__slide">' . $c . '</div>', $cards ) ) . '</div>';
			$html .= '<div class="abt-slider__nav"><button type="button" class="abt-slider__prev" aria-label="' . esc_attr__( 'Previous testimonials', 'ashbi-testimonials' ) . '"><span aria-hidden="true">&larr;</span></button>';
			$html .= '<button type="button" class="abt-slider__next" aria-label="' . esc_attr__( 'Next testimonials', 'ashbi-testimonials' ) . '"><span aria-hidden="true">&rarr;</span></button></div>';
		} else {
			$html .= '<div class="abt__items">' . implode( '', $cards ) . '</div>';
		}

		if ( ! empty( $layout['more'] ) ) {
			$html .= sprintf(
				'<div class="abt__more"><button type="button" class="abt-load-more" data-abt-more="%s">%s</button></div>',
				esc_attr( wp_json_encode( $layout['more'] ) ),
				esc_html__( 'Load more reviews', 'ashbi-testimonials' )
			);
		}

		return $html . '</section>';
	}

	/* ==================================================================
	   Structured data
	   ================================================================== */

	/**
	 * Queue AggregateRating structured data. Only emitted for things Google
	 * accepts review snippets for (products and software), never for the
	 * site's own organisation, which would be self-serving markup.
	 *
	 * @param string $type    Product|SoftwareApplication.
	 * @param string $name    Item name.
	 * @param array  $stats   Rating stats.
	 * @param array  $reviews Up to five testimonials.
	 * @param string $url     Item URL.
	 */
	public function queue_schema( $type, $name, array $stats, array $reviews = array(), $url = '' ) {
		if ( '1' !== abt_settings()['schema'] || ! $stats['rated'] || '' === $name || ! in_array( $type, array( 'Product', 'SoftwareApplication' ), true ) ) {
			return;
		}
		// WooCommerce already prints Product markup on its product pages.
		if ( 'Product' === $type && function_exists( 'is_product' ) && is_product() ) {
			return;
		}

		$data = array(
			'@context'        => 'https://schema.org',
			'@type'           => $type,
			'name'            => $name,
			'aggregateRating' => array(
				'@type'       => 'AggregateRating',
				'ratingValue' => $stats['average'],
				'reviewCount' => $stats['rated'],
				'bestRating'  => 5,
				'worstRating' => 1,
			),
		);
		if ( $url ) {
			$data['url'] = $url;
		}
		if ( 'SoftwareApplication' === $type ) {
			$data['applicationCategory'] = 'BusinessApplication';
		}
		foreach ( array_slice( $reviews, 0, 5 ) as $item ) {
			if ( ! $item['rating'] ) {
				continue;
			}
			$data['review'][] = array(
				'@type'         => 'Review',
				'author'        => array( '@type' => 'Person', 'name' => $item['author'] ),
				'datePublished' => $item['date'],
				'reviewBody'    => wp_strip_all_tags( $item['content'] ),
				'reviewRating'  => array( '@type' => 'Rating', 'ratingValue' => $item['rating'], 'bestRating' => 5 ),
			);
		}

		// One entity per item: the first shortcode describing it on a page wins.
		$key = md5( $url ?: $name );
		if ( ! isset( $this->schema[ $key ] ) ) {
			$this->schema[ $key ] = apply_filters( 'abt_schema', $data );
		}
	}

	public function print_schema() {
		foreach ( $this->schema as $data ) {
			if ( $data ) {
				echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . "</script>\n";
			}
		}
	}
}

