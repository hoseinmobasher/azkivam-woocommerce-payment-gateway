<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function Load_Azkivam_Gateway() {
	if ( ! function_exists( 'Woocommerce_Add_Azkivam_Gateway' ) && class_exists( 'WC_Payment_Gateway' ) && ! class_exists( 'WC_Azkivam' ) ) {
		add_filter( 'woocommerce_payment_gateways', 'Woocommerce_Add_Azkivam_Gateway' );
		function Woocommerce_Add_Azkivam_Gateway( $methods ) {
			$methods[] = 'WC_Azkivam';

			return $methods;
		}

		add_filter( 'woocommerce_currencies', 'Woocommerce_Azkivam_Gateway_add_IR_currency' );
		function Woocommerce_Azkivam_Gateway_add_IR_currency( $currencies ) {
			$currencies['IRR']  = __( 'ریال', 'woocommerce' );
			$currencies['IRT']  = __( 'تومان', 'woocommerce' );
			$currencies['IRHR'] = __( 'هزار ریال', 'woocommerce' );
			$currencies['IRHT'] = __( 'هزار تومان', 'woocommerce' );

			return $currencies;
		}

		add_filter( 'woocommerce_currency_symbol', 'Woocommerce_Azkivam_Gateway_add_IR_currency_symbol', 10, 2 );
		function Woocommerce_Azkivam_Gateway_add_IR_currency_symbol( $currency_symbol, $currency ) {
			switch ( $currency ) {
				case 'IRR':
					$currency_symbol = 'ریال';
					break;
				case 'IRT':
					$currency_symbol = 'تومان';
					break;
				case 'IRHR':
					$currency_symbol = 'هزار ریال';
					break;
				case 'IRHT':
					$currency_symbol = 'هزار تومان';
					break;
			}

			return $currency_symbol;
		}

		class WC_Azkivam extends WC_Payment_Gateway {
			private $merchantId;
			private $failedMessage;
			private $successMessage;

			public function __construct() {
				$this->id                 = 'WC_Azkivam';
				$this->method_title       = __( 'پرداخت اعتباری ازکی‌وام', 'woocommerce' );
				$this->method_description = __( 'تنظیمات درگاه پرداخت اعتباری ازکی‌وام برای افزونه فروشگاه‌ساز ووکامرس', 'woocommerce' );
				$this->icon               = apply_filters( 'WC_Azkivam_logo', WP_PLUGIN_URL . '/' . plugin_basename( __DIR__ ) . '/assets/images/logo.svg' );
				$this->has_fields         = false;

				$this->init_form_fields();
				$this->init_settings();

				$this->title       = $this->settings['title'];
				$this->description = $this->settings['description'];

				$this->merchantId     = $this->settings['merchantId'];
				$this->successMessage = $this->settings['successMessage'];
				$this->failedMessage  = $this->settings['failedMessage'];

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
					$this,
					'process_admin_options'
				) );
				add_action( 'woocommerce_receipt_' . $this->id . '', array( $this, 'Send_to_Azkivam_Gateway' ) );
				add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ) . '', array(
					$this,
					'Return_from_Azkivam_Gateway'
				) );
			}

			public function init_form_fields() {
				$this->form_fields = apply_filters( 'WC_Azkivam_Config', array(
					'enabled'        => array(
						'title'       => __( 'فعالسازی/غیرفعالسازی', 'woocommerce' ),
						'type'        => 'checkbox',
						'label'       => __( 'فعالسازی درگاه ازکی‌وام', 'woocommerce' ),
						'description' => __( 'برای فعالسازی درگاه پرداخت ازکی‌وام باید چک باکس را تیک بزنید', 'woocommerce' ),
						'default'     => 'yes',
						'desc_tip'    => true,
					),
					'title'          => array(
						'title'       => __( 'عنوان درگاه', 'woocommerce' ),
						'type'        => 'text',
						'description' => __( 'عنوان درگاه که در طی خرید به مشتری نمایش داده میشود', 'woocommerce' ),
						'default'     => __( 'پرداخت اعتباری ازکی‌وام', 'woocommerce' ),
						'desc_tip'    => true,
					),
					'description'    => array(
						'title'       => __( 'توضیحات درگاه', 'woocommerce' ),
						'type'        => 'text',
						'desc_tip'    => true,
						'description' => __( 'توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', 'woocommerce' ),
						'default'     => __( 'پرداخت اعتباری از طریق درگاه پرداخت ازکی‌وام', 'woocommerce' )
					),
					'merchantId'     => array(
						'title'       => __( 'کد فروشگاه', 'woocommerce' ),
						'type'        => 'text',
						'description' => __( 'کد فروشگاه نزد ازکی‌وام', 'woocommerce' ),
						'default'     => '',
						'desc_tip'    => true
					),
					'apiKey'         => array(
						'title'       => __( 'کد امنیتی فروشگاه', 'woocommerce' ),
						'type'        => 'text',
						'description' => __( 'کد امنیتی فروشگاه برای ارتباط با درگاه ازکی‌وام', 'woocommerce' ),
						'default'     => '',
						'desc_tip'    => true
					),
					'paymentUri'     => array(
						'title'       => __( 'آدرس API ازکی‌وام', 'woocommerce' ),
						'type'        => 'text',
						'description' => __( 'آدرس API ازکی‌وام', 'woocommerce' ),
						'default'     => '',
						'desc_tip'    => true
					),
					'successMessage' => array(
						'title'       => __( 'پیام پرداخت موفق', 'woocommerce' ),
						'type'        => 'textarea',
						'description' => __( 'متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {transaction_id} برای نمایش کد رهگیری (توکن) ازکی‌وام استفاده نمایید .', 'woocommerce' ),
						'default'     => __( 'با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'woocommerce' ),
					),
					'failedMessage'  => array(
						'title'       => __( 'پیام پرداخت ناموفق', 'woocommerce' ),
						'type'        => 'textarea',
						'description' => __( 'متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده نمایید . این دلیل خطا از سایت ازکی‌وام ارسال میگردد .', 'woocommerce' ),
						'default'     => __( 'پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'woocommerce' ),
					),
				) );
			}

			public function process_payment( $order_id ): array {
				$order = new WC_Order( $order_id );

				return array(
					'result'   => 'success',
					'redirect' => $order->get_checkout_payment_url( true )
				);
			}

			public function Send_to_Azkivam_Gateway( $order_id ) {
				global $woocommerce;
				$woocommerce->session->order_id_azkivam = $order_id;

				$order    = new WC_Order( $order_id );
				$currency = $order->get_currency();
				$currency = apply_filters( 'WC_Azkivam_Currency', $currency, $order_id );

				$form = '<form action="" method="POST" class="azkivam-checkout-form" id="azkivam-checkout-form">
						<input type="submit" name="azkivam_submit" class="button alt" id="azkivam-payment-button" value="' . __( 'پرداخت', 'woocommerce' ) . '"/>
						<a class="button cancel" href="' . $woocommerce->cart->get_checkout_url() . '">' . __( 'بازگشت', 'woocommerce' ) . '</a>
					 </form><br/>';
				$form = apply_filters( 'WC_Azkivam_Form', $form, $order_id, $woocommerce );

				do_action( 'WC_Azkivam_Gateway_Before_Form', $order_id, $woocommerce );
				echo $form;
				do_action( 'WC_Azkivam_Gateway_After_Form', $order_id, $woocommerce );

				$amount             = (int) $order->get_total( $currency );
				$amount             = apply_filters( 'woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $amount, $currency );
				$strToLowerCurrency = strtolower( $currency );

				if ( strtolower( $currency ) === strtolower( 'IRHT' ) ) {
					$amount *= 1000;
				} else if ( strtolower( $currency ) === strtolower( 'IRHR' ) ) {
					$amount *= 100;
				}

				$amount = apply_filters( 'woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $amount, $currency );
				$amount = apply_filters( 'woocommerce_order_amount_total_IRANIAN_gateways_irt', $amount, $currency );
				$amount = apply_filters( 'woocommerce_order_amount_total_Azkivam_gateway', $amount, $currency );

				$callback_uri = add_query_arg( 'wc_order', $order_id, WC()->api_request_url( 'WC_Azkivam' ) );

				$mobile_number = get_post_meta( $order_id, '_billing_phone', true ) ?: '-';
				$mobile_number = apply_filters( 'WC_Azkivam_Mobile', $mobile_number, $order_id );
				$mobile_number = preg_match( '/^09[0-9]{9}/i', $mobile_number ) ? $mobile_number : '';

				if ( $strToLowerCurrency === strtolower( 'IRT' ) ||
				     $strToLowerCurrency === strtolower( 'TOMAN' ) ||
				     $strToLowerCurrency === strtolower( 'Iran TOMAN' ) ||
				     $strToLowerCurrency === strtolower( 'Iranian TOMAN' ) ||
				     $strToLowerCurrency === strtolower( 'Iran-TOMAN' ) ||
				     $strToLowerCurrency === strtolower( 'Iranian-TOMAN' ) ||
				     $strToLowerCurrency === strtolower( 'Iran_TOMAN' ) ||
				     $strToLowerCurrency === strtolower( 'Iranian_TOMAN' ) ||
				     $strToLowerCurrency === strtolower( 'IRHT' ) ||
				     $strToLowerCurrency === strtolower( 'تومان' ) ||
				     $strToLowerCurrency === strtolower( 'IRHR' ) ||
				     $strToLowerCurrency === strtolower( 'تومان ایران' ) ) {
					$amount = $amount * 10;
				}

				$items = array();
				for ( $index = 0; $index < count( $order->get_items() ); $index ++ ) {
					$key     = array_keys( $order->get_items() )[ $index ];
					$value   = $order->get_items()[ $key ];
					$items[] = array(
						'name'   => $value->get_name(),
						'url'    => get_permalink( $value->get_product_id() ),
						'count'  => $value->get_quantity(),
						'amount' => $value->get_total() / $value->get_quantity()
					);
				}

				$provider_id                       = mt_rand( 100000000, 999999999 );
				$woocommerce->session->provider_id = $provider_id;

				$result = $this->doRequest( '/payment/purchase', array(
						'amount'        => $amount,
						'redirect_uri'  => $callback_uri,
						'fallback_uri'  => $callback_uri,
						'provider_id'   => $provider_id,
						'mobile_number' => $mobile_number,
						'items'         => $items
					)
				);

				if ( $result->rsCode == 0 ) {
					header( 'Location: ' . $result->result->payment_uri );
					exit;
				} else {
					$Message = 'تراکنش ناموفق بود - کد خطا ' . $result->rsCode;
					$Fault   = '';
				}

				if ( ! empty( $Message ) && $Message ) {
					$Note = sprintf( __( 'خطا در هنگام ارسال به ازکی‌وام : %s', 'woocommerce' ), $Message );
					$Note = apply_filters( 'WC_Azkivam_Send_to_Gateway_Failed_Note', $Note, $order_id, $Fault );
					$order->add_order_note( $Note );

					$Notice = sprintf( __( 'در هنگام اتصال به ازکی‌وام خطای زیر رخ داده است : <br/>%s', 'woocommerce' ), $Message );
					$Notice = apply_filters( 'WC_Azkivam_Send_to_Gateway_Failed_Notice', $Notice, $order_id, $Fault );
					if ( $Notice ) {
						wc_add_notice( $Notice, 'error' );
					}

					do_action( 'WC_Azkivam_Send_to_Gateway_Failed', $order_id, $Fault );
				}
			}

			public function Return_from_Azkivam_Gateway() {
				global $woocommerce;
				if ( isset( $_GET['wc_order'] ) ) {
					$order_id = $_GET['wc_order'];
				} else {
					$order_id = $woocommerce->session->order_id_azkivam;
					unset( $woocommerce->session->order_id_azkivam );
				}

				if ( $order_id ) {
					$order = new WC_Order( $order_id );

					if ( $order->status !== 'completed' ) {
						if ( $_GET['status'] == 'Done' ) {
							$ticketId = $_GET['ticketId'];
							$result   = $this->doRequest( '/payment/verify', array( 'ticket_id' => $ticketId ) );

							$TransactionId = $ticketId;
							if ( $result->rsCode == 0 ) {
								$Status  = 'completed';
								$Fault   = '';
								$Message = '';
							} else {
								$Status  = 'failed';
								$Fault   = $result->rsCode;
								$Message = $this->getErrorMessage( $result->rsCode );
							}

						} else {
							$Status  = 'failed';
							$Fault   = '';
							$Message = 'تراکنش انجام نشد.';
						}

						if ( $Status == 'completed' && isset( $TransactionId ) ) {
							update_post_meta( $order_id, '_transaction_id', $TransactionId );
							$order->payment_complete( $TransactionId );
							$woocommerce->cart->empty_cart();

							$Note = sprintf( __( 'پرداخت موفقیت آمیز بود .<br/> کد رهگیری : %s', 'woocommerce' ), $TransactionId );
							$Note = apply_filters( 'WC_Azkivam_Return_from_Gateway_Success_Note', $Note, $order_id, $TransactionId );
							if ( $Note ) {
								$order->add_order_note( $Note, 1 );
							}

							$Notice = wpautop( wptexturize( $this->successMessage ) );
							$Notice = str_replace( '{transaction_id}', $TransactionId, $Notice );
							$Notice = apply_filters( 'WC_Azkivam_Return_from_Gateway_Success_Notice', $Notice, $order_id, $TransactionId );
							if ( $Notice ) {
								wc_add_notice( $Notice, 'success' );
							}

							do_action( 'WC_Azkivam_Return_from_Gateway_Success', $order_id, $TransactionId );
							wp_redirect( add_query_arg( 'wc_status', 'success', $this->get_return_url( $order ) ) );
							exit;
						}

						if ( $TransactionId ) {
							$tr_id = ( '<br/>شناسه پرداخت : ' . $TransactionId );
						} else {
							$tr_id = '';
						}

						$ProviderId = $woocommerce->session->provider_id;
						if ( $ProviderId ) {
							$pr_id = ( '<br/>شناسه یکتای خرید : ' . $ProviderId );
						} else {
							$pr_id = '';
						}

						$Note = sprintf( __( 'خطا در هنگام بازگشت از بانک : %s %s %s', 'woocommerce' ), $Message, $tr_id, $pr_id );
						$Note = apply_filters( 'WC_Azkivam_Return_from_Gateway_Failed_Note', $Note, $order_id, $TransactionId, $Fault );
						if ( $Note ) {
							$order->add_order_note( $Note, 1 );
						}

						$Notice = wpautop( wptexturize( $this->failedMessage ) );
						$Notice = str_replace( array( '{transaction_id}', '{fault}' ), array(
							$TransactionId,
							$Message
						), $Notice );

						$Notice = apply_filters( 'WC_Azkivam_Return_from_Gateway_Failed_Notice', $Notice, $order_id, $TransactionId, $Fault );
						if ( $Notice ) {
							wc_add_notice( $Notice, 'error' );
						}

						do_action( 'WC_Azkivam_Return_from_Gateway_Failed', $order_id, $TransactionId, $Fault );

						wp_redirect( $woocommerce->cart->get_checkout_url() );
						exit;
					}

					$TransactionId = get_post_meta( $order_id, '_transaction_id', true );

					$Notice = wpautop( wptexturize( $this->successMessage ) );
					$Notice = str_replace( '{transaction_id}', $TransactionId, $Notice );
					$Notice = apply_filters( 'WC_Azkivam_Return_from_Gateway_ReSuccess_Notice', $Notice, $order_id, $TransactionId );
					if ( $Notice ) {
						wc_add_notice( $Notice, 'success' );
					}

					do_action( 'WC_Azkivam_Return_from_Gateway_ReSuccess', $order_id, $TransactionId );
					wp_redirect( add_query_arg( 'wc_status', 'success', $this->get_return_url( $order ) ) );
					exit;
				}

				$Fault  = __( 'شماره سفارش وجود ندارد .', 'woocommerce' );
				$Notice = wpautop( wptexturize( $this->failedMessage ) );
				$Notice = str_replace( '{fault}', $Fault, $Notice );
				$Notice = apply_filters( 'WC_Azkivam_Return_from_Gateway_No_Order_ID_Notice', $Notice, $order_id, $Fault );
				if ( $Notice ) {
					wc_add_notice( $Notice, 'error' );
				}

				do_action( 'WC_Azkivam_Return_from_Gateway_No_Order_ID', $order_id, '0', $Fault );

				wp_redirect( $woocommerce->cart->get_checkout_url() );
				exit;
			}

			public function signature( $sub_url, $request_method, $api_key ): string {
				$plain  = $sub_url . '#' . time() . '#' . $request_method . '#' . $api_key;
				$key    = hex2bin( $api_key );
				$digest = openssl_encrypt( $plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA );

				return bin2hex( $digest );
			}

			public function doRequest( $action, $data ) {
				try {
					$api_key             = $this->settings['apiKey'];
					$signature           = $this->signature( $action, 'POST', $api_key );
					$merchant_id         = $this->settings['merchantId'];
					$payment_uri         = $this->settings['paymentUri'];
					$data['merchant_id'] = $merchant_id;
					$data_string         = json_encode( $data );

					$ch = curl_init();
					curl_setopt( $ch, CURLOPT_URL, $payment_uri . $action );
					curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
					curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
					curl_setopt( $ch, CURLOPT_POSTFIELDS, $data_string );
					curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
						'Content-Type: application/json',
						'Signature: ' . $signature,
						'MerchantId: ' . $merchant_id,
						'Content-Length: ' . strlen( $data_string )
					) );

					$result = curl_exec( $ch );
					curl_close( $ch );

					return json_decode( $result );
				} catch ( Exception $ex ) {
					return false;
				}
			}

			public function getErrorMessage( $rsCode ): string {
				switch ( $rsCode ) {
					case 1:
						return "خطای داخلی اتفاق افتاده است.";
					case 12:
						return "فروشگاه فعال نیست.";
					case 13:
						return "شماره موبایل معتبر نیست.";
					case 20:
						return "شماره موبایل مشتری با ثبت نام شده در درگاه ازکی‌وام یکسان نیست.";
					case 21:
						return "اعتبار کافی نیست.";
					case 28:
						return "تراکنش قابل تأیید نیست.";
					default:
						return "پرداخت ناموفق است.";
				}
			}
		}
	}
}

add_action( 'plugins_loaded', 'Load_Azkivam_Gateway', 0 );
