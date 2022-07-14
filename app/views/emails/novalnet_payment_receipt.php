<?php
/**
 * Novalnet payment addon
 *
 * This script is used for Novalnet payment receipt notice template design
 *
 * @author     Novalnet AG
 * @copyright  Copyright (c) Novalnet
 * @license    https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 * @link       https://www.novalnet.de
 * @package    Novalnetpaymentaddon
 *
 * Script: novalnet_payment_receipt.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' ); // Exit if accessed directly.
}
?>
<div id="body" style="font-family: 'Calibri';width: 600px; background: white; padding: 40px; margin: 0 auto; text-align: left;font-size:16px;">
	<table width="100%" style="border-collapse: collapse;">
		<tbody>
			<tr>
				<td valign="top">
					<table class="content-body" align="center"  style="background: #fafafa;border-collapse: collapse;" >
						<tbody>
							<tr>
								<td align="center">
									<table width="600" align="center" style="border-collapse: collapse;">
										<tbody>
											<tr>
												<td class="banner" align="left" style="background: #0080c9;background: url(https://cdn.novalnet.de/email/images/bg.png) no-repeat;padding: 10px;background-size: cover;">
													<table width="100%" style="border-collapse: collapse">
														<tbody>
															<tr>
																<td valign="top" align="center">
																	<table width="100%" style="border-collapse: collapse;">
																		<tbody>
																			<tr>
																				<td class="logo" align="center" style="display: block;margin: 5px 0 50px;">
																				<h1 style="color:white">{$blog_name}</h1>
																				</td>
																			</tr>
																		</tbody>
																	</table>
																</td>
															</tr>
														</tbody>
													</table>
												</td>
												<tr class="receipt">
													<td align="center">
														<h3 style="font-size: 20px;line-height: 1.2;margin: 10px;color: #0080c9;padding-top: 10px;"><?php echo esc_html( __( 'Payment Notice ', 'memberpress-novalnet-addon' ) ); ?></h3>
													</td>
												</tr>
											</tr>
											<tr>
												<td class="td-padding" align="left" style="padding: 0 20px;">
													<table align="left" style="border-collapse: collapse;">
														<tbody>
															<tr>
																<td align="left">
																	<table width="100%" style="border-collapse: collapse;">
																		<tbody>
																			<tr>
																				<td align="right">
																					<p style="font-size: 14px;line-height: 1.4;margin-top: 1em;margin-bottom: 1em;">
																					<?php
																					/* translators: %s:order_no*/
																					echo esc_html( sprintf( __( 'Order No: %s', 'memberpress-novalnet-addon' ), '{$order_no}' ) );
																					?>
																					</p>
																				</td>
																			</tr>
																		</tbody>
																	</table>
																</td>
															</tr>
														</tbody>
													</table>
													<table align="right" style="border-collapse: collapse;">
														<tbody>
															<tr>
																<td align="left">
																	<table width="100%" style="border-collapse: collapse;">
																		<tbody>
																			<tr>
																				<td align="right">
																					<p style="font-size: 14px;line-height: 1.4;margin-top: 1em;margin-bottom: 1em;">{$trans_date}</p>
																				</td>
																			</tr>
																		</tbody>
																	</table>
																</td>
															</tr>
														</tbody>
													</table>
												</td>
											</tr>
											<tr>
												<td class="td-padding" align="left" style="padding: 0 20px;">
													<table width="100%" style="border-collapse: collapse;">
														<tbody>
															<tr>
																<td width="600" valign="top" align="center">
																	<table width="100%" style="border-collapse: collapse;">
																		<tbody>
																			<tr>
																				<td class="seprator" align="center">
																					<table width="100%" height="100%" style="border-collapse: collapse;">
																						<tbody>
																							<tr>
																								<td style="border-bottom: 1px solid #efefef;background: rgba(0, 0, 0, 0) none repeat scroll 0 0;height: 1px;width: 100%;margin: 0;"></td>
																							</tr>
																						</tbody>
																					</table>
																				</td>
																			</tr>
																		</tbody>
																	</table>
																</td>
															</tr>
														</tbody>
													</table>
												</td>
											</tr>
											<tr>
												<td class="td-padding" align="left" style="padding: 0 20px;">
													<table width="100%" style="border-collapse: collapse;">
														<tbody>
															<tr>
																<td valign="top" align="center">
																	<table width="100%" style="border-collapse: collapse;">
																		<tbody>
																			<tr>
																				<td align="left">
																					<h2 style="margin-bottom: 5px;">
																					<?php
																					/* translators: %s:user_full_name*/
																					echo esc_html( sprintf( __( 'Dear %s,', 'memberpress-novalnet-addon' ), '{$user_full_name}' ) );
																					?>
																					</h2>
																				</td>
																			</tr>
																			<tr>
																				<td class="seprator" align="left">
																					<table width="10%" height="100%" style="border-collapse: collapse;">
																						<tbody>
																							<tr>
																								<td class="blue-seprator" style="border-bottom: 3px solid #0080c9;background: rgba(0, 0, 0, 0) none repeat scroll 0 0;height: 1px;width: 100%;margin: 0;"></td>
																							</tr>
																						</tbody>
																					</table>
																				</td>
																			</tr>
																			<tr>
																				<td align="left">
																					<p style="font-size: 14px;line-height: 1.4;margin-top: 1em;margin-bottom: 1em;">	
																						<?php
																						/* translators: %1$s: site_url, %2$s: blog_name*/
																						echo wp_kses( sprintf( __( 'Thank you for placing your order at <a href="%1$s">%2$s</a>. Please refer your transaction details below:', 'memberpress-novalnet-addon' ), '{$site_url}', '{$blog_name}' ), array( 'a' => array( 'href' => array() ) ) );
																						?>
																				</td>
																			</tr>
																		</tbody>
																	</table>
																</td>
															</tr>
														</tbody>
													</table>
												</td>
											</tr>
											<tr>
												<td class="td-padding" align="left" style="padding: 0 20px;">
													<table width="100%" style="border-collapse: collapse;">
														<tbody>
															<tr>
																<td width="560" valign="top" align="center">
																	<table width="100%" style="border-collapse: collapse;">
																		<tbody>
																			<tr>
																				<td class="seprator" align="center">
																					<table width="100%" height="100%" style="border-collapse: collapse;">
																						<tbody>
																							<tr>
																								<td style="border-bottom: 1px solid #efefef;background: rgba(0, 0, 0, 0) none repeat scroll 0 0;height: 1px;width: 100%;margin: 0;"></td>
																							</tr>
																						</tbody>
																					</table>
																				</td>
																			</tr>
																		</tbody>
																	</table>
																</td>
															</tr>
														</tbody>
													</table>
												</td>
											</tr>
											<tr>
												<td class="td-padding" align="left" style="padding: 0 20px;">
													<table width="100%" style="border-collapse: collapse;">
														<tbody>
															<tr>
																<td>
																	<table width="100%" style="border-collapse: collapse;">
																		<tbody>
																			<tr>
																				<td align="left">
																					<table width="100%" style="border-collapse: collapse;">
																						<tbody>
																							<tr>
																								<td align="left">
																									<h3 style="font-size: 16px;line-height: 1.2;margin-top: 1em;margin-bottom: 1em;">
																										<?php echo esc_html( __( 'Order Summary', 'memberpress-novalnet-addon' ) ); ?>
																									</h3>
																								</td>
																							</tr>
																							<tr class="tr-class" style="background: #f2f2f2;border-bottom: 1px solid #e3e3e3"><td align="left" class="td-class" style="padding: 10px 20px"><p style="font-size: 14px;line-height: 1.4;margin: 0">
																							<?php echo esc_html( __( 'TID :', 'memberpress-novalnet-addon' ) ); ?>
																							</p></td><td align="right" class="td-class" style="padding: 10px 20px;"><p style="font-size: 14px;line-height: 1.4;margin: 0"><a href="https://card.novalnet.de" target="_blank" style="color: #0080c9;text-decoration: none">{$trans_num}</a></p></td></tr>
																						</tbody>
																					</table>
																				</td>
																			</tr>
																		</tbody>
																	</table>
																</td>
															</tr>
															<tr>
																<td>
																	<table width="100%" style="border-collapse: collapse;">
																		<tbody>
																			<tr>
																				<td align="left">
																					<table width="100%" style="border-collapse: collapse;">
																						<tbody>
																							<tr>
																								<td align="left">
																									<h3 style="font-size: 16px;line-height: 1.2;margin-top: 1em;margin-bottom: 1em;">
																									<?php
																									echo esc_html( __( 'Transaction Note', 'memberpress-novalnet-addon' ) );
																									?>
																									</h3>
																								</td>
																							</tr>
																							<tr class="tr-class" style="background: #f2f2f2;border-bottom: 1px solid #e3e3e3;">
																							<td colspan=2 align="right" class="td-class" style="padding: 10px 20px;">
																								<p style="font-size: 14px;line-height: 1.4;margin: 0;text-align:left;">
																								{$novalnet_txn_details}
																								</p>
																								</td>
																								</tr>
																						</tbody>
																					</table>
																				</td>
																			</tr>
																		</tbody>
																	</table>
																</td>
															</tr>
														</tbody>
													</table>
												</td>
											</tr>
										</tbody>
									</table>
								</td>
							</tr>
						</tbody>
					</table>
					<table class="content-body" align="center" style="background: #fafafa;border-collapse: collapse;">
						<tbody>
							<tr>
								<td align="center">
									<table width="600" align="center" style="border-collapse: collapse;">
										<tbody>
											<tr>
												<td align="left">
													<table width="100%" style="border-collapse: collapse;">
														<tbody>
															<tr>
																<td valign="top" align="center">
																	<table width="100%" style="border-collapse: collapse;">
																		<tbody>
																			<tr>
																				<td class="seprator td-padding" align="center" style="padding: 0 20px;">
																					<table width="100%" height="100%" style="border-collapse: collapse;">
																						<tbody>
																							<tr>
																								<td style="border-bottom: 1px solid #efefef;background: rgba(0, 0, 0, 0) none repeat scroll 0 0;height: 1px;width: 100%;margin: 0;"></td>
																							</tr>
																						</tbody>
																					</table>
																				</td>
																			</tr>
																		</tbody>
																	</table>
																</td>
															</tr>
														</tbody>
													</table>
												</td>
											</tr>
										</tbody>
									</table>
								</td>
							</tr>
						</tbody>
					</table>
					<table class="content-footer" align="center" style="border-collapse:collapse;background:#0080c9;color:#fff;">
						<tbody>
							<tr>
								<td align="center">
									<table width="600" align="center" style="border-collapse:collapse;">
										<tbody>
											<tr class="footer" style="font-size:13px;color:#fff;">
												<td class="td-padding" align="left">
													<table width="100%" style="border-collapse:collapse;">
														<tbody>
															<tr>
																<td align="center">
																	<h1 style="color:white">{$blog_name}</h1>
																</td>
															</tr>
															<tr>
																<td align="center">
																	<p style="font-size:13px;line-height:1.4;margin-top:1em;margin-bottom:1em;color:#fff!important;"><span style="color:white;">{$business_name}</span><br><span style="color:#fff;">{$biz_address1} {$biz_address2}</span><br><span style="color:#fff;">{$biz_city}</span><br><span style="color:#fff;">{$biz_state} {$biz_postcode}</span><br><span style="color:#fff;">{$biz_country}</span></p>
																</td>
															</tr>
														</tbody>
													</table>
												</td>
											</tr>
										</tbody>
									</table>
								</td>
							</tr>
						</tbody>
					</table>
				</td>
			</tr>
		</tbody>
	</table>
</div>
