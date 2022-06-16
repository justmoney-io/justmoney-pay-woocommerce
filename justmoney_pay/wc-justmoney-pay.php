<?php
/*
  Plugin Name: JustMoney Pay Crypto Checkout
  Plugin URI:
  Description: Allows you to use JustMoney Pay Crypto Checkout with the WooCommerce plugin.
  Version: 1.0.0
  Author: JustMoney
  Author URI: https://pay.just.money
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/* Add a custom payment class to WC
  ------------------------------------------------------------ */
add_action( 'plugins_loaded', 'woocommerce_justmoney_pay' );

/**
 * Check if WooCommerce is active
 **/
function woocommerce_justmoney_pay() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	} // if the WC payment gateway class is not available, do nothing
	if ( class_exists( 'WC_Justmoney_Pay_Gateway' ) ) {
		return;
	}

	class WC_Justmoney_Pay_Gateway extends \WC_Payment_Gateway {

		/**** Plugin properties ****/
		protected $plugin_name;
		protected $version;


		// Logging
		public static $log_enabled = false;
		public static $log = false;
		private $seller_wallet;
		private $seller_wallet_tron;
		private $custom_style;
		private $debug;

		const PAYMENT_METHOD_ID = 'justmoney_pay';

		/**** Plugin Constructor and Initializer(Run) ****/
		/**
		 * WC_Gateway_Justmoneypay_Inline constructor.
		 */
		public function __construct() {
			//Initialize all the basics components of the plugin
			$this->plugin_name        = 'JustMoney Pay Payment Method';
			$this->method_description = __( 'JustMoney Pay Crypto Checkout', 'woocommerce' );
			$this->supports[]         = 'products';
			$this->version            = '1.0.0';

			$this->id         = self::PAYMENT_METHOD_ID;
			$this->icon       = apply_filters( 'woocommerce_justmoneypay_icon',
				plugin_dir_url( __FILE__ ) . "jm-100x38.svg" );
			$this->has_fields = false;

			// Load the settings
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables
			$this->title        = 'JustMoney Pay';
			$this->seller_wallet    = $this->get_option( 'seller_wallet' );
			$this->seller_wallet_tron    = $this->get_option( 'seller_wallet_tron' );
			$this->custom_style = $this->get_option( 'style' );
			$this->description  = '<img style="width: 100%;height: auto;max-width: 1000px;max-height: none;float: none;" src="'.plugin_dir_url(__FILE__).'/small_dark.jpg";"/><br/>Crypto checkout by <a href="https://pay.just.money/" target="_blank">JustMoney Pay </a>';
			$this->debug        = $this->get_option( 'debug' );

			self::$log_enabled = $this->debug;


			if ( ! $this->is_valid_for_use() ) {
				$this->enabled = false;
			}

			$this->add_actions();
		}

		/**** Plugin methods ****/

		private function add_actions() {
			//put your actions here
			add_action( 'woocommerce_receipt_' . $this->id, [ $this, 'receipt_page' ] );

			// Save options
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id,
				[
					$this,
					'process_admin_options',
				] );

			// Payment listener/API hook
			add_action( 'woocommerce_api_jm_status_update', [ $this, 'check_status_update_response' ] );
			add_action( 'woocommerce_api_jm_payment_response', [ $this, 'check_payment_response' ] );

        }

		/**
		 * Logging method
		 *
		 * @param string $message
		 */
		public static function log( $message ) {
			if ( self::$log_enabled ) {
				if ( empty( self::$log ) ) {
					self::$log = new WC_Logger();
				}
				self::$log->add( 'justmoneypay', $message );
			}
		}

		/**
		 * Admin Panel Options
		 */
		public function admin_options() {
			require_once plugin_dir_path( __FILE__ ) . 'templates/admin-options.php';
		}

		/**
		 * Initialise Gateway Settings Form Fields
		 *
		 * @access public
		 * @return void
		 */
		public function init_form_fields() {
			require_once plugin_dir_path( __FILE__ ) . 'templates/init_form_fields.php';
			$this->form_fields = getJustMoneyPayFormFields();
		}

		/**
		 * Check if this gateway is enabled and available in the user's country
		 *
		 * @access public
		 * @return bool
		 */
		function is_valid_for_use() {
			$supported_currencies = [
				'USD'
			];

			if ( ! in_array( get_woocommerce_currency(),
				apply_filters( 'woocommerce_justmoneypay_supported_currencies',
					$supported_currencies ) ) ) {
				return false;
			}

			return true;
		}


		/**
		 * Process the payment and return the result
		 *
		 * @access public
		 *
		 * @param int $order_id
		 *
		 * @return array
		 */
		public function process_payment( $order_id ) {

            if ( ! $this->seller_wallet || ! $this->seller_wallet_tron || strlen($this->seller_wallet) < 42 || strlen($this->seller_wallet_tron) < 34) {
                throw new Exception( 'Merchandiser needs to input their wallets for JustMoney Pay!' );
            }

			$order = new WC_Order( $order_id );
			$order->update_meta_data( '_jm_order_type', 'justmoney_pay' );
			$order->save_meta_data();
			$order->save();

			try {

				$order_params    = $this->build_checkout_parameters( $order );

				$api_params = array_merge(
                					$order_params['setup_data'],
                					$order_params['cart_data'],
                				);

                require_once plugin_dir_path( __FILE__ ) . 'JustMoneyPayApi.php';
                $api = new JustMoneyPay_Api();
                $jm_order_data = $api->call( 'newOrder', $api_params, 'POST' );

                print_r($jm_order_data);
				if (!$jm_order_data) {
				        wc_add_notice( 'There has been an error processing your order', $notice_type = 'error' );

                				return [
                					'result'   => 'failure',
                					'messages' => 'There has been an error processing your order',
                				];
				}
                $order->update_meta_data( '_jm_order_number', $jm_order_data["id"] );
                $order->update_meta_data( '_jm_order_hash', $jm_order_data["hash"] );
                $order->save_meta_data();
                $order->save();

				$pay_url = $jm_order_data["paymentUrl"];

				return [
					'result'   => 'success',
					'redirect' => $pay_url
				];

			} catch ( Exception $e ) {
				wc_add_notice( $e->getMessage(), $notice_type = 'error' );

				return [
					'result'   => 'failure',
					'messages' => 'There has been an error processing your order',
				];
			}
		}

		/**
		 * @param WC_Order $order
		 *
		 * @return array
		 */
		public function build_checkout_parameters( WC_Order $order ) {
			global $woocommerce;
			$woocommerce_version_formatted = str_replace('.', '_', $woocommerce->version);

            $expiration = time() + ( 3600 * 5 );

			//1. Setup data
			$setup_data             = [];
			$setup_data['wallets'] = [];
            $setup_data['wallets']['evm'] = $this->seller_wallet;
            $setup_data['wallets']['tron'] = $this->seller_wallet_tron;

			//2. Set the BASE needed fields.
			$cart_data                     = [];
			$cart_data['src']              = 'WOOCOMMERCE_' . $woocommerce_version_formatted;
			$cart_data['returnUrl']       = add_query_arg( 'wc-api', 'jm_payment_response', home_url( '/' ) ) . "&pm={$order->get_payment_method()}" . "&orderId={$order->get_id()}";
            $cart_data['statusHookUrl']       = add_query_arg( 'wc-api', 'jm_status_update', home_url( '/' ) ) . "&pm={$order->get_payment_method()}" . "&orderId={$order->get_id()}";
			$cart_data['expiration']       = $expiration;
			$cart_data['orderId']       = $order->get_id();
			$cart_data['currency']         = get_woocommerce_currency();
			$cart_data["totalAmount"] = $order->get_total();

			//3. Language config
			$current_locale_setting = get_option( 'WPLANG' );
			$current_store_lang     = get_locale();
			$lang                   = ! $current_store_lang ? $current_locale_setting : $current_store_lang;
			$langCode               = strstr( $lang, '_', true );
			$cart_data['language']  = $langCode;

			return [
				'setup_data'    => $setup_data,
				'cart_data'     => $cart_data
			];
		}


		/**
		 * Validate & process JustMoney Pay request
		 *
		 * @access public
		 * @return void|string
		 */
		public function check_status_update_response() {
			if ( $_SERVER['REQUEST_METHOD'] === 'GET' ) {
				return;
			}
            $postParams = json_decode(file_get_contents('php://input'), true);
            $params = $_GET;

            if ( ! isset( $params['pm'] ) || (string) $params['pm'] !== self::PAYMENT_METHOD_ID ) {
                return;
            }

            if ( ! isset( $params['orderId']) || empty( $params['orderId'] )) {
                return;
            }

            $order = wc_get_order( (int) $params['orderId'] );

            if ( ! $order instanceof WC_Order ) {
                $this->log( 'There was a request for an order that doesn\'t exist in current shop! Requested params: '
                    . strip_tags( http_build_query( $params ) ) );
                return;
            }
            if ( $order->has_status( 'pending' ) ) {
                $refNo = $order->get_meta("_jm_order_hash");
                require_once plugin_dir_path( __FILE__ ) . 'JustMoneyPayApi.php';
                $api = new JustMoneyPay_Api();
                $api_response = $api->call( 'getStatus/' . $refNo, [], 'GET' );

                if ( ! isset( $api_response['status'] )
                    || empty( $api_response['status'] )
                    || ! in_array( $api_response['status'], [ 'DONE' ] )
                ) {
                    $this->log( 'Api did not respond with expected result' );
                    return;
                }

                $order->payment_complete();
                $order->add_order_note( __( 'JustMoneyPay transaction ID: ' . $refNo ),false, false );
                $order->add_order_note( __( "Order payment is completed." ), false, false );
                $order->save();
                echo $postParams['secret'];
                exit();
            } else if($order->has_status( 'completed' ) || $order->has_status( 'processing' )) {
                echo $postParams['secret'];
                exit();
            }
		}

		/**
		 * @return void
		 */
		public function check_payment_response() {
			$params = $_GET;
			if ( ! isset( $params['pm'] ) || (string) $params['pm'] !== self::PAYMENT_METHOD_ID ) {
				return;
			}

			if ( ! isset( $params['orderId']) || empty( $params['orderId'] )) {
				$this->go_to_404_page();
			}

			$order = wc_get_order( (int) $params['orderId'] );

			if ( ! $order instanceof WC_Order ) {
				$this->log( 'There was a request for an order that doesn\'t exist in current shop! Requested params: '
				            . strip_tags( http_build_query( $params ) ) );
				$this->go_to_404_page();
			}

			$refNo = $order->get_meta("_jm_order_hash");
			require_once plugin_dir_path( __FILE__ ) . 'JustMoneyPayApi.php';
			$api = new JustMoneyPay_Api();
			$api_response = $api->call( 'getStatusByHash/' . $refNo, [], 'GET' );
            print_r($api_response);
			if ( ! isset( $api_response['status'] )
			     || empty( $api_response['status'] )
			     || ! in_array( $api_response['status'], [ 'DONE' ] )
			) {
				$this->log( 'Api did not respond with expected result' );
				$this->go_to_404_page();
			}

			$redirect_url = $order->get_checkout_order_received_url();
			if ( wp_redirect( $redirect_url ) ) {
				if ( $order->has_status( 'pending' ) ) {
                    $order->payment_complete();
                    $order->add_order_note( __( 'JustMoney Pay transaction ID: ' . $refNo ),false, false );
                    $order->add_order_note( __( "Order payment is completed." ), false, false );
					$order->save();
				}
				global $woocommerce;
				$woocommerce->cart->empty_cart();
			}
		}

		/**
		 * Returns a 404 page
		 */
		private function go_to_404_page()
		{
			status_header( 404 );
			nocache_headers();
			include( get_query_template( '404' ) );
			die;
		}
	}

	function add_justmoneypay_gateway( $methods ) {
		$methods[] = 'WC_Justmoney_Pay_Gateway';

		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways',
		'add_justmoneypay_gateway' );

}
