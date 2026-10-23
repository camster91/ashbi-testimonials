<?php
/**
 * Admin: collection dashboard, testimonial editor, import/export and settings.
 *
 * @package Ashbi_Testimonials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABT_Admin {

	const SLUG = 'ashbi-testimonials';

	/** CSV columns, in export order. */
	const CSV_COLUMNS = array( 'author', 'content', 'rating', 'headline', 'role', 'company', 'location', 'email', 'avatar_url', 'product_id', 'subject', 'source', 'source_url', 'verified', 'featured', 'status', 'date', 'collections' );

	/**
	 * @var ABT_Admin|null
	 */
	private static $instance = null;

	/**
	 * @return ABT_Admin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ABT_PLUGIN_FILE ), array( $this, 'action_links' ) );

		foreach ( array( 'list', 'get', 'save', 'delete', 'bulk', 'collection_save', 'collection_delete', 'search_posts', 'import', 'preview' ) as $action ) {
			add_action( 'wp_ajax_abt_' . $action, array( $this, 'ajax_' . $action ) );
		}
		add_action( 'admin_post_abt_export', array( $this, 'export' ) );
		add_action( 'admin_post_abt_save_settings', array( $this, 'save_settings' ) );
	}

	public function register_menu() {
		$pending = $this->pending_total();
		$badge   = $pending ? ' <span class="awaiting-mod">' . number_format_i18n( $pending ) . '</span>' : '';

		add_menu_page(
			__( 'Ashbi Testimonials', 'ashbi-testimonials' ),
			__( 'Testimonials', 'ashbi-testimonials' ) . $badge,
			abt_capability(),
			self::SLUG,
			array( $this, 'render_page' ),
			'dashicons-format-quote',
			26
		);
	}

	private function pending_total() {
		$counts = wp_count_posts( ABT_Data::POST_TYPE );
		return isset( $counts->pending ) ? (int) $counts->pending : 0;
	}

	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">' . esc_html__( 'Manage', 'ashbi-testimonials' ) . '</a>' );
		return $links;
	}

	public function enqueue( $hook ) {
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'abt-admin', ABT_PLUGIN_URL . 'assets/css/admin.css', array(), ABT_VERSION );
		wp_enqueue_style( 'abt-frontend', ABT_PLUGIN_URL . 'assets/css/frontend.css', array(), ABT_VERSION );
		ABT_Render::instance()->register_assets();
		wp_enqueue_script( 'abt-frontend' );
		wp_enqueue_script( 'abt-admin', ABT_PLUGIN_URL . 'assets/js/admin.js', array( 'wp-i18n', 'abt-frontend' ), ABT_VERSION, true );
		wp_set_script_translations( 'abt-admin', 'ashbi-testimonials', ABT_PLUGIN_DIR . 'languages' );

		$types = array();
		foreach ( ABT_Data::types() as $key => $type ) {
			$types[ $key ] = array( 'label' => $type['label'], 'icon' => $type['icon'] );
		}

		wp_localize_script(
			'abt-admin',
			'abtAdmin',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'abt_admin' ),
				'pageUrl'     => admin_url( 'admin.php?page=' . self::SLUG ),
				'collection'  => $this->current_collection_id(),
				'collections' => array_map(
					static fn( $c ) => array( 'id' => $c['id'], 'name' => $c['name'], 'slug' => $c['slug'] ),
					ABT_Data::instance()->collections()
				),
				'types'       => $types,
				'layouts'     => ABT_Data::layouts(),
				'parts'       => ABT_Render::PARTS,
				'defaultShow' => ABT_Render::DEFAULT_PARTS,
				'csvColumns'  => self::CSV_COLUMNS,
				'woo'         => ABT_WooCommerce::active(),
			)
		);
	}

	/** -1 = all testimonials, 0 = dashboard, otherwise a collection term ID. */
	private function current_collection_id() {
		if ( ! isset( $_GET['collection'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only.
			return 0;
		}
		$ref = sanitize_key( wp_unslash( $_GET['collection'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only.
		if ( 'all' === $ref ) {
			return -1;
		}
		$term = ABT_Data::instance()->find_collection( $ref );
		return $term ? (int) $term->term_id : 0;
	}

	public function render_page() {
		if ( ! current_user_can( abt_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to manage testimonials.', 'ashbi-testimonials' ) );
		}

		$data          = ABT_Data::instance();
		$collection_id = $this->current_collection_id();

		if ( 0 === $collection_id ) {
			$collections = $data->collections();
			$all_stats   = $data->stats( array() );
			$pending     = $this->pending_total();
			$settings    = abt_settings();
			include ABT_PLUGIN_DIR . 'admin/views/dashboard.php';
			return;
		}

		$collection = -1 === $collection_id
			? array( 'id' => -1, 'name' => __( 'All testimonials', 'ashbi-testimonials' ), 'slug' => '', 'type' => 'general', 'layout' => 'grid', 'accent' => '' )
			: $data->collection_to_array( get_term( $collection_id, ABT_Data::TAXONOMY ) );
		include ABT_PLUGIN_DIR . 'admin/views/collection.php';
	}

	/* ==================================================================
	   AJAX
	   ================================================================== */

	private function guard() {
		check_ajax_referer( 'abt_admin', 'nonce' );
		if ( ! current_user_can( abt_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage testimonials.', 'ashbi-testimonials' ) ), 403 );
		}
	}

	/**
	 * Read a POST field. Values are sanitised by the caller or ABT_Data::sanitize().
	 *
	 * @param string $key     Field.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	private function post( $key, $default = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked in guard(); sanitised at use.
		return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $default;
	}

	private function json_field( $key ) {
		$decoded = json_decode( (string) $this->post( $key, '' ), true );
		return is_array( $decoded ) ? $decoded : array();
	}

	public function ajax_list() {
		$this->guard();
		$collection = (int) $this->post( 'collection', 0 );
		$filters    = array(
			'collection' => $collection > 0 ? $collection : 0,
			'status'     => sanitize_key( (string) $this->post( 'status', 'any' ) ) ?: 'any',
			'search'     => sanitize_text_field( (string) $this->post( 'search', '' ) ),
			'rating'     => absint( $this->post( 'rating', 0 ) ),
			'product_id' => absint( $this->post( 'product_id', 0 ) ),
			'subject'    => sanitize_text_field( (string) $this->post( 'subject', '' ) ),
			'orderby'    => in_array( $this->post( 'orderby' ), array( 'date', 'rating', 'featured' ), true ) ? $this->post( 'orderby' ) : 'date',
			'order'      => 'asc' === $this->post( 'order' ) ? 'ASC' : 'DESC',
			'per_page'   => max( 10, min( 100, absint( $this->post( 'per_page', 20 ) ) ) ),
			'page'       => max( 1, absint( $this->post( 'page', 1 ) ) ),
		);
		$result = ABT_Data::instance()->query( $filters );

		$counts = array();
		foreach ( array( 'any', 'publish', 'pending', 'draft', 'trash' ) as $status ) {
			$q                 = new WP_Query( array_merge( ABT_Data::instance()->query_args( array( 'collection' => $filters['collection'], 'status' => $status, 'per_page' => 1 ) ), array( 'fields' => 'ids' ) ) );
			$counts[ $status ] = (int) $q->found_posts;
		}

		$result['counts'] = $counts;
		$result['stats']  = ABT_Data::instance()->stats( array( 'collection' => $filters['collection'] ) );
		wp_send_json_success( $result );
	}

	public function ajax_get() {
		$this->guard();
		$item = ABT_Data::instance()->get( absint( $this->post( 'id', 0 ) ) );
		$item ? wp_send_json_success( $item ) : wp_send_json_error( array( 'message' => __( 'That testimonial no longer exists.', 'ashbi-testimonials' ) ), 404 );
	}

	public function ajax_save() {
		$this->guard();
		$fields = $this->json_field( 'item' );
		$id     = absint( $fields['id'] ?? 0 );
		$saved  = ABT_Data::instance()->save( $fields, $id );
		if ( is_wp_error( $saved ) ) {
			wp_send_json_error( array( 'message' => $saved->get_error_message() ), 400 );
		}
		wp_send_json_success( ABT_Data::instance()->get( $saved ) );
	}

	public function ajax_delete() {
		$this->guard();
		$ids = array_filter( array_map( 'absint', (array) $this->json_field( 'ids' ) ) );
		foreach ( $ids as $id ) {
			if ( ABT_Data::POST_TYPE === get_post_type( $id ) ) {
				wp_trash_post( $id );
			}
		}
		wp_send_json_success( array( 'deleted' => count( $ids ) ) );
	}

	/** Bulk status change, feature toggle or collection assignment. */
	public function ajax_bulk() {
		$this->guard();
		$ids    = array_filter( array_map( 'absint', (array) $this->json_field( 'ids' ) ) );
		$action = sanitize_key( (string) $this->post( 'bulk', '' ) );
		$value  = sanitize_key( (string) $this->post( 'value', '' ) );

		foreach ( $ids as $id ) {
			if ( ABT_Data::POST_TYPE !== get_post_type( $id ) ) {
				continue;
			}
			if ( 'restore' === $action ) {
				wp_untrash_post( $id );
			} elseif ( 'purge' === $action && 'trash' === get_post_status( $id ) ) {
				wp_delete_post( $id, true );
			} elseif ( 'status' === $action && in_array( $value, ABT_Data::STATUSES, true ) ) {
				wp_update_post( array( 'ID' => $id, 'post_status' => $value ) );
			} elseif ( 'featured' === $action ) {
				update_post_meta( $id, ABT_Data::META['featured'], '1' === $value ? 1 : 0 );
			} elseif ( 'add_collection' === $action && absint( $value ) ) {
				wp_set_object_terms( $id, absint( $value ), ABT_Data::TAXONOMY, true );
			} elseif ( 'remove_collection' === $action && absint( $value ) ) {
				wp_remove_object_terms( $id, absint( $value ), ABT_Data::TAXONOMY );
			}
		}
		ABT_Data::instance()->flush();
		wp_send_json_success( array( 'updated' => count( $ids ) ) );
	}

	public function ajax_collection_save() {
		$this->guard();
		$fields = $this->json_field( 'collection' );
		$result = ABT_Data::instance()->save_collection( $fields, absint( $fields['id'] ?? 0 ) );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		$result['url'] = admin_url( 'admin.php?page=' . self::SLUG . '&collection=' . $result['id'] );
		wp_send_json_success( $result );
	}

	public function ajax_collection_delete() {
		$this->guard();
		$result = ABT_Data::instance()->delete_collection( absint( $this->post( 'id', 0 ) ), '1' === $this->post( 'trash_items', '0' ) );
		is_wp_error( $result ) ? wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 ) : wp_send_json_success();
	}

	/** Find products, pages or posts to attach a review to. */
	public function ajax_search_posts() {
		$this->guard();
		$types = ABT_WooCommerce::active() ? array( 'product' ) : array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) ) );
		$types = (array) apply_filters( 'abt_reviewable_post_types', $types );
		$term  = sanitize_text_field( (string) $this->post( 'q', '' ) );

		$args = array(
			'post_type'      => $types,
			'post_status'    => array( 'publish', 'private', 'draft' ),
			'posts_per_page' => 15,
			'no_found_rows'  => true,
		);
		if ( ctype_digit( $term ) ) {
			$args['post__in'] = array( (int) $term );
		} else {
			$args['s'] = $term;
		}

		$out = array();
		foreach ( get_posts( $args ) as $post ) {
			$type  = get_post_type_object( $post->post_type );
			$out[] = array(
				'id'    => $post->ID,
				'title' => get_the_title( $post ) ?: __( '(no title)', 'ashbi-testimonials' ),
				'type'  => $type ? $type->labels->singular_name : $post->post_type,
			);
		}
		wp_send_json_success( $out );
	}

	/** Import one chunk of CSV rows (the browser splits big files). */
	public function ajax_import() {
		$this->guard();
		$rows       = array_slice( $this->json_field( 'rows' ), 0, 250 );
		$collection = absint( $this->post( 'collection', 0 ) );
		$created    = 0;
		$errors     = array();

		wp_defer_term_counting( true );
		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$result = $this->import_row( $row, $collection );
			if ( is_wp_error( $result ) ) {
				$errors[] = array( 'row' => (int) $index, 'message' => $result->get_error_message() );
			} else {
				++$created;
			}
		}
		wp_defer_term_counting( false );

		wp_send_json_success( array( 'created' => $created, 'errors' => $errors ) );
	}

	/** Import a single decoded CSV record, restoring collections by slug. */
	public function import_row( array $row, $collection = 0 ) {
		$row = array_change_key_case( $row, CASE_LOWER );
		// Accept common alternative headings.
		foreach ( array( 'name' => 'author', 'review' => 'content', 'text' => 'content', 'testimonial' => 'content', 'title' => 'headline', 'stars' => 'rating', 'product' => 'product_id', 'sku' => 'subject' ) as $alias => $field ) {
			if ( isset( $row[ $alias ] ) && ! isset( $row[ $field ] ) ) {
				$row[ $field ] = $row[ $alias ];
			}
		}
		if ( isset( $row['status'] ) && 'published' === strtolower( (string) $row['status'] ) ) {
			$row['status'] = 'publish';
		}

		$ids = array();
		if ( ! empty( $row['collections'] ) ) {
			$groups = is_string( $row['collections'] ) ? json_decode( $row['collections'], true ) : $row['collections'];
			if ( ! is_array( $groups ) ) {
				return new WP_Error( 'abt_csv_collections', __( 'The collections column must contain an exported JSON list.', 'ashbi-testimonials' ) );
			}
			foreach ( $groups as $group ) {
				if ( ! is_array( $group ) || empty( $group['slug'] ) || empty( $group['name'] ) || ! is_string( $group['slug'] ) || ! is_string( $group['name'] ) ) {
					return new WP_Error( 'abt_csv_collection', __( 'An exported collection needs a name and slug.', 'ashbi-testimonials' ) );
				}
				$term = get_term_by( 'slug', sanitize_title( (string) $group['slug'] ), ABT_Data::TAXONOMY );
				if ( $term ) {
					$ids[] = (int) $term->term_id;
				} else {
					$saved = ABT_Data::instance()->save_collection( array_filter( $group, 'is_scalar' ) );
					if ( is_wp_error( $saved ) ) {
						return $saved;
					}
					$ids[] = $saved['id'];
				}
			}
		}
		if ( $collection ) {
			$ids[] = absint( $collection );
		}
		$row['collections'] = array_values( array_unique( $ids ) );
		return ABT_Data::instance()->save( $row );
	}

	/** Build an export record with portable collection metadata and safe cells. */
	public function export_row( array $item ) {
		$groups = array();
		foreach ( $item['collections'] as $id ) {
			$term = get_term( $id, ABT_Data::TAXONOMY );
			if ( $term instanceof WP_Term ) {
				$group = ABT_Data::instance()->collection_to_array( $term );
				unset( $group['id'], $group['count'] );
				$groups[] = $group;
			}
		}
		$item['collections'] = wp_json_encode( $groups );
		$row = array();
		foreach ( self::CSV_COLUMNS as $column ) {
			$value = 'avatar_url' === $column ? $item['avatar'] : ( $item[ $column ] ?? '' );
			// Neutralise spreadsheet formulas.
			$row[] = is_string( $value ) && preg_match( '/^[=+\-@]/', $value ) ? "'" . $value : $value;
		}
		return $row;
	}

	/** Render a shortcode for the builder's live preview. */
	public function ajax_preview() {
		$this->guard();
		$atts = array_map( 'sanitize_text_field', array_filter( $this->json_field( 'atts' ), 'is_scalar' ) );
		$atts['schema'] = '0';
		$html = ABT_Shortcode::instance()->testimonials( $atts );
		wp_send_json_success( array( 'html' => $html ?: '<p class="abt-preview-empty">' . esc_html__( 'No published testimonials match these settings yet.', 'ashbi-testimonials' ) . '</p>' ) );
	}

	/* ==================================================================
	   Export and settings
	   ================================================================== */

	public function export() {
		if ( ! current_user_can( abt_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to export testimonials.', 'ashbi-testimonials' ) );
		}
		check_admin_referer( 'abt_export' );
		$collection = isset( $_GET['collection'] ) ? absint( $_GET['collection'] ) : 0;
		$term       = $collection ? get_term( $collection, ABT_Data::TAXONOMY ) : null;
		$name       = $term instanceof WP_Term ? $term->slug : 'all';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=testimonials-' . sanitize_file_name( $name ) . '-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming download.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 BOM so spreadsheets read accents.
		fputcsv( $out, self::CSV_COLUMNS, ',', '"', '' );

		$page = 1;
		do {
			$result = ABT_Data::instance()->query( array( 'collection' => $collection, 'status' => 'any', 'per_page' => 200, 'page' => $page ) );
			foreach ( $result['items'] as $item ) {
				fputcsv( $out, $this->export_row( $item ), ',', '"', '' );
			}
			++$page;
		} while ( $page <= $result['pages'] );

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'ashbi-testimonials' ) );
		}
		check_admin_referer( 'abt_save_settings' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked above.
		$settings = array(
			'schema'         => isset( $_POST['schema'] ) ? '1' : '0',
			'woo_tab'        => isset( $_POST['woo_tab'] ) ? '1' : '0',
			'woo_collection' => isset( $_POST['woo_collection'] ) ? (string) absint( $_POST['woo_collection'] ) : '0',
			'notify_email'   => isset( $_POST['notify_email'] ) ? sanitize_email( wp_unslash( $_POST['notify_email'] ) ) : '',
			'accent'         => isset( $_POST['accent'] ) ? ( sanitize_hex_color( wp_unslash( $_POST['accent'] ) ) ?: '#2457ff' ) : '#2457ff',
			'date_format'    => isset( $_POST['date_format'] ) ? sanitize_text_field( wp_unslash( $_POST['date_format'] ) ) : '',
			'delete_on_uninstall' => isset( $_POST['delete_on_uninstall'] ) && '1' === $_POST['delete_on_uninstall'] ? '1' : '0',
		);
		// phpcs:enable
		update_option( ABT_SETTINGS_OPTION, $settings, false );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&settings-updated=1' ) );
		exit;
	}
}
