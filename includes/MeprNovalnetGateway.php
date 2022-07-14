<?php
/**
 * Novalnet payment addon
 *
 * This script is used for Novalnet gateway process
 *
 * @author     Novalnet AG
 * @copyright  Copyright (c) Novalnet
 * @link       https://www.novalnet.de
 * @package    Novalnetpaymentaddon
 *
 * Script: MeprNovalnetGateway.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

/**
 * MeprNovalnetGateway Payment gateway class
 *
 * @class   MeprNovalnetGateway
 */
class MeprNovalnetGateway extends MeprBaseRealGateway {
	/**
	 * Store Payment Gateway response data
	 *
	 * @var array
	 */
	private $gateway_data;
	/**
	 * Store Webhook data
	 *
	 * @var array
	 */
	public $event_data;
	/**
	 * Store the POST/GET request
	 *
	 * @var array
	 */
	public $request;
	/** Used in the view to identify the gateway */
	public function __construct() {
		$this->name = 'Novalnet';
		$this->icon = MP_NOVALNET_IMAGES_URL . 'novalnet.svg';
		$this->desc = esc_html__( 'You will be redirected. Please don`t close or refresh the browser until the payment is completed', 'memberpress-novalnet-addon' );
		$this->key  = 'novalnet';
		$this->set_defaults();
		$this->capabilities = array(
			'process-payments',
			'process-refunds',
			'create-subscriptions',
			'cancel-subscriptions',
			'suspend-subscriptions',
			'resume-subscriptions',
		);
		// Setup the notification actions for this gateway.
		$this->notifiers     = array(
			'ipn'    => 'listener',
			'cancel' => 'cancel_handler',
			'return' => 'return_handler',
		);
		$this->message_pages = array(
			'cancel'         => 'cancel_message',
			'payment_failed' => 'payment_failed_message',
		);

		add_action( 'mepr-admin-txn-form-before-user', array( $this, 'mepr_novalnet_display_txn_note' ), 1 );
		add_filter( 'mepr_transaction_email_vars', 'MeprNovalnetGateway::mepr_novalnet_add_email_vars' );
		add_filter( 'mepr_transaction_email_params', 'MeprNovalnetGateway::mepr_novalnet_add_email_params', 99, 2 );

		// Store the request data.
		$this->request = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/** Displays the transaction details on the txn edit page
	 *
	 * @param object $txn Memberpress current transaction object.
	 */
	public function mepr_novalnet_display_txn_note( $txn ) {
		if ( $txn->gateway !== $this->id ) {
			return;
		}
		$data               = novalnet_helper()->mepr_novalnet_get_response_data( $txn->id );
		$txn_details        = novalnet_helper()->mepr_novalnet_prepare_txn_details( $data );
		$notes              = novalnet_helper()->mepr_novalnet_get_transaction_note( $txn->id );
		$instalment         = novalnet_helper()->mepr_novalnet_get_instalment_data( $txn->id );
		$allowedhtml_cycles = array(
			'b'  => true,
			'br' => true,
		);

		if ( ! empty( $txn_details ) ) { ?>
		<tr valign="top">
			<th scope="row"><label><?php esc_html_e( 'Novalnet Transaction Note :', 'memberpress-novalnet-addon' ); ?></label></th>
			<td>
				<div>
					<?php
					echo wp_kses( $txn_details, $allowedhtml_cycles );
					?>
				</div>
			</td>
		</tr>
			<?php
		}
		if ( ! empty( $instalment ) ) {
			?>
		<tr valign="top">
			<th scope="row"><label><?php esc_html_e( 'Novalnet Instalment Cycles :', 'memberpress-novalnet-addon' ); ?></label></th>
			<td>
			<div>
			<?php
			foreach ( $instalment as $key => $cycle ) {
				$amount = novalnet_helper()->mepr_novalnet_format_amount( $cycle['amount'] );
				echo wp_kses( '<b>' . __( 'Cycle', 'memberpress-novalnet-addon' ) . " {$key}</b><br>", $allowedhtml_cycles );
				echo wp_kses( '' . __( 'Date', 'memberpress-novalnet-addon' ) . " : {$cycle['date']}<br>", $allowedhtml_cycles );
				echo wp_kses( '' . __( 'Amount', 'memberpress-novalnet-addon' ) . " : $amount<br>", $allowedhtml_cycles );
				if ( isset( $cycle['tid'] ) ) {
					echo wp_kses( "TID : {$cycle['tid']}<br>", $allowedhtml_cycles );
				}
				echo wp_kses( '' . __( 'Status', 'memberpress-novalnet-addon' ) . " : {$cycle['status_text']}<br><br>", $allowedhtml_cycles );
			}
			?>
			</div>
			</td>
		</tr>
		<?php } ?>
		<?php if ( ! empty( $notes ) ) { ?>
		<tr valign="top">
			<th scope="row"><label><?php esc_html_e( 'Novalnet Transaction History :', 'memberpress-novalnet-addon' ); ?></label></th>
			<td>
				<div>
				<?php
				foreach ( $notes as $key => $note ) {
					echo wp_kses( $note . '<br>', $allowedhtml_cycles );
				}
				?>
				</div>
			</td>
		</tr>
		<?php } ?>
		<?php
	}

	/**
	 * Add custom email variable to append transaction details
	 *
	 * @param array $email_vars Memberpress transaction email variables.
	 * @return array $email_vars
	 */
	public static function mepr_novalnet_add_email_vars( $email_vars ) {
		$email_vars[] .= 'novalnet_txn_details';
		return $email_vars;
	}

	/**
	 * Update custom email variable values
	 *
	 * @param array  $params Memberpress transaction email params.
	 * @param object $txn Memberpress transaction.
	 * @return array $params
	 */
	public static function mepr_novalnet_add_email_params( $params, $txn ) {
		$allowedhtml_cycles                = array(
			'b'  => true,
			'br' => true,
		);
		$data                              = novalnet_helper()->mepr_novalnet_get_response_data( $txn->id );
		$txn_details                       = novalnet_helper()->mepr_novalnet_prepare_txn_details( $data );
		$params['novalnet_txn_details']    = wp_kses( $txn_details, $allowedhtml_cycles );
		$params['order_no']                = $txn->id;
		$params['site_url']                = site_url();
		$params['novalnet_payment_method'] = novalnet_helper()->mepr_novalnet_get_novalnet_txn_type( $data['transaction']['payment_type'] );
		return $params;
	}

	/**
	 * Initialize the gateway settings object with base gateway settings
	 *
	 * @param object $settings Payment gateway settings object.
	 */
	public function load( $settings ) {
		$this->settings = (object) $settings;
		$this->set_defaults();
	}

	/** Update the default values for the gateway settings object */
	protected function set_defaults() {
		if ( ! isset( $this->settings ) ) {
			$this->settings = array();
		}
		$this->settings  = (object) array_merge(
			array(
				'gateway'            => 'MeprNovalnetGateway',
				'id'                 => $this->generate_id(),
				'label'              => '',
				'icon'               => MP_NOVALNET_IMAGES_URL . 'novalnet.svg',
				'use_label'          => true,
				'use_icon'           => true,
				'desc'               => esc_html__( 'You will be redirected. Please don`t close or refresh the browser until the payment is completed', 'memberpress-novalnet-addon' ),
				'use_desc'           => false,
				'test_mode'          => false,
				'payment_action'     => 'capture',
				'webhook_ip_control' => false,
				'enforce_3d'         => false,
			),
			(array) $this->settings
		);
		$this->id        = $this->settings->id;
		$this->label     = $this->settings->label;
		$this->use_label = $this->settings->use_label;
		$this->icon      = $this->settings->icon;
		$this->use_icon  = $this->settings->use_icon;
		$this->desc      = $this->settings->desc;
		$this->use_desc  = $this->settings->use_desc;
	}

	/** Displays the form on the MemberPress Options page */
	public function display_options_form() {
		$mepr_options               = MeprOptions::fetch();
		$test_mode                  = ( 'on' === $this->settings->test_mode || true === $this->settings->test_mode );
		$api_signature              = trim( $this->settings->api_signature );
		$payment_access_key         = trim( $this->settings->payment_access_key );
		$project_id                 = trim( $this->settings->project_id );
		$tariff_id                  = trim( $this->settings->tariff_id );
		$saved_tariff_id            = trim( $this->settings->saved_tariff_id );
		$subs_tariff_id             = trim( $this->settings->subs_tariff_id );
		$saved_subs_tariff_id       = trim( $this->settings->saved_subs_tariff_id );
		$payment_action             = trim( 'authorize' === $this->settings->payment_action ) ? 'authorize' : 'capture';
		$webhook_ip_control         = ( 'on' === $this->settings->webhook_ip_control || true === $this->settings->webhook_ip_control );
		$webhook_email              = trim( $this->settings->webhook_email );
		$enforce_3d                 = ( 'on' === $this->settings->enforce_3d || true === $this->settings->enforce_3d );
		$sepa_due_date              = trim( $this->settings->sepa_duedate );
		$invoice_due_date           = trim( $this->settings->invoice_duedate );
		$slip_expiry_date           = trim( $this->settings->slip_expiry_date );
		$selected_instalment_cycles = trim( $this->settings->selected_instalment_cycles );

		$allowedhtml = array(
			'textarea' => array(
				'id'    => array(),
				'class' => array(),
				'name'  => array(),
				'rows'  => array(),
				'cols'  => array(),
			),
			'input'    => array(
				'type'         => array(),
				'name'         => array(),
				'autocomplete' => array(),
				'id'           => array(),
				'value'        => array(),
				'min'          => array(),
				'max'          => array(),
				'checked'      => array(),
				'data-value'   => array(),
				'class'        => array(),
			),
			'select'   => array(
				'name'  => array(),
				'class' => array(),
				'id'    => array(),
				'value' => array(),
			),
			'option'   => array(
				'id'       => array(),
				'value'    => array(),
				'selected' => array(),
			),
		);
		?>
		<!-- Configuration fields start -->
		<div>
			<table>
				<!-- Global configuration -->
				<tr>
					<td><h3><?php esc_html_e( 'Novalnet API Configuration', 'memberpress-novalnet-addon' ); ?></h3></td>
				</tr>
				<tr>
					<td>
						<strong><?php esc_html_e( 'Product activation key ', 'memberpress-novalnet-addon' ); ?></strong>
						<?php
							MeprAppHelper::info_tooltip(
								'mepr-novalnet-api_signature',
								__( 'Product activation key', 'memberpress-novalnet-addon' ),
								__( 'Your product activation key is a unique token for merchant authentication and payment processing. Get your Product activation key from the <a href="https://admin.novalnet.de/">Novalnet Admin Portal</a> : PROJECT > Choose your project > Shop Parameters > API Signature (Product activation key)', 'memberpress-novalnet-addon' )
							);
						?>
					</td>
					<td>
						<?php
						echo wp_kses(
							"<input type='text' class='mepr-auto-trim' autocomplete='OFF' name='{$mepr_options->integrations_str}[{$this->id}][api_signature]' id='api_signature' value='{$api_signature}'>",
							$allowedhtml
						);
						?>
					</td>
				</tr>
				<tr>
					<td>
						<strong><?php esc_html_e( 'Payment access key', 'memberpress-novalnet-addon' ); ?></strong>
						<?php
							MeprAppHelper::info_tooltip(
								'mepr-novalnet-payment_access_key',
								__( 'Payment access key', 'memberpress-novalnet-addon' ),
								__( 'Your secret key used to encrypt the data to avoid user manipulation and fraud.', 'memberpress-novalnet-addon' )
							);
						?>
						</td>
					<td>
						<?php
						echo wp_kses(
							"<input type='text' class='mepr-auto-trim' autocomplete='OFF' name='{$mepr_options->integrations_str}[{$this->id}][payment_access_key]' id='payment_access_key' value='{$payment_access_key}'>",
							$allowedhtml
						);
						?>
					</td>
				</tr>
				<tr>
					<td></td>
					<td>
						<?php
						echo wp_kses( "<input type='checkbox' name='{$mepr_options->integrations_str}[{$this->id}][test_mode]' id='test_mode' data-value='{$this->id}' " . checked( $test_mode, true, false ) . '/>', $allowedhtml );
						?>
						<label for="test_mode"><strong><?php esc_html_e( 'Enable test mode', 'memberpress-novalnet-addon' ); ?></strong></label>
						<?php
							MeprAppHelper::info_tooltip(
								'mepr-novalnet-test_mode',
								__( 'Enable test mode', 'memberpress-novalnet-addon' ),
								__( 'The payment will be processed in the test mode therefore amount for this transaction will not be charged.', 'memberpress-novalnet-addon' )
							);
						?>
					</td>
				</tr>
				<tr class="mepr_hidden">
					<td><strong><?php esc_html_e( 'Project ID', 'memberpress-novalnet-addon' ); ?></strong></td>
					<td>
						<?php
						echo wp_kses(
							"<input type='text' class='mepr-auto-trim' name='{$mepr_options->integrations_str}[{$this->id}][project_id]' id='project_id' value='{$project_id}'>",
							$allowedhtml
						);
						?>
					</td>
				</tr>
				<tr>
					<td>
						<strong><?php esc_html_e( 'Select Tariff ID', 'memberpress-novalnet-addon' ); ?></strong>
						<?php
							MeprAppHelper::info_tooltip(
								'mepr-novalnet-tariff_id',
								__( 'Select Tariff ID', 'memberpress-novalnet-addon' ),
								__( 'Select a Tariff ID to match the preferred tariff plan you created at the Novalnet Admin Portal for this project', 'memberpress-novalnet-addon' )
							);
						?>
					</td>
					<td>
						<?php
						echo wp_kses(
							"<input type='text' class='mepr-auto-trim' name='{$mepr_options->integrations_str}[{$this->id}][tariff_id]' id='tariff_id' data-value='{$tariff_id}'>",
							$allowedhtml
						);
						echo wp_kses(
							"<input type='hidden' class='mepr-auto-trim' autocomplete='OFF' name='{$mepr_options->integrations_str}[{$this->id}][saved_tariff_id]' id='saved_tariff_id' value='{$saved_tariff_id}'>",
							$allowedhtml
						);
						?>
					</td>
				</tr>
				<tr>
					<td>
						<strong><?php esc_html_e( 'Select Subscription Tariff ID', 'memberpress-novalnet-addon' ); ?></strong>
						<?php
							MeprAppHelper::info_tooltip(
								'mepr-novalnet-subs_tariff_id',
								__( 'Select Subscription Tariff ID', 'memberpress-novalnet-addon' ),
								__( 'Select the preferred Novalnet subscription tariff ID available for your project. For more information, please refer the Installation Guide', 'memberpress-novalnet-addon' )
							);
						?>
					</td>
					<td>
						<?php
						echo wp_kses(
							"<input type='text' class='mepr-auto-trim' name='{$mepr_options->integrations_str}[{$this->id}][subs_tariff_id]' id='subs_tariff_id' data-value='{$subs_tariff_id}'>",
							$allowedhtml
						);
						echo wp_kses(
							"<input type='hidden' class='mepr-auto-trim' autocomplete='OFF' name='{$mepr_options->integrations_str}[{$this->id}][saved_subs_tariff_id]' id='saved_subs_tariff_id' value='{$saved_subs_tariff_id}'>",
							$allowedhtml
						);
						?>
					</td>
				</tr>
				<tr>
					<td>
						<strong><?php esc_html_e( 'Payment action:', 'memberpress-novalnet-addon' ); ?></strong>
						<?php
						MeprAppHelper::info_tooltip(
							'mepr-novalnet-payment_action',
							__( 'Payment action', 'memberpress-novalnet-addon' ),
							__( 'Choose whether or not the payment should be charged immediately. Capture completes the transaction by transferring the funds from buyer account to merchant account. Authorize verifies payment details and reserves funds to capture it later, giving time for the merchant to decide on the order.', 'memberpress-novalnet-addon' )
						);
						?>
					</td>
					<td>
						<?php
						echo wp_kses(
							"<select class='mepr-auto-trim' name='{$mepr_options->integrations_str}[{$this->id}][payment_action]' id='payment_action' value='{$payment_action}'>
						<option id='capture' value='capture' " . selected( $payment_action, 'capture', false ) . '>' . __( 'Capture', 'memberpress-novalnet-addon' ) . "</option>
						<option id='authorize' value='authorize' " . selected( $payment_action, 'authorize', false ) . '>' . __( 'Authorize', 'memberpress-novalnet-addon' ) . '</option>
						</select>',
							$allowedhtml
						);
						?>
					</td>
				</tr>
				<tr>
					<td colspan=2><h2><?php esc_html_e( 'Notification / Webhook URL Setup', 'memberpress-novalnet-addon' ); ?> </h2></td>
				</tr>
				<tr>
					<td>
						<strong><?php esc_html_e( 'Notification / Webhook URL', 'memberpress-novalnet-addon' ); ?></strong>
						<?php
							MeprAppHelper::info_tooltip(
								'mepr-novalnet-webhook',
								__( 'Notification / Webhook URL', 'memberpress-novalnet-addon' ),
								__( 'You must configure the webhook endpoint in your <a href="https://admin.novalnet.de" target="_blank">Novalnet Admin portal</a>. This will allow you to receive notifications about the transaction', 'memberpress-novalnet-addon' )
							);
						?>
					</td>
					<td id="webhook_url" >
						<?php MeprAppHelper::clipboard_input( $this->notify_url( 'ipn' ), 'memberpress-novalnet-webhook-url' ); ?>
						<br><button class="button" style="margin-top:10px" id="webhook_configure" onclick= "";> <?php esc_html_e( 'Configure', 'memberpress-novalnet-addon' ); ?> </button>
					</td>
				</tr>
				<tr>
					<td></td>
					<td>
						<?php
						echo wp_kses( "<input type='checkbox' name='{$mepr_options->integrations_str}[{$this->id}][webhook_ip_control]' id='webhook_ip_control' data-value='{$this->id}' " . checked( $webhook_ip_control, true, false ) . '/>', $allowedhtml );
						?>
						<label for="webhook_ip_control"><strong><?php esc_html_e( 'Allow manual testing of the Notification / Webhook URL', 'memberpress-novalnet-addon' ); ?></strong></label>
					</td>
				</tr>
				<tr>
					<td>
						<strong><?php esc_html_e( 'Sent e-mail to', 'memberpress-novalnet-addon' ); ?></strong>
						<?php
							MeprAppHelper::info_tooltip(
								'mepr-novalnet-webhook_email',
								__( 'Sent e-mail to', 'memberpress-novalnet-addon' ),
								__( 'Notification / Webhook URL execution messages will be sent to this e-mail', 'memberpress-novalnet-addon' )
							);
						?>
					</td>
					<td>
						<?php
						echo wp_kses(
							"<input type='text' class='mepr-auto-trim' autocomplete='OFF' name='{$mepr_options->integrations_str}[{$this->id}][webhook_email]' id='webhook_email' value='{$webhook_email}'>",
							$allowedhtml
						);
						?>
					</td>
				</tr>
				<!-- Credit Card configuration -->
				<tr>
					<td colspan=2><h3><?php esc_html_e( 'Credit/Debit Cards', 'memberpress-novalnet-addon' ); ?> </h3>	</td>
				</tr>
				<tr>
					<td></td>
					<td>
						<?php
						echo wp_kses( "<input type='checkbox' name='{$mepr_options->integrations_str}[{$this->id}][enforce_3d]' id='enforce_3d' data-value='{$this->id}' " . checked( $enforce_3d, true, false ) . '/>', $allowedhtml );
						?>
						<label for="enforce_3d"><strong><?php esc_html_e( 'Enforce 3D Secure payments outside the EU', 'memberpress-novalnet-addon' ); ?> </strong></label>
						<?php
							MeprAppHelper::info_tooltip(
								'mepr-novalnet-enforce_3d',
								__( 'Enforce 3D Secure payments outside the EU', 'memberpress-novalnet-addon' ),
								__( 'By enabling this option, all payments from cards issued outside the EU will be authenticated via 3DS 2.0 SCA.', 'memberpress-novalnet-addon' )
							);
						?>
					</td>
				</tr>
				<!-- SEPA configuration -->
				<tr><td colspan=2><h3><?php esc_html_e( 'Direct Debit SEPA', 'memberpress-novalnet-addon' ); ?> </h2></td></tr>
				<tr>
					<td>
						<strong><?php esc_html_e( 'Payment due date (in days)', 'memberpress-novalnet-addon' ); ?></strong>
						<?php
							MeprAppHelper::info_tooltip(
								'mepr-novalnet-sepa_duedate',
								__( 'Payment due date', 'memberpress-novalnet-addon' ),
								__( 'Number of days after which the payment is debited (must be between 2 and 14 days).', 'memberpress-novalnet-addon' )
							);
						?>
					</td>
					<td>
						<?php
						echo wp_kses(
							"<input type='number' class='mepr-auto-trim' autocomplete='OFF' name='{$mepr_options->integrations_str}[{$this->id}][sepa_duedate]' id='sepa_duedate' min=2 max=14 value='{$sepa_due_date}'>",
							$allowedhtml
						);
						?>
					</td>
				</tr>
				<!-- Invoice/ Prepayment configuration -->
				<tr><td colspan=2><h3> <?php esc_html_e( 'Invoice/ Prepayment', 'memberpress-novalnet-addon' ); ?> </h3></td></tr>
				<tr>
					<td>
						<strong><?php esc_html_e( 'Payment due date (in days)', 'memberpress-novalnet-addon' ); ?></strong>
						<?php
							MeprAppHelper::info_tooltip(
								'mepr-novalnet-invoice_duedate',
								__( 'Payment due date', 'memberpress-novalnet-addon' ),
								__( 'Number of days given to the buyer to transfer the amount to Novalnet (must be greater than 7 days). If this field is left blank, 14 days will be set as due date by default', 'memberpress-novalnet-addon' )
							);
						?>
					</td>
					<td>
						<?php
						echo wp_kses(
							"<input type='number' class='mepr-auto-trim' autocomplete='OFF' name='{$mepr_options->integrations_str}[{$this->id}][invoice_duedate]' id='invoice_duedate' min=7 value='{$invoice_due_date}'>",
							$allowedhtml
						);
						?>
					</td>
				</tr>
				<!-- Cash payment configuration -->
				<tr><td colspan=2><h3><?php esc_html_e( 'Barzahlen/viacash', 'memberpress-novalnet-addon' ); ?></h3></td></tr>
				<tr>
					<td>
						<strong><?php esc_html_e( 'Slip expiry date (in days)', 'memberpress-novalnet-addon' ); ?></strong>
						<?php
							MeprAppHelper::info_tooltip(
								'mepr-novalnet-slip_expiry_date',
								__( 'Slip expiry date', 'memberpress-novalnet-addon' ),
								__( 'Number of days given to the buyer to pay at a store. If this field is left blank, 14 days will be set as slip expiry date by default.', 'memberpress-novalnet-addon' )
							);
						?>
					</td>
					<td>
						<?php
						echo wp_kses(
							"<input type='number' class='mepr-auto-trim' autocomplete='OFF' name='{$mepr_options->integrations_str}[{$this->id}][slip_expiry_date]' id='slip_expiry_date' min=1 value='{$slip_expiry_date}'>",
							$allowedhtml
						);
						?>
					</td>
				</tr>
				<!-- Instalment configuration -->
				<tr>
					<td colspan=2><h3><?php esc_html_e( 'Instalment by Invoice/ Instalment by Direct Debit SEPA', 'memberpress-novalnet-addon' ); ?></h3></td>
				</tr>
				<tr>
					<td>
						<strong><?php esc_html_e( 'Instalment cycles (separated by comma)', 'memberpress-novalnet-addon' ); ?></strong>
						<?php
							MeprAppHelper::info_tooltip(
								'mepr-novalnet-instalment_cycles',
								__( 'Instalment cycles', 'memberpress-novalnet-addon' ),
								__( 'Select the instalment cycles that can be availed in the instalment plan', 'memberpress-novalnet-addon' )
							);
						?>
					</td>
					<td>
						<?php
						echo wp_kses(
							"<input type='text' class='mepr-auto-trim' autocomplete='OFF' name='{$mepr_options->integrations_str}[{$this->id}][selected_instalment_cycles]' id='selected_instalment_cycles' value='{$selected_instalment_cycles}'>",
							$allowedhtml
						);
						?>
					</td>
				</tr>
				<!-- Payment Description -->
				<tr><td colspan=2><h3><?php esc_html_e( 'Payment Description', 'memberpress-novalnet-addon' ); ?></h3></td></tr>
				<tr>
					<td colspan=2>
					<?php
					echo wp_kses(
						"<textarea name='{$mepr_options->integrations_str}[{$this->id}][desc]' rows=3 cols=45>{$this->settings->desc}</textarea>",
						$allowedhtml
					);
					?>
					</td>
				</tr>
			</table>
			</div>
		<?php
	}

	/**
	 * Validates the form on the MemberPress Options page
	 *
	 * @param array $errors Parameters to store errors.
	 * @return array $errors
	 */
	public function validate_options_form( $errors ) {
		$mepr_options = MeprOptions::fetch();
		if ( ( empty( $_POST[ $mepr_options->integrations_str ][ $this->id ]['api_signature'] ) || empty( $_POST[ $mepr_options->integrations_str ][ $this->id ]['payment_access_key'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$errors[] = esc_html__( 'Please fill in all the mandatory fields', 'memberpress-novalnet-addon' );
		}
		return $errors;
	}

	/**
	 * This gets called on the 'init' hook when the signup form is processed
	 *
	 * @param object $txn Memberpress transaction.
	 *
	 * @throws Exception If the gateway id is mismatched with id.
	 */
	public function display_payment_page( $txn ) {
		if ( ! empty( $_SESSION['response'] ) ) { // If session value sets.
			unset( $_SESSION['response'] );
		}
		$mepr_options = MeprOptions::fetch();
		if ( isset( $txn ) && ( $txn instanceof MeprTransaction ) ) {
			$product = $txn->product();
		} else {
			throw new Exception( __( 'Sorry, we couldn\'t complete the transaction. Try back later.', 'memberpress-novalnet-addon' ) );
		}

		if ( $txn->amount <= 0.00 ) { // If the amount is zero then create free transaction.
			MeprTransaction::create_free_transaction( $txn );
			return;
		}

		if ( $txn->gateway === $this->id ) {
			$parameters    = array();
			$user          = $txn->user();
			$txn_details   = json_decode( $txn, true );
			$mepr_options  = json_decode( $mepr_options, true );
			$mepr_settings = $mepr_options['integrations'][ $this->id ];
			novalnet_helper()->mepr_novalnet_form_merchant_params( $mepr_settings, $parameters );
			novalnet_helper()->mepr_novalnet_form_customer_params( $user, $parameters );
			$endpoint_action           = ( 'authorize' != $mepr_settings['payment_action'] ) ? 'seamless_payment' : 'seamless_authorize'; //phpcs:ignore WordPress.PHP.StrictComparisons
			$parameters['transaction'] = array(
				'currency'         => $mepr_options['currency_code'],
				'amount'           => novalnet_helper()->mepr_novalnet_convert_smaller_currency_unit( $txn_details['total'] ),
				'test_mode'        => ( 'on' === $mepr_options['integrations'][ $this->id ]['test_mode'] ) ? '1' : '0',
				'order_no'         => $txn->id,
				'return_url'       => novalnet_helper()->mepr_novalnet_form_url( $this->notify_url( 'return' ), $txn ),
				'error_return_url' => novalnet_helper()->mepr_novalnet_form_url( $this->notify_url( 'cancel' ), $txn ),
			);
			if ( isset( $mepr_options['integrations'][ $this->id ]['enforce_3d'] ) && 'on' === $mepr_options['integrations'][ $this->id ]['enforce_3d'] ) {
				$parameters['transaction']['enforce_3d'] = 1;
			}
			novalnet_helper()->mepr_novalnet_form_hosted_page_params( $parameters );
			novalnet_helper()->mepr_novalnet_form_custom_params( $parameters );
			novalnet_helper()->mepr_novalnet_form_due_date_params( $mepr_settings, $parameters );
			if ( $txn->subscription() ) { // If subscription transaction.
				novalnet_helper()->mepr_novalnet_form_subscription_params( $mepr_settings, $parameters, $txn );
			}
			$response = novalnet_helper()->mepr_novalnet_send_request( $parameters, novalnet_helper()->mepr_novalnet_format_endpoint( $endpoint_action ), $this->settings->payment_access_key ); // Returned from Novalnet for getting redirect URL.
			if ( ! empty( $response['result'] ) && 'SUCCESS' === $response['result']['status'] ) {
				$_SESSION['response'] = $response;
				return;
			} else {
				MeprUtils::wp_redirect( novalnet_helper()->mepr_novalnet_message_page_url( $this->id, $prd, 'cancel' ) );
			}
		}
		throw new Exception( __( 'Sorry, we couldn\'t complete the transaction. Try back later.', 'memberpress-novalnet-addon' ) );
	}

	/**
	 * This gets called on the_content and just renders the payment form
	 * We're loading up a hidden form and submitting it with JS
	 *
	 * @param float   $amount Payment amount.
	 * @param object  $user Current user details.
	 * @param integer $product_id Memberpress product id.
	 * @param integer $transaction_id Memberpress transaction id.
	 */
	public function display_payment_form( $amount, $user, $product_id, $transaction_id ) {
		$prd = new MeprProduct( $product_id );
		if ( empty( $_SESSION['response']['result']['redirect_url'] ) ) {
			MeprUtils::wp_redirect( novalnet_helper()->mepr_novalnet_message_page_url( $this->id, $prd, 'cancel' ) );
		}
		$mepr_options = MeprOptions::fetch();
		$txn          = new MeprTransaction( $transaction_id );

		if ( $mepr_options->enable_spc ) {
			?>
			<script type="text/javascript">
			setTimeout(function() {
				document.getElementById( "mepr_novalnet_form" ).submit();
			}, 1000); //Let's wait one second to let some stuff load up.
			</script>
			<?php
		} else {
			$allowedhtml_cycles = array(
				'table' => array(
					'class' => array(),
				),
				'th'    => array(
					'class' => array(),
				),
				'td'    => array(
					'class' => array(),
				),
				'tr'    => array(
					'class' => array(),
				),
			);
			$invoice            = MeprTransactionsHelper::get_invoice( $txn );
			echo wp_kses( $invoice, $allowedhtml_cycles );
		}
		$redirect_url = esc_url_raw( $_SESSION['response']['result']['redirect_url'] );
		?>
		<p id="novalnet_redirecting_message"><?php esc_html_e( 'You will be redirected to the payment page to complete the payment', 'memberpress-novalnet-addon' ); ?></p>
		<div class="mp_wrapper mp_payment_form_wrapper">
			<div class="mp_wrapper mp_payment_form_wrapper">
				<?php
				echo wp_kses(
					"<form action='{$redirect_url}' method='post' id='mepr_novalnet_form' class='mepr-checkout-form mepr-form mepr-card-form'>",
					array(
						'form' => array(
							'action' => true,
							'method' => true,
							'id'     => true,
							'class'  => true,
						),
					)
				);
				?>
					<div class="mepr_spacer">&nbsp;</div>
					<?php
					if ( ! $mepr_options->enable_spc ) {
						echo wp_kses(
							"<input type='submit' class='mepr-submit' id='mepr_nn_form_submit' value='" . esc_attr__( 'Pay Now', 'memberpress-novalnet-addon' ) . "'/>",
							array(
								'input' => array(
									'type'  => true,
									'class' => true,
									'id'    => true,
									'value' => true,
								),
							)
						);
					}
					?>
				</form>
			</div>
			<script>
			jQuery(document).ready(function ($) {
				$( '#mepr_novalnet_form' ).submit(function() {
					$( '#mepr_nn_form_submit' ).attr( 'disabled',true );
					$( '#mepr_nn_form_submit' ).prop( 'disabled',true );
					setTimeout(function() {
						$( '#mepr_nn_form_submit' ).removeAttr( 'disabled' );
						$( '#mepr_nn_form_submit' ).prop( 'disabled',false );
					}, 5000);
				});
			});
			</script>
		</div>
		<?php
	}

	/*** Failure transaction from Novalnet lands here */
	public function cancel_handler() {
		$mepr_options = MeprOptions::fetch();
		if ( ! empty( $this->request['txn_id'] ) && is_numeric( $this->request['txn_id'] ) ) { // If shop's txn id is available.
			$this->gateway_data['txn_data'] = array(
				'txn_id'       => sanitize_text_field( $this->request['txn_id'] ),
				'txn_num'      => ! empty( $this->request['tid'] ) ? sanitize_text_field( $this->request['tid'] ) : sanitize_text_field( $this->request['txn_num'] ),
				'status_text'  => sanitize_text_field( $this->request['status_text'] ),
				'payment_type' => ( isset( $this->request['payment_type'] ) && ! empty( $this->request['payment_type'] ) ) ? sanitize_text_field( $this->request['payment_type'] ) : '',
			);
			$txn                            = new MeprTransaction( $this->gateway_data['txn_data']['txn_id'] );
			$txn->store();

			if ( $txn->subscription_id > 0 ) { // If subscription membership.
				$sub         = $txn->subscription();
				$sub->status = MeprSubscription::$pending_str;
				$sub->store();
			}
			$this->record_payment_failure();
			if ( isset( $txn->product_id ) && $txn->product_id > 0 ) { // If product id is available.
				$product = new MeprProduct( $txn->product_id );
				if ( empty( $this->request['tid'] ) ) { // If Novalnet TID is available.
					MeprUtils::wp_redirect( novalnet_helper()->mepr_novalnet_message_page_url( $this->id, $product, 'cancel' ) );
				} else {
					MeprUtils::wp_redirect( novalnet_helper()->mepr_novalnet_message_page_url( $this->id, $product, 'payment_failed' ) );
				}
			} else {
				// If all else fails, just send them to their account page.
				MeprUtils::wp_redirect( $mepr_options->account_page_url( 'action=subscriptions' ) );
			}
		} else {
			// If all else fails, just send them to their account page.
			MeprUtils::wp_redirect( $mepr_options->account_page_url( 'action=subscriptions' ) );
		}
	}

	/** Used to record a declined payment */
	public function record_payment_failure() {
		$txn = new MeprTransaction( $this->gateway_data['txn_data']['txn_id'] );
		if ( MeprTransaction::$failed_str !== $txn->status ) {
			$txn->status    = MeprTransaction::$failed_str;
			$txn->trans_num = $this->gateway_data['txn_data']['txn_num'];
			if ( $txn->subscription_id > 0 ) { // If subscription txn.
				$sub       = $txn->subscription();
				$first_txn = $sub->first_txn();
				if ( false === $first_txn || ! ( $first_txn instanceof MeprTransaction ) ) { // If initial transaction.
					$coupon_id = $sub->coupon_id;
				} else {
					$coupon_id = $first_txn->coupon_id;
				}
				$txn->user_id         = $sub->user_id;
				$txn->product_id      = $sub->product_id;
				$txn->coupon_id       = $coupon_id;
				$txn->txn_type        = MeprTransaction::$payment_str;
				$txn->subscription_id = $sub->id;
				$txn->gateway         = $this->id;
				// If first payment fails, Novalnet will not set up the subscription, so we need to mark it as cancelled in memberpress.
				if ( 0 === $sub->txn_count && ! ( $sub->trial && 0.00 === $sub->trial_amount ) ) {
					$sub->status = MeprSubscription::$cancelled_str;
				}
				$sub->expire_txns(); // Expire associated transactions for the old subscription.
				$sub->store();
			}
			if ( isset( $this->gateway_data['txn_data']['status_text'] ) && ! empty( $this->gateway_data['txn_data']['status_text'] ) ) {
				$message = '';
				if ( isset( $this->gateway_data['txn_data']['payment_type'] ) && '' !== $this->gateway_data['txn_data']['payment_type'] ) {
					$message .= novalnet_helper()->mepr_novalnet_get_novalnet_txn_type( $this->gateway_data['txn_data']['payment_type'] ) . '<br>';
				}
				if ( false === strpos( $this->gateway_data['txn_data']['txn_num'], 'mp-txn' ) ) {
					/* translators: %s:txn_num*/
					$message .= '' . sprintf( __( 'Novalnet transaction ID: %s', 'memberpress-novalnet-addon' ), $this->gateway_data['txn_data']['txn_num'] ) . '<br>';
				}
				$message .= $this->gateway_data['txn_data']['status_text'];
				novalnet_helper()->mepr_novalnet_add_transaction_note( $message, $txn->id );
			}
			$txn->store();
			MeprUtils::send_failed_txn_notices( $txn );
		}
		return $txn;
	}

	/*** Success transaction from Novalnet lands here */
	public function return_handler() {
		$novalnet_txn_secret            = isset( $_SESSION['response']['transaction']['txn_secret'] ) ? sanitize_text_field( wp_unslash( $_SESSION['response']['transaction']['txn_secret'] ) ) : sanitize_text_field( $this->request['txn_secret'] );
		$this->gateway_data['txn_data'] = array(
			'txn_id'     => sanitize_text_field( $this->request['txn_id'] ),
			'txn_num'    => ! empty( $this->request['tid'] ) ? sanitize_text_field( $this->request['tid'] ) : sanitize_text_field( $this->request['txn_num'] ),
			'txn_secret' => $novalnet_txn_secret,
			'checksum'   => sanitize_text_field( $this->request['checksum'] ),
			'status'     => sanitize_text_field( $this->request['status'] ),
		);
		$txn                            = new MeprTransaction( (int) $this->gateway_data['txn_data']['txn_id'] );
		$product                        = new MeprProduct( $txn->product_id );
		if ( ! empty( $this->gateway_data['txn_data']['checksum'] ) && ! empty( $this->gateway_data['txn_data']['txn_num'] ) && $this->gateway_data['txn_data']['txn_secret'] && ! empty( $this->gateway_data['txn_data']['status'] ) && 'SUCCESS' === $this->gateway_data['txn_data']['status'] ) { // If checksum received.
			$mepr_options       = MeprOptions::fetch();
			$token_string       = $this->gateway_data['txn_data']['txn_num'] . $this->gateway_data['txn_data']['txn_secret'] . $this->gateway_data['txn_data']['status'] . strrev( $this->settings->payment_access_key );
			$generated_checksum = hash( 'sha256', $token_string );
			if ( $generated_checksum !== $this->gateway_data['txn_data']['checksum'] ) { // Check incoming checksum and generated checksum are equal or not.
				$this->gateway_data['txn_data']['status_text'] = __( 'While redirecting some data has been changed. The hash check failed', 'memberpress-novalnet-addon' );
				$this->record_payment_failure();
				MeprUtils::wp_redirect( novalnet_helper()->mepr_novalnet_message_page_url( $this->id, $product, 'payment_failed' ) );
			} else {
				if ( ! empty( $txn->id ) ) {
					$sub             = $txn->subscription();
					$sanitized_title = sanitize_title( $product->post_title );
					$query_params    = array(
						'membership'    => $sanitized_title,
						'trans_num'     => $txn->trans_num,
						'membership_id' => $product->ID,
					);
					if ( $txn->subscription_id > 0 ) {
						$sub          = $txn->subscription();
						$query_params = array_merge( $query_params, array( 'subscr_id' => $sub->subscr_id ) );
					}
					// If webhook already received.
					if ( strpos( $txn->trans_num, 'mp-txn' ) === false ) {
						MeprUtils::wp_redirect( $mepr_options->thankyou_page_url( build_query( $query_params ) ) );
					}

					$transaction_details = array( 'transaction' => array( 'tid' => $this->gateway_data['txn_data']['txn_num'] ) );
					$response            = novalnet_helper()->mepr_novalnet_send_request( $transaction_details, novalnet_helper()->mepr_novalnet_format_endpoint( 'transaction_details' ), $this->settings->payment_access_key );
					if ( isset( $response['instalment'] ) && 'ON_HOLD' !== $response['transaction']['status'] ) {
						novalnet_helper()->mepr_novalnet_store_instalment_data( $response );
					}
					novalnet_helper()->mepr_novalnet_store_response_data( $response, $txn->id );
					$this->gateway_data['txn_data']['payment_type'] = $response['transaction']['payment_type'];
					$this->gateway_data['txn_data']['status']       = $response['transaction']['status'];
					$this->gateway_data['txn_data']['amount']       = $response['transaction']['amount'];
					if ( $sub ) {
						$this->gateway_data['txn_data']['next_cycle'] = $response['subscription']['next_cycle_date'];
						$this->record_create_subscription();
					} else {
						$this->record_payment();
					}
					MeprUtils::wp_redirect( $mepr_options->thankyou_page_url( build_query( $query_params ) ) );
				} else {
					$this->gateway_data['txn_data']['status_text'] = __( 'Transaction ID not found', 'memberpress-novalnet-addon' );
					$this->record_payment_failure();
					MeprUtils::wp_redirect( novalnet_helper()->mepr_novalnet_message_page_url( $this->id, $product, 'payment_failed' ) );
				}
			}
		} else {
			$this->gateway_data['txn_data']['status_text'] = ! empty( $this->request['status_text'] ) ? sanitize_text_field( $this->request['status_text'] ) : __( 'Some data was missing', 'memberpress-novalnet-addon' );
			$this->record_payment_failure();
			MeprUtils::wp_redirect( novalnet_helper()->mepr_novalnet_message_page_url( $this->id, $product, 'payment_failed' ) );
		}
	}

	/** Used to record a successful payment */
	public function record_payment() {
		if ( empty( $this->gateway_data['txn_data']['txn_id'] ) ) {
			return false;
		}
		$txn = new MeprTransaction( $this->gateway_data['txn_data']['txn_id'] );
		// If the txn has already completed.
		if ( MeprTransaction::$complete_str === $txn->status && $txn->trans_num === $this->gateway_data['txn_data']['txn_num'] ) {
			return false;
		}
		$txn->trans_num = $this->gateway_data['txn_data']['txn_num'];
		$txn->txn_type  = MeprTransaction::$payment_str;

		$txn->status = $this->mepr_novalnet_get_transaction_status( $this->gateway_data['txn_data']['status'], $this->gateway_data['txn_data']['payment_type'] );

		$txn->created_at = MeprUtils::ts_to_mysql_date( time() );
		// This will only work before maybe_cancel_old_sub is run.
		$upgrade   = $txn->is_upgrade();
		$downgrade = $txn->is_downgrade();

		$event_txn = $txn->maybe_cancel_old_sub();

		$txn->store();
		$product = $txn->product();

		if ( 'lifetime' === $product->period_type ) {
			if ( $upgrade ) {
				$this->upgraded_sub( $txn, $event_txn );
			} elseif ( $downgrade ) {
				$this->downgraded_sub( $txn, $event_txn );
			} else {
				$this->new_sub( $txn );
			}

			MeprUtils::send_signup_notices( $txn );
		}
		if ( ( 'ON_HOLD' !== $this->gateway_data['txn_data']['status'] ) && in_array( $this->gateway_data['txn_data']['payment_type'], array( 'CASHPAYMENT', 'INVOICE', 'GUARANTEED_INVOICE', 'PREPAYMENT', 'MULTIBANCO', 'INSTALMENT_INVOICE' ), true ) ) {
			$this->mepr_novalnet_send_transaction_receipt_notices( $txn );
		}
		MeprUtils::send_transaction_receipt_notices( $txn );
		return $txn;
	}

	/** Used to record a successful subscription */
	public function record_create_subscription() {
		$mepr_options = MeprOptions::fetch();

		if ( empty( $this->gateway_data['txn_data']['txn_id'] ) ) {
			return false;
		}

		$temp_txn = new MeprTransaction( $this->gateway_data['txn_data']['txn_id'] );

		if ( (int) $temp_txn->id <= 0 ) {
			return;
		}

		$sub = $temp_txn->subscription();

		if ( (int) $sub->id > 0 ) {
			$timestamp       = time();
			$sub->status     = MeprSubscription::$active_str;
			$sub->created_at = gmdate( 'c', $timestamp );
			$sub->store();

			$txn = $sub->first_txn();
			if ( false == $txn || ! ( $txn instanceof MeprTransaction ) ) { //phpcs:ignore WordPress.PHP.StrictComparisons
				$txn             = new MeprTransaction();
				$txn->user_id    = $sub->user_id;
				$txn->product_id = $sub->product_id;
			}

			$txn->created_at = MeprUtils::ts_to_mysql_date( $timestamp );

			if ( $sub->trial ) {
				$expires_at = MeprUtils::ts_to_mysql_date( time() + MeprUtils::days( $sub->trial_days ), 'Y-m-d 23:59:59' );
			} else {
				$expires_at = $txn->created_at; // Expire immediately.
			}

			$txn->status   = MeprTransaction::$confirmed_str;
			$txn->txn_type = MeprTransaction::$subscription_confirmation_str;
			$txn->set_subtotal( 0.00 ); // Just a confirmation txn.
			$txn->expires_at = $expires_at;
			$txn->store( true );

			$this->gateway_data['txn_data']['renewal'] = false;

			$txn = $this->record_subscription_payment();

			// This will only work before maybe_cancel_old_sub is run.
			$upgrade   = $sub->is_upgrade();
			$downgrade = $sub->is_downgrade();

			$event_txn = $sub->maybe_cancel_old_sub();
			if ( $upgrade ) {
				$this->upgraded_sub( $sub, $event_txn );
			} elseif ( $downgrade ) {
				$this->downgraded_sub( $sub, $event_txn );
			} else {
				$this->new_sub( $sub, true );
			}

			MeprUtils::send_signup_notices( $txn );

			return array(
				'subscription' => $sub,
				'transaction'  => $txn,
			);
		}
	}

	/** Used to record a successful recurring payment */
	public function record_subscription_payment() {
		if ( ! isset( $this->gateway_data['txn_data']['txn_id'] ) || empty( $this->gateway_data['txn_data']['txn_id'] ) ) {
			return false; }
		$temp_txn = new MeprTransaction( $this->gateway_data['txn_data']['txn_id'] );

		if ( (int) $temp_txn->id <= 0 ) {
			return; }

		$sub = $temp_txn->subscription();

		if ( $sub ) {
			$timestamp = time();
			$first_txn = new MeprTransaction( $sub->first_txn_id );

			if ( ! isset( $first_txn->id ) || empty( $first_txn->id ) ) {
				$first_txn             = new MeprTransaction();
				$first_txn->user_id    = $sub->user_id;
				$first_txn->product_id = $sub->product_id;
			}
			// If this is a trial payment, let's just convert the confirmation txn into a payment txn.
			if ( $sub->trial && count( $sub->transactions() ) === 1 && ! $this->gateway_data['txn_data']['renewal'] ) {
				$txn             = $first_txn; // For use below in send notices.
				$txn->created_at = MeprUtils::ts_to_mysql_date( $timestamp );
				$txn->expires_at = MeprUtils::ts_to_mysql_date( $sub->trial_expires_at(), 'Y-m-d 23:59:59' );
				$txn->gateway    = $this->id;
				$txn->trans_num  = $this->gateway_data['txn_data']['txn_num'];
				$txn->txn_type   = MeprTransaction::$payment_str;
				$txn->status     = $this->mepr_novalnet_get_transaction_status( $this->gateway_data['txn_data']['status'], $this->gateway_data['txn_data']['payment_type'] );
				$txn->set_gross( novalnet_helper()->mepr_novalnet_format_amount( $this->gateway_data['txn_data']['amount'], false ) );
				$txn->subscription_id = $sub->id;
				$txn->store();
			} else {
				$existing = MeprTransaction::get_one( $this->gateway_data['txn_data']['txn_id'] );
				if ( null !== $existing && isset( $existing->id ) && (int) $existing->id > 0 && ! $this->gateway_data['txn_data']['renewal'] ) {
					$txn = new MeprTransaction( $existing->id );
				} else {
					$txn = new MeprTransaction();
				}
				$txn->created_at      = MeprUtils::ts_to_mysql_date( $timestamp );
				$txn->user_id         = $first_txn->user_id;
				$txn->product_id      = $first_txn->product_id;
				$txn->gateway         = $this->id;
				$txn->trans_num       = $this->gateway_data['txn_data']['txn_num'];
				$txn->txn_type        = MeprTransaction::$payment_str;
				$txn->status          = $this->mepr_novalnet_get_transaction_status( $this->gateway_data['txn_data']['status'], $this->gateway_data['txn_data']['payment_type'], true );
				$txn->subscription_id = $sub->id;
				$txn->set_gross( novalnet_helper()->mepr_novalnet_format_amount( $this->gateway_data['txn_data']['amount'], false ) );
				$txn->expires_at = MeprUtils::ts_to_mysql_date( $sub->get_expires_at(), 'Y-m-d 23:59:59' );
				$txn->store();

				// Check that the subscription status is still enabled.
				if ( MeprSubscription::$active_str !== $sub->status ) {
					$sub->status = MeprSubscription::$active_str;
					$sub->store();
				}
				// The total payment occurrences is already capped in record_create_subscription().
				$sub->limit_payment_cycles();
			}
			if ( ! $this->gateway_data['txn_data']['renewal'] ) {
				MeprUtils::send_transaction_receipt_notices( $txn );
			}
			if ( ( 'ON_HOLD' !== $this->gateway_data['txn_data']['status'] ) && in_array( $this->gateway_data['txn_data']['payment_type'], array( 'CASHPAYMENT', 'INVOICE', 'GUARANTEED_INVOICE', 'PREPAYMENT', 'MULTIBANCO', 'INSTALMENT_INVOICE' ), true ) ) {
				if ( ! $this->gateway_data['txn_data']['renewal'] ) {
					$this->mepr_novalnet_send_transaction_receipt_notices( $txn );
				} else {
					$this->gateway_data['txn_data']['novalnet_notice'] = true;
				}
			}
			return $txn;
		}
		return false;
	}

	/**
	 * This method should be used by the class to record a successful refund
	 *
	 * @param MeprTransaction $txn Memberpress transaction.
	 *
	 * @throws MeprGatewayException If the refund failed.
	 */
	public function process_refund( MeprTransaction $txn ) {
		if ( MeprTransaction::$complete_str === $txn->status && is_numeric( $txn->trans_num ) ) {
			$parameters = array(
				'transaction' => array(
					'tid'    => $txn->trans_num,
					'amount' => novalnet_helper()->mepr_novalnet_convert_smaller_currency_unit( $txn->amount ),
				),
				'custom'      => array(
					'lang' => novalnet_helper()->mepr_novalnet_get_language(),
				),
			);
			$response   = novalnet_helper()->mepr_novalnet_send_request( $parameters, novalnet_helper()->mepr_novalnet_format_endpoint( 'transaction_refund' ), $this->settings->payment_access_key );
			if ( isset( $response['result']['status'] ) && ( 'SUCCESS' === $response['result']['status'] ) ) {
				$this->gateway_data['refund_data'] = array(
					'tid'           => $txn->trans_num,
					'refund_amount' => $response['transaction']['refund']['amount'],
					'refund_tid'    => ( isset( $response['transaction']['refund']['tid'] ) ) ? $response['transaction']['refund']['tid'] : '',
					'txn_id'        => $txn->id,
				);
				$this->record_refund();
				return;
			} else {
				/* translators: %1$s:txn-id, %2$s:status-text*/
				throw new MeprGatewayException( sprintf( __( 'Payment refund failed for the order: %1$s due to: %2$s', 'memberpress-novalnet-addon' ), $txn->id, $response['result']['status_text'] ) );
			}
		}
		/* translators: %1$s:txn_id*/
		throw new MeprGatewayException( sprintf( __( 'Payment refund failed for the order: %1$s', 'memberpress-novalnet-addon' ), $txn->id ) );
	}

	/** This method should be used by the class to record a successful refund */
	public function record_refund() {
		if ( isset( $this->gateway_data['refund_data'] ) && ! empty( $this->gateway_data['refund_data'] ) ) {
			$txn = new MeprTransaction( $this->gateway_data['refund_data']['txn_id'] );
			global $wpdb;
			$mepr_db = new MeprDb();
			if ( $txn ) {
				/* translators: %1$s:tid, %2$s:refund-amount*/
				$message = sprintf( __( 'Refund has been initiated for the TID: %1$s with the amount %2$s.', 'memberpress-novalnet-addon' ), $this->gateway_data['refund_data']['tid'], novalnet_helper()->mepr_novalnet_format_amount( $this->gateway_data['refund_data']['refund_amount'] ) );
				if ( ! empty( $this->gateway_data['refund_data']['refund_tid'] ) ) {
					/* translators: %s:refund-tid*/
					$message .= sprintf( __( 'New TID: %s for the refunded amount', 'memberpress-novalnet-addon' ), $this->gateway_data['refund_data']['refund_tid'] );
				}
				$mepr_db->add_metadata( $wpdb->prefix . 'mepr_transaction_meta', 'transaction_id', $txn->id, 'novalnet_refunds', $this->gateway_data['refund_data']['refund_amount'], false );
				novalnet_helper()->mepr_novalnet_add_transaction_note( $message, $this->gateway_data['refund_data']['txn_id'] );
				$refunds         = $mepr_db->get_metadata( $wpdb->prefix . 'mepr_transaction_meta', 'transaction_id', $txn->id, 'novalnet_refunds', false );
				$formatted_total = novalnet_helper()->mepr_novalnet_convert_smaller_currency_unit( $txn->total );
				if ( ( array_sum( $refunds ) >= $formatted_total || $this->gateway_data['refund_data']['refund_amount'] >= $formatted_total ) && MeprTransaction::$refunded_str !== $txn->status ) {
					$txn->status = MeprTransaction::$refunded_str;
					$txn->store();
				}
				// Just reassign to total for modify amount in mail, but not store txn.
				$txn->total = novalnet_helper()->mepr_novalnet_format_amount( $this->gateway_data['refund_data']['refund_amount'], false );
				MeprUtils::send_refunded_txn_notices( $txn );
				return array(
					'message' => $message,
					'txn'     => $txn,
				);
			}
		}
		return false;
	}

	/**
	 * Used to cancel a subscription
	 *
	 * @param string $sub_id Memberpress subscription id.
	 *
	 * @throws MeprGatewayException If the subscription cancel failed.
	 */
	public function process_cancel_subscription( $sub_id ) {
		$sub        = new MeprSubscription( $sub_id );
		$txn        = ( $sub->first_txn() ) ? $sub->first_txn() : $sub->latest_txn();
		$parameters = array(
			'subscription' => array(
				'tid' => $txn->trans_num,
			),
			'custom'       => array(
				'lang' => novalnet_helper()->mepr_novalnet_get_language(),
			),
		);
		$response   = novalnet_helper()->mepr_novalnet_send_request( $parameters, novalnet_helper()->mepr_novalnet_format_endpoint( 'subscription_cancel' ), $this->settings->payment_access_key );
		if ( novalnet_helper()->mepr_novalnet_is_success_status( $response ) ) {
			$this->gateway_data['subscription_cancel'] = array(
				'txn_id'    => $response['transaction']['order_no'],
				'subs_id'   => $sub_id,
				'is_expire' => false,
				'notify'    => true,
			);
			return $this->record_cancel_subscription();
		}
		throw new MeprGatewayException();
	}

	/** This method should be used by the class to record a successful cancellation */
	public function record_cancel_subscription() {
		if ( ! isset( $this->gateway_data['subscription_cancel'] ) ) {
			return false;
		}
		if ( isset( $this->gateway_data['subscription_cancel']['subs_id'] ) ) {
			$sub = new MeprSubscription( $this->gateway_data['subscription_cancel']['subs_id'] );
		} else {
			$txn = new MeprTransaction( $this->gateway_data['subscription_cancel']['txn_id'] );
			$sub = $txn->subscription();
		}

		if ( ! $sub ) {
			return false;
		}

		if ( MeprSubscription::$cancelled_str === $sub->status ) { // If subscription already cancelled.
			return $sub;
		}

		$sub->status = MeprSubscription::$cancelled_str;
		$sub->store();

		if ( isset( $this->gateway_data['subscription_cancel']['is_expire'] ) ) {
			$sub->limit_reached_actions();
		}

		if ( $this->gateway_data['subscription_cancel']['notify'] ) {
			MeprUtils::send_cancelled_sub_notices( $sub );
		}
		/* translators: %1$s: date, %2$s: time*/
		$message = sprintf( __( 'Subscription has been cancelled on %1$s %2$s', 'memberpress-novalnet-addon' ), gmdate( 'Y-m-d' ), gmdate( 'H:i:s' ) );
		novalnet_helper()->mepr_novalnet_add_transaction_note( $message, $this->gateway_data['subscription_cancel']['txn_id'] );
		return array(
			'message' => $message,
			'sub'     => $sub,
		);
	}

	/**
	 * Used to suspend a subscription
	 *
	 * @param string $sub_id Memberpress subscription id.
	 *
	 * @throws MeprGatewayException If the subcription subspend failed.
	 */
	public function process_suspend_subscription( $sub_id ) {
		$sub        = new MeprSubscription( $sub_id );
		$txn        = ( $sub->first_txn() ) ? $sub->first_txn() : $sub->latest_txn();
		$parameters = array(
			'subscription' => array(
				'tid' => $txn->trans_num,
			),
			'custom'       => array(
				'lang' => novalnet_helper()->mepr_novalnet_get_language(),
			),
		);
		$response   = novalnet_helper()->mepr_novalnet_send_request( $parameters, novalnet_helper()->mepr_novalnet_format_endpoint( 'subscription_suspend' ), $this->settings->payment_access_key );
		if ( novalnet_helper()->mepr_novalnet_is_success_status( $response ) ) {
			$this->gateway_data['subscription_suspend'] = array(
				'txn_id'  => $response['transaction']['order_no'],
				'subs_id' => $sub_id,
				'notify'  => true,
			);
			return $this->record_suspend_subscription();
		}
		throw new MeprGatewayException();
	}

	/**
	 * This method should be used by the class to record a successful suspension
	 */
	public function record_suspend_subscription() {
		if ( ! isset( $this->gateway_data['subscription_suspend'] ) ) {
			return false;
		}
		if ( isset( $this->gateway_data['subscription_suspend']['subs_id'] ) ) {
			$sub = new MeprSubscription( $this->gateway_data['subscription_suspend']['subs_id'] );
		} else {
			$txn = new MeprTransaction( $this->gateway_data['subscription_suspend']['txn_id'] );
			$sub = $txn->subscription();
		}
		if ( ! $sub ) {
			return false;
		}
		if ( MeprSubscription::$suspended_str === $sub->status ) { // If subscription already suspend.
			return $sub;
		}
		$sub->status = MeprSubscription::$suspended_str;
		$sub->store();
		if ( $this->gateway_data['subscription_suspend']['notify'] ) {
			MeprUtils::send_suspended_sub_notices( $sub );
		}
		/* translators: %s:date*/
		$message = sprintf( __( 'This subscription transaction has been suspended on %s', 'memberpress-novalnet-addon' ), gmdate( 'Y-m-d' ) );
		novalnet_helper()->mepr_novalnet_add_transaction_note( $message, $this->gateway_data['subscription_suspend']['txn_id'] );
		return array(
			'message' => $message,
			'sub'     => $sub,
		);
	}

	/** Used to suspend a subscription
	 *
	 * @param string $sub_id Memberpress subscription id.
	 *
	 * @throws MeprGatewayException If the subscription resume failed.
	 */
	public function process_resume_subscription( $sub_id ) {
		$sub        = new MeprSubscription( $sub_id );
		$txn        = ( $sub->first_txn() ) ? $sub->first_txn() : $sub->latest_txn();
		$parameters = array(
			'subscription' => array(
				'tid' => $txn->trans_num,
			),
			'custom'       => array(
				'lang' => novalnet_helper()->mepr_novalnet_get_language(),
			),
		);
		$response   = novalnet_helper()->mepr_novalnet_send_request( $parameters, novalnet_helper()->mepr_novalnet_format_endpoint( 'subscription_reactivate' ), $this->settings->payment_access_key );

		if ( novalnet_helper()->mepr_novalnet_is_success_status( $response ) ) {
			$this->gateway_data['subscription_resume'] = array(
				'tid'             => $txn->trans_num,
				'txn_id'          => $response['transaction']['order_no'],
				'subs_next_cycle' => $response['subscription']['next_cycle_date'],
				'subs_id'         => $sub_id,
				'notify'          => true,
			);
			return $this->record_resume_subscription();
		}
		throw new MeprGatewayException();
	}

	/** This method should be used by the class to record a successful resuming. */
	public function record_resume_subscription() {
		if ( ! isset( $this->gateway_data['subscription_resume'] ) ) {
			return false;
		}
		if ( isset( $this->gateway_data['subscription_resume']['subs_id'] ) ) {
			$sub = new MeprSubscription( $this->gateway_data['subscription_resume']['subs_id'] );
		} else {
			$txn = new MeprTransaction( $this->gateway_data['subscription_resume']['txn_id'] );
			$sub = $txn->subscription();
		}
		if ( ! $sub ) {
			return false;
		}
		if ( MeprSubscription::$active_str === $sub->status ) { // If subscription already active.
			return $sub;
		}
		$sub->status = MeprSubscription::$active_str;
		$sub->store();
		if ( $this->gateway_data['subscription_resume']['notify'] ) {
			MeprUtils::send_resumed_sub_notices( $sub );
		}
		/* translators: %1$s:tid, %2$s:date, %3$s:next-cycle*/
		$message = sprintf( __( 'Subscription has been reactivated for the TID:%1$s on %2$s. Next charging date :%3$s', 'memberpress-novalnet-addon' ), $this->gateway_data['subscription_resume']['tid'], gmdate( 'Y-m-d' ), $this->gateway_data ['subscription_resume']['subs_next_cycle'] );
		novalnet_helper()->mepr_novalnet_add_transaction_note( $message, $this->gateway_data['subscription_resume']['txn_id'] );
		return array(
			'message' => $message,
			'sub'     => $sub,
		);
	}

	/**
	 * Get the txn status based on payment provider status
	 *
	 * @param string $status Novalnet status.
	 * @param string $payment Novalnet payment method.
	 */
	public function mepr_novalnet_get_transaction_status( $status, $payment ) {
		if ( 'CONFIRMED' === $status ) {
			$txn_status = MeprTransaction::$complete_str;
		} elseif ( 'PENDING' === $status ) {
			$txn_status = MeprTransaction::$pending_str;
			if ( 'INVOICE' === $payment ) {
				$txn_status = MeprTransaction::$complete_str;
			}
		} elseif ( 'ON_HOLD' === $status ) {
			$txn_status = MeprTransaction::$pending_str;
		} else {
			$txn_status = MeprTransaction::$failed_str;
		}
		return $txn_status;
	}

	/** Shows the payment canceled message to the customer */
	public function cancel_message() {
		?>
		<h4><?php esc_html_e( 'Your payment was cancelled', 'memberpress-novalnet-addon' ); ?></h4>
		<p>
			<?php
			/* translators: %s: purchase_retry_url*/
			echo wp_kses( MeprHooks::apply_filters( 'mepr_novalnet_cancel_message', sprintf( __( 'You can retry your purchase by <a href="%s">clicking here</a>.', 'memberpress-novalnet-addon' ), MeprUtils::get_permalink() ) ), array( 'a' => array( 'href' => true ) ) );
			?>
			<br/>
		</p>
		<?php
	}

	/** Shows the payment failed message to the customer */
	public function payment_failed_message() {
		?>
		<h4><?php esc_html_e( 'Your payment was failed', 'memberpress-novalnet-addon' ); ?></h4>
		<p>
			<?php
				/* translators: %s: purchase_retry_url*/
				echo wp_kses( MeprHooks::apply_filters( 'mepr_novalnet_cancel_message', sprintf( __( 'You can retry your purchase by <a href="%s">clicking here</a>.', 'memberpress-novalnet-addon' ), MeprUtils::get_permalink() ) ), array( 'a' => array( 'href' => true ) ) );
			?>
			<br/>
		</p>
		<?php
	}

	/**
	 * Used to handle payment request
	 *
	 * @param object $txn Memberpress transaction.
	 */
	public function process_payment( $txn ) {
		// Handled in the Hosted payment page, only record_payment is needed here.
	}

	/**
	 * This abstract method used to handle trail payment.
	 *
	 * @param object $transaction Memberpress transaction.
	 */
	public function process_trial_payment( $transaction ) { }
	/**
	 * This abstract method used to record a successful trail payment.
	 *
	 * @param object $transaction Memberpress transaction.
	 */
	public function record_trial_payment( $transaction ) { }
	/**
	 * This abstract method used to create subscription.
	 *
	 * @param object $txn Memberpress transaction.
	 */
	public function process_create_subscription( $txn ) {
		// This all happens in the IPN so record_created_subscription is all that's needed.
	}
	/**
	 * This abstract method used to update subscription.
	 *
	 * @param string $sub_id Memberpress subscription id.
	 */
	public function process_update_subscription( $sub_id ) {
	}

	/** This method should be used by the class to record a successful subscription update  */
	public function record_update_subscription() {
		$txn              = new MeprTransaction( $this->gateway_data['subscription_update']['transaction']['order_no'] );
		$sub              = $txn->subscription();
		$recurring_amount = novalnet_helper()->mepr_novalnet_convert_smaller_currency_unit( $sub->total );
		$transaction_data = novalnet_helper()->mepr_novalnet_get_response_data( $this->gateway_data['subscription_update']['transaction']['order_no'] );
		if ( ( ! empty( $this->gateway_data['subscription_update']['subscription']['amount'] ) && (int) $recurring_amount !== (int) $this->gateway_data['subscription_update']['subscription']['amount'] ) ) {
			/* translators: %1$s: amount, %2$s: next_cycle_date*/
			$message = sprintf( __( 'Subscription updated successfully. You will be charged %1$s on %2$s.', 'memberpress-novalnet-addon' ), novalnet_helper()->mepr_novalnet_format_amount( $this->gateway_data['subscription_update']['subscription'] ['amount'] ), $this->gateway_data['subscription_update']['subscription']['next_cycle_date'] );
		}

		if ( ( ! empty( $this->gateway_data['subscription_update']['transaction'] ['payment_type'] ) && ! empty( $transaction_data['transaction']['payment_type'] ) && $transaction_data['transaction']['payment_type'] !== $this->gateway_data['subscription_update']['transaction'] ['payment_type'] ) ) {
			/* translators: %s: next_cycle_date*/
			$message = sprintf( __( 'Successfully changed the payment method for next subscription on %s', 'memberpress-novalnet-addon' ), $this->gateway_data['subscription_update']['subscription']['next_cycle_date'] );
		}
		novalnet_helper()->mepr_novalnet_add_transaction_note( $message, $this->gateway_data['subscription_update']['transaction']['order_no'] );
		return array(
			'message' => $message,
			'sub'     => $sub,
		);
	}

	/**
	 * This process is get called when the customer submits the form
	 *
	 * @param object $txn Memberpress transaction.
	 */
	public function process_signup_form( $txn ) {
	}

	/**
	 * Validates the payment form before a payment is processed
	 *
	 * @param array $errors Variable to store errors.
	 */
	public function validate_payment_form( $errors ) {
	}

	/**
	 * Redirects the user
	 *
	 * @param object $txn Memberpress transaction.
	 */
	public function process_payment_form( $txn ) {
	}

	/**
	 * Displays the update account form on the subscription account page
	 *
	 * @param string $sub_id Memberpress subscription id.
	 * @param array  $errors Variable to store errors.
	 * @param string $message messages string.
	 */
	public function display_update_account_form( $sub_id, $errors = array(), $message = '' ) {
	}

	/**
	 * Validates the payment form before a payment is processed
	 *
	 * @param array $errors Variable to store errors.
	 */
	public function validate_update_account_form( $errors = array() ) {
	}

	/** Actually pushes the account update to the payment processor
	 *
	 * @param string $sub_id Memberpress subscription id.
	 */
	public function process_update_account_form( $sub_id ) {
	}

	/** Returns boolean ... whether or not we should be sending in test mode or not */
	public function is_test_mode() {
		return true;
	}

	/** Listens for an incoming connection from Novalnet and then handles the request appropriately. */
	public function listener() {
		if ( $this->validate_ipn() ) {
			$this->process_ipn();
		}
		return false;
	}

	/** Validate webhook request */
	public function validate_ipn() {
		$webhook_ip_control  = ( 'on' === $this->settings->webhook_ip_control || true === $this->settings->webhook_ip_control ) ? '1' : '0';
		$request_received_ip = novalnet_helper()->mepr_novalnet_get_ip_address();
		$novalnet_host_ip    = gethostbyname( 'pay-nn.de' );
		if ( ! empty( $novalnet_host_ip ) && ! empty( $request_received_ip ) ) {
			if ( $novalnet_host_ip !== $request_received_ip && ! $webhook_ip_control ) {
				novalnet_helper()->mepr_novalnet_display_message( array( 'message' => 'Unauthorised access from the IP ' . $request_received_ip ) );
			}
		} else {
			novalnet_helper()->mepr_novalnet_display_message( array( 'message' => 'Unauthorised access from the IP. Host/recieved IP is empty' ) );
		}
		return true;
	}

	/** Webhook hanlder function */
	public function process_ipn() {
		try {
			$json_data        = WP_REST_SERVER::get_raw_data();
			$this->event_data = novalnet_helper()->mepr_novalnet_unserialize_data( $json_data );
		} catch ( Exception $e ) {
			novalnet_helper()->mepr_novalnet_display_message( array( 'message' => 'Received data is not in the JSON format ' . $e ) );
		}
		novalnet_helper()->mepr_novalnet_validate_event_data( $this->event_data );
		novalnet_helper()->mepr_novalnet_validate_checksum( $this->settings->payment_access_key, $this->event_data );
		$this->event_tid     = ! empty( $this->event_data ['event'] ['tid'] ) ? $this->event_data ['event'] ['tid'] : '';
		$this->event_type    = $this->event_data['event']['type'];
		$this->parent_tid    = ( isset( $this->event_data['event']['parent_tid'] ) && ! empty( $this->event_data['event']['parent_tid'] ) ) ? $this->event_data['event']['parent_tid'] : $this->event_data['event']['tid'];
		$this->order_details = $this->mepr_novalnet_get_order_reference();

		switch ( $this->event_type ) {

			case 'PAYMENT':
				novalnet_helper()->mepr_novalnet_display_message( array( 'message' => "The webhook notification received ('" . $this->event_data['transaction']['payment_type'] . "') for the TID: '" . $this->event_tid . "'" ) );
				break;
			case 'TRANSACTION_CANCEL':
				$this->gateway_data['txn_data'] = array(
					'txn_id'  => $this->event_data['transaction']['order_no'],
					'txn_num' => $this->event_data['transaction']['tid'],
				);
				$this->record_payment_failure();
				/* translators: %1$s: date, %2$s: time*/
				$message = sprintf( __( 'The transaction has been canceled on %1$s %2$s', 'memberpress-novalnet-addon' ), gmdate( 'Y-m-d' ), gmdate( 'H:i:s' ) );
				novalnet_helper()->mepr_novalnet_add_transaction_note( $message, $this->gateway_data['txn_data']['txn_id'] );
				novalnet_helper()->mepr_novalnet_send_webhook_mail( $message, $this->id );
				novalnet_helper()->mepr_novalnet_display_message( array( 'message' => $message ) );
				break;
			case 'TRANSACTION_CAPTURE':
				$this->gateway_data['txn_data'] = array(
					'txn_id'       => $this->event_data['transaction']['order_no'],
					'txn_num'      => $this->event_data['transaction']['tid'],
					'status'       => $this->event_data['transaction']['status'],
					'payment_type' => $this->event_data['transaction']['payment_type'],
					'amount'       => $this->event_data['transaction']['amount'],
				);
				if ( isset( $this->event_data['instalment'] ) ) {
					novalnet_helper()->mepr_novalnet_store_instalment_data( $this->event_data );
				}
				novalnet_helper()->mepr_novalnet_update_response_data( $this->event_data['transaction']['order_no'], $this->event_data );
				/* translators: %1$s: date, %2$s: time*/
				$message = sprintf( __( 'The transaction has been confirmed on %1$s %2$s', 'memberpress-novalnet-addon' ), gmdate( 'Y-m-d' ), gmdate( 'H:i:s' ) );
				novalnet_helper()->mepr_novalnet_add_transaction_note( $message, $this->gateway_data['txn_data']['txn_id'] );
				$this->record_payment();
				novalnet_helper()->mepr_novalnet_send_webhook_mail( $message, $this->id );
				novalnet_helper()->mepr_novalnet_display_message( array( 'message' => $message ) );
				break;

			case 'TRANSACTION_REFUND':
				$this->gateway_data['refund_data'] = array(
					'tid'           => $this->parent_tid,
					'refund_amount' => $this->event_data['transaction']['refund']['amount'],
					'refund_tid'    => ( isset( $this->event_data['transaction']['refund']['tid'] ) ) ? $this->event_data['transaction']['refund']['tid'] : '',
					'txn_id'        => $this->event_data['transaction']['order_no'],
				);
				$data                              = $this->record_refund();
				if ( is_array( $data ) ) {
					novalnet_helper()->mepr_novalnet_send_webhook_mail( $data['message'], $this->id );
					novalnet_helper()->mepr_novalnet_display_message( array( 'message' => $data['message'] ) );
				}
				break;
			case 'RENEWAL':
				$data = $this->mepr_novalnet_handle_renewal();
				if ( isset( $data['txn'] ) && ! empty( $data['txn'] ) ) {
					if ( in_array( $this->event_data['transaction']['payment_type'], array( 'INVOICE', 'PREPAYMENT' ), true ) ) {
						$this->event_data['transaction']['invoice_ref'] = 'BNR-' . $this->settings->project_id . '-' . $data['txn']->id;
						$data['invoice_ref']                            = 'BNR-' . $this->settings->project_id . '-' . $data['txn']->id;
					}
					$this->event_data['transaction']['order_no'] = $data['txn']->id;
					novalnet_helper()->mepr_novalnet_store_response_data( $this->event_data, $data['txn']->id );
					$data['order_no'] = $data['txn']->id;
					if ( isset( $this->gateway_data['txn_data']['novalnet_notice'] ) && true === $this->gateway_data['txn_data']['novalnet_notice'] ) {
						$this->mepr_novalnet_send_transaction_receipt_notices( $data['txn'] );
					}
					MeprUtils::send_transaction_receipt_notices( $data['txn'] );
				}
				novalnet_helper()->mepr_novalnet_add_transaction_note( $data['message'], $this->order_details->id );
				if ( isset( $data['subs_cancel_note'] ) && ! empty( $data['subs_cancel_note'] ) ) {
					novalnet_helper()->mepr_novalnet_add_transaction_note( $data['subs_cancel_note'], $this->order_details->id );
				}
				novalnet_helper()->mepr_novalnet_send_webhook_mail( $data['message'], $this->id );
				unset( $data['txn'] );
				novalnet_helper()->mepr_novalnet_display_message( $data );
				break;
			case 'CREDIT':
				/* translators: %1$s: parent-tid, %2$s: amount, %3$s: date, %4$s: time, %5$s: event-tid*/
				$message = sprintf( __( 'Credit has been successfully received for the TID: %1$s with amount %2$s on %3$s %4$s. Please refer PAID order details in our Novalnet Admin Portal for the TID: %5$s', 'memberpress-novalnet-addon' ), $this->parent_tid, novalnet_helper()->mepr_novalnet_format_amount( $this->event_data['transaction']['amount'] ), gmdate( 'd.m.Y' ), gmdate( 'H:i:s' ), $this->event_tid );
				$this->mepr_novalnet_handle_credit();
				novalnet_helper()->mepr_novalnet_add_transaction_note( $message, $this->order_details->id );
				novalnet_helper()->mepr_novalnet_send_webhook_mail( $message, $this->id );
				novalnet_helper()->mepr_novalnet_display_message( array( 'message' => $message ) );
				break;

			case 'CHARGEBACK':
				/* translators: %1$s: parent-tid, %2$s: amount, %3$s: date, %4$s:event-tid*/
				$message = sprintf( __( 'Chargeback executed successfully for the TID: %1$s amount: %2$s on %3$s. The subsequent TID: %4$s', 'memberpress-novalnet-addon' ), $this->parent_tid, novalnet_helper()->mepr_novalnet_format_amount( $this->event_data['transaction']['amount'] ), gmdate( 'd.m.Y H:i:s' ), $this->event_tid );
				novalnet_helper()->mepr_novalnet_add_transaction_note( $message, $this->event_data['transaction']['order_no'] );
				novalnet_helper()->mepr_novalnet_send_webhook_mail( $message, $this->id );
				novalnet_helper()->mepr_novalnet_display_message( array( 'message' => $message ) );
				break;

			case 'INSTALMENT':
				$this->gateway_data['instalment_data'] = array(
					'transaction' => $this->event_data['transaction'],
					'instalment'  => $this->event_data['instalment'],
				);
				novalnet_helper()->mepr_novalnet_store_instalment_data_webhook( $this->gateway_data['instalment_data'] );
				/* translators: %1$s: parent_tid, %2$s: amount, %3$s: event_tid*/
				$message = sprintf( __( 'A new instalment has been received for the Transaction ID:%1$s with amount %2$s. The new instalment transaction ID is: %3$s', 'memberpress-novalnet-addon' ), $this->parent_tid, novalnet_helper()->mepr_novalnet_format_amount( $this->event_data['instalment']['cycle_amount'] ), $this->event_tid );
				novalnet_helper()->mepr_novalnet_add_transaction_note( $message, $this->event_data['transaction']['order_no'] );
				novalnet_helper()->mepr_novalnet_send_webhook_mail( $message, $this->id );
				novalnet_helper()->mepr_novalnet_display_message( array( 'message' => $message ) );
				break;
			case 'SUBSCRIPTION_SUSPEND':
				$this->gateway_data['subscription_suspend'] = array(
					'txn_id' => $this->event_data['transaction']['order_no'],
					'notify' => true,
				);
				$data                                       = $this->record_suspend_subscription();
				if ( is_array( $data ) ) {
					novalnet_helper()->mepr_novalnet_send_webhook_mail( $data['message'], $this->id );
					novalnet_helper()->mepr_novalnet_display_message( array( 'message' => $data['message'] ) );
				}
				break;
			case 'SUBSCRIPTION_REACTIVATE':
				$this->gateway_data['subscription_resume'] = array(
					'tid'             => $this->parent_tid,
					'txn_id'          => $this->event_data['transaction']['order_no'],
					'subs_next_cycle' => $this->event_data ['subscription']['next_cycle_date'],
					'notify'          => true,
				);
				$data                                      = $this->record_resume_subscription();
				if ( is_array( $data ) ) {
					novalnet_helper()->mepr_novalnet_send_webhook_mail( $data['message'], $this->id );
					novalnet_helper()->mepr_novalnet_display_message( array( 'message' => $data['message'] ) );
				}
				break;
			case 'SUBSCRIPTION_CANCEL':
				$this->gateway_data['subscription_cancel'] = array(
					'txn_id'    => $this->event_data['transaction']['order_no'],
					'is_expire' => false,
					'notify'    => true,
				);
				$data                                      = $this->record_cancel_subscription();
				if ( is_array( $data ) ) {
					novalnet_helper()->mepr_novalnet_send_webhook_mail( $data['message'], $this->id );
					novalnet_helper()->mepr_novalnet_display_message( array( 'message' => $data['message'] ) );
				}
				break;

			case 'SUBSCRIPTION_UPDATE':
				$this->gateway_data['subsscription_update'] = array(
					'transaction'  => $this->event_data['transaction'],
					'subscription' => $this->event_data['subscription'],
				);
				novalnet_helper()->mepr_novalnet_update_response_data( $this->event_data['transaction']['order_no'], $this->event_data );
				$data = $this->record_update_subscription();
				novalnet_helper()->mepr_novalnet_send_webhook_mail( $message, $this->id );
				novalnet_helper()->mepr_novalnet_display_message( array( 'message' => $message ) );
				break;

			case 'INSTALMENT_CANCEL':
				/* translators: %1$s: parent_tid, %2$s: date*/
				$message = sprintf( __( 'Instalment has been cancelled for the TID %1$s on %2$s', 'memberpress-novalnet-addon' ), $this->parent_tid, gmdate( 'Y-m-d' ) );
				novalnet_helper()->mepr_novalnet_add_transaction_note( $message, $this->event_data['transaction']['order_no'] );
				novalnet_helper()->mepr_novalnet_send_webhook_mail( $message, $this->id );
				novalnet_helper()->mepr_novalnet_display_message( array( 'message' => $message ) );
				break;

			case 'TRANSACTION_UPDATE':
				$message = $this->mepr_novalnet_handle_transaction_update();
				if ( ! empty( $message ) ) {
					novalnet_helper()->mepr_novalnet_add_transaction_note( $message, $this->event_data['transaction']['order_no'] );
					novalnet_helper()->mepr_novalnet_send_webhook_mail( $message, $this->id );
					novalnet_helper()->mepr_novalnet_display_message( array( 'message' => $message ) );
				}
				break;
			default:
				novalnet_helper()->mepr_novalnet_display_message( array( 'message' => "The webhook notification has been received for the unhandled EVENT type('" . $this->event_type . "')" ) );
		}
	}


	/** Returns boolean ... whether or not we should be forcing ssl */
	public function force_ssl() {
		return false; // redirects off site where ssl is installed.
	}

	/** This gets called on wp_enqueue_script and enqueues a set of
	 * scripts for use on the page containing the payment form
	 */
	public function enqueue_payment_form_scripts() {
	}

	/** The process to get txn details for the webhook process */
	public function mepr_novalnet_get_order_reference() {
		if ( isset( $this->event_data['transaction']['order_no'] ) ) {
			$order_details = MeprTransaction::get_one( $this->event_data['transaction']['order_no'] );
		} else {
			$order_details = MeprTransaction::get_one_by_trans_num( $this->parent_tid );
		}
		if ( ! empty( $order_details->id ) && ( $order_details->trans_num != $this->parent_tid ) ) { //phpcs:ignore WordPress.PHP.StrictComparisons
			$this->mepr_novalnet_handle_communication_failure();
		}
		return $order_details;
	}

	/** Handle initial payment communication failure */
	public function mepr_novalnet_handle_communication_failure() {
		if ( ! empty( $this->event_data ) ) {
			novalnet_helper()->mepr_novalnet_store_response_data( $this->event_data, $this->event_data['transaction']['order_no'] );
			$txn                            = new MeprTransaction( (int) $this->event_data['transaction']['order_no'] );
			$this->gateway_data['txn_data'] = array(
				'txn_id'       => $this->event_data['transaction']['order_no'],
				'txn_num'      => $this->event_data['transaction']['tid'],
				'status'       => $this->event_data['transaction']['status'],
				'payment_type' => $this->event_data['transaction']['payment_type'],
				'amount'       => $this->event_data['transaction']['amount'],
			);
			if ( $txn->subscription() ) {
				$this->record_create_subscription();
			} else {
				$this->record_payment();
			}
		}
	}

	/**
	 * Send Payment Receipt Notice mail to customer
	 *
	 * @param object $txn Memberpress transaction.
	 */
	public function mepr_novalnet_send_transaction_receipt_notices( $txn ) {
		$email = MeprEmailFactory::fetch( 'MeprNovalnetPaymentReceiptEmail' );
		if ( $email->enabled() && $txn instanceof MeprTransaction ) {
			$usr = $txn->user();
			if ( $usr->ID > 0 ) {
				$email->to             = $usr->formatted_email();
				$params                = MeprTransactionsHelper::get_email_params( $txn );
				$country_codes         = MeprUtils::countries();
				$params['biz_country'] = ucwords( strtolower( $country_codes[ $params['biz_country'] ] ) );
				$email->send( $params );
			}
		}
	}

	/** This method is for handling transaction update in the webhook process */
	public function mepr_novalnet_handle_transaction_update() {
		if ( in_array( $this->event_data['transaction']['status'], array( 'PENDING', 'ON_HOLD', 'CONFIRMED', 'DEACTIVATED' ), true ) ) {
			$transaction_data = novalnet_helper()->mepr_novalnet_get_response_data( $this->event_data['transaction']['order_no'] );
			if ( 'DEACTIVATED' === $this->event_data['transaction']['status'] ) {
				$this->gateway_data['txn_data'] = array(
					'txn_id'  => $this->event_data['transaction']['order_no'],
					'txn_num' => $this->event_data['transaction']['tid'],
				);
				novalnet_helper()->mepr_novalnet_update_response_data( $this->event_data['transaction']['order_no'], $this->event_data );
				$this->record_payment_failure();
				/* translators: %1$s: date, %2$s: time*/
				$message = sprintf( __( 'The transaction has been canceled on %1$s %2$s', 'memberpress-novalnet-addon' ), gmdate( 'Y-m-d' ), gmdate( 'H:i:s' ) );
				novalnet_helper()->mepr_novalnet_display_message( array( 'message' => $message ) );
			} else {
				if ( in_array( $transaction_data['transaction']['status'], array( 'PENDING', 'ON_HOLD' ), true ) ) {
					if ( 'CONFIRMED' === $this->event_data['transaction']['status'] ) {
						if ( isset( $this->event_data['instalment'] ) && ! empty( $this->event_data['instalment'] ) ) {
							novalnet_helper()->mepr_novalnet_store_instalment_data( $this->event_data );
						}
					}

					if ( empty( $this->event_data['instalment']['cycle_amount'] ) ) {
						$amount = $this->event_data['transaction']['amount'];
					} else {
						$amount = $this->event_data['instalment']['cycle_amount'];
					}

					if ( ! empty( $this->event_data['transaction']['due_date'] ) ) {
						/* translators: %1$s: tid, %2$s: amount, %3$s: due date */
						$message = sprintf( __( 'Transaction updated successfully for the TID: %1$s with amount %2$s and due date %3$s.', 'memberpress-novalnet-addon' ), $this->event_tid, novalnet_helper()->mepr_novalnet_format_amount( $amount ), $this->event_data['transaction']['due_date'] );
					} else {
						/* translators: %1$s: tid, %2$s: amount*/
						$message = sprintf( __( 'Transaction updated successfully for the TID: %1$s with amount %2$s.', 'memberpress-novalnet-addon' ), $this->event_tid, novalnet_helper()->mepr_novalnet_format_amount( $amount ) );
					}
					$this->gateway_data['txn_data'] = array(
						'txn_id'       => $this->event_data['transaction']['order_no'],
						'txn_num'      => $this->event_data['transaction']['tid'],
						'status'       => $this->event_data['transaction']['status'],
						'payment_type' => $this->event_data['transaction']['payment_type'],
						'amount'       => $this->event_data['transaction']['amount'],
					);
					novalnet_helper()->mepr_novalnet_update_response_data( $this->event_data['transaction']['order_no'], $this->event_data );
					$this->record_payment();
				}
			}
			if ( ! empty( $message ) ) {
				return $message;
			} else {
				/* translators: %1$s: event_tid */
				novalnet_helper()->mepr_novalnet_display_message( array( 'message' => sprintf( __( 'Callback script executed already. Refer TID: %1$s', 'memberpress-novalnet-addon' ), $this->event_tid ) ) );
			}
		}
	}

	/** This process is get called when a webhook received for CREDIT event */
	public function mepr_novalnet_handle_credit() {
		if ( in_array( $this->event_data['transaction']['payment_type'], array( 'INVOICE_CREDIT', 'CASHPAYMENT_CREDIT', 'MULTIBANCO_CREDIT' ), true ) ) {
			global $wpdb;
			$mepr_db          = new MeprDb();
			$callback_amount  = $mepr_db->get_metadata( $wpdb->prefix . 'mepr_transaction_meta', 'transaction_id', $this->event_data['transaction']['order_no'], 'novalnet_callback_amount', true );
			$refunds          = $mepr_db->get_metadata( $wpdb->prefix . 'mepr_transaction_meta', 'transaction_id', $this->event_data['transaction']['order_no'], 'novalnet_refunds', false );
			$transaction_data = novalnet_helper()->mepr_novalnet_get_response_data( $this->event_data['transaction']['order_no'] );
			if ( (int) $callback_amount < (int) $transaction_data['transaction']['amount'] ) {
				// Calculate total amount.
				$paid_amount = (int) $callback_amount + $this->event_data['transaction']['amount'];
				// Calculate including refunded amount.
				$amount_to_be_paid = ( ! empty( $refunds ) ) ? $transaction_data['transaction']['amount'] - array_sum( $refunds ) : $transaction_data['transaction']['amount'];
				if ( ( (int) $paid_amount >= (int) $amount_to_be_paid ) ) {
					$txn         = new MeprTransaction( $this->event_data['transaction']['order_no'] );
					$txn->status = MeprTransaction::$complete_str;
					$txn->store();
				}
				$mepr_db->update_metadata( $wpdb->prefix . 'mepr_transaction_meta', 'transaction_id', $this->event_data['transaction']['order_no'], 'novalnet_callback_amount', $paid_amount );
			}
		}
	}

	/** The process to create renewal transactions for subscription orders */
	public function mepr_novalnet_handle_renewal() {
		if ( in_array( $this->event_data['transaction']['status'], array( 'CONFIRMED', 'PENDING' ), true ) ) {
			if ( ! empty( $this->order_details ) && $this->order_details->subscription_id ) {
				$sub                            = new MeprSubscription( $this->order_details->subscription_id );
				$next_cycle_date                = ( ! empty( $this->event_data['subscription']['next_cycle_date'] ) ) ? $this->event_data['subscription']['next_cycle_date'] : null;
				$this->gateway_data['txn_data'] = array(
					'txn_id'       => $this->order_details->id,
					'txn_num'      => $this->event_data['transaction']['tid'],
					'status'       => $this->event_data['transaction']['status'],
					'payment_type' => $this->event_data['transaction']['payment_type'],
					'amount'       => $this->event_data['transaction']['amount'],
					'next_cycle'   => $next_cycle_date,
					'renewal'      => true,
				);
				$new_txn                        = $this->record_subscription_payment();
				/* translators: %1$s: tid, %2$s: amount, %3$s: date */
				$message = sprintf( __( 'Subscription has been successfully renewed for the TID: %1$s with the amount %2$s on %3$s. The renewal TID is:%4$s', 'memberpress-novalnet-addon' ), $this->parent_tid, novalnet_helper()->mepr_novalnet_format_amount( $this->event_data ['transaction']['amount'] ), gmdate( 'Y-m-d' ), $this->event_tid );

				$total_length   = $sub->limit_cycles_num;
				$related_orders = ( $sub->trial ) ? ( count( $sub->transactions() ) - 1 ) : count( $sub->transactions() );

				$subscription_cancel_note = '';
				if ( $sub->limit_cycles && ! empty( $total_length ) && $related_orders >= $total_length ) {
					$parameters['subscription']['tid']    = $this->order_details->trans_num;
					$parameters['subscription']['reason'] = '';
					$parameters['custom']['lang']         = novalnet_helper()->mepr_novalnet_get_language();
					$parameters['custom']['shop_invoked'] = 1;

					novalnet_helper()->mepr_novalnet_send_request( $parameters, novalnet_helper()->mepr_novalnet_format_endpoint( 'subscription_cancel' ), $this->settings->payment_access_key );
					/* translators: %s: tid */
					$subscription_cancel_note = sprintf( __( 'Subscription has been cancelled since the subscription has exceeded the maximum time period for the TID: %s', 'memberpress-novalnet-addon' ), $this->order_details->trans_num );
				} elseif ( ! empty( $next_cycle_date ) ) {
					/* translators: %s: next cycle date */
					$message .= sprintf( __( ' Next charging date will be on %1$s', 'memberpress-novalnet-addon' ), $next_cycle_date );
				}
				$response = array(
					'message' => $message,
					'txn'     => $new_txn,
				);
				if ( '' !== $subscription_cancel_note ) {
					$response['subs_cancel_note'] = $subscription_cancel_note;
				}
				return $response;
			} else {
				/* translators: %s: parent_tid*/
				$message = sprintf( __( 'Subscription not found for the transaction: %s', 'memberpress-novalnet-addon' ), $this->parent_tid );
				return array( 'message' => $message );
			}
		}
	}
}
