<?php

namespace WP_Reset_Taahzino;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cpt_Items_Reset {

	const BATCH_SIZE   = 50;
	const AJAX_ACTION  = 'wrt_delete_cpt_items';
	const NONCE_ACTION = 'wrt_delete_cpt_items';

	public function __construct() {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax' ) );
	}

	public function handle_ajax() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'delete_posts' ) ) {
			wp_send_json_error( array(
				'message' => __( 'You do not have permission to perform this action.', 'wp-reset-taahzino' ),
			), 403 );
		}

		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';

		$pto = get_post_type_object( $post_type );
		if ( ! $pto || $pto->_builtin ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid or built-in post type.', 'wp-reset-taahzino' ),
			), 400 );
		}

		$posts = get_posts( array(
			'post_type'      => $post_type,
			'posts_per_page' => self::BATCH_SIZE,
			'post_status'    => 'any',
			'fields'         => 'ids',
		) );

		if ( empty( $posts ) ) {
			wp_send_json_success( array(
				'deleted'   => 0,
				'remaining' => 0,
			) );
		}

		/**
		 * Fires before a batch of CPT items is permanently deleted.
		 *
		 * @param int[]  $posts     Post IDs about to be deleted.
		 * @param string $post_type The post type slug.
		 */
		do_action( 'wp_reset_taahzino_before_delete_cpt_items', $posts, $post_type );

		$deleted = 0;

		foreach ( $posts as $post_id ) {
			$result = wp_delete_post( $post_id, true );

			if ( $result ) {
				$deleted++;

				/**
				 * Fires after a single CPT item has been permanently deleted.
				 *
				 * @param int    $post_id   The deleted post ID.
				 * @param string $post_type The post type slug.
				 */
				do_action( 'wp_reset_taahzino_cpt_item_deleted', $post_id, $post_type );
			}
		}

		$remaining_posts = get_posts( array(
			'post_type'      => $post_type,
			'posts_per_page' => 1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		) );
		$remaining = count( $remaining_posts ) > 0 ? $this->count_all( $post_type ) : 0;

		/**
		 * Fires after a batch of CPT items has been permanently deleted.
		 *
		 * @param int    $deleted   Number of items deleted in this batch.
		 * @param int    $remaining Number of items still remaining.
		 * @param string $post_type The post type slug.
		 */
		do_action( 'wp_reset_taahzino_after_delete_cpt_items', $deleted, $remaining, $post_type );

		wp_send_json_success( array(
			'deleted'   => $deleted,
			'remaining' => $remaining,
		) );
	}

	private function count_all( $post_type ) {
		$ids = get_posts( array(
			'post_type'      => $post_type,
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		) );
		return count( $ids );
	}

	public static function get_custom_post_types() {
		return get_post_types( array( '_builtin' => false ), 'objects' );
	}
}
