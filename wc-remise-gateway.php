<?php
/**
 * Plugin Name: Payment Gateway Remise for WooCommerce
 * Plugin URI: https://www.wpmarket.jp/product/wc_remise_gateway/
 * Description: Take Remise payments on your store of WooCommerce.
 * Author: Hiroaki Miyashita
 * Author URI: https://www.wpmarket.jp/
 * Version: 0.1
 * Requires at least: 4.4
 * Tested up to: 5.7.1
 * WC requires at least: 3.0
 * WC tested up to: 5.2.2
 * Text Domain: wc-remise-gateway
 * Domain Path: /
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function wc_remise_gateway_missing_admin_notices() {
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Remise requires WooCommerce to be installed and active. You can download %s here.', 'wc-remise-gateway' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

function wc_remise_gateway_mode_admin_notices() {
	echo '<div class="error"><p><strong><a href="https://www.wpmarket.jp/product/wc_remise_gateway/?domain='.$_SERVER['HTTP_HOST'].'" target="_blank">'.__( 'In order to use Remise, you have to purchase the authentication key at the following site.', 'wc-remise-gateway' ).'</a></strong></p></div>';
}

add_action( 'plugins_loaded', 'wc_remise_gateway_plugins_loaded' );
add_filter( 'woocommerce_payment_gateways', 'wc_remise_gateway_woocommerce_payment_gateways' );

function wc_remise_gateway_plugins_loaded() {
	load_plugin_textdomain( 'wc-remise-gateway', false, plugin_basename( dirname( __FILE__ ) ) );

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wc_remise_gateway_missing_admin_notices' );
		return;
	}
	
	$remise_option = get_option('woocommerce_remise_credit_settings');
	if ( empty($remise_option['authentication_key']) ) :
		add_action( 'admin_notices', 'wc_remise_gateway_mode_admin_notices' );	
	endif;

	if ( ! class_exists( 'WC_Gateway_Remise_Credit' ) ) :

		class WC_Gateway_Remise_Credit extends WC_Payment_Gateway {
			
			public function __construct() {
				$this->id = 'remise_credit';
				$this->method_title = __('Remise - Credit Card', 'wc-remise-gateway');
				$this->method_description = __('Enable the credit card payment by Remise. You can change the other settings here.', 'wc-remise-gateway');
				$this->has_fields = false;
				$this->supports = array('refunds');
				
				$this->init_form_fields();
				$this->init_settings();
				
				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->shopco = $this->get_option( 'shopco' );
				$this->hostid = $this->get_option( 'hostid' );
				$this->mode = $this->get_option( 'mode' );
				$this->authorization = $this->get_option( 'authorization' );
				$this->item = $this->get_option( 'item' );
				$this->status = $this->get_option( 'status' );
				$this->logging = $this->get_option( 'logging' );
				$this->authentication_key = $this->get_option( 'authentication_key' );
																				
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
				add_action( 'woocommerce_api_wc_remise', array( $this, 'check_for_webhook' ) );
			}
			
			function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-remise-gateway' ),
						'label'       => __( 'Enable Remise - Credit Card', 'wc-remise-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-remise-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-remise-gateway' ),
						'default'     => __( 'Credit Card', 'wc-remise-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-remise-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-remise-gateway' ),
						'default'     => __( 'Pay with your credit card', 'wc-remise-gateway' ),
						'desc_tip'    => true,
					),
					'shopco'    => array(
						'title' => __('Shop Code', 'wc-remise-gateway'),
						'type' => 'text',
						'default' => '' 
					),
					'hostid'    => array(
						'title' => __('Host ID', 'wc-remise-gateway'),
						'type' => 'text',
						'default' => '' 
					),
					'mode'    => array(
						'title' => __('Mode', 'wc-remise-gateway'),
						'type' => 'select',
						'default' => '',
						'options'     => array(
							'real' => __('Real', 'wc-remise-gateway'),
							'sandbox'  => __('Sandbox', 'wc-remise-gateway')
						)
					),
					'authorization'    => array(
						'title' => __('Authorization', 'wc-remise-gateway'),
						'type' => 'select',
						'default' => '',
						'options'     => array(
							'CAPTURE' => __('Capture', 'wc-remise-gateway'),
							'AUTH'  => __('Authorize', 'wc-remise-gateway')
						)
					),
					'item'    => array(
						'title' => __('Item', 'wc-remise-gateway'),
						'type' => 'select',
						'default' => '',
						'options'     => array(
							'0000120' => __('Mail-order (item)', 'wc-remise-gateway'),
							'0000990'  => __('Others (service)', 'wc-remise-gateway')
						)
					),
					'status'    => array(
						'title' => __('Status', 'wc-remise-gateway'),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __('Processing', 'wc-remise-gateway'),
							'completed' => __('Completed', 'wc-remise-gateway')
						)
					),
					'logging'    => array(
						'title'       => __( 'Logging', 'wc-remise-gateway' ),
						'label'       => __( 'Log debug messages', 'wc-remise-gateway' ),
						'type'        => 'checkbox',
						'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'wc-remise-gateway' ),
						'default'     => 'no',
						'desc_tip'    => true,
					),
					'authentication_key'    => array(
						'title' => __('Authentication Key', 'wc-remise-gateway'),
						'type' => 'text',
						'default' => '',
						'description' => '<a href="https://www.wpmarket.jp/product/wc_remise_gateway/?domain='.$_SERVER['HTTP_HOST'].'" target="_blank">'.__( 'In order to use Remise, you have to purchase the authentication key at the following site.', 'wc-remise-gateway' ).'</a>',
					),
				);
			}

			function process_admin_options( ) {
				$this->init_settings();

				$post_data = $this->get_post_data();
				
				$check_value = $this->wc_remise_gateway_check_authentication_key( $post_data['woocommerce_remise_credit_authentication_key'] );
				if ( $check_value == false ) :
					$_POST['woocommerce_remise_credit_authentication_key'] = '';
				endif;
				
				if ( $post_data['woocommerce_remise_credit_mode'] == 'real' && $check_value == false ) :
					$_POST['woocommerce_remise_credit_mode'] = 'sandbox';
			
					$settings = new WC_Admin_Settings();
         			$settings->add_error( __('Because Authentication Key is not valid, you can not set Real as the mode.', 'wc-remise-gateway') );
				endif;

				return parent::process_admin_options();
			}
			
			function wc_remise_gateway_check_authentication_key( $auth_key ) {
				$request = wp_remote_get('https://www.wpmarket.jp/auth/?gateway=remise&domain='.$_SERVER['HTTP_HOST'].'&auth_key='.$auth_key);
				if ( ! is_wp_error( $request ) && $request['response']['code'] == 200 ) :
					if ( $request['body'] == 1 ) :
						return true;
					else :
						return false;
					endif;
				else :
					return false;
				endif;
			}
			
			function process_payment( $order_id ) {
				$order = new WC_Order( $order_id );

				return array(
					'result' => 'success',
					'redirect' => $order->get_checkout_payment_url(true)
				);
			}
			
			function receipt_page( $order_id ) {
				global $woocommerce;
				$order = new WC_Order( $order_id );
				
				echo '<p>' . __( 'Redirecting automatically to the payment screen by Remise. If not, please push the following submit button.', 'wc-remise-gateway' ) . '</p>';
				$woocommerce->cart->empty_cart();

				wc_enqueue_js( 'jQuery( "#submit-form" ).click();' );

				$billing_email = $order->get_billing_email();
				
				if ( $this->mode == 'real' ) :
					$url = 'ssl01.remise.jp'; 
				else :
					$url = 'test.remise.jp'; 
				endif;

				echo '<form action="' . 'https://' . $url . '/rpgw2/pc/card/paycard.aspx' . '" method="post" accept-charset="Shift_JIS">
<input type="hidden" name="SHOPCO" value="' . esc_attr( $this->shopco ) . '">
<input type="hidden" name="HOSTID" value="' . esc_attr( $this->hostid ) . '">
<input type="hidden" name="S_TORIHIKI_NO" value="' . esc_attr( $order_id ) . '">
<input type="hidden" name="JOB" value="' . esc_attr( $this->authorization ) . '">
<input type="hidden" name="REMARKS3" value="A0001894" />
<input type="hidden" name="ITEM" value="' . esc_attr( $this->item ) . '">
<input type="hidden" name="AMOUNT" value="' . esc_attr( $order->get_total() ) . '">
<input type="hidden" name="TOTAL" value="' . esc_attr( $order->get_total() ) . '">
<input type="hidden" name="MAIL" value="' . esc_attr( $billing_email ) . '">
<input type="hidden" name="EXITURL" value="' . esc_attr( $order->get_checkout_payment_url( false ) ) . '">
<input type="hidden" name="RETURL" value="' . esc_attr( $this->get_return_url( $order ) ) . '">
<input type="hidden" name="NG_RETURL" value="' . esc_attr( $order->get_checkout_payment_url( false ) ) . '">
<div class="btn-submit-payment">
<input type="submit" id="submit-form" value="' . esc_attr( __('To the payment screen', 'wc-remise-gateway') ) . '">
</div>
</form>';
			}
			
			function check_for_webhook() {
				
				$netmask = 32;
				$ips = array(
					'183.177.132.57/29',
					'3.114.65.232',
				);
 
				$check_ip = $_SERVER['REMOTE_ADDR'];
				if( !isset($check_ip) ) exit;
 
				$ip_check = false;
				foreach($ips as $ip) :
					if(strpos($ip,'/')) list($ip, $netmask) = explode("/", $ip);
 
					$check = ip2long($check_ip) >> (32 - $netmask);
					$long  = ip2long($ip) >> (32 - $netmask);
 
					if ( $check == $long ) :
						$ip_check = true;
						break;
					endif;
				endforeach;
				
				if ( $ip_check !== true ) exit;
				
				if ( !empty($_POST['X-S_TORIHIKI_NO']) || !empty($_POST['S_TORIHIKI_NO']) ) :
					echo '<SDBKDATA>STATUS=800</SDBKDATA>';
				else :
					exit;
				endif;
				
				$response = $this->mb_convert_encoding_recursive($_POST, 'UTF-8', 'SJIS');
				$this->logging( $response );
				foreach ( $response as $key => $val ) :
					$response[$key] = sanitize_text_field( $val );
				endforeach;
				if ( !empty($response['X-S_TORIHIKI_NO']) ) :
					$order = new WC_Order( $response['X-S_TORIHIKI_NO'] );
					if ( !empty($order) ) :
						if ( !empty($response['X-TRANID']) ) :
							if ( empty($this->status) ) $this->status = 'processing';
							if ( $response['X-R_CODE']!='0:0000' || $response['X-ERRINFO']!='000000000' ) :
								$order->update_status( 'failed', sprintf( __( 'Remise settlement failed (R_CODE=%s, ERRCODE=%s, ERRINFO=%s).', 'wc-remise-gateway' ), $response['X-R_CODE'], $response['X-ERRCODE'], $response['X-ERRINFO'] ) );
							else :
								$order->update_status( $this->status, sprintf( __( 'Remise settlement completed (Transaction ID: %s).', 'wc-remise-gateway' ), $response['X-TRANID'] ) );				
							endif;
						endif;
					endif;
				endif;
				if ( !empty($response['S_TORIHIKI_NO']) ) :				
					$order = new WC_Order( $response['S_TORIHIKI_NO'] );
					if ( !empty($response['JOB_ID']) ) :
						$remise_option = get_option('woocommerce_remise_cvs_settings');
						if ( empty($remise_option['status']) ) $remise_option['status'] = 'processing';
						$order->update_status( $remise_option['status'], sprintf( __( 'Remise settlement completed (Job ID: %s, TOTAL: %s, CENDATE: %s).', 'wc-remise-gateway' ), $response['JOB_ID'], $response['TOTAL'], $response['CENDATE'] ) );				
					endif;
				endif;
				
				exit;
			}
			
			function logging( $error ) {
				if ( !empty($this->logging) ) :
					$logger = wc_get_logger();
					$logger->debug( wc_print_r( $error, true ), array( 'source' => 'wc-remise-gateway' ) );
				endif;
			}
			
			function mb_convert_encoding_recursive($mix, $toEncoding, $fromEncoding) {
    			if (is_string($mix)) {
					return mb_convert_encoding($mix, $toEncoding, $fromEncoding);
				}
				
				if (is_array($mix)) {
					foreach ($mix as $key => $var) {
						$mix[$key] = $this->mb_convert_encoding_recursive($var, $toEncoding, $fromEncoding);
					}
					return $mix;
 				}

				if (is_object($mix)) {
					$properties = get_object_vars($mix);
					foreach ($properties as $propertyName => $var) {
						$mix->$propertyName = $this->mb_convert_encoding_recursive($var, $toEncoding, $fromEncoding);
					}
					return $mix;
				}

				return $mix;
			}
			
		}

	endif;
	
	if ( ! class_exists( 'WC_Gateway_Remise_Cvs' ) ) :

		class WC_Gateway_Remise_Cvs extends WC_Payment_Gateway {			
			public function __construct() {
				$this->id = 'remise_cvs';
				$this->method_title = __('Remise - Multi Payments', 'wc-remise-gateway');
				$this->method_description = __('Enable Multi Payments by Remise.', 'wc-remise-gateway');
				$this->has_fields = false;
				$this->supports = array('refunds');
				
				$this->init_form_fields();
				$this->init_settings();
				
				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->status = $this->get_option( 'status' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
			}
			
			function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-remise-gateway' ),
						'label'       => __( 'Enable Remise - Multi Payments', 'wc-remise-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-remise-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-remise-gateway' ),
						'default'     => __( 'Multi Payments', 'wc-remise-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-remise-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-remise-gateway' ),
						'default'     => __( 'Pay with Multi Payments', 'wc-remise-gateway' ),
						'desc_tip'    => true,
					),
					'status'    => array(
						'title' => __('Status', 'wc-remise-gateway'),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __('Processing', 'wc-remise-gateway'),
							'completed' => __('Completed', 'wc-remise-gateway')
						)
					),
				);
			}
			
			function process_payment( $order_id ) {
				$order = new WC_Order( $order_id );

				return array(
					'result' => 'success',
					'redirect' => $order->get_checkout_payment_url(true)
				);
			}
			
			function receipt_page( $order_id ) {
				global $woocommerce;
				$order = new WC_Order( $order_id );
				
				$remise_option = get_option('woocommerce_remise_credit_settings');
				$shopco = !empty($remise_option['shopco']) ? $remise_option['shopco'] : '';
				$hostid = !empty($remise_option['hostid']) ? $remise_option['hostid'] : '';
				$mode = !empty($remise_option['mode']) ? $remise_option['mode'] : 'sandbox';
				
				echo '<p>' . __( 'Redirecting automatically to the payment screen by Remise. If not, please push the following submit button.', 'wc-remise-gateway' ) . '</p>';
				$woocommerce->cart->empty_cart();

				wc_enqueue_js( 'jQuery( "#submit-form" ).click();' );
				
				$billing_name = $order->get_billing_last_name().$order->get_billing_first_name();
				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();

				if ( $remise_option['mode'] == 'real' ) :
					$url = 'ssl01.remise.jp'; 
				else :
					$url = 'test.remise.jp'; 
				endif;

				echo '<form action="' . 'https://' . $url . '/rpgw2/pc/cvs/paycvs.aspx' . '" method="post" accept-charset="Shift_JIS">
<input type="hidden" name="SHOPCO" value="' . esc_attr( $shopco ) . '">
<input type="hidden" name="HOSTID" value="' . esc_attr( $hostid ) . '">
<input type="hidden" name="S_TORIHIKI_NO" value="' . esc_attr( $order_id ) . '">
<input type="hidden" name="REMARKS3" value="A0001894" />
<input type="hidden" name="NAME1" value="' . esc_attr( $billing_name ) . '">
<input type="hidden" name="TEL" value="' . esc_attr( $billing_phone ) . '">
<input type="hidden" name="MAIL" value="' . esc_attr( $billing_email ) . '">
<input type="hidden" name="TOTAL" value="' . esc_attr( $order->get_total() ) . '">
<input type="hidden" name="EXITURL" value="' . esc_attr( $order->get_checkout_payment_url( false ) ) . '">
<input type="hidden" name="RETURL" value="' . esc_attr( $this->get_return_url( $order ) ) . '">
<input type="hidden" name="NG_RETURL" value="' . esc_attr( $order->get_checkout_payment_url( false ) ) . '">
<div class="btn-submit-payment">
<input type="submit" id="submit-form" value="' . esc_attr( __('To the payment screen', 'wc-remise-gateway') ) . '">
</div>
</form>';
			}
			
		}
	
	endif;

}

function wc_remise_gateway_woocommerce_payment_gateways( $methods ) {
	$methods[] = 'WC_Gateway_Remise_Credit';
	$methods[] = 'WC_Gateway_Remise_Cvs';
	return $methods;
}
?>