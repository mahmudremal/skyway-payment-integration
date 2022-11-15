<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, POST");
/****************************************************************************
 * Plugin Name: Skyway Payment Gateway
 * Plugin URI: https://github.com/mahmudremal/
 * Description: Integrate Skyway as payment gateway in Woocommerce.
 * Author: Skyway Gateway
 * Author URI: https://github.com/mahmudremal
 * Version: 1.0.2
 * Text Domain: wc-gateway-skyway
 * Domain Path: /i18n/languages/
 *************************************************************************************/


/*********Read the comments for code understanding: Ishan: 29th April 2022************/

// To check if the absoulte path is defined in the wp-config.php or not. Code Comment
defined('ABSPATH') or exit;

/************************************* Code Comment
 * Step1: Get all the active plugins using apply_filters: We are trying to pass get_options('active_plugins') 
 * as a parameter to the callback method hooked to the hookname active_plugins. so that it returns the values
 * as all the set of active plugins.
 */
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

/** Code Comment
 * Step 2: Pass our gateway class as a value to the callback hooked to the hook name woocommerce_payment_gateways
 * :Doing this we will add us as gateway defined in the woocomerce gateway list.
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + pt gateway
 */
function wc_sw_add_to_gateways($gateways)
{
    $gateways[] = 'WC_Gateway_SW';
    return $gateways;
}
add_filter('woocommerce_payment_gateways', 'wc_sw_add_to_gateways');

/** Code Comment
 * STEP 3: Add our custom plugin link to the list of available pligin links on WC.
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_sw_gateway_plugin_links($links)
{

    /** Code Comment
     * Below is the link generated similar to what we will have for our Payment methods in section 
     * WC >> Settings >> Payment methog. Clicking on the configure or directly on the Payment method we will move to the link which is exactly
     * same as the $plugin_link we are generating
     */
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=sw_gateway') . '">' . __('Configure', 'wc-gateway-sw') . '</a>'
    );

    return array_merge($plugin_links, $links);
}
// Code Comment: plugin_basename(__FILE__): __FILE__ : is the magic word  to get the current file name
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wc_sw_gateway_plugin_links');

/**Code Comment
 * STEP4: SW Payment Gateway: We write the WC_Gateway_SW class extending the 
 * woocommerce's WC_Payment_Gateway class. 
 * This class will be written inside a function wc_sw_gateway_init which will be added as a callback
 * method for the action hook "plugins_loaded", hence firing it on the load of the plugin.
 * Giving Priority as 12
 *
 * Provides an SW Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_Gateway_SW
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		SkyVerge
 */

add_action('plugins_loaded', 'wc_sw_gateway_init', 12);

function wc_sw_gateway_init()
{
    class WC_Gateway_SW extends WC_Payment_Gateway
    {
        /** Code Comment
         * InSide Class Step 1: Create a constructor for the class to set the basic class properties
         * for the gateway
         * initate form fields (using init_form_fields) and Load the settings using (init_settings).
         */
        public function __construct()
        {
            $this->id                 = 'sw_gateway';
            $this->icon               = apply_filters('woocommerce_sw_icon', '');
            $this->has_fields         = false;
            $this->method_title       = __('SkyWay', 'wc-gateway-sw');

            /** Code Comment
             * gateways can support subscriptions, refunds, saved payment methods,
             * but for now we will go with basic payment so not adding 'refunds' like in PT, 
             * We will support only products
             **/
            $this->supports = array(
                'products'
            );

            $this->init_form_fields();
            /** Code Comment
             * init_settings is a WC function to initialize settings.
             * it Store all settings in a single database entry and make sure the $settings array is either the 
             * default or the settings stored in the database.
             **/
            $this->init_settings();
            $this->title        = $this->get_option('title');
            $this->description  = $this->get_option('description');
            $this->currency  = $this->get_option('currency');
            $this->skywayOrderId = uniqid();
            // Set the Merchant Code & Merchant Key
            if ($this->currency === 'USD') {
                $this->institutionId = "1dab8624-a663-46fa-b635-63fb79dedb66"; 
                $this->institutionKey = 'Z340HXF42MTI73MRHFH8BJ4GI';
            } else if ($this->currency === 'THB') {
                $this->institutionId = "7b067ce8-c114-4f6a-b795-3be481e1e1cf";
                $this->institutionKey = '$2a$12$cNO7Me0YkmpaE.5DRJ/aAO';
            } else if ($this->currency == 'AUD') {
                $this->institutionId = "";
                $this->institutionKey = '';
            } else if ($this->currency == 'CAD') {
                $this->institutionId = "";
                $this->institutionKey = '';
            }
            $this->type = "PAY_IN";
            $this->api_url = 'https://app.skyway-gateway.com/api/transaction/v1/paymentToken';
            $this->return_url = get_site_url() . '/?skyway_return=WC_Gateway_SW';
            $this->callback_url = get_site_url() . '/?skyway_callback=WC_Gateway_SW';
            $this->cancel_url = get_site_url() . '/?skyway_cancel=WC_Gateway_SW';
            if (isset($_GET['section']) && $_GET['section'] == 'sw_gateway') {
                //$this->method_description = __( '<h2>Callback URL : '.$this->callback_url.'</h2><h2>Return URL : '.$this->return_url.'</h2>', 'wc-gateway-pt' );
            } else {
                $this->method_description = __('SkyWay', 'wc-gateway-sw');
            }

            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        }


        public function thankyou_page()
        {
            /*if ( $this->instructions ) {
				 wpautop( wptexturize( $this->instructions ) );
			}*/
        }

        /** Code Comment
         * init_form_fields: Initiate the form fields for the settigs option in Payment Gateway. 
         * Even though we have given the currency parameter in the setting but the user still have to change the 
         * currency value from the general settings of the woo commerce tab in admin portal. (Implementation as of now)
         * Planning to take the currency from the general settings rather than adding that here. But as of now we dont know how to do that. 
         * So will do it later.
         */
        public function init_form_fields()
        {
            $this->form_fields = apply_filters('wc_sw_form_fields', array(
                'enabled' => array(
                    'title'   => __('Enable/Disable', 'wc-gateway-sw'),
                    'type'    => 'checkbox',
                    'label'   => __('Enable SkyWay Payment', 'wc-gateway-sw'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title'       => __('Title', 'wc-gateway-sw'),
                    'type'        => 'text',
                    'description' => __('This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-sw'),
                    'default'     => __('Pay Via SkyWay', 'wc-gateway-sw'),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __('Description', 'wc-gateway-sw'),
                    'type'        => 'textarea',
                    'description' => __('Payment method description that the customer will see on your checkout.', 'wc-gateway-sw'),
                    'default'     => __('SkyWay Description.', 'wc-gateway-sw'),
                    'desc_tip'    => true,
                ),
                'currency' => array(
                    'title'       => __('Currency', 'wc-gateway-sw'),
                    'type'        => 'select',
                    'id'          => 'curr_select',
                    'description' => __('This controls the currency for the payment method the customer sees during checkout.', 'wc-gateway-sw'),
                    'placeholder' => __('Select Currency', 'wc-gateway-sw'),
                    'default'     => __('USD', 'wc-gateway-sw'),
                    'options'     => $this->get_currency_types(),
                    'desc_tip'    => true,
                ),
            ));
        }

        /** Code Comment (WC Method)
         * Below function will be used if in future we are willing to validate the form fields
         */
        public function validate_fields()
        {
        }

        /** Code Comment(WC Method)
         * Process the payment and return the result
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment($order_id)
        {
            $error_msg = '';
            $this->skywayOrderId = $order_id . '-' . uniqid();
            $order = wc_get_order($order_id);
            log_me('Order Details' . $order);
            log_me('skyway cart id'. $this->skywayOrderId);
            $response = $this->generate_payment_token($order, $order_id);
            if (isset($response['status']) && $response['status'] == 'SUCCESS') {
                log_me("returning success status by code: " .$response['status']);
                    // $resp = $this->submit_consent_token($response['token']);
                log_me("Redirect URL" . plugin_dir_url(__FILE__).'skyway-redirect.php?token='.$response['token']);
                update_post_meta($order_id,'skyway_order_ref',$order_id);
                    return array(
                        'result'   => 'success',
                        'redirect' => 'http://13.126.189.125/skyway-redirect.php?token='.$response['token']
                    );
            } elseif ($response['status'] == 'FAILURE') {
                if ($response['statusMessage']['code'] == "G2100") {
                    wc_add_notice('Your requested transaction failed.', 'error');
                    return false;
                }

                if ($response['error']['code'] == 601) {
                    wc_add_notice('Order cannot be placed as the merchant account has been deactivated.', 'error');
                    return false;
                }

                if ($response['error']['code'] == 603) {
                    wc_add_notice('The transaction is not properly configured in Paytender. Please contact support@paytender.com.', 'error');
                    return false;
                }
            } else {
                wc_add_notice('The server is currently unable to process this request. Please try again in a few minutes. If the issue persists, please contact support@paytender.com.', 'error');
                return false;
            }
        }

        /************************************* Custom Methods For Class *****************************************
         * Custom Methods for the class (Not Woo Commerce Methods)
         * 1) get_currency_types
         * 2) generate_payment_token :Submit Order detail to Payment Gateway and get Return Url
         * 3) get_instid_instkey:
         */

        public function get_currency_types()
        {
            $types['USD']  = __('USD', 'wc-gateway-sw');
            $types['THB'] = __('THB', 'wc-gateway-sw');
            return $types;
        }

        public function get_instid_instkey()
        {
            $currency = $this->currency;
            $inst_values  = '';
            if ($currency != null) {
                if ($currency == 'USD') {
                }
            }
        }

        /** Code Comment
         *  Function : Generate Secure Hash Method for passing the values to the api_url path.
         *  generateSecureHash: Generate the Secure Hash based on the values saved in the checkout page 
         *  and settings page this is SHA Algorithm based encryption.
         */
        public function generateSecureHash($order)
        {
            /**
             * 1) $this->institutionId should be based on the currency 
             * 2) $this->reference can be either a uiniqid() or it can be the cart ID or Order ID here we are considering
             * the $order->get_id();
             */
            $data = $this->institutionId . ";" . $this->institutionKey . ";" . $this->skywayOrderId . ";" . $this->type . ";" . $order->get_total() . ";" . $this->currency;
            log_me("secure hash string" . $data);
            // $data = $this->institutionId . ";" . $this->institutionKey . ";" . $this->reference . ";" . $this->type . ";" . $order->get_total() . ";" . $this->currency;
            return $hash_generated = hash('sha512', $data);
        }

        /** 
         * @param array $order, int $order_id
         * @return array
         */
        public function generate_payment_token($order, $order_id)
        {
            global $woocommerce;
            $timestamp = time();
            $secureHash = $this->generateSecureHash($order);
            $order_data = $order->get_data();
            if (array_key_exists("location", $order)) {
                $location = $order->get_location();
            } else {
                $location = null;
            }
            $id = $order_id;
            $items = $order->get_items();

            if ($order->get_billing_address_2()) {
                $address = $order->get_billing_address_1() + ',' + $order->get_billing_address_2();
            } else {
                $address = $order->get_billing_address_1();
            }

            $data = '{
                "institutionId": "'.$this->institutionId.'",
                "returnURL": "'.$this->return_url.'",
                "callbackURL":"'.$this->callback_url.'",
                "hash": "' . $secureHash.'",
                "type": "'.$this->type.'",
                "cartId": "' . $this->skywayOrderId . '",
                "currency": "'.$this->currency.'",
                "amount":  "'.$order->get_total().'",
                "payinRequest": {
                        "firstname": "'.$order->get_billing_first_name().'",
                        "lastname": "'.$order->get_billing_last_name().'",
                        "address": "'.$address.'",
                        "channelType": "BANK_TRANSFER"
                    }
            }';
            
            $url = $this->api_url;
            $ch = curl_init();
            log_me('Skyway Request Body ' . $data);
            // $jsondata = stripslashes($data);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

            $result = curl_exec($ch);
            $output = json_decode($result, true);
            log_me("Payment API Response Body" . $result);
            $Tokenpost = $output['token'];
            log_me('SkyWay Token Response' . $Tokenpost);
            return $output;
        }
    }
}

/**
 * Add logger for server
 *
 * @param str $message
 */
function log_me($message)
{
    if (is_array($message)) {
        $message = json_encode($message);
    }
    $uploads_dir = wp_upload_dir();
    $file_path = $uploads_dir['basedir'] . '/sw_logs.log';
    $file = fopen($file_path, "a");
    $data = date('Y-m-d h:i:s') . " :: " . $message . "\n" . PHP_EOL;

    fwrite($file, $data);
    fclose($file);
}

function add_query_vars_filter($vars)
{
    $vars[] = "skyway_callback";
    $vars[] = "skyway_return";
    $vars[] = "skyway_cancel";
    return $vars;
}
add_filter('query_vars', 'add_query_vars_filter');

function read_query_var()
{
    if (get_query_var('skyway_callback')) {
        if (get_query_var('skyway_callback') == 'WC_Gateway_SW') {
            $json = file_get_contents('php://input');
            // $action = json_decode($json, true);
            $newArr = explode("&", $json);
            // Fetch cart id values.
            $cart_id_arr = preg_grep('/^cartId=.*/', $newArr);
            if ($cart_id_arr == false) {

            } else {
                $cart_id_index = array_keys($cart_id_arr)[0];
                $cart_id_val = explode("=", $cart_id_arr[$cart_id_index])[1];
                log_me('Cart ID value:' . $cart_id_val);
            }
            // Fetch the status values.
            $status_arr = preg_grep('/^status=.*/', $newArr);
            if ($status_arr == false) {
            } else {
                $status_index = array_keys($status_arr)[0];
                $status_val = explode("=", $status_arr[$status_index])[1];
                log_me('Status value:' . $status_val);
            }
            
            if ($cart_id_val && $status_val) {
                global $wpdb;
                // $order_ref = $cart_id_val;
                $order_ref = explode("-", $cart_id_val)[0];
                log_me('order_ref Value:' . $order_ref);
                log_me('card id complete value :'. $cart_id_val);
                // In Skyway case the cartId and the order ID are same, 
                $results = $wpdb->get_results($wpdb->prepare("SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = 'skyway_order_ref' AND meta_value = %s", $order_ref));
                foreach ($results as $result) {
                    $order_id  = $result->post_id;
                }
                if ($order_id > 0) {
                    log_me('After Foreach and SQL Update:' . $order_id);
                    if ($status_val == 'SUCCESS') {
                        $order = wc_get_order($order_id);
                        $order->update_status('completed');
                    }
                    if ($status_val == 'FAILED') {
                        $order = wc_get_order($order_id);
                        $order->update_status('failed');
                    }
                    if ($status_val == 'PENDING') {
                        $order = wc_get_order($order_id);
                        $order->update_status('pending');
                    }
                }
            }
        }
    }    // callback ends	

    if (get_query_var('skyway_return') == 'WC_Gateway_SW') {
        log_me("Skyway Return Function initiated" . $_GET['ref']);
        if (isset($_GET['ref'])) {
            global $wpdb;
            $order_ref = $_GET['ref'];
            $results = $wpdb->get_results($wpdb->prepare("SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = 'skyway_order_ref' AND meta_value = %s", $order_ref));
            foreach ($results as $result) {
                $order_id  = $result->post_id;
            }
            if ($order_id > 0) {
                $order_key = get_post_meta($order_id, '_order_key', true);
                if ($order_key) {
                    $return_url = wc_get_checkout_url() . '/order-received/' . $order_id . '/?key=' . $order_key;
                    wp_redirect($return_url);
                    exit;
                }
            }
        }
    } // Return ends	
    if (get_query_var('skyway_cancel') == 'WC_Gateway_SW') {
        $json = file_get_contents('php://input');
        $action = json_decode($json, true);
        log_me('skyway_cancel');
        log_me($json);
    }
}
add_action('template_redirect', 'read_query_var');
