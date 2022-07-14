<?php
/**
 * Novalnet payment addon
 *
 * This script is used for Novalnet payment receipt notice
 *
 * @author     Novalnet AG
 * @copyright  Copyright (c) Novalnet
 * @license    https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 * @link       https://www.novalnet.de
 * @package    Novalnetpaymentaddon
 *
 * Script: MeprNovalnetPaymentReceiptEmail.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' ); // Exit if accessed directly.
}

/**
 * Novalnet Payment Receipt Email for Memberpress
 */
class MeprNovalnetPaymentReceiptEmail extends MeprBaseOptionsUserEmail {
	/**
	 * Set the default values for title, description, subject & body
	 *
	 * @param array $args Email settings.
	 */
	public function set_defaults( $args = array() ) {
		$this->title       = __( '<b>Novalnet Payment Receipt</b> Notice', 'memberpress-novalnet-addon' );
		$this->description = __( 'This email is sent to the user to get the bank and store details to pay for their membership purchase. This email is applicable for Novalnet Invoice, Instalment by Invoice, Prepayment, Barzahlen/viacash and Multibanco.', 'memberpress-novalnet-addon' );
		$this->ui_order    = 100;

		$enabled         = true;
		$use_template    = true;
		$this->show_form = true;
		$subject         = __( 'Novalnet Payment', 'memberpress-novalnet-addon' );
		$body            = $this->body_partial();

		$this->defaults      = compact( 'enabled', 'subject', 'body', 'use_template' );
		$novalnet_email_vars = array(
			'novalnet_payment_method',
			'tid',
		);
		$this->variables     = array_merge( MeprTransactionsHelper::get_email_vars(), $novalnet_email_vars );
	}
}

