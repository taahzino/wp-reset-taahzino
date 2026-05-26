<?php

namespace WP_Reset_Taahzino;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Woo_Orders_Reset {

	const BATCH_SIZE          = 50;
	const AJAX_TRASH          = 'wrt_trash_orders';
	const AJAX_EMPTY_TRASH    = 'wrt_empty_trash_orders';
	const NONCE_TRASH         = 'wrt_trash_orders';
	const NONCE_EMPTY_TRASH   = 'wrt_empty_trash_orders';

	const ORDER_STATUSES = array(
		'pending',
		'processing',
		'on-hold',
		'completed',
		'cancelled',
		'refunded',
		'failed',
	);

	public function __construct() {
		add_action( 'wp_ajax_' . self::AJAX_TRASH, array( $this, 'handle_trash_ajax' ) );
		add_action( 'wp_ajax_' . self::AJAX_EMPTY_TRASH, array( $this, 'handle_empty_trash_ajax' ) );
	}

	public function handle_trash_ajax() {
		check_ajax_referer( self::NONCE_TRASH, 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array(
				'message' => __( 'You do not have permission to perform this action.', 'wp-reset-taahzino' ),
			), 403 );
		}

		if ( ! Plugin::is_woocommerce_active() ) {
			wp_send_json_error( array(
				'message' => __( 'WooCommerce is not active.', 'wp-reset-taahzino' ),
			), 400 );
		}

		$orders = wc_get_orders( array(
			'limit'  => self::BATCH_SIZE,
			'status' => self::ORDER_STATUSES,
		) );

		if ( empty( $orders ) ) {
			wp_send_json_success( array(
				'trashed'   => 0,
				'remaining' => 0,
			) );
		}

		/**
		 * Fires before a batch of orders is moved to trash.
		 *
		 * @param WC_Order[] $orders Orders about to be trashed.
		 */
		do_action( 'wp_reset_taahzino_before_trash_orders', $orders );

		$trashed = 0;

		foreach ( $orders as $order ) {
			$order_id = $order->get_id();
			$result   = $order->delete( false );

			if ( $result ) {
				$trashed++;

				/**
				 * Fires after a single order has been moved to trash.
				 *
				 * @param int $order_id The trashed order ID.
				 */
				do_action( 'wp_reset_taahzino_order_trashed', $order_id );
			}
		}

		$remaining = count( wc_get_orders( array(
			'limit'  => -1,
			'status' => self::ORDER_STATUSES,
			'return' => 'ids',
		) ) );

		/**
		 * Fires after a batch of orders has been moved to trash.
		 *
		 * @param int $trashed   Number of orders trashed in this batch.
		 * @param int $remaining Number of orders still remaining.
		 */
		do_action( 'wp_reset_taahzino_after_trash_orders', $trashed, $remaining );

		wp_send_json_success( array(
			'trashed'   => $trashed,
			'remaining' => $remaining,
		) );
	}

	public function handle_empty_trash_ajax() {
		check_ajax_referer( self::NONCE_EMPTY_TRASH, 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array(
				'message' => __( 'You do not have permission to perform this action.', 'wp-reset-taahzino' ),
			), 403 );
		}

		if ( ! Plugin::is_woocommerce_active() ) {
			wp_send_json_error( array(
				'message' => __( 'WooCommerce is not active.', 'wp-reset-taahzino' ),
			), 400 );
		}

		$orders = wc_get_orders( array(
			'limit'  => self::BATCH_SIZE,
			'status' => 'trash',
		) );

		if ( empty( $orders ) ) {
			wp_send_json_success( array(
				'deleted'   => 0,
				'remaining' => 0,
			) );
		}

		/**
		 * Fires before a batch of trashed orders is permanently deleted.
		 *
		 * @param WC_Order[] $orders Orders about to be deleted.
		 */
		do_action( 'wp_reset_taahzino_before_delete_orders', $orders );

		$deleted = 0;

		foreach ( $orders as $order ) {
			$order_id = $order->get_id();
			$result   = $order->delete( true );

			if ( $result ) {
				$deleted++;

				/**
				 * Fires after a single order has been permanently deleted.
				 *
				 * @param int $order_id The deleted order ID.
				 */
				do_action( 'wp_reset_taahzino_order_deleted', $order_id );
			}
		}

		$remaining = count( wc_get_orders( array(
			'limit'  => -1,
			'status' => 'trash',
			'return' => 'ids',
		) ) );

		/**
		 * Fires after a batch of trashed orders has been permanently deleted.
		 *
		 * @param int $deleted   Number of orders deleted in this batch.
		 * @param int $remaining Number of trashed orders still remaining.
		 */
		do_action( 'wp_reset_taahzino_after_delete_orders', $deleted, $remaining );

		wp_send_json_success( array(
			'deleted'   => $deleted,
			'remaining' => $remaining,
		) );
	}
}
