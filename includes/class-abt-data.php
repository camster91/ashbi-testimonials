<?php
/**
 * Storage: a private post type for testimonials grouped by a collection taxonomy.
 *
 * Posts scale to many thousands of reviews and use WordPress' own indexes,
 * trash and revisions, unlike a single serialised option.
 *
 * @package Ashbi_Testimonials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABT_Data {

	const POST_TYPE = 'abt_testimonial';
	const TAXONOMY  = 'abt_collection';

	/**
	 * Testimonial meta: field => meta key. Every key is always stored so
	 * ordering and filtering never miss posts that lack a value.
	 */
	const META = array(
		'custom_fields' => '_abt_custom_fields',
		'headline'   => '_abt_headline',
		'rating'     => '_abt_rating',
		'role'       => '_abt_role',
		'company'    => '_abt_company',
		'location'   => '_abt_location',
		'email'      => '_abt_email',
		'avatar_id'  => '_abt_avatar_id',
		'avatar_url' => '_abt_avatar_url',
		'product_id' => '_abt_product_id',
		'subject'    => '_abt_subject',
		'origin'     => '_abt_origin',
		'source'     => '_abt_source',
		'source_url' => '_abt_source_url',
		'verified'   => '_abt_verified',
		'featured'   => '_abt_featured',
	);

	const STATUSES = array( 'publish', 'pending', 'draft' );

	/**
	 * @var ABT_Data|null
	 */
	private static $instance = null;

	/**
	 * @return ABT_Data
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'flush' ) );
		add_action( 'deleted_post', array( $this, 'flush' ) );
		add_action( 'trashed_post', array( $this, 'flush' ) );
		add_action( 'untrashed_post', array( $this, 'flush' ) );
		add_action( 'edited_' . self::TAXONOMY, array( $this, 'flush' ) );
		add_action( 'delete_' . self::TAXONOMY, array( $this, 'flush' ) );
		add_filter( 'wp_untrash_post_status', array( $this, 'untrash_status' ), 10, 3 );
	}

	/** Restore testimonials to their previous status instead of core's default draft. */
	public function untrash_status( $new_status, $post_id, $previous_status ) {
		return self::POST_TYPE === get_post_type( $post_id ) && in_array( $previous_status, self::STATUSES, true ) ? $previous_status : $new_status;
	}

	/** Register the post type and collection taxonomy. Both are private to the admin editor. */
	public function register() {
		if ( post_type_exists( self::POST_TYPE ) ) {
			return;
		}

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Testimonials', 'ashbi-testimonials' ),
					'singular_name' => __( 'Testimonial', 'ashbi-testimonials' ),
				),
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'rewrite'         => false,
				'query_var'       => false,
				'supports'        => array( 'title', 'editor', 'revisions' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Collections', 'ashbi-testimonials' ),
					'singular_name' => __( 'Collection', 'ashbi-testimonials' ),
				),
				'public'       => false,
				'show_ui'      => false,
				'show_in_rest' => false,
				'hierarchical' => false,
				'rewrite'      => false,
				'query_var'    => false,
			)
		);
	}

	/** Invalidate cached rating summaries after any change. */
	public function flush() {
		update_option( ABT_CACHE_OPTION, (int) get_option( ABT_CACHE_OPTION, 1 ) + 1 );
	}

	/* ==================================================================
	   Collection types
	   ================================================================== */

	/** Collection types change copy, defaults and structured data. */
	public static function types() {
		return array(
			'general'  => array(
				'label'  => __( 'Testimonials', 'ashbi-testimonials' ),
				'hint'   => __( 'Quotes from clients, partners or customers.', 'ashbi-testimonials' ),
				'icon'   => 'format-quote',
				'schema' => '',
			),
			'store'    => array(
				'label'  => __( 'Store reviews', 'ashbi-testimonials' ),
				'hint'   => __( 'What shoppers say about your business overall.', 'ashbi-testimonials' ),
				'icon'   => 'store',
				'schema' => '',
			),
			'product'  => array(
				'label'  => __( 'Product reviews', 'ashbi-testimonials' ),
				'hint'   => __( 'Reviews attached to one product or thousands.', 'ashbi-testimonials' ),
				'icon'   => 'products',
				'schema' => 'Product',
			),
			'software' => array(
				'label'  => __( 'Software reviews', 'ashbi-testimonials' ),
				'hint'   => __( 'Ratings for apps, SaaS plans or plugins.', 'ashbi-testimonials' ),
				'icon'   => 'desktop',
				'schema' => 'SoftwareApplication',
			),
			'service'  => array(
				'label'  => __( 'Service reviews', 'ashbi-testimonials' ),
				'hint'   => __( 'Feedback on projects, courses or appointments.', 'ashbi-testimonials' ),
				'icon'   => 'businessperson',
				'schema' => '',
			),
		);
	}

	public static function layouts() {
		return array(
			'grid'      => __( 'Grid', 'ashbi-testimonials' ),
			'masonry'   => __( 'Masonry', 'ashbi-testimonials' ),
			'slider'    => __( 'Slider', 'ashbi-testimonials' ),
			'list'      => __( 'List', 'ashbi-testimonials' ),
			'spotlight' => __( 'Spotlight', 'ashbi-testimonials' ),
			'compact'   => __( 'Compact', 'ashbi-testimonials' ),
		);
	}

	/* ==================================================================
	   Collections
	   ================================================================== */

	/**
	 * All collections with display settings and counts.
	 *
	 * @return array[]
	 */
	public function collections() {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$pending = $this->pending_counts();
		$out     = array();
		foreach ( $terms as $term ) {
			$item            = $this->collection_to_array( $term );
			$item['pending'] = $pending[ (int) $term->term_taxonomy_id ] ?? 0;
			$item['stats']   = $this->stats( array( 'collection' => $term->term_id ) );
			$out[]           = $item;
		}
		return $out;
	}

	/**
	 * Resolve a collection by ID or slug.
	 *
	 * @param int|string $ref ID or slug.
	 * @return WP_Term|null
	 */
	public function find_collection( $ref ) {
		if ( is_numeric( $ref ) ) {
			$term = get_term( (int) $ref, self::TAXONOMY );
		} else {
			$term = get_term_by( 'slug', sanitize_title( (string) $ref ), self::TAXONOMY );
		}
		return $term instanceof WP_Term ? $term : null;
	}

	public function collection_to_array( WP_Term $term ) {
		$type = (string) get_term_meta( $term->term_id, 'abt_type', true );
		return array(
			'id'     => (int) $term->term_id,
			'name'   => $term->name,
			'slug'   => $term->slug,
			'type'   => isset( self::types()[ $type ] ) ? $type : 'general',
			'layout' => $this->clean_layout( get_term_meta( $term->term_id, 'abt_layout', true ) ),
			'accent' => (string) ( sanitize_hex_color( (string) get_term_meta( $term->term_id, 'abt_accent', true ) ) ?: '' ),
			'count'  => (int) $term->count,
			'fields' => ABT_Fields::for_collection( $term->term_id ),
		);
	}

	/**
	 * Create or update a collection.
	 *
	 * @param array $data name, slug, type, layout, accent.
	 * @param int   $id   Existing term ID.
	 * @return array|WP_Error
	 */
	public function save_collection( array $data, $id = 0 ) {
		$fields = ABT_Fields::definitions( $data['fields'] ?? ( $id ? ABT_Fields::for_collection( $id ) : array() ) );
		if ( is_wp_error( $fields ) ) { return $fields; }
		$name = sanitize_text_field( (string) ( $data['name'] ?? '' ) );
		if ( '' === $name ) {
			return new WP_Error( 'abt_collection_name', __( 'Give the collection a name.', 'ashbi-testimonials' ) );
		}
		$args = array();
		if ( ! empty( $data['slug'] ) ) {
			$args['slug'] = sanitize_title( (string) $data['slug'] );
		}

		if ( $id ) {
			$args['name'] = $name;
			$result       = wp_update_term( (int) $id, self::TAXONOMY, $args );
		} else {
			$result = wp_insert_term( $name, self::TAXONOMY, $args );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$term_id = (int) $result['term_id'];
		update_term_meta( $term_id, 'abt_fields', $fields );
		$type    = sanitize_key( (string) ( $data['type'] ?? 'general' ) );
		update_term_meta( $term_id, 'abt_type', isset( self::types()[ $type ] ) ? $type : 'general' );
		update_term_meta( $term_id, 'abt_layout', $this->clean_layout( $data['layout'] ?? '' ) );
		update_term_meta( $term_id, 'abt_accent', (string) ( sanitize_hex_color( (string) ( $data['accent'] ?? '' ) ) ?: '' ) );
		$this->flush();

		return $this->collection_to_array( get_term( $term_id, self::TAXONOMY ) );
	}

	/**
	 * Delete a collection. Testimonials are kept unless $trash_items is true.
	 *
	 * @param int  $id          Term ID.
	 * @param bool $trash_items Move the collection's testimonials to the trash.
	 * @return true|WP_Error
	 */
	public function delete_collection( $id, $trash_items = false ) {
		if ( $trash_items ) {
			$ids = get_posts(
				array(
					'post_type'      => self::POST_TYPE,
					'post_status'    => self::STATUSES,
					'fields'         => 'ids',
					'posts_per_page' => -1,
					'no_found_rows'  => true,
					'tax_query'      => array( array( 'taxonomy' => self::TAXONOMY, 'terms' => (int) $id ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				)
			);
			foreach ( $ids as $post_id ) {
				wp_trash_post( $post_id );
			}
		}
		$result = wp_delete_term( (int) $id, self::TAXONOMY );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$this->flush();
		return true;
	}

	private function clean_layout( $layout ) {
		$layout = sanitize_key( (string) $layout );
		return isset( self::layouts()[ $layout ] ) ? $layout : 'grid';
	}

	/** Pending testimonial counts keyed by term_taxonomy_id. */
	private function pending_counts() {
		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Grouped count across all collections; one cheap admin query.
			$wpdb->prepare(
				"SELECT tr.term_taxonomy_id AS tt, COUNT(*) AS n FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
				WHERE p.post_type = %s AND p.post_status = 'pending'
				GROUP BY tr.term_taxonomy_id",
				self::POST_TYPE
			)
		);
		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row->tt ] = (int) $row->n;
		}
		return $out;
	}

	/* ==================================================================
	   Testimonials
	   ================================================================== */

	/**
	 * Normalise submitted testimonial fields.
	 *
	 * @param array $in Raw fields.
	 * @return array
	 */
	public function sanitize( array $in ) {
		$rating = isset( $in['rating'] ) ? (int) $in['rating'] : 0;
		$status = sanitize_key( (string) ( $in['status'] ?? 'publish' ) );
		$date   = sanitize_text_field( (string) ( $in['date'] ?? '' ) );
		$cols   = $in['collections'] ?? array();

		return array(
			'author'      => sanitize_text_field( (string) ( $in['author'] ?? '' ) ),
			'content'     => wp_kses_post( (string) ( $in['content'] ?? '' ) ),
			'headline'    => sanitize_text_field( (string) ( $in['headline'] ?? '' ) ),
			'rating'      => max( 0, min( 5, $rating ) ),
			'role'        => sanitize_text_field( (string) ( $in['role'] ?? '' ) ),
			'company'     => sanitize_text_field( (string) ( $in['company'] ?? '' ) ),
			'location'    => sanitize_text_field( (string) ( $in['location'] ?? '' ) ),
			'email'       => sanitize_email( (string) ( $in['email'] ?? '' ) ),
			'avatar_id'   => absint( $in['avatar_id'] ?? 0 ),
			'avatar_url'  => esc_url_raw( (string) ( $in['avatar_url'] ?? '' ) ),
			'product_id'  => absint( $in['product_id'] ?? 0 ),
			'subject'     => sanitize_text_field( (string) ( $in['subject'] ?? '' ) ),
			'origin'      => in_array( $in['origin'] ?? '', array( 'direct', 'manual', 'imported' ), true ) ? $in['origin'] : 'manual',
			'source'      => sanitize_text_field( (string) ( $in['source'] ?? '' ) ),
			'source_url'  => esc_url_raw( (string) ( $in['source_url'] ?? '' ) ),
			'verified'    => ! empty( $in['verified'] ) && '0' !== (string) $in['verified'] ? 1 : 0,
			'featured'    => ! empty( $in['featured'] ) && '0' !== (string) $in['featured'] ? 1 : 0,
			'status'      => in_array( $status, self::STATUSES, true ) ? $status : 'publish',
			'date'        => preg_match( '/^\d{4}-\d{2}-\d{2}/', $date ) ? substr( $date, 0, 10 ) : '',
			'collections' => array_values( array_filter( array_map( 'absint', is_array( $cols ) ? $cols : explode( ',', (string) $cols ) ) ) ),
		);
	}

	/**
	 * Insert or update a testimonial.
	 *
	 * @param array $raw Raw fields.
	 * @param int   $id  Existing post ID.
	 * @return int|WP_Error Post ID.
	 */
	public function save( array $raw, $id = 0 ) {
		if ( $id && ! array_key_exists( 'origin', $raw ) ) {
			$raw['origin'] = get_post_meta( $id, self::META['origin'], true );
		}
		$custom = ABT_Fields::snapshots( $raw['custom_fields'] ?? ( $id ? get_post_meta( $id, self::META['custom_fields'], true ) ?: array() : array() ) );
		if ( is_wp_error( $custom ) ) { return $custom; }
		$data = $this->sanitize( $raw );
		$data['custom_fields'] = $custom;
		if ( '' === $data['author'] ) {
			return new WP_Error( 'abt_author', __( 'Add the reviewer’s name.', 'ashbi-testimonials' ) );
		}
		if ( '' === trim( wp_strip_all_tags( $data['content'] ) ) && 0 === $data['rating'] ) {
			return new WP_Error( 'abt_content', __( 'Add the testimonial text or a star rating.', 'ashbi-testimonials' ) );
		}
		if ( $id && self::POST_TYPE !== get_post_type( $id ) ) {
			return new WP_Error( 'abt_missing', __( 'That testimonial no longer exists.', 'ashbi-testimonials' ) );
		}

		if ( $data['date'] && ( ! wp_checkdate( (int) substr( $data['date'], 5, 2 ), (int) substr( $data['date'], 8, 2 ), (int) substr( $data['date'], 0, 4 ), $data['date'] ) || $data['date'] > current_time( 'Y-m-d' ) ) ) {
			return new WP_Error( 'abt_date', __( 'Choose a valid review date that is today or earlier.', 'ashbi-testimonials' ) );
		}

		$post = array(
			'post_type'    => self::POST_TYPE,
			'post_title'   => $data['author'],
			'post_content' => $data['content'],
			'post_status'  => $data['status'],
		);
		if ( $id ) {
			$post['ID'] = (int) $id;
		}
		if ( $data['date'] ) {
			$post['post_date']     = $data['date'] . ' 00:00:00';
			$post['post_date_gmt'] = get_gmt_from_date( $post['post_date'] );
			$post['edit_date']     = true;
		}

		$post_id = $id ? wp_update_post( wp_slash( $post ), true ) : wp_insert_post( wp_slash( $post ), true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		foreach ( self::META as $field => $key ) {
			update_post_meta( $post_id, $key, $data[ $field ] );
		}
		wp_set_object_terms( $post_id, $data['collections'], self::TAXONOMY );
		$this->flush();

		return (int) $post_id;
	}

	/**
	 * A testimonial as a plain array for the editor and templates.
	 *
	 * @param int|WP_Post $post Post or ID.
	 * @return array|null
	 */
	public function get( $post ) {
		$post = get_post( $post );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$item = array(
			'id'      => (int) $post->ID,
			'author'  => $post->post_title,
			'content' => $post->post_content,
			'status'  => $post->post_status,
			'date'    => mysql2date( 'Y-m-d', $post->post_date ),
			'time'    => (int) get_post_time( 'U', true, $post ),
		);
		foreach ( self::META as $field => $key ) {
			$item[ $field ] = get_post_meta( $post->ID, $key, true );
		}
		$custom = ABT_Fields::snapshots( $item['custom_fields'] ?: array() );
		$item['custom_fields'] = is_wp_error( $custom ) ? array() : $custom;
		foreach ( array( 'rating', 'avatar_id', 'product_id', 'verified', 'featured' ) as $int_field ) {
			$item[ $int_field ] = (int) $item[ $int_field ];
		}

		$terms               = get_the_terms( $post->ID, self::TAXONOMY );
		$item['collections'] = is_array( $terms ) ? array_map( static fn( $t ) => (int) $t->term_id, $terms ) : array();

		$item['avatar'] = $item['avatar_id'] ? (string) wp_get_attachment_image_url( $item['avatar_id'], 'thumbnail' ) : (string) $item['avatar_url'];

		$item['product_title'] = '';
		$item['product_url']   = '';
		if ( $item['product_id'] && get_post_status( $item['product_id'] ) &&
			( ( is_post_publicly_viewable( $item['product_id'] ) && ! post_password_required( $item['product_id'] ) ) || current_user_can( 'edit_post', $item['product_id'] ) ) ) {
			$item['product_title'] = get_the_title( $item['product_id'] );
			$item['product_url']   = (string) get_permalink( $item['product_id'] );
		}

		return $item;
	}

	/**
	 * Build WP_Query args from plugin filters.
	 *
	 * @param array $args Filters.
	 * @return array
	 */
	public function query_args( array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'collection' => 0,
				'status'     => 'publish',
				'search'     => '',
				'rating'     => 0,
				'min_rating' => 0,
				'product_id' => 0,
				'subject'    => '',
				'featured'   => false,
				'verified'   => false,
				'ids'        => array(),
				'orderby'    => 'date',
				'order'      => 'DESC',
				'per_page'   => 20,
				'page'       => 1,
				'offset'     => 0,
			)
		);

		if ( 'any' === $args['status'] ) {
			$status = self::STATUSES;
		} elseif ( 'trash' === $args['status'] || in_array( $args['status'], self::STATUSES, true ) ) {
			$status = $args['status'];
		} else {
			$status = 'publish';
		}
		$order  = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';

		$q = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => $status,
			'posts_per_page' => max( 1, min( 200, (int) $args['per_page'] ) ),
			'paged'          => max( 1, (int) $args['page'] ),
			'meta_query'     => array( 'relation' => 'AND' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		);
		if ( (int) $args['offset'] > 0 ) {
			$q['offset'] = (int) $args['offset'];
		}

		$collections = array_filter( array_map( 'absint', (array) $args['collection'] ) );
		if ( $collections ) {
			$q['tax_query'] = array( array( 'taxonomy' => self::TAXONOMY, 'terms' => $collections ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}
		if ( '' !== (string) $args['search'] ) {
			$q['s'] = (string) $args['search'];
		}
		if ( ! empty( $args['ids'] ) ) {
			$q['post__in'] = array_map( 'absint', (array) $args['ids'] );
		}
		if ( (int) $args['rating'] > 0 ) {
			$q['meta_query'][] = array( 'key' => self::META['rating'], 'value' => (int) $args['rating'], 'type' => 'NUMERIC' );
		} elseif ( (int) $args['min_rating'] > 0 ) {
			$q['meta_query'][] = array( 'key' => self::META['rating'], 'value' => (int) $args['min_rating'], 'compare' => '>=', 'type' => 'NUMERIC' );
		}
		if ( (int) $args['product_id'] > 0 ) {
			$q['meta_query'][] = array( 'key' => self::META['product_id'], 'value' => (int) $args['product_id'] );
		}
		if ( '' !== (string) $args['subject'] ) {
			$q['meta_query'][] = array( 'key' => self::META['subject'], 'value' => (string) $args['subject'] );
		}
		if ( $args['featured'] ) {
			$q['meta_query'][] = array( 'key' => self::META['featured'], 'value' => '1' );
		}
		if ( $args['verified'] ) {
			$q['meta_query'][] = array( 'key' => self::META['verified'], 'value' => '1' );
		}

		switch ( $args['orderby'] ) {
			case 'rating':
				$q['meta_query']['abt_rating'] = array( 'key' => self::META['rating'], 'type' => 'NUMERIC' );
				$q['orderby']                  = array( 'abt_rating' => $order, 'date' => 'DESC' );
				break;
			case 'featured':
				$q['meta_query']['abt_featured'] = array( 'key' => self::META['featured'], 'type' => 'NUMERIC' );
				$q['orderby']                    = array( 'abt_featured' => 'DESC', 'date' => $order );
				break;
			case 'random':
				$q['orderby'] = 'rand';
				break;
			case 'ids':
				$q['orderby'] = 'post__in';
				break;
			default:
				$q['orderby'] = array( 'date' => $order, 'ID' => $order );
		}

		return $q;
	}

	/**
	 * Query testimonials.
	 *
	 * @param array $args Filters, see query_args().
	 * @return array{items: array[], total: int, pages: int}
	 */
	public function query( array $args ) {
		$query = new WP_Query( $this->query_args( $args ) );
		return array(
			'items' => array_values( array_filter( array_map( array( $this, 'get' ), $query->posts ) ) ),
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * Rating summary for published testimonials. Computed with one grouped
	 * query so it stays fast with tens of thousands of reviews, then cached.
	 *
	 * @param array $args Collection, product, subject, IDs, rating, minimum rating, featured and verified filters.
	 * @return array{count: int, rated: int, average: float, breakdown: array<int,int>}
	 */
	public function stats( array $args ) {
		global $wpdb;

		$collections = array_filter( array_map( 'absint', (array) ( $args['collection'] ?? array() ) ) );
		$product_id  = absint( $args['product_id'] ?? 0 );
		$subject     = (string) ( $args['subject'] ?? '' );

		$rating     = max( 0, (int) ( $args['rating'] ?? 0 ) );
		$min_rating = max( 0, (int) ( $args['min_rating'] ?? 0 ) );
		$featured   = ! empty( $args['featured'] );
		$verified   = ! empty( $args['verified'] );
		$ids        = array_map( 'absint', (array) ( $args['ids'] ?? array() ) );
		sort( $ids );

		$key    = 'abt_stats_' . md5( wp_json_encode( array( 'eligibility-v1', $collections, $product_id, $subject, $rating, $min_rating, $featured, $verified, $ids, get_option( ABT_CACHE_OPTION, 1 ) ) ) );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$joins  = '';
		$params = array( self::META['rating'] );
		if ( $collections ) {
			$tt_ids = array();
			foreach ( $collections as $term_id ) {
				$term = get_term( $term_id, self::TAXONOMY );
				if ( $term instanceof WP_Term ) {
					$tt_ids[] = (int) $term->term_taxonomy_id;
				}
			}
			if ( ! $tt_ids ) {
				$tt_ids = array( 0 );
			}
			$joins .= " INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID AND tr.term_taxonomy_id IN (" . implode( ',', $tt_ids ) . ')';
		}
		if ( $product_id ) {
			$joins   .= " INNER JOIN {$wpdb->postmeta} pp ON pp.post_id = p.ID AND pp.meta_key = %s AND pp.meta_value = %s";
			$params[] = self::META['product_id'];
			$params[] = (string) $product_id;
		}
		if ( '' !== $subject ) {
			$joins   .= " INNER JOIN {$wpdb->postmeta} ps ON ps.post_id = p.ID AND ps.meta_key = %s AND ps.meta_value = %s";
			$params[] = self::META['subject'];
			$params[] = $subject;
		}
		foreach ( array( 'featured' => $featured, 'verified' => $verified ) as $field => $enabled ) {
			if ( $enabled ) {
				$alias = 'p_' . $field;
				$joins .= " INNER JOIN {$wpdb->postmeta} {$alias} ON {$alias}.post_id = p.ID AND {$alias}.meta_key = %s AND {$alias}.meta_value = '1'";
				$params[] = self::META[ $field ];
			}
		}
		foreach ( array( 'origin' => 'po', 'source' => 'psource', 'source_url' => 'purl' ) as $field => $alias ) {
			$joins .= " LEFT JOIN {$wpdb->postmeta} {$alias} ON {$alias}.post_id = p.ID AND {$alias}.meta_key = %s";
			$params[] = self::META[ $field ];
		}
		$params[] = self::POST_TYPE;
		$where = '';
		if ( $rating > 0 ) {
			$where .= ' AND CAST(pm.meta_value AS UNSIGNED) = %d';
			$params[] = $rating;
		} elseif ( $min_rating > 0 ) {
			$where .= ' AND CAST(pm.meta_value AS UNSIGNED) >= %d';
			$params[] = $min_rating;
		}
		if ( $ids ) {
			$where .= ' AND p.ID IN (' . implode( ',', $ids ) . ')';
		}

		// Integer IDs are cast above; every other value is a placeholder.
		$sql  = "SELECT CAST(pm.meta_value AS UNSIGNED) AS rating, COUNT(DISTINCT p.ID) AS n,
			COUNT(DISTINCT CASE WHEN CAST(pm.meta_value AS UNSIGNED) BETWEEN 1 AND 5 AND
			( COALESCE(po.meta_value, '') <> 'direct' OR COALESCE(purl.meta_value, '') <> '' OR
			LOWER(TRIM(COALESCE(psource.meta_value, ''))) NOT IN ('', 'website', 'email', 'survey') ) THEN p.ID END) AS ineligible FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
			{$joins}
			WHERE p.post_type = %s AND p.post_status = 'publish' {$where}
			GROUP BY rating";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Cached in a versioned transient; dynamic parts are cast integers and core table names.

		$breakdown = array( 5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0 );
		$count     = 0;
		$sum       = 0;
		$ineligible = 0;
		foreach ( (array) $rows as $row ) {
			$rating = (int) $row->rating;
			$n      = (int) $row->n;
			$count += $n;
			$ineligible += (int) $row->ineligible;
			if ( $rating >= 1 && $rating <= 5 ) {
				$breakdown[ $rating ] += $n;
				$sum                  += $rating * $n;
			}
		}
		$rated  = array_sum( $breakdown );
		$result = array(
			'count'     => $count,
			'rated'     => $rated,
			'average'   => $rated ? round( $sum / $rated, 2 ) : 0.0,
			'breakdown' => $breakdown,
			'ineligible' => $ineligible,
		);
		set_transient( $key, $result, DAY_IN_SECONDS );
		return $result;
	}
}

