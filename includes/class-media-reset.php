<?php

namespace WP_Reset_Taahzino;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Media_Reset {

	const BATCH_SIZE        = 50;
	const AJAX_COUNT        = 'wrt_count_media_by_prefix';
	const AJAX_DELETE       = 'wrt_delete_media_by_prefix';
	const NONCE_ACTION      = 'wrt_media_prefix';

	public function __construct() {
		add_action( 'wp_ajax_' . self::AJAX_COUNT,  array( $this, 'handle_count_ajax' ) );
		add_action( 'wp_ajax_' . self::AJAX_DELETE, array( $this, 'handle_delete_ajax' ) );
	}

	public function handle_count_ajax() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'delete_posts' ) ) {
			wp_send_json_error( array(
				'message' => __( 'You do not have permission to perform this action.', 'wp-reset-taahzino' ),
			), 403 );
		}

		$prefix = $this->get_prefix();
		if ( is_wp_error( $prefix ) ) {
			wp_send_json_error( array( 'message' => $prefix->get_error_message() ), 400 );
		}

		$ids   = self::get_by_prefix( $prefix, -1 );
		$count = count( $ids );

		wp_send_json_success( array( 'count' => $count ) );
	}

	public function handle_delete_ajax() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'delete_posts' ) ) {
			wp_send_json_error( array(
				'message' => __( 'You do not have permission to perform this action.', 'wp-reset-taahzino' ),
			), 403 );
		}

		$prefix = $this->get_prefix();
		if ( is_wp_error( $prefix ) ) {
			wp_send_json_error( array( 'message' => $prefix->get_error_message() ), 400 );
		}

		$ids = self::get_by_prefix( $prefix, self::BATCH_SIZE );

		if ( empty( $ids ) ) {
			wp_send_json_success( array(
				'deleted'   => 0,
				'remaining' => 0,
			) );
		}

		/**
		 * Fires before a batch of media attachments is permanently deleted.
		 *
		 * @param int[]  $ids    Attachment IDs about to be deleted.
		 * @param string $prefix The filename prefix being matched.
		 */
		do_action( 'wp_reset_taahzino_before_delete_media', $ids, $prefix );

		$deleted = 0;

		foreach ( $ids as $attachment_id ) {
			$result = wp_delete_attachment( $attachment_id, true );

			if ( $result ) {
				$deleted++;

				/**
				 * Fires after a single media attachment has been permanently deleted.
				 *
				 * @param int    $attachment_id The deleted attachment ID.
				 * @param string $prefix        The filename prefix being matched.
				 */
				do_action( 'wp_reset_taahzino_media_deleted', $attachment_id, $prefix );
			}
		}

		$remaining_ids = self::get_by_prefix( $prefix, -1 );
		$remaining     = count( $remaining_ids );

		/**
		 * Fires after a batch of media attachments has been permanently deleted.
		 *
		 * @param int    $deleted   Number of attachments deleted in this batch.
		 * @param int    $remaining Number of matching attachments still remaining.
		 * @param string $prefix    The filename prefix being matched.
		 */
		do_action( 'wp_reset_taahzino_after_delete_media', $deleted, $remaining, $prefix );

		wp_send_json_success( array(
			'deleted'   => $deleted,
			'remaining' => $remaining,
		) );
	}

	private function get_prefix() {
		$raw = isset( $_POST['prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['prefix'] ) ) : '';

		if ( strlen( $raw ) < 1 ) {
			return new \WP_Error( 'empty_prefix', __( 'Please enter a filename prefix to search.', 'wp-reset-taahzino' ) );
		}

		return $raw;
	}

	/**
	 * Return attachment IDs whose filename basename starts with $prefix.
	 *
	 * @param string $prefix The filename prefix (case-sensitive, no wildcard needed).
	 * @param int    $limit  Number of results (-1 for all).
	 * @return int[]
	 */
	public static function get_by_prefix( $prefix, $limit = self::BATCH_SIZE ) {
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => '_wp_attached_file',
					'value'   => '/' . $prefix,
					'compare' => 'LIKE',
				),
				array(
					'key'     => '_wp_attached_file',
					'value'   => $prefix,
					'compare' => 'LIKE',
				),
			),
		);

		$raw_ids = get_posts( $args );

		// PHP-side filter: confirm the basename actually starts with the prefix.
		$filtered = array();
		foreach ( $raw_ids as $id ) {
			$file = get_post_meta( $id, '_wp_attached_file', true );
			if ( $file && strpos( basename( $file ), $prefix ) === 0 ) {
				$filtered[] = $id;
				if ( $limit > 0 && count( $filtered ) >= $limit ) {
					break;
				}
			}
		}

		return $filtered;
	}
}
