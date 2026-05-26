<?php

namespace WP_Reset_Taahzino;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Page {

	const PAGE_SLUG = 'wp-reset-taahzino';

	private $hook_suffix = '';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menu() {
		$this->hook_suffix = add_management_page(
			__( 'WP Reset', 'wp-reset-taahzino' ),
			__( 'WP Reset', 'wp-reset-taahzino' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( $hook ) {
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'wrt-admin',
			WP_RESET_TAAHZINO_URL . 'assets/css/admin.css',
			array(),
			WP_RESET_TAAHZINO_VERSION
		);

		wp_enqueue_script(
			'wrt-admin',
			WP_RESET_TAAHZINO_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WP_RESET_TAAHZINO_VERSION,
			true
		);

		wp_localize_script( 'wrt-admin', 'wrtData', array(
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'nonceTrash'      => wp_create_nonce( 'wrt_trash_orders' ),
			'nonceEmptyTrash' => wp_create_nonce( 'wrt_empty_trash_orders' ),
			'i18n'            => array(
				'confirmTrash'      => __( 'Are you sure you want to move ALL WooCommerce orders to trash? This cannot be easily undone for large datasets.', 'wp-reset-taahzino' ),
				'trashing'          => __( 'Trashing orders...', 'wp-reset-taahzino' ),
				'trashed'           => __( 'Trashed %1$d of %2$d orders...', 'wp-reset-taahzino' ),
				'trashComplete'     => __( 'All orders have been moved to trash.', 'wp-reset-taahzino' ),
				'error'             => __( 'An error occurred. Please try again.', 'wp-reset-taahzino' ),
				'noOrders'          => __( 'No orders found to trash.', 'wp-reset-taahzino' ),
				'confirmEmpty'      => __( 'Are you sure you want to PERMANENTLY DELETE all trashed WooCommerce orders? This action cannot be undone.', 'wp-reset-taahzino' ),
				'deleting'          => __( 'Permanently deleting orders...', 'wp-reset-taahzino' ),
				'deleted'           => __( 'Deleted %1$d of %2$d orders...', 'wp-reset-taahzino' ),
				'emptyComplete'     => __( 'All trashed orders have been permanently deleted.', 'wp-reset-taahzino' ),
				'noTrashedOrders'   => __( 'No trashed orders found to delete.', 'wp-reset-taahzino' ),
			),
		) );
	}

	public function render_page() {
		$woo_active    = Plugin::is_woocommerce_active();
		$order_count   = 0;
		$trashed_count = 0;

		if ( $woo_active ) {
			$order_count = count( wc_get_orders( array(
				'limit'  => -1,
				'status' => array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' ),
				'return' => 'ids',
			) ) );

			$trashed_count = count( wc_get_orders( array(
				'limit'  => -1,
				'status' => 'trash',
				'return' => 'ids',
			) ) );
		}
		?>
		<div class="wrap wrt-wrap">
			<h1><?php esc_html_e( 'WP Reset by Taahzino', 'wp-reset-taahzino' ); ?></h1>
			<p class="wrt-description">
				<?php esc_html_e( 'Reset various aspects of your WordPress site. Choose a feature below to get started.', 'wp-reset-taahzino' ); ?>
			</p>

			<div class="wrt-card">
				<h2><?php esc_html_e( 'WooCommerce Orders', 'wp-reset-taahzino' ); ?></h2>

				<?php if ( ! $woo_active ) : ?>
					<div class="wrt-notice wrt-notice-warning">
						<p><?php esc_html_e( 'WooCommerce is not active. Please activate WooCommerce to use this feature.', 'wp-reset-taahzino' ); ?></p>
					</div>
				<?php else : ?>
					<p>
						<?php
						printf(
							/* translators: %s: number of orders */
							esc_html__( 'Found %s order(s) that can be moved to trash.', 'wp-reset-taahzino' ),
							'<strong id="wrt-order-count">' . esc_html( number_format_i18n( $order_count ) ) . '</strong>'
						);
						?>
					</p>

					<div id="wrt-progress-wrap" style="display:none;">
						<div class="wrt-progress-bar">
							<div class="wrt-progress-bar-fill" id="wrt-progress-fill"></div>
						</div>
						<p class="wrt-progress-text" id="wrt-progress-text"></p>
					</div>

					<div id="wrt-result" style="display:none;"></div>

					<p class="wrt-actions">
						<button type="button"
							id="wrt-trash-orders"
							class="button button-primary"
							data-total="<?php echo esc_attr( $order_count ); ?>"
							<?php disabled( $order_count, 0 ); ?>>
							<?php esc_html_e( 'Move All Orders to Trash', 'wp-reset-taahzino' ); ?>
						</button>
					</p>
				<?php endif; ?>
			</div>

			<div class="wrt-card">
				<h2><?php esc_html_e( 'Empty Order Trash', 'wp-reset-taahzino' ); ?></h2>

				<?php if ( ! $woo_active ) : ?>
					<div class="wrt-notice wrt-notice-warning">
						<p><?php esc_html_e( 'WooCommerce is not active. Please activate WooCommerce to use this feature.', 'wp-reset-taahzino' ); ?></p>
					</div>
				<?php else : ?>
					<p>
						<?php
						printf(
							/* translators: %s: number of trashed orders */
							esc_html__( 'Found %s trashed order(s) that can be permanently deleted.', 'wp-reset-taahzino' ),
							'<strong id="wrt-trashed-count">' . esc_html( number_format_i18n( $trashed_count ) ) . '</strong>'
						);
						?>
					</p>

					<div id="wrt-empty-progress-wrap" style="display:none;">
						<div class="wrt-progress-bar">
							<div class="wrt-progress-bar-fill" id="wrt-empty-progress-fill"></div>
						</div>
						<p class="wrt-progress-text" id="wrt-empty-progress-text"></p>
					</div>

					<div id="wrt-empty-result" style="display:none;"></div>

					<p class="wrt-actions">
						<button type="button"
							id="wrt-empty-trash-orders"
							class="button button-link-delete"
							data-total="<?php echo esc_attr( $trashed_count ); ?>"
							<?php disabled( $trashed_count, 0 ); ?>>
							<?php esc_html_e( 'Permanently Delete All Trashed Orders', 'wp-reset-taahzino' ); ?>
						</button>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
