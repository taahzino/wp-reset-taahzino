<?php

namespace WP_Reset_Taahzino;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_dependencies();
		$this->init();
	}

	private function load_dependencies() {
		require_once WP_RESET_TAAHZINO_PATH . 'includes/class-admin-page.php';
		require_once WP_RESET_TAAHZINO_PATH . 'includes/class-woo-orders-reset.php';
	}

	private function init() {
		new Admin_Page();
		new Woo_Orders_Reset();
	}

	public static function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}
}
