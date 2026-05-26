<?php

namespace WP_Reset_Taahzino;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Users_Reset {

	const BATCH_SIZE    = 50;
	const AJAX_ACTION   = 'wrt_delete_users';
	const NONCE_ACTION  = 'wrt_delete_users';

	public function __construct() {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_delete_users_ajax' ) );
	}

	public function handle_delete_users_ajax() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'delete_users' ) ) {
			wp_send_json_error( array(
				'message' => __( 'You do not have permission to perform this action.', 'wp-reset-taahzino' ),
			), 403 );
		}

		$role = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : '';

		$valid_roles = wp_roles()->get_names();
		if ( empty( $role ) || ! isset( $valid_roles[ $role ] ) ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid role selected.', 'wp-reset-taahzino' ),
			), 400 );
		}

		$current_user_id = get_current_user_id();

		$users = get_users( array(
			'role'    => $role,
			'number'  => self::BATCH_SIZE,
			'exclude' => array( $current_user_id ),
		) );

		if ( empty( $users ) ) {
			wp_send_json_success( array(
				'deleted'   => 0,
				'remaining' => 0,
			) );
		}

		/**
		 * Fires before a batch of users is deleted.
		 *
		 * @param WP_User[] $users Users about to be deleted.
		 * @param string    $role  The role being purged.
		 */
		do_action( 'wp_reset_taahzino_before_delete_users', $users, $role );

		require_once ABSPATH . 'wp-admin/includes/user.php';

		$deleted = 0;

		foreach ( $users as $user ) {
			$user_id = $user->ID;

			$posts = get_posts( array(
				'author'         => $user_id,
				'post_type'      => get_post_types(),
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'fields'         => 'ids',
			) );

			foreach ( $posts as $post_id ) {
				wp_delete_post( $post_id, true );
			}

			$result = wp_delete_user( $user_id );

			if ( $result ) {
				$deleted++;

				/**
				 * Fires after a single user has been deleted.
				 *
				 * @param int    $user_id The deleted user ID.
				 * @param string $role    The role the user belonged to.
				 */
				do_action( 'wp_reset_taahzino_user_deleted', $user_id, $role );
			}
		}

		$remaining_users = get_users( array(
			'role'        => $role,
			'exclude'     => array( $current_user_id ),
			'count_total' => true,
			'fields'      => 'ID',
		) );
		$remaining = count( $remaining_users );

		/**
		 * Fires after a batch of users has been deleted.
		 *
		 * @param int    $deleted   Number of users deleted in this batch.
		 * @param int    $remaining Number of users still remaining.
		 * @param string $role      The role being purged.
		 */
		do_action( 'wp_reset_taahzino_after_delete_users', $deleted, $remaining, $role );

		wp_send_json_success( array(
			'deleted'   => $deleted,
			'remaining' => $remaining,
		) );
	}
}
