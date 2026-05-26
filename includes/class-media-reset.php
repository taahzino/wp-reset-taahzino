<?php

namespace WP_Reset_Taahzino;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Media_Reset {

	const BATCH_SIZE   = 50;
	const AJAX_COUNT   = 'wrt_count_media_by_prefix';
	const AJAX_DELETE  = 'wrt_delete_media_by_prefix';
	const AJAX_ZIP     = 'wrt_create_media_zip';
	const NONCE_ACTION = 'wrt_media_prefix';
	const EXPORTS_DIR  = 'wrt-exports';

	public function __construct() {
		add_action( 'wp_ajax_' . self::AJAX_COUNT,  array( $this, 'handle_count_ajax' ) );
		add_action( 'wp_ajax_' . self::AJAX_DELETE, array( $this, 'handle_delete_ajax' ) );
		add_action( 'wp_ajax_' . self::AJAX_ZIP,    array( $this, 'handle_create_zip_ajax' ) );
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

	public function handle_create_zip_ajax() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'delete_posts' ) ) {
			wp_send_json_error( array(
				'message' => __( 'You do not have permission to perform this action.', 'wp-reset-taahzino' ),
			), 403 );
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_send_json_error( array(
				'message' => __( 'The ZipArchive PHP extension is not available on this server.', 'wp-reset-taahzino' ),
			), 500 );
		}

		$prefix = $this->get_prefix();
		if ( is_wp_error( $prefix ) ) {
			wp_send_json_error( array( 'message' => $prefix->get_error_message() ), 400 );
		}

		$ids = self::get_by_prefix( $prefix, -1 );
		if ( empty( $ids ) ) {
			wp_send_json_error( array(
				'message' => __( 'No matching files found to zip.', 'wp-reset-taahzino' ),
			), 404 );
		}

		$upload_dir  = wp_upload_dir();
		$base_dir    = $upload_dir['basedir'];
		$base_url    = $upload_dir['baseurl'];
		$exports_dir = $base_dir . '/' . self::EXPORTS_DIR;

		if ( ! is_dir( $exports_dir ) ) {
			wp_mkdir_p( $exports_dir );
			// Prevent directory browsing.
			file_put_contents( $exports_dir . '/index.php', '<?php // Silence is golden.' );
		}

		$zip_filename = 'media-' . sanitize_file_name( $prefix ) . '.zip';
		$zip_path     = $exports_dir . '/' . $zip_filename;
		$zip_url      = $base_url . '/' . self::EXPORTS_DIR . '/' . $zip_filename;

		// Remove stale zip for the same prefix.
		if ( file_exists( $zip_path ) ) {
			unlink( $zip_path );
		}

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $zip_path, \ZipArchive::CREATE ) ) {
			wp_send_json_error( array(
				'message' => __( 'Could not create the zip file. Check server write permissions for the uploads directory.', 'wp-reset-taahzino' ),
			), 500 );
		}

		$added = 0;
		foreach ( $ids as $id ) {
			$file      = get_post_meta( $id, '_wp_attached_file', true );
			$full_path = $base_dir . '/' . $file;
			if ( $file && file_exists( $full_path ) ) {
				$zip->addFile( $full_path, basename( $file ) );
				$added++;
			}
		}

		$zip->close();

		if ( 0 === $added ) {
			if ( file_exists( $zip_path ) ) {
				unlink( $zip_path );
			}
			wp_send_json_error( array(
				'message' => __( 'No physical files were found on disk for the matching attachments.', 'wp-reset-taahzino' ),
			), 404 );
		}

		wp_send_json_success( array(
			'url'   => $zip_url,
			'count' => $added,
		) );
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

		// Clean up any zip exported for this prefix before deleting the originals.
		$this->cleanup_zip( $prefix );

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
	 * Delete the zip file previously created for $prefix, if it exists.
	 *
	 * @param string $prefix
	 */
	private function cleanup_zip( $prefix ) {
		$zip_path = $this->get_zip_path( $prefix );
		if ( file_exists( $zip_path ) ) {
			unlink( $zip_path );
		}
	}

	/**
	 * Return the server-side path to the zip file for a given prefix.
	 *
	 * @param string $prefix
	 * @return string
	 */
	private function get_zip_path( $prefix ) {
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/' . self::EXPORTS_DIR . '/media-' . sanitize_file_name( $prefix ) . '.zip';
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
