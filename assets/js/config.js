/**
 * Novalnet payment addon
 *
 * This script is used for getting merchant details and configure webhook URL
 *
 * @author     Novalnet AG
 * @copyright  Copyright (c) Novalnet
 * @license    https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 * @link       https://www.novalnet.de
 * @category   JS
 * @package    Novalnet
 * Script: config.js
 */

(function( $ ) {
	mepr_novalnet_admin = {
		/**
		 * Initialize event handlers and validation.
		 */
		init : function(){
			if ( $( '#api_signature' ).val() != '' && $( '#payment_access_key' ).val() != '' ) {
				mepr_novalnet_admin.get_merchant_details();
			}
			$( '#api_signature, #payment_access_key' ).closest( 'form' ).on(
				'submit',
				function (event) {
					var form               = this;
					var selected_tariff_id = $( '#tariff_id :selected' ).val();
					$( "#saved_tariff_id" ).val( selected_tariff_id );
					var selected_subs_tariff_id = $( '#subs_tariff_id :selected' ).val();
					$( "#saved_subs_tariff_id" ).val( selected_subs_tariff_id );

					if ($( '#api_signature' ).val() == '' && $( '#payment_access_key' ).val() == '') {
						alert( 'Fill the required fields' );
						event.preventDefault();
						event.stopPropagation();
						return false;
					} else {
						mepr_novalnet_admin.get_merchant_details();
						return true;
					}
				}
			);
			$( '#webhook_configure' ).on(
				'click',
				function() {
					var webhook_url = $.trim( $( '.memberpress-novalnet-webhook-url' ).val() );
					var regex       = /(http|https):\/\/(\w+:{0,1}\w*)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%!\-\/]))?/;
					if ( ! regex .test( webhook_url ) || '' === $( '.memberpress-novalnet-webhook-url' ).val() || undefined === $( '.memberpress-novalnet-webhook-url' )) {
						alert( 'Enter the valid webhook URL' );
						return false;
					}
					if ( $( '.memberpress-novalnet-webhook-url' ).val() != '' ) {
						alert( 'Are you sure you want to configure the Webhook URL in Novalnet Admin Portal?' );
						mepr_novalnet_admin.handle_webhook_configure();
					}
					return false;
				}
			);
		},
		get_merchant_details:function(){
			var signature  = $.trim( $( '#api_signature' ).val() );
			var access_key = $.trim( $( '#payment_access_key' ).val() );
			var file_path  = $.trim( $( '#file_path' ).val() );
			if ( signature == '' || access_key == '' ) {
				null_basic_params();
				return false;
			}
			var data_to_send = {
				'signature': signature,
				'access_key':access_key,
				'action': 'novalnet_get_merchant_details',
			}
			mepr_novalnet_admin.ajax_call( data_to_send );
		},
		handle_webhook_configure:function(){
			var signature            = $.trim( $( '#api_signature' ).val() );
			var mepr_clipboard_input = $.trim( $( '.memberpress-novalnet-webhook-url' ).val() );
			var access_key           = $.trim( $( '#payment_access_key' ).val() );
			if ( signature == '' || access_key == '' ) {
				mepr_novalnet_admin.null_basic_params();
				return false;
			}
			var data_to_send = {
				'signature': signature,
				'access_key': access_key,
				'mepr_clipboard_input' : mepr_clipboard_input,
				'action': 'novalnet_configure_webhook',
			}
			mepr_novalnet_admin.ajax_call( data_to_send , 'webhook_configure' );
		},
		ajax_call:function(data_to_send, type = 'merchant_data'){
			$.ajax(
				{
					type: 'POST',
					url: ajaxurl,
					data: data_to_send,
					success: function( data ) {
						if ( 'merchant_data' === type ) {
							mepr_novalnet_admin.process_result( data );
						} else if ( 'webhook_configure' === type ) {
							var response = $.parseJSON( data );
							alert( response.result.status_text );
						}
					}
				}
			);
		},
		process_result:function(hash_string){
			var result               = $.parseJSON( hash_string );
			var saved_tariff_id      = $( "#saved_tariff_id" ).val();
			var saved_subs_tariff_id = $( '#saved_subs_tariff_id' ).val();
			var tariff_attr_name     = $( '#tariff_id' ).attr( 'name' );
			var sub_attr_name        = $( '#subs_tariff_id' ).attr( 'name' );
			$( '#tariff_id' ).replaceWith( '<select id="tariff_id" name = "' + tariff_attr_name + '"></select>' );
			$( '#subs_tariff_id' ).replaceWith( '<select id="subs_tariff_id" name = "' + sub_attr_name + '"></select>' );
			var tariff = result.merchant.tariff;
			$( '#client_key' ).val( result.merchant.client_key );
			$( '#project_id' ).val( result.merchant.project );
			$( '#tariff_id, #subs_tariff_id' ).empty().append();
			if ( tariff != undefined ) {
				for ( var tariff_id in result.merchant.tariff ) {
					var tariff_type  = result.merchant.tariff[ tariff_id ].type;
					var tariff_value = result.merchant.tariff[ tariff_id ].name;
					if ( '4' !== $.trim( tariff_type ) ) {
						$( '#tariff_id' ).append(
							$(
								'<option>',
								{
									value: $.trim( tariff_id ),
									text : $.trim( tariff_value )
									}
							)
						);
					}
					// Assign subscription tariff id.
					if ( '4' === $.trim( tariff_type ) ) {
						$( '#subs_tariff_id' ).append(
							$(
								'<option>',
								{
									value: $.trim( tariff_id ),
									text : $.trim( tariff_value )
								}
							)
						);
						if ( typeof saved_subs_tariff_id != 'undefined' && saved_subs_tariff_id != '' ) {
							$( "#subs_tariff_id" ).val( saved_subs_tariff_id );
						}
					}
					// Assign tariff id.
					if ( typeof saved_tariff_id != 'undefined' && saved_tariff_id != '' ) {
						$( "#tariff_id" ).val( saved_tariff_id );
					}
				}
			} else {
				null_basic_params();
				alert( result.status_desc );
			}
		},
		null_basic_params:function(){
			$( '#payment_access_key' ).val( '' );
			$( '#client_key' ).val( '' );
			$( '#tariff_id' ).find( 'option' ).remove();
			$( '#tariff_id' ).append(
				$(
					'<option>',
					{
						value: '',
						text : '',
					}
				)
			);
			$( '#subs_tariff_id' ).append(
				$(
					'<option>',
					{
						value: '',
						text : '',
					}
				)
			);
			$( '#subs_tariff_id' ).find( 'option' ).remove();
		}
	};
	$( document ).ready(
		function () {
			mepr_novalnet_admin.init();
		}
	);
})( jQuery );
