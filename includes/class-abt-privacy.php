<?php
/**
 * Integrations with WordPress's verified personal data requests.
 *
 * @package Ashbi_Testimonials
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABT_Privacy {
	const BATCH_SIZE = 100;
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
		add_action( 'admin_init', array( $this, 'policy' ) );
	}

	public function exporters( $exporters ) {
		$exporters['ashbi-testimonials'] = array(
			'exporter_friendly_name' => __( 'Ashbi Testimonials', 'ashbi-testimonials' ),
			'callback' => array( $this, 'export' ),
		);
		return $exporters;
	}

	public function erasers( $erasers ) {
		$erasers['ashbi-testimonials'] = array(
			'eraser_friendly_name' => __( 'Ashbi Testimonials', 'ashbi-testimonials' ),
			'callback' => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/** Query only this site's reviews, including pending, draft and trash. */
	private function matching_ids( $email, $page = 1 ) {
		if ( ! is_email( $email ) ) {
			return array();
		}
		return get_posts( array(
			'post_type' => ABT_Data::POST_TYPE,
			'post_status' => array_values( get_post_stati() ),
			'fields' => 'ids',
			'posts_per_page' => self::BATCH_SIZE,
			'paged' => max( 1, (int) $page ),
			'orderby' => 'ID',
			'order' => 'ASC',
			'meta_key' => ABT_Data::META['email'],
			'meta_value' => $email,
		) );
	}

	/** WordPress owns request verification and delivery; no public endpoint. */
	public function export( $email_address, $page = 1 ) {
		$ids = $this->matching_ids( $email_address, $page );
		$items = array();
		$labels = array(
			'headline' => __( 'Headline', 'ashbi-testimonials' ),
			'rating' => __( 'Rating', 'ashbi-testimonials' ),
			'role' => __( 'Job title', 'ashbi-testimonials' ),
			'company' => __( 'Company', 'ashbi-testimonials' ),
			'location' => __( 'Location', 'ashbi-testimonials' ),
			'email' => __( 'Email', 'ashbi-testimonials' ),
			'avatar_id' => __( 'Media Library avatar ID', 'ashbi-testimonials' ),
			'avatar_url' => __( 'Avatar URL', 'ashbi-testimonials' ),
			'product_id' => __( 'Associated product or page ID', 'ashbi-testimonials' ),
			'subject' => __( 'Subject', 'ashbi-testimonials' ),
			'source' => __( 'Source', 'ashbi-testimonials' ),
			'source_url' => __( 'Source URL', 'ashbi-testimonials' ),
			'verified' => __( 'Verified flag', 'ashbi-testimonials' ),
			'featured' => __( 'Featured flag', 'ashbi-testimonials' ),
		);
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			$data = array(
				array( 'name' => __( 'Name', 'ashbi-testimonials' ), 'value' => $post->post_title ),
				array( 'name' => __( 'Review', 'ashbi-testimonials' ), 'value' => $post->post_content ),
				array( 'name' => __( 'Status', 'ashbi-testimonials' ), 'value' => $post->post_status ),
				array( 'name' => __( 'Date', 'ashbi-testimonials' ), 'value' => $post->post_date ),
			);
			foreach ( $labels as $field => $label ) {
				$value = get_post_meta( $id, ABT_Data::META[ $field ], true );
				if ( is_scalar( $value ) && '' !== (string) $value ) {
					$data[] = array( 'name' => $label, 'value' => (string) $value );
				}
			}
			// Historical author/text values are personal data too.
			foreach ( wp_get_post_revisions( $id ) as $revision ) {
				$data[] = array(
					'name' => __( 'Review revision', 'ashbi-testimonials' ),
					'value' => $revision->post_date . "\n" . $revision->post_title . "\n" . $revision->post_content,
				);
			}
			$terms = wp_get_object_terms( $id, ABT_Data::TAXONOMY, array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $terms ) && $terms ) {
				$data[] = array( 'name' => __( 'Collections', 'ashbi-testimonials' ), 'value' => implode( ', ', $terms ) );
			}
			$items[] = array(
				'group_id' => 'ashbi-testimonials',
				'group_label' => __( 'Testimonials', 'ashbi-testimonials' ),
				'item_id' => 'abt-testimonial-' . $id,
				'data' => $data,
			);
		}
		return array( 'data' => $items, 'done' => count( $ids ) < self::BATCH_SIZE );
	}

	/** Delete from the first batch each time: deletion shifts later page offsets. */
	public function erase( $email_address, $page = 1 ) {
		$ids = $this->matching_ids( $email_address );
		$result = array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => count( $ids ) < self::BATCH_SIZE );
		$failed = false;
		$media_retained = false;
		foreach ( $ids as $id ) {
			$media_retained = $media_retained || (int) get_post_meta( $id, ABT_Data::META['avatar_id'], true ) > 0;
			if ( wp_delete_post( $id, true ) && ! get_post( $id ) ) {
				$result['items_removed'] = true;
			} else {
				$failed = true;
			}
		}
		if ( $failed ) {
			$result['items_retained'] = true;
			$result['done'] = true;
			$result['messages'][] = __( 'Some matching testimonials could not be deleted. Review retained records and retry this request after resolving the deletion failure.', 'ashbi-testimonials' );
		}
		if ( $media_retained ) {
			$result['items_retained'] = true;
			$result['messages'][] = __( 'Referenced Media Library files were retained because they may be shared elsewhere. Review and remove personal media separately if appropriate.', 'ashbi-testimonials' );
		}
		return $result;
	}

	public function policy() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content( 'Ashbi Testimonials', '<p>' . esc_html__( 'Suggested text: When you submit a review, we store your name, email, review, rating and any optional profile details you provide. Approved reviews and public profile details may be displayed on this site; your email is not displayed by this plugin. Submissions are held for moderation. Temporary hashed connection and retry identifiers limit submissions and prevent duplicate reviews for one hour. Processing locks are normally removed immediately; interrupted requests can leave expired lock records until a retry or explicit plugin cleanup. Reviews remain until a site administrator removes them. You can request an export or erasure using the email associated with your reviews. Review-related media, backups and copies held by external services require separate handling. Administrators should describe their actual retention period and any configured email delivery or external media services.', 'ashbi-testimonials' ) . '</p>' );
		}
	}
}
