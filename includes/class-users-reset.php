<?php

namespace WP_Reset_Taahzino;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Users_Reset {

	const BATCH_SIZE   = 50;
	const AJAX_ACTION  = 'wrt_delete_users';
	const NONCE_ACTION = 'wrt_delete_users';

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

		$role        = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : '';
		$reassign_id = isset( $_POST['reassign_to'] ) ? absint( $_POST['reassign_to'] ) : 0;

		$valid_roles = wp_roles()->get_names();
		if ( empty( $role ) || ! isset( $valid_roles[ $role ] ) ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid role selected.', 'wp-reset-taahzino' ),
			), 400 );
		}

		if ( ! $reassign_id || ! get_userdata( $reassign_id ) ) {
			wp_send_json_error( array(
				'message' => __( 'Please select a valid administrator to reassign content to.', 'wp-reset-taahzino' ),
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
		 * @param WP_User[] $users       Users about to be deleted.
		 * @param string    $role        The role being purged.
		 * @param int       $reassign_id User ID to reassign content to.
		 */
		do_action( 'wp_reset_taahzino_before_delete_users', $users, $role, $reassign_id );

		require_once ABSPATH . 'wp-admin/includes/user.php';

		$deleted = 0;

		foreach ( $users as $user ) {
			$user_id = $user->ID;

			// Reassign all content to the chosen administrator before deleting.
			$result = wp_delete_user( $user_id, $reassign_id );

			if ( $result ) {
				$deleted++;

				/**
				 * Fires after a single user has been deleted.
				 *
				 * @param int    $user_id     The deleted user ID.
				 * @param string $role        The role the user belonged to.
				 * @param int    $reassign_id User ID content was reassigned to.
				 */
				do_action( 'wp_reset_taahzino_user_deleted', $user_id, $role, $reassign_id );
			}
		}

		$remaining_users = get_users( array(
			'role'    => $role,
			'exclude' => array( $current_user_id ),
			'fields'  => 'ID',
		) );
		$remaining = count( $remaining_users );

		/**
		 * Fires after a batch of users has been deleted.
		 *
		 * @param int    $deleted     Number of users deleted in this batch.
		 * @param int    $remaining   Number of users still remaining.
		 * @param string $role        The role being purged.
		 * @param int    $reassign_id User ID content was reassigned to.
		 */
		do_action( 'wp_reset_taahzino_after_delete_users', $deleted, $remaining, $role, $reassign_id );

		wp_send_json_success( array(
			'deleted'   => $deleted,
			'remaining' => $remaining,
		) );
	}
}
