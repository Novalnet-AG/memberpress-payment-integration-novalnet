<?php
/**
 * Plugin Name: Novalnet payment addon - Memberpress
 * Plugin URI: https://www.novalnet.de/modul/memberpress
 * Description: Novalnet integration for Memberpress, provides the most convenient way to increase your sales and deliver secured and trusted means of checkout experience by accepting all payment methods worldwide for your customers
 * Version: 1.0.0
 * WP requires at least: 5.0
 * WP tested up to: 6.0
 * Author: Novalnet AG
 * Author URI: https://www.novalnet.de
 * Text Domain: memberpress-novalnet-addon
 * Domain Path: /i18n/languages/
 * License: GPLv2
 *
 * @package Novalnetpaymentaddon
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' ); // Exit if accessed directly.
}
add_action( 'plugins_loaded', 'mepr_load_memberpress_novalnet' );

if ( ! function_exists( 'mepr_load_memberpress_novalnet' ) ) {
	/**
	 * Load novalnet payment addon
	 */
	function mepr_load_memberpress_novalnet() {
		define( 'MP_NOVALNET_VERSION', '1.0.0' );
		define( 'MP_NOVALNET_PLUGIN_NAME', 'memberpress-novalnet-addon' );
		define( 'MP_NOVALNET_PATH', plugin_dir_path( __FILE__ ) );
		define( 'MP_NOVALNET_URL', plugin_dir_url( __FILE__ ) );
		define( 'MP_NOVALNET_IMAGES_URL', MP_NOVALNET_URL . 'assets/images/' );
		load_plugin_textdomain( 'memberpress-novalnet-addon', false, 'memberpress-novalnet-addon/i18n/languages' );

		// Requires MeprNovalnetGateway.
		if ( ! class_exists( 'MeprNovalnetGateway' ) ) {
			require_once MP_NOVALNET_PATH . 'class-meprnovalnet.php';
			new MeprNovalnet();
		}

		// Requires Memberpress plugin to be installed and activated notice.
		if ( ! class_exists( 'MeprBaseRealGateway' ) ) {
			add_action( 'admin_notices', 'mepr_novalnet_require_memberpress_notice' );
			return;
		}
	}
}

if ( ! function_exists( 'mepr_novalnet_require_memberpress_notice' ) ) {
	/**
	 * Requires Memberpress plugin to be installed and activated notice
	 */
	function mepr_novalnet_require_memberpress_notice() {
		$class   = 'notice notice-error is-dismissible';
		$message = __( 'Novalnet payment addon requires memberpress to be installed and activated', 'memberpress-novalnet-addon' );
		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );

	}
}
