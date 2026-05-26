<?php
/**
 * Plugin Name: WP Reset by Taahzino
 * Plugin URI:  https://taahzino.com/plugins/wp-reset
 * Description: Reset various aspects of your WordPress site. Move WooCommerce orders to trash and more.
 * Version:     1.1.0
 * Author:      Taahzino
 * Author URI:  https://taahzino.com
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-reset-taahzino
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_RESET_TAAHZINO_VERSION', '1.1.0' );
define( 'WP_RESET_TAAHZINO_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_RESET_TAAHZINO_URL', plugin_dir_url( __FILE__ ) );

require_once WP_RESET_TAAHZINO_PATH . 'includes/class-plugin.php';

add_action( 'plugins_loaded', array( WP_Reset_Taahzino\Plugin::class, 'instance' ) );
