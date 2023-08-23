<?php
/**
 * Handling Novalnet validation / process functions
 *
 * @class    NovalnetHelper
 * @package  Novalnetpaymentaddon/includes/
 * @category Class
 * @author   Novalnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * NovalnetHelper Class.
 */
class NovalnetHelper {
	/**
	 * End point URL
	 *
	 * @var string
	 */
	private $endpoint = 'https://payport.novalnet.de/v2/';

	/**
	 * Helper class instance
	 *
	 * @var null
	 */
	protected static $instance = null;

	/**
	 * Mandatory Parameters.
	 *
	 * @var array
	 */
	private $mandatory = array(
		'event'       => array(
			'type',
			'checksum',
			'tid',
		),
		'merchant'    => array(
			'vendor',
			'project',
		),
		'result'      => array(
			'status',
		),
		'transaction' => array(
			'tid',
			'payment_type',
			'status',
		),
	);

	/**
	 * Creates the hepler instance
	 *
	 * @return object $instance
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Getting merchant details from Novalnet server
	 *
	 * @param array $input This contains merchant credentials.
	 * @return string
	 */
	public function mepr_novalnet_get_merchant_details( $input ) {
		if ( ! empty( $input['signature'] ) ) {
			$data['merchant'] = array(
				'signature' => $input['signature'],
			);
			$data['custom']   = array(
				'lang' => $this->mepr_novalnet_get_language(),
			);

			$response = $this->mepr_novalnet_send_request( $data, $this->mepr_novalnet_format_endpoint( 'merchant_details' ), $input['access_key'] );
			return self::mepr_novalnet_serialize_data( $response );
		}
	}

	/**
	 * Configuring the webhook URL in the Novalnet Admin  portal
	 *
	 * @param array $input This contains merchant credentials and webhook url.
	 * @return string
	 */
	public function mepr_novalnet_configure_webhook( $input ) {
		if ( ! empty( $input['signature'] ) ) {
			$data['merchant'] = array(
				'signature' => $input['signature'],
			);
			$data['webhook']  = array(
				'url' => $input['webhook'],
			);
			$data['custom']   = array(
				'lang' => $this->mepr_novalnet_get_language(),
			);
			$response         = $this->mepr_novalnet_send_request( $data, $this->mepr_novalnet_format_endpoint( 'webhook/configure' ), $input['access_key'] );
			return self::mepr_novalnet_serialize_data( $response );
		}
	}

	/**
	 * Form merchant params
	 *
	 * @param array $mepr_settings Memberpress payment setting array.
	 * @param array $parameters Request data.
	 */
	public function mepr_novalnet_form_merchant_params( $mepr_settings, &$parameters ) {
		$parameters['merchant'] = array(
			'signature' => $mepr_settings['api_signature'],
			'tariff'    => $mepr_settings['saved_tariff_id'],
		);
	}

	/**
	 * Form end customer params
	 *
	 * @param object $user The current user details.
	 * @param array  $parameters Request data.
	 */
	public function mepr_novalnet_form_customer_params( $user, &$parameters ) {
		$street = get_user_meta( $user->ID, 'mepr-address-one', true );
		if ( ! empty( get_user_meta( $user->ID, 'mepr-address-two', true ) ) ) {
			$street = get_user_meta( $user->ID, 'mepr-address-one', true ) . '.' . get_user_meta( $user->ID, 'mepr-address-two', true );
		}

		$parameters['customer'] = array(
			'first_name'  => $user->first_name,
			'last_name'   => $user->last_name,
			'email'       => $user->user_email,
			'customer_ip' => self::mepr_novalnet_get_ip_address(),
			'customer_no' => $user->ID,
			'billing'     => array(
				'street'       => $street,
				'city'         => get_user_meta( $user->ID, 'mepr-address-city', true ),
				'zip'          => get_user_meta( $user->ID, 'mepr-address-zip', true ),
				'state'        => get_user_meta( $user->ID, 'mepr-address-state', true ),
				'country_code' => get_user_meta( $user->ID, 'mepr-address-country', true ),
			),
		);
		if ( ! empty( get_user_meta( $user->ID, 'mepr_dob', true ) ) ) {
			$parameters['customer']['birth_date'] = MeprAppHelper::format_date_utc( get_user_meta( $user->ID, 'mepr_dob', true ), '', 'Y-m-d' );
		}
	}

	/**
	 * Format the URL
	 *
	 * @param MeprTransaction $txn        Memberpress transaction.
	 * @param string          $action_url Notify url.
	 *
	 * @return string
	 */
	public function mepr_novalnet_form_url( $txn, $action_url = '' ) {
		$action_delim = MeprUtils::get_delim( $action_url );
		return $action_url . $action_delim . 'txn_id=' . $txn->id . '&txn_num=' . $txn->trans_num;
	}

	/**
	 * Form hosted page related params
	 *
	 * @param array $parameters Request data.
	 */
	public function mepr_novalnet_form_hosted_page_params( &$parameters ) {
		$parameters['hosted_page'] = array(
			'hide_blocks' => array( 'ADDRESS_FORM', 'SHOP_INFO', 'LANGUAGE_MENU', 'TARIFF' ),
			'skip_pages'  => array( 'CONFIRMATION_PAGE', 'SUCCESS_PAGE', 'PAYMENT_PAGE' ),
		);
	}

	/**
	 * Form custom params
	 *
	 * @param array $parameters Request data.
	 */
	public function mepr_novalnet_form_custom_params( &$parameters ) {
		$parameters['custom'] = array(
			'lang' => $this->mepr_novalnet_get_language(),
		);
	}

	/**
	 * Get memberpress/ WordPress language
	 *
	 * @return string
	 */
	public function mepr_novalnet_get_language() {
		$mepr_options = MeprOptions::fetch();
		$mepr_details = json_decode( $mepr_options, true );
		if ( ! empty( $mepr_details['language_code'] ) ) { // Memberpress plugin language first priority.
			return $mepr_details['language_code'];
		} else {
			$language = get_bloginfo( 'language' );
			return strtoupper( substr( $language, 0, 2 ) );
		}

	}

	/**
	 * Converting the amount into cents
	 *
	 * @param float $amount The amount.
	 * @return int
	 */
	public function mepr_novalnet_convert_smaller_currency_unit( $amount ) {
		return str_replace( ',', '', sprintf( '%0.2f', $amount ) ) * 100;
	}

	/**
	 * Converting the amount to the shop format
	 *
	 * @param int  $amount Amount in smaller_currency_unit.
	 * @param bool $symbol Flag for currency symbol.
	 * @return string;
	 */
	public function mepr_novalnet_format_amount( $amount, $symbol = true ) {
		$mepr_options = MeprOptions::fetch();
		if ( $symbol ) {
			if ( $mepr_options->currency_symbol_after ) {
				$amount = preg_replace( '~\$~', '\\\$', sprintf( MeprUtils::format_currency_float( $amount / 100 ) . '%s', stripslashes( $mepr_options->currency_symbol ) ) );
			} else {
				$amount = preg_replace( '~\$~', '\\\$', sprintf( '%s' . MeprUtils::format_currency_float( $amount / 100 ), stripslashes( $mepr_options->currency_symbol ) ) );
			}
		} else {
			$amount = MeprUtils::format_currency_float( $amount / 100 );
		}
		return $amount;
	}

	/**
	 * Form due date params
	 *
	 * @param array $mepr_settings Novalnet payment gateway settings.
	 * @param array $parameters Request data.
	 */
	public function mepr_novalnet_form_due_date_params( $mepr_settings, &$parameters ) {
		$due_dates = array();
		// Due date for Invoice & Prepayment.
		if ( $mepr_settings['invoice_duedate'] >= 7 ) {
			$due_dates['INVOICE']    = gmdate( 'Y-m-d', strtotime( '+ ' . $mepr_settings['invoice_duedate'] . ' day' ) );
			$due_dates['PREPAYMENT'] = gmdate( 'Y-m-d', strtotime( '+ ' . $mepr_settings['invoice_duedate'] . ' day' ) );
		}
		// Due date for Direct Debit SEPA.
		if ( $mepr_settings['sepa_duedate'] >= 2 && $mepr_settings['sepa_duedate'] <= 14 ) {
			$due_dates['DIRECT_DEBIT_SEPA'] = gmdate( 'Y-m-d', strtotime( '+ ' . $mepr_settings['sepa_duedate'] . ' day' ) );
		}
		// Due date for Cashpayment.
		if ( $mepr_settings['slip_expiry_date'] >= 1 ) {
			$due_dates['CASHPAYMENT'] = gmdate( 'Y-m-d', strtotime( '+ ' . $mepr_settings['slip_expiry_date'] . ' day' ) );
		}

		if ( ! empty( $due_dates ) ) {
			$parameters['transaction']['due_dates'] = $due_dates;
		}
		// Cycle information for Instalment payments.
		if ( ! empty( $mepr_settings['selected_instalment_cycles'] ) ) {
			$parameters['instalment'] = array(
				'cycles_list' => array_map( 'trim', explode( ',', $mepr_settings['selected_instalment_cycles'] ) ),
			);
		}
	}

	/**
	 * Form subscription params
	 *
	 * @param array  $mepr_settings Novalnet payment gateway settings.
	 * @param array  $parameters Request data.
	 * @param object $txn Memberpress transaction.
	 */
	public function mepr_novalnet_form_subscription_params( $mepr_settings, &$parameters, $txn ) {
		$subscription               = $txn->subscription();
		$parameters['subscription'] = array(
			'interval' => $subscription->period . $subscription->period_type[0],
		);
		if ( 1 === (int) $subscription->trial ) { // If trail available.
			$parameters['subscription']['trial_amount']   = $this->mepr_novalnet_convert_smaller_currency_unit( $subscription->trial_total );
			$parameters['subscription']['trial_interval'] = $subscription->trial_days . 'd';
		}
		$parameters['merchant']['tariff'] = $mepr_settings['saved_subs_tariff_id'];
		// Display only the subsccription supported payments.
		$parameters['hosted_page']['display_payments'] = array( 'CREDITCARD', 'PREPAYMENT', 'INVOICE', 'PAYPAL', 'DIRECT_DEBIT_SEPA', 'GUARANTEED_INVOICE', 'GUARANTEED_DIRECT_DEBIT_SEPA' );
	}

	/**
	 * Perform server call and format the response
	 *
	 * @param array  $request Request data.
	 * @param string $url Request url.
	 * @param string $payment_access_key Merchant access key.
	 * @return mixed
	 */
	public function mepr_novalnet_send_request( $request, $url, $payment_access_key ) {
		$json_request = self::mepr_novalnet_serialize_data( $request );

		// Post the values to the paygate.
		$response = wp_remote_post(
			$url,
			array(
				'method'  => 'POST',
				'headers' => $this->mepr_novalnet_form_headers( $payment_access_key ),
				'timeout' => 240,
				'body'    => $json_request,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'result' => array(
					'status'      => 'FAILURE',
					'status_code' => '106',
					'status_text' => $response->get_error_message(),
				),
			);
		} elseif ( ! empty( $response['body'] ) ) {
			return json_decode( $response['body'], true, 512, JSON_BIGINT_AS_STRING );
		}
		return array(
			'result' => array(
				'status_code' => '106',
				'status'      => 'FAILURE',
				'status_text' => __( 'Please enter the required fields under Novalnet API Configuration', 'memberpress-novalnet-addon' ),
			),
		);
	}

	/**
	 * Format the end point URL
	 *
	 * @param string $action Novalnet action string.
	 * @return string
	 */
	public function mepr_novalnet_format_endpoint( $action = '' ) {
		return $this->endpoint . str_replace( '_', '/', $action );
	}

	/**
	 * Form the headers
	 *
	 * @param string $payment_access_key Merchant Payment access key.
	 * @return array
	 */
	public static function mepr_novalnet_form_headers( $payment_access_key ) {
		// Form headers.
		return array(
			'Content-Type'    => 'application/json',
			'charset'         => 'utf-8',
			'Accept'          => 'application/json',
			'X-NN-Access-Key' => base64_encode( $payment_access_key ),
		);
	}

	/**
	 * Returns the url of a given message page for the current membership
	 *
	 * @param string $gateway_id Novlanet payment gateway id.
	 * @param object $product Memberpress product object.
	 * @param string $action Url action.
	 * @return string
	 */
	public function mepr_novalnet_message_page_url( $gateway_id, $product, $action ) {
		$permalink_structure = get_option( 'permalink_structure' );
		$force_ugly_urls     = get_option( 'mepr_force_ugly_gateway_notify_urls' );
		if ( $force_ugly_urls || empty( $permalink_structure ) ) {
			return $product->url( "&pmt=$gateway_id&action=$action" );
		} else {
			return $product->url( "?pmt=$gateway_id&action=$action" );
		}
	}

	/**
	 * Check status of response
	 *
	 * @param array $data Gateway response data.
	 * @return boolean
	 */
	public function mepr_novalnet_is_success_status( $data ) {
		return ( ( ! empty( $data['result']['status'] ) && 'SUCCESS' === $data['result']['status'] ) || ( ! empty( $data['status'] ) && 'SUCCESS' === $data['status'] ) );
	}

	/**
	 * Retrieves messages from server response.
	 *
	 * @since 1.0.1
	 * @param array $data The response data.
	 *
	 * @return string
	 */
	public function mepr_novalnet_response_text( $data ) {
		if ( ! empty( $data ['result']['status_text'] ) ) {
			return $data ['result']['status_text'];
		}
		if ( ! empty( $data ['status_text'] ) ) {
			return $data ['status_text'];
		}
		return __( 'Payment was not successful. An error occurred', 'memberpress-novalnet-addon' );
	}

	/**
	 * Store tansaction details
	 *
	 * @param array   $data Gateway response data.
	 * @param integer $txn_id Memberpress transaction id.
	 * @return bool
	 */
	public function mepr_novalnet_store_response_data( $data, $txn_id ) {
		if ( ! empty( $data ) && ! empty( $txn_id ) ) {
			global $wpdb;
			$mepr_db           = new MeprDb();
			$response_keys     = array( 'transaction', 'instalment', 'subscription' );
			$transaction_keys  = array( 'amount', 'bank_details', 'currency', 'due_date', 'invoice_ref', 'order_no', 'nearest_stores', 'payment_type', 'status', 'test_mode', 'tid', 'partner_payment_reference' );
			$payment_data_keys = array( 'card_brand', 'last_four' );
			if ( $this->mepr_novalnet_is_success_status( $data ) && in_array( $data['transaction']['payment_type'], array( 'GOOGLEPAY', 'APPLEPAY' ), true ) ) {
				$transaction_keys[] = 'payment_data';
			}
			$response_data                = array_intersect_key( $data, array_flip( $response_keys ) );
			$response_data['transaction'] = array_intersect_key( $response_data['transaction'], array_flip( $transaction_keys ) );
			if ( isset( $response_data['transaction']['payment_data'] ) ) {
				$response_data['transaction']['payment_data'] = array_intersect_key( $response_data['transaction']['payment_data'], array_flip( $payment_data_keys ) );
			}
			$response_json = $this->mepr_novalnet_serialize_data( $response_data );
			return $mepr_db->update_metadata( $wpdb->prefix . 'mepr_transaction_meta', 'transaction_id', $txn_id, 'novalnet_txn_details', $response_json );
		}
	}

	/**
	 * Get stored transaction data
	 *
	 * @param integer $txn_id Memberpress transaction id.
	 * @return array $transaction_data
	 */
	public function mepr_novalnet_get_response_data( $txn_id ) {
		global $wpdb;
		$mepr_db          = new MeprDb();
		$transaction_data = $mepr_db->get_metadata( $wpdb->prefix . 'mepr_transaction_meta', 'transaction_id', $txn_id, 'novalnet_txn_details', $single = true );
		$transaction_data = $this->mepr_novalnet_unserialize_data( $transaction_data );
		return $transaction_data;
	}

	/**
	 * Prepare comment string
	 *
	 * @param array $data Gateway response data.
	 * @return string $txn_details
	 */
	public function mepr_novalnet_prepare_txn_details( $data ) {
		// Form transaction details.
		$txn_details = $this->mepr_novalnet_form_txn_details( $data );
		if ( 'PENDING' === $data['transaction']['status'] && in_array( $data['transaction']['payment_type'], array( 'GUARANTEED_INVOICE', 'GUARANTEED_DIRECT_DEBIT_SEPA', 'INSTALMENT_INVOICE', 'INSTALMENT_DIRECT_DEBIT_SEPA' ), true ) ) {
			$txn_details .= '<br><br>' . __( 'Your order is under verification and we will soon update you with the order status. Please note that this may take upto 24 hours.', 'memberpress-novalnet-addon' );
		} elseif ( ! empty( $data ['transaction']['bank_details'] ) && ! empty( $data ['transaction']['amount'] ) && empty( $data ['instalment']['prepaid'] ) ) {
			// Form Novalnet bank details.
			$txn_details .= $this->mepr_novalnet_form_bank_details( $data );
		} elseif ( ! empty( $data['transaction']['nearest_stores'] ) ) {
			// Form Cashpayment store details.
			$txn_details .= $this->mepr_novalnet_form_nearest_store_details( $data );

		} elseif ( ! empty( $data['transaction']['partner_payment_reference'] ) ) {

			/* translators: %s: amount */
			$txn_details .= '<br>' . sprintf( __( 'Please use the following payment reference details to pay the amount of %s at a Multibanco ATM or through your internet banking.', 'memberpress-novalnet-addon' ), $this->mepr_novalnet_format_amount( $data['transaction']['amount'] ) );

			/* translators: %s: partner_payment_reference */
			$txn_details .= '<br>' . sprintf( __( 'Payment Reference : %s', 'memberpress-novalnet-addon' ), $data['transaction']['partner_payment_reference'] ) . '<br>';
		}
		return $txn_details;
	}

	/**
	 * Form transaction details
	 *
	 * @param array   $data Gateway response data.
	 * @param boolean $is_error Error notice flag.
	 * @return string $txn_details
	 */
	public function mepr_novalnet_form_txn_details( $data, $is_error = false ) {

		$txn_details = '';

		if ( ! empty( $data ['transaction']['tid'] ) ) {

			if ( isset( $data['transaction']['payment_type'] ) && in_array( $data['transaction']['payment_type'], array( 'GOOGLEPAY', 'APPLEPAY' ), true ) ) {
				$payment = $this->mepr_novalnet_get_novalnet_txn_type( $data['transaction']['payment_type'] );
				/* translators: %1$s: brand, %2$s: last four */
				$card_mask = ( isset( $data['transaction']['payment_data']['card_brand'] ) ) ? sprintf(
					'(%1$s ****%2$s)',
					strtolower(
						$data['transaction']['payment_data']['card_brand']
					),
					$data['transaction']['payment_data']['last_four']
				) : '';
				/* translators: %1$s: payment, %2$s: brand, %2$s: last four */
				$txn_details .= sprintf( __( 'Your order was successfully processed using %1$s %2$s', 'memberpress-novalnet-addon' ), $payment, $card_mask ) . '<br>';
			}

			$txn_details .= $this->mepr_novalnet_get_novalnet_txn_type( $data['transaction']['payment_type'] );
			/* translators: %s: TID */
			$txn_details .= '<br>' . sprintf( __( 'Novalnet transaction ID: %s', 'memberpress-novalnet-addon' ), $data ['transaction']['tid'] );

			if ( ! empty( $data ['transaction'] ['test_mode'] ) ) {
				$txn_details .= '<br>' . __( 'Test order', 'memberpress-novalnet-addon' );
			}
		}
		if ( $is_error ) {
			$txn_details .= '<br>' . $data['result']['status_text'];
		}
		return $txn_details;
	}

	/**
	 * Prepare amount transger comment string
	 *
	 * @param array   $input Gateway response data.
	 * @param boolean $reference Payment reference flag.
	 * @return string $bank_details
	 */
	public function mepr_novalnet_form_bank_details( $input, $reference = true ) {

		$order_amount = $input ['transaction']['amount'];
		if ( ! empty( $input['instalment']['cycle_amount'] ) ) {
			$order_amount = $input ['instalment']['cycle_amount'];
		}
		if ( in_array( $input['transaction']['status'], array( 'CONFIRMED', 'PENDING' ), true ) && ! empty( $input ['transaction']['due_date'] ) ) {
			/* translators: %1$s: amount, %2$s: due date */
			$bank_details = '<br><br>' . sprintf( __( 'Please transfer the amount of %1$s to the following account on or before %2$s', 'memberpress-novalnet-addon' ), $this->mepr_novalnet_format_amount( $order_amount ), $input ['transaction']['due_date'] ) . '<br><br>';
			if ( ! empty( $input['instalment']['cycle_amount'] ) ) {
				/* translators: %1$s:amount, %2$s:due-date*/
				$bank_details = '<br><br>' . sprintf( __( 'Please transfer the instalment cycle amount of %1$s to the following account on or before %2$s', 'memberpress-novalnet-addon' ), $this->mepr_novalnet_format_amount( $order_amount ), $input ['transaction']['due_date'] ) . '<br><br>';
			}
		} else {
			/* translators: %s: amount*/
			$bank_details = '<br><br>' . sprintf( __( 'Please transfer the amount of %1$s to the following account', 'memberpress-novalnet-addon' ), $this->mepr_novalnet_format_amount( $order_amount ) ) . '<br><br>';

			if ( ! empty( $input['instalment']['cycle_amount'] ) ) {
				/* translators: %1$s:order-amount*/
				$bank_details = '<br><br>' . sprintf( __( 'Please transfer the instalment cycle amount of %1$s to the following account.', 'memberpress-novalnet-addon' ), $this->mepr_novalnet_format_amount( $order_amount ) ) . '<br><br>';
			}
		}

		foreach ( array(
			/* translators: %s: account_holder */
			'account_holder' => __( 'Account holder: %s', 'memberpress-novalnet-addon' ),

			/* translators: %s: bank_name */
			'bank_name'      => __( 'Bank: %s', 'memberpress-novalnet-addon' ),

			/* translators: %s: bank_place */
			'bank_place'     => __( 'Place: %s', 'memberpress-novalnet-addon' ),

			/* translators: %s: iban */
			'iban'           => __( 'IBAN: %s', 'memberpress-novalnet-addon' ),

			/* translators: %s: bic */
			'bic'            => __( 'BIC: %s', 'memberpress-novalnet-addon' ),
		) as $key => $text ) {
			if ( ! empty( $input ['transaction']['bank_details'][ $key ] ) ) {
				$bank_details .= sprintf( $text, $input ['transaction']['bank_details'][ $key ] ) . '<br>';
			}
		}

		// Form reference details.
		if ( $reference ) {
			$bank_details .= '<br>' . __( 'Please use any of the following payment references when transferring the amount. This is necessary to match it with your corresponding order', 'memberpress-novalnet-addon' );
			/* translators: %s:  TID */
			$bank_details .= '<br>' . sprintf( __( 'Payment Reference 1: TID %s', 'memberpress-novalnet-addon' ), $input ['transaction']['tid'] );

			if ( ! empty( $input ['transaction']['invoice_ref'] ) ) {
				/* translators: %s: invoice_ref */
				$bank_details .= '<br>' . sprintf( __( 'Payment Reference 2: %s', 'memberpress-novalnet-addon' ), $input ['transaction']['invoice_ref'] );
			}
		}
		return $bank_details;
	}

	/**
	 * Form cashpayment store details
	 *
	 * @param array $data Gateway response data.
	 * @return string $store_details
	 */
	public static function mepr_novalnet_form_nearest_store_details( $data ) {
		$nearest_stores = $data['transaction']['nearest_stores'];
		$store_details  = '';

		if ( ! empty( $data['transaction']['due_date'] ) ) {
			/* translators: %s: due_date */
			$store_details .= '<br>' . sprintf( __( 'Slip expiry date : %s', 'memberpress-novalnet-addon' ), $data['transaction']['due_date'] );
		}
		$store_details .= '<br><br>' . __( 'Store(s) near to you: ', 'memberpress-novalnet-addon' ) . '<br><br>';
		$country_codes  = MeprUtils::countries();
		foreach ( $nearest_stores as $nearest_store ) {
			$store_details .= $nearest_store['store_name'] . '</br>';
			$store_details .= $nearest_store['street'] . '</br>';
			$store_details .= $nearest_store['city'] . '</br>';
			$store_details .= $nearest_store['zip'] . '</br>';
			$store_details .= ucwords( strtolower( $country_codes[ $nearest_store['country_code'] ] ) ) . '</br></br>';
			$store_details .= '<br>';
		}
		return $store_details;
	}

	/**
	 * Update stored transaction data
	 *
	 * @param integer $txn_id Memberpress transaction id.
	 * @param array   $response Gateway response data.
	 */
	public function mepr_novalnet_update_response_data( $txn_id, $response ) {
		$transaction_data = $this->mepr_novalnet_get_response_data( $txn_id );
		if ( ! empty( $transaction_data['transaction'] ) && ! empty( $response['transaction'] ) ) {
			$response['transaction'] = array_merge( $transaction_data['transaction'], $response['transaction'] );
		}
		$this->mepr_novalnet_store_response_data( $response, $txn_id );
	}

	/**
	 *  Get server ip
	 */
	public function mepr_novalnet_get_ip_address() {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' );
		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );
				foreach ( $ips as $ip ) {
					$ip = trim( $ip );
					if ( MeprUtils::is_ip( $ip ) ) {
						return $ip;
					}
				}
			}
		}
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : false;
	}

	/**
	 * Check event data contains all mandatory parameters
	 *
	 * @param array $event_data Current webhook event data.
	 */
	public function mepr_novalnet_validate_event_data( $event_data ) {
		if ( ! empty( $event_data ['custom'] ['shop_invoked'] ) ) {
			$this->mepr_novalnet_display_message( array( 'message' => 'Process already handled in the shop.' ) );
		}
		foreach ( $this->mandatory as $category => $parameters ) {
			if ( empty( $event_data[ $category ] ) ) {
				$this->mepr_novalnet_display_message( array( 'message' => "Required parameter category($category) not received" ) );
			} elseif ( ! empty( $parameters ) ) {
				foreach ( $parameters as $parameter ) {
					if ( empty( $event_data [ $category ] [ $parameter ] ) ) {
						$this->mepr_novalnet_display_message( array( 'message' => "Required parameter($parameter) in the category($category) not received" ) );
					} elseif ( in_array( $parameter, array( 'tid', 'parent_tid' ), true ) && ! preg_match( '/^\d{17}$/', $event_data [ $category ] [ $parameter ] ) ) {
						$this->mepr_novalnet_display_message( array( 'message' => "Invalid TID received in the category($category) not received $parameter" ) );
					}
				}
			}
		}
	}

	/**
	 * Novalnet checksum validation process
	 *
	 * @param string $payment_access_key Payment access key.
	 * @param array  $event_data Current webhook event data.
	 */
	public function mepr_novalnet_validate_checksum( $payment_access_key, $event_data ) {
		$token_string = $event_data['event']['tid'] . $event_data['event']['type'] . $event_data['result']['status'];
		if ( isset( $event_data['transaction']['amount'] ) ) {
			$token_string .= $event_data['transaction']['amount'];
		}
		if ( isset( $event_data['transaction']['currency'] ) ) {
			$token_string .= $event_data['transaction']['currency'];
		}

		if ( ! empty( $payment_access_key ) ) {
			$token_string .= strrev( $payment_access_key );
		}
		$generated_checksum = hash( 'sha256', $token_string );

		if ( $generated_checksum !== $event_data['event']['checksum'] ) {
			$this->mepr_novalnet_display_message( array( 'message' => 'While notifying some data has been changed. The hash check failed' ) );
			exit;
		}
	}

	/**
	 * Display callback response message
	 *
	 * @param string $message Webhook response.
	 */
	public function mepr_novalnet_display_message( $message ) {
		wp_send_json( $message, 200 );
		exit;
	}

	/**
	 * Get novalnet payment method name
	 *
	 * @param string $novalnet_payment_type Novalnet Payment type.
	 */
	public function mepr_novalnet_get_novalnet_txn_type( $novalnet_payment_type ) {
		$payment_name = array(
			'CREDITCARD'                             => __( 'Credit/Debit Cards', 'memberpress-novalnet-addon' ),
			'ONLINE_TRANSFER'                        => __( 'Sofort online bank transfer', 'memberpress-novalnet-addon' ),
			'PAYPAL'                                 => __( 'PayPal', 'memberpress-novalnet-addon' ),
			'IDEAL'                                  => __( 'iDEAL', 'memberpress-novalnet-addon' ),
			'GIROPAY'                                => __( 'Giropay', 'memberpress-novalnet-addon' ),
			'EPS'                                    => __( 'eps', 'memberpress-novalnet-addon' ),
			'PRZELEWY24'                             => __( 'Przelewy24', 'memberpress-novalnet-addon' ),
			'CASHPAYMENT'                            => __( 'Barzahlen/viacash', 'memberpress-novalnet-addon' ),
			'INVOICE'                                => __( 'Invoice', 'memberpress-novalnet-addon' ),
			'GUARANTEED_INVOICE'                     => __( 'Invoice', 'memberpress-novalnet-addon' ),
			'DIRECT_DEBIT_SEPA'                      => __( 'Direct Debit SEPA', 'memberpress-novalnet-addon' ),
			'PREPAYMENT'                             => __( 'Prepayment', 'memberpress-novalnet-addon' ),
			'BANCONTACT'                             => __( 'Bancontact', 'memberpress-novalnet-addon' ),
			'MULTIBANCO'                             => __( 'Multibanco', 'memberpress-novalnet-addon' ),
			'POSTFINANCE_CARD'                       => __( 'PostFinance Card', 'memberpress-novalnet-addon' ),
			'POSTFINANCE'                            => __( 'PostFinance E-Finance', 'memberpress-novalnet-addon' ),
			'APPLEPAY'                               => __( 'Apple Pay', 'memberpress-novalnet-addon' ),
			'GOOGLEPAY'                              => __( 'Google Pay', 'memberpress-novalnet-addon' ),
			'ALIPAY'                                 => __( 'Alipay', 'memberpress-novalnet-addon' ),
			'WECHATPAY'                              => __( 'WeChat Pay', 'memberpress-novalnet-addon' ),
			'TRUSTLY'                                => __( 'Trustly', 'memberpress-novalnet-addon' ),
			'CASH_ON_DELIVERY'                       => __( 'Cash on pickup', 'memberpress-novalnet-addon' ),
			'ONLINE_BANK_TRANSFER'                   => __( 'Online bank transfer', 'memberpress-novalnet-addon' ),
			'INSTALMENT_INVOICE'                     => __( 'Instalment by Invoice', 'memberpress-novalnet-addon' ),
			'GUARANTEED_DIRECT_DEBIT_SEPA'           => __( 'Direct Debit SEPA', 'memberpress-novalnet-addon' ),
			'INSTALMENT_DIRECT_DEBIT_SEPA'           => __( 'Instalment by Direct Debit SEPA', 'memberpress-novalnet-addon' ),
			'INSTALMENT_INVOICE_WITH_RATE'           => __( 'Instalment by Invoice Rate', 'memberpress-novalnet-addon' ),
			'INSTALMENT_DIRECT_DEBIT_SEPA_WITH_RATE' => __( 'Instalment by Direct Debit SEPA Rate', 'memberpress-novalnet-addon' ),
		);
		return $payment_name[ $novalnet_payment_type ];
	}

	/**
	 * Store instalment data
	 *
	 * @param array $data   Instalment details.
	 * @param int   $txn_id Transaction ID.
	 */
	public function mepr_novalnet_store_instalment_data( $data, $txn_id ) {
		$instalment_details = array();
		$txn_total          = 0;
		if ( ! empty( $data['instalment'] ) && isset( $data['instalment']['cycles_executed'] ) ) {
			if ( ! empty( $txn_id ) ) {
				$txn       = new MeprTransaction( (int) $txn_id );
				$txn_total = $this->mepr_novalnet_convert_smaller_currency_unit( $txn->total );
			}
			$transaction_amount = ( ! empty( $txn_total ) && $txn_total > $data['transaction']['amount'] ) ? $txn_total : $data['transaction']['amount'];
			$cycle_amount       = $data['instalment']['cycle_amount'];
			$cycles             = $data['instalment']['cycle_dates'];
			$total_cycles       = count( $cycles );
			$last_cycle_amount  = $transaction_amount - ( $cycle_amount * ( $total_cycles - 1 ) );
			$instalment_details = array(
				'currency'        => $data['instalment']['currency'],
				'cycle_amount'    => $cycle_amount,
				'cycles_executed' => $data['instalment']['cycles_executed'],
				'pending_cycles'  => $data['instalment']['pending_cycles'],
				'cycle_details'   => array(),
			);

			foreach ( $cycles as $cycle => $cycle_date ) {
				$instalment_details['cycle_details'][ $cycle ]['amount'] = ( $cycle == $total_cycles ) ? $last_cycle_amount : $cycle_amount; // phpcs:ignore WordPress.PHP.StrictComparisons
				if ( $instalment_details['cycles_executed'] === $cycle ) {
					$instalment_details['cycle_details'][ $cycle ]['tid']             = ( ! empty( $data['instalment']['tid'] ) ) ? $data['instalment']['tid'] : $data['transaction']['tid'];
					$instalment_details['cycle_details'][ $cycle ]['next_cycle_date'] = $data['instalment']['next_cycle_date'];
				}
				$instalment_details['cycle_details'][ $cycle ]['date'] = $cycle_date;
			}
		}
		if ( ! empty( $instalment_details ) ) {
			global $wpdb;
			$mepr_db = new MeprDb();
			$mepr_db->update_metadata( $wpdb->prefix . 'mepr_transaction_meta', 'transaction_id', $data['transaction']['order_no'], 'novalnet_instalment', $this->mepr_novalnet_serialize_data( $instalment_details ) );
		}
	}

	/**
	 * Get Store instalment data.
	 *
	 * @param int $txn_id Memberpress transaction id.
	 *
	 * @return array
	 */
	public function mepr_novalnet_get_instalment_data( $txn_id ) {
		global $wpdb;
		$instalment      = array();
		$mepr_db         = new MeprDb();
		$instalment_data = $mepr_db->get_metadata( $wpdb->prefix . 'mepr_transaction_meta', 'transaction_id', $txn_id, 'novalnet_instalment', $single = true );
		if ( ! empty( $instalment_data ) ) {
			$instalment = $this->mepr_novalnet_unserialize_data( $instalment_data, true );
		}
		return $instalment;
	}

	/**
	 * Update Instalment date
	 *
	 * @param array $data Updated Instalment data.
	 */
	public function mepr_novalnet_store_instalment_data_webhook( $data ) {
		global $wpdb;
		$mepr_db            = new MeprDb();
		$instalment_details = $this->mepr_novalnet_unserialize_data( $mepr_db->get_metadata( $wpdb->prefix . 'mepr_transaction_meta', 'transaction_id', $data['transaction']['order_no'], 'novalnet_instalment', true ), true );
		$instalment         = array();
		$cycles_executed    = $data['instalment']['cycles_executed'];
		if ( ! empty( $instalment_details ) && ! empty( $cycles_executed ) ) {
			$total_cycles                          = count( $instalment_details['cycle_details'] );
			$instalment_details['cycles_executed'] = $cycles_executed;
			$instalment_details['pending_cycles']  = ( ! empty( $data['instalment']['pending_cycles'] ) ) ? $data['instalment']['pending_cycles'] : $total_cycles - $cycles_executed;
			$instalment ['tid']                    = $data['transaction']['tid'];
			$instalment ['amount']                 = $data['instalment']['cycle_amount'];
			$instalment ['date']                   = current_time( 'Y-m-d H:i:s' );
			if ( ! empty( $data['instalment']['next_cycle_date'] ) ) {
				$instalment ['next_cycle_date'] = $data['instalment']['next_cycle_date'];
			}
			$instalment_details['cycle_details'][ $cycles_executed ] = $instalment;
		}

		if ( ! empty( $instalment_details ) ) {
			$mepr_db->update_metadata( $wpdb->prefix . 'mepr_transaction_meta', 'transaction_id', $data['transaction']['order_no'], 'novalnet_instalment', $this->mepr_novalnet_serialize_data( $instalment_details ) );
		}
	}

	/**
	 * Update Instalment cancel
	 *
	 * @since 1.0.1
	 *
	 * @param array $data Novalnet webhook data.
	 */
	public function mepr_novalnet_update_instalment_cancel( $data ) {
		global $wpdb;
		$mepr_db            = new MeprDb();
		$instalment_details = $this->mepr_novalnet_unserialize_data( $mepr_db->get_metadata( $wpdb->prefix . 'mepr_transaction_meta', 'transaction_id', $data['transaction']['order_no'], 'novalnet_instalment', true ), true );

		if ( ! empty( $instalment_details ) && is_array( $instalment_details ) ) {
			$instalment_details['is_instalment_cancelled'] = 1;
			$instalment_details['is_full_cancelled']       = 1;
			if ( ! empty( $data['instalment']['cancel_type'] ) ) {
				$instalment_details['is_full_cancelled'] = ( 'ALL_CYCLES' === (string) $data['instalment']['cancel_type'] ) ? 1 : 0;
			}
		}

		if ( ! empty( $instalment_details ) ) {
			$mepr_db->update_metadata( $wpdb->prefix . 'mepr_transaction_meta', 'transaction_id', $data['transaction']['order_no'], 'novalnet_instalment', $this->mepr_novalnet_serialize_data( $instalment_details ) );
		}
	}

	/**
	 * Update Instalment cycles refund
	 *
	 * @since 1.0.1
	 *
	 * @param array $data Novalnet webhook data.
	 */
	public function mepr_novalnet_update_instalment_refund( $data ) {
		global $wpdb;
		$mepr_db            = new MeprDb();
		$instalment_details = $this->mepr_novalnet_unserialize_data( $mepr_db->get_metadata( $wpdb->prefix . 'mepr_transaction_meta', 'transaction_id', $data['transaction']['order_no'], 'novalnet_instalment', true ), true );
		if ( ! empty( $instalment_details ) && is_array( $instalment_details ) ) {
			foreach ( $instalment_details['cycle_details'] as $cycle => $instalment ) {
				if ( ! empty( $instalment ['tid'] ) && (string) $instalment ['tid'] === (string) $data ['transaction']['tid'] ) {
					$instalment_details['cycle_details'][ $cycle ]['refund_amount'] = ( ! empty( $instalment_details['cycle_details'][ $cycle ]['refund_amount'] ) ) ? $instalment_details['cycle_details'][ $cycle ]['refund_amount'] + $data ['transaction'] ['refund'] ['amount'] : $data ['transaction'] ['refund'] ['amount'];
				}
			}
		}
		if ( ! empty( $instalment_details ) ) {
			$mepr_db->update_metadata( $wpdb->prefix . 'mepr_transaction_meta', 'transaction_id', $data['transaction']['order_no'], 'novalnet_instalment', $this->mepr_novalnet_serialize_data( $instalment_details ) );
		}
	}

	/**
	 * Convert array to json data
	 *
	 * @param array $data Data to serialize.
	 * @return json $result
	 */
	public function mepr_novalnet_serialize_data( $data ) {
		$result = '';
		if ( ! empty( $data ) ) {
			$result = wp_json_encode( $data );
		}
		return $result;
	}

	/**
	 * Convert serialize data to array data
	 *
	 * @param string  $data Data to unserialize.
	 * @param boolean $need_as_array Flag to check is it need as array.
	 * @return array $result
	 */
	public function mepr_novalnet_unserialize_data( $data, $need_as_array = true ) {
		$result = array();
		if ( is_serialized( $data ) ) {
			return maybe_unserialize( $data );
		}
		$result = json_decode( $data, $need_as_array, 512, JSON_BIGINT_AS_STRING );
		if ( json_last_error() === 0 ) {
			return $result;
		}
		return $result;
	}

	/**
	 * Add transaction note
	 *
	 * @param string  $note Transaction note message.
	 * @param integer $txn_id Memberpress transaction.
	 * @return array
	 */
	public function mepr_novalnet_add_transaction_note( $note, $txn_id ) {
		if ( ! empty( $note ) && ! empty( $txn_id ) ) {
			global $wpdb;
			$mepr_db            = new MeprDb();
			$allowedhtml_cycles = array(
				'b'  => true,
				'br' => true,
			);
			$note               = wp_kses( $note, $allowedhtml_cycles );
			return $mepr_db->add_metadata( $wpdb->prefix . 'mepr_transaction_meta', 'transaction_id', $txn_id, 'novalnet_txn_notes', $note, $unique = false );
		}
	}

	/**
	 * Get stored transaction note
	 *
	 * @param integer $txn_id Memberpress transaction.
	 * @return array $notes
	 */
	public function mepr_novalnet_get_transaction_note( $txn_id ) {
		global $wpdb;
		$mepr_db = new MeprDb();
		$notes   = $mepr_db->get_metadata( $wpdb->prefix . 'mepr_transaction_meta', 'transaction_id', $txn_id, 'novalnet_txn_notes', $single = false );
		return $notes;
	}

	/**
	 * Send callback notification mail
	 *
	 * @param string $message Webhook mail message.
	 * @param string $id Payment gateway id.
	 */
	public function mepr_novalnet_send_webhook_mail( $message, $id ) {
		$mepr_options  = MeprOptions::fetch();
		$mepr_settings = json_decode( $mepr_options, true );
		$subject       = 'Novalnet Callback Script Access Report - ' . MeprUtils::blogname();
		$recipient     = ( ! empty( $mepr_settings['integrations'][ $id ]['webhook_email'] ) ) ? $mepr_settings['integrations'][ $id ]['webhook_email'] : '';
		if ( MeprUtils::is_email( $recipient ) ) {
			MeprUtils::wp_mail( $recipient, $subject, $message );
		}
	}
}

if ( ! function_exists( 'novalnet_helper' ) ) {
	/**
	 * Method to get the helper instance
	 *
	 * @return NovalnetHelper
	 */
	function novalnet_helper() {
		return NovalnetHelper::instance();
	}
}

