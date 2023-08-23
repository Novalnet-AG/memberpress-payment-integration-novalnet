<?php
/**
 * Novalnet payment addon
 *
 * This script is used for including gateway path and
 * sending auto configuration call & configuring webhook URL
 *
 * @author     Novalnet AG
 * @copyright  Copyright (c) Novalnet
 * @link       https://www.novalnet.de
 * @package    Novalnetpaymentaddon
 *
 * Script: MeprNovalnet.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' ); // Exit if accessed directly.
}

/**
 * MeprNovalnet Class.
 *
 * @class   MeprNovalnet
 */
class MeprNovalnet {

	/**
	 * Store the POST/GET request
	 *
	 * @var array
	 */
	public $request;

	/**
	 * MeprNovalnet Constructor.
	 */
	public function __construct() {
		add_filter( 'mepr-gateway-paths', array( $this, 'mepr_novalnet_get_gateway_path' ), 10, 1 );
		add_filter( 'mepr-ctrls-paths', array( $this, 'mepr_novalnet_get_gateway_path' ), 99, 1 );
		add_filter( 'mepr-email-paths', array( $this, 'mepr_novalnet_get_mail_path' ), 99, 1 );
		add_filter( 'mepr_view_paths', array( $this, 'mepr_novalnet_get_view_path' ), 99, 1 );
		add_action( 'mepr-options-admin-enqueue-script', array( $this, 'mepr_novalnet_enqueue_script' ) );
		add_filter( 'wp_ajax_novalnet_get_merchant_details', array( $this, 'mepr_novalnet_get_merchant_details' ) );
		add_filter( 'wp_ajax_novalnet_configure_webhook', array( $this, 'mepr_novalnet_configure_webhook' ) );
		// Store the request data.
		$this->request = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification
		include_once 'includes/class-novalnet-helper.php';
	}

	/**
	 * Load Novalnet gateway path to general gateway page
	 *
	 * @param array $path This contains payment gateway paths.
	 * @return array
	 */
	public function mepr_novalnet_get_gateway_path( $path ) {
		array_push( $path, MP_NOVALNET_PATH . 'includes' );
		return $path;
	}

	/**
	 * Load Novalnet email path
	 *
	 * @param array $path This contains email paths.
	 * @return array
	 */
	public function mepr_novalnet_get_mail_path( $path ) {
		array_push( $path, MP_NOVALNET_PATH . 'app/emails' );
		return $path;
	}

	/**
	 * Load Novalnet email views
	 *
	 * @param array $path This contains email template paths.
	 * @return array
	 */
	public function mepr_novalnet_get_view_path( $path ) {
		array_push( $path, MP_NOVALNET_PATH . 'app/views' );
		return $path;
	}

	/**
	 * Include the configuration JS file
	 *
	 * @param string $hook Current actions hook name.
	 * @return array
	 */
	public static function mepr_novalnet_enqueue_script( $hook ) {
		$mepr_options = MeprOptions::fetch();
		$mepr_options = json_decode( $mepr_options, true );
		if ( 'memberpress_page_memberpress-options' === $hook ) {
			wp_enqueue_script(
				'mp-novalnet-options-js',
				MP_NOVALNET_URL . 'assets/js/config.js',
				array( 'jquery' ),
				MP_NOVALNET_VERSION,
				true
			);
			return $hook;
		}
	}

	/**
	 * Getting merchant details from Novalnet server
	 */
	public function mepr_novalnet_get_merchant_details() {
		check_ajax_referer( 'novalnet-merchant-details', 'security_key' );
		$data             = array(
			'signature'  => sanitize_text_field( $this->request['signature'] ),
			'access_key' => sanitize_text_field( $this->request['access_key'] ),
		);
		$merchant_details = novalnet_helper()->mepr_novalnet_get_merchant_details( $data );
		wp_send_json( $merchant_details );
	}

	/**
	 * Configuring the webhook URL in the Novalnet Admin  portal
	 */
	public function mepr_novalnet_configure_webhook() {
		check_ajax_referer( 'novalnet-merchant-details', 'security_key' );
		$input    = array(
			'signature'  => sanitize_text_field( $this->request['signature'] ),
			'access_key' => sanitize_text_field( $this->request['access_key'] ),
			'webhook'    => esc_url_raw( $this->request['mepr_clipboard_input'] ),
		);
		$response = novalnet_helper()->mepr_novalnet_configure_webhook( $input );
		wp_send_json( $response );
	}
}

