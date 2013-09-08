<?php

/*
  Plugin Name: WooCommerce PayZippy Payment Gateway
  Plugin URI: http://www.vipulkumar.tk
  Description: PayZippy Payment Gateway for Woocommerce
  Version: 1.0
  Author: Vipul Kumar
  Author URI: http://www.vipulkumar.tk
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_action('plugins_loaded', 'woocommerce_vipulucky_payzippy_init', 0);

function woocommerce_vipulucky_payzippy_init() {

    if (!class_exists('WC_Payment_Gateway'))
        return;

    /**
     * PayZippy Standard Payment Gateway
     *
     * Provides a PayZippy Standard Payment Gateway.
     *
     * @class       WC_Gateway_PayZippy
     * @extends     WC_Payment_Gateway
     * @version     1.0.0
     * @author      Vipul Kumar - vipulucky93[at]gmail[dot]com
     */
    class WC_Gateway_PayZippy extends WC_Payment_Gateway {

        /*
         * 
         * Constructor for the gateway.
         *
         * @access public
         * @return void
         */
        public function __construct() {
            
            //plugin id
            $this->id = 'payzippy';
            //Payment Gateway title
            $this->method_title = 'PayZippy';
            //true only in case of direct payment method, false in our case
            $this->has_fields = false;
            //payment gateway logo
            $this->icon = plugins_url('images/payzippy-logo.png', __FILE__);
            
            //redirect URL
            $this->redirect_url = site_url('/wc-api/WC_Gateway_Payzippy');
            
            //Load settings
            $this->init_form_fields();
            $this->init_settings();
            
            // Define user set variables
            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->merchant_id = $this->settings['merchant_id'];
            $this->merchant_key_id = $this->settings['merchant_key_id'];
            $this->salt = $this->settings['salt'];
            $this->liveurl = 'https://www.payzippy.com/payment/api/charging/v1';
            
            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            add_action('woocommerce_receipt_payzippy', array(&$this, 'receipt_page'));
            
            // Payment listener/API hook
            add_action( 'woocommerce_api_wc_gateway_payzippy', array( $this, 'check_payzippy_response' ) );
        }

        function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'vipulucky'),
                    'type' => 'checkbox',
                    'label' => __('Enable PayZippy Payment Module.', 'vipulucky'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('Title:', 'vipulucky'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'vipulucky'),
                    'default' => __('PayZippy', 'vipulucky')),
                'description' => array(
                    'title' => __('Description:', 'vipulucky'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'vipulucky'),
                    'default' => __('Pay securely by Credit or Debit card or internet banking through PayZippy Secure Servers.', 'vipulucky')),
                'merchant_id' => array(
                    'title' => __('Merchant ID', 'vipulucky'),
                    'type' => 'text',
                    'description' => __('The merchant ID issued by PayZippy."')),
                'merchant_key_id' => array(
                    'title' => __('Merchant Key ID', 'vipulucky'),
                    'type' => 'text',
                    'description' => __('The merchant key issued by PayZippy."')),
                'salt' => array(
                    'title' => __('Secret Key', 'vipulucky'),
                    'type' => 'text',
                    'description' => __('The secret key given to Merchant by PayZippy', 'vipulucky'),
                )
            );
        }

        public function admin_options() {
            echo '<h3>' . __('PayZippy Payment Gateway', 'vipulucky') . '</h3>';
            echo '<p>' . __('PayZippy payment gateway by Flipkart for online shopping in India') . '</p>';
            echo '<table class="form-table">';
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            echo '</table>';
        }

        function payment_fields() {
            if ($this->description)
                echo wpautop(wptexturize($this->description));
        }

        /**
         * Order Receipt Page - Proceed to Gateway
         * */
        function receipt_page($order) {
            echo '<p>' . __('Thank you for your order, please click the button below to pay with PayZippy.', 'vipulucky') . '</p>';
            echo $this->generate_payzippy_form($order);
        }

        /**
         * Generate payzippy button link
         * */
        public function generate_payzippy_form($order_id) {

            global $woocommerce;
            //create oder
            $order = new WC_Order($order_id);
            //create unique id based on order id to send to payzippy
            $txnid = $order_id . '_' . date("ymds");
            
            //set order total (multiplied by hundered because payzippy uses paise, not rupees for payment)
            $total = ($order->order_total) * 100;
            
            //String to generate hash
            $str = "$order->billing_email|$this->redirect_url|INR|SHA256|$this->merchant_id|$this->merchant_key_id|$txnid|CREDIT|$total|SALE|REDIRECT|$this->salt";
            //generate hash by sha-256 encryption
            $hash = hash('sha256', $str);
            
            //parameters to send to payzippy through the form
            $payzippy_args = array(
                'merchant_id' => $this->merchant_id,
                'merchant_transaction_id' => $txnid,
                'merchant_key_id' => $this->merchant_key_id,
                'buyer_email_address' => $order->billing_email,
                'transaction_type' => 'SALE',
                'transaction_amount' => ($order->order_total) * 100,
                'payment_method' => 'CREDIT',
                'currency' => 'INR',
                'ui_mode' => 'REDIRECT',
                'hash_method' => 'SHA256',
                'hash' => $hash,
                'callback_url' => $this->redirect_url
            );
            
            //generate form for the arguments
            $payzippy_argsarray = array();
            foreach ($payzippy_args as $key => $value) {
                $payzippy_args_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
            }
            
            $woocommerce->add_inline_js( '
                jQuery("#submit_payzippy_payment_form").click();
            ' );
            
            return '<form action="' . $this->liveurl . '" method="post" id="payzippy_payment_form">
            ' . implode('', $payzippy_args_array) . '
            <input type="submit" class="button-alt" id="submit_payzippy_payment_form" value="' . __('Pay via PayZippy', 'vipulucky') . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Cancel order &amp; restore cart', 'vipulucky') . '</a>
            </form>';
        }

        /**
         * Process the payment and return the result
         *
         * @access public
         * @param int $order_id
         * @return array
         */
        function process_payment($order_id) {
            global $woocommerce;
            $order = new WC_Order($order_id);
            return array(
                'result' => 'success',
                'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id')))));
        }

        /**
         * Check for valid payzippy server callback
         * */
        function check_payzippy_response() {
            
            global $woocommerce;
            $woocommerce->cart->empty_cart();
            if (isset($_REQUEST['merchant_transaction_id']) && isset($_REQUEST['payzippy_transaction_id'])) {
                $order_id = explode('_', $_REQUEST['merchant_transaction_id']);
                $order_id = (int) $order_id[0];
                if ($order_id != '') {
                    try {
                        //get parameters
                        $order = new WC_Order($order_id);
                        $hash = $_REQUEST['hash'];
                        
                        $bank_name = $_REQUEST['bank_name'];
                        $buyer_email_address = $order->billing_email;
                        $fraud_action = $_REQUEST['fraud_action'];
                        $fraud_details = $_REQUEST['fraud_details'];
                        $hash_method = 'SHA256';
                        $is_international = $_REQUEST['is_international'];
                        $merchant_id = $this->merchant_id;
                        $merchant_key_id = $this->merchant_key_id;
                        $merchant_transaction_id = $_REQUEST['merchant_transaction_id'];
                        $payment_method = $_REQUEST['payment_method'];
                        $payzippy_transaction_id = $_REQUEST['payzippy_transaction_id'];
                        $transaction_amount = $_REQUEST['transaction_amount'];
                        $transaction_currency = $_REQUEST['transaction_currency'];
                        $transaction_response_code = $_REQUEST['transaction_response_code'];
                        $transaction_response_message = $_REQUEST['transaction_response_message'];
                        $transaction_time = $_REQUEST['transaction_time'];
                        $transaction_status = $_REQUEST['transaction_status'];
                        $transaction_type = 'SALE';
                        $version = $_REQUEST['version'];
                        
                        //string to generate hash from
                        $hashstr = "$order->billing_email|$transaction_currency|$hash_method|$merchant_id|$merchant_key_id|$order_id_time|$amount|$transaction_type|REDIRECT|$this->salt";
                        //generate hash
                        $checkhash = hash('sha256', $hashstr);
                        
                        //set transaction completion as false [i.e. set flag = false]
                        $transauthorised = false;
                        
                        //the order is not yet complete
                        if ($order->status !== 'completed') {
                            //our generated hash matches the hash sent by payzippy
                            if ($hash == $checkhash) {
                                //the status sent by payzippy
                                $status = strtolower($transaction_status);
                                //if transaction was successful
                                if ($status == "success") {
                                    $transauthorised = true;
                                    $woocommerce->add_message( __('Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.', 'vipulucky') );
                                    if ($order->status == 'processing') {
                                        
                                    } else {
                                        $order->payment_complete();
                                        $order->add_order_note('PayZippy payment successful<br/>Unnique Id from PayZippy: ' . $_REQUEST['payzippy_transaction_id']);
                                        $woocommerce->cart->empty_cart();
                                    }
                                    $redirect = add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(woocommerce_get_page_id('thanks'))));
                                    wp_redirect($redirect);
                                    exit;
                                } else if ($status == "pending") {
                                    $woocommerce->add_message( __('Thank you for shopping with us. Right now your payment staus is pending, We will keep you posted regarding the status of your order through e-mail.', 'vipulucky') );
                                    $order->add_order_note('PayZippy payment status is pending<br/>Unnique Id from PayZippy: ' . $_REQUEST['payzippy_transaction_id']);
                                    $order->update_status('on-hold');
                                    $woocommerce->cart->empty_cart();
                                    $redirect = add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(woocommerce_get_page_id('thanks'))));
                                    wp_redirect($redirect);
                                    exit;
                                } else {
                                    $woocommerce->add_error( __('Transaction failed due to the reason: '. $_REQUEST['transaction_response_message'], 'vipulucky') );
                                    $order->add_order_note('Transaction Declined: ' . $_REQUEST['transaction_response_message']);
                                    $redirect = add_query_arg('pay_for_order', 'true', add_query_arg('order', $order->order_key, add_query_arg('order_id', $order_id, get_permalink(woocommerce_get_page_id('pay')))));	  
                                    wp_redirect($redirect);
                                    exit;
                                    //Here you need to put in the routines for a failed
                                    //transaction such as sending an email to customer
                                    //setting database status etc etc
                                }
                            } else {
                                $woocommerce->add_error( __('Security Error. Illegal access detected', 'vipulucky') );
                                $redirect = add_query_arg('pay_for_order', 'true', add_query_arg('order', $order->order_key, add_query_arg('order_id', $order_id, get_permalink(woocommerce_get_page_id('pay')))));	  
                                wp_redirect($redirect);
                                exit;
                                //Here you need to simply ignore this and dont need
                                //to perform any operation in this condition
                            }
                            if ($transauthorised == false) {
                                $order->update_status('failed');
                                $order->add_order_note('Failed');
                            }
                        }
                    } catch (Exception $e) {
                        // $errorOccurred = true;
                        $msg = "Error";
                        wp_redirect(home_url('/'));
                        exit;
                    }
                }
            }
        }

    }

    /**
     * Add the Gateway to WooCommerce
     * */
    function woocommerce_add_vipulucky_payzippy_gateway($methods) {
        $methods[] = 'WC_Gateway_PayZippy';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_vipulucky_payzippy_gateway');
}
