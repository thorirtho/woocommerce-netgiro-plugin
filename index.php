<?php /** @noinspection ALL */

/*
Plugin Name: Netgíró Payment gateway for Woocommerce
Plugin URI: http://www.netgiro.is
Description: Netgíró Payment gateway for Woocommerce
Version: 4.1.0
Author: Netgíró
Author URI: http://www.netgiro.is
WC requires at least: 4.6.0
WC tested up to: 5.7.2
*/

require 'helpers/netgiro_direct_helpers.php';

add_action('plugins_loaded', 'woocommerce_netgiro_direct_init', 0);

function woocommerce_netgiro_direct_init()
{
    if (!class_exists('WC_Payment_Gateway')) return;

    function woocommerce_add_netgiro_gateway($methods)
    {
        $methods[] = 'WC_netgiro_direct';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_netgiro_gateway');

    class WC_netgiro_direct extends WC_Payment_Gateway
    {
        public function __construct()
        {
            $this->id = 'netgiro_direct';
            $this->medthod_title = 'Netgíró';
            $this->method_description = 'Plugin for accepting Netgiro payments with Woocommerce web shop.';
            $this->has_fields = true;
            $this->icon = plugins_url('/logo_x25.png', __FILE__);

            $this->init_form_fields();
            $this->init_settings();

            // API
            $this->insert_cart_url = $this->get_option('test') == 'yes' ? 'https://test.netgiro.is/api/Checkout/InsertCart' : 'https://api.netgiro.is/v1/Checkout/InsertCart';
            $this->confirm_cart_url = $this->get_option('test') == 'yes' ? 'https://test.netgiro.is/api/Checkout/InsertCart' : 'https://api.netgiro.is/v1/Checkout/InsertCart';
            $this->check_cart_url = $this->get_option('test') == 'yes' ? 'https://test.netgiro.is/api/Checkout/InsertCart' : 'https://api.netgiro.is/v1/Checkout/InsertCart';
            $this->cancel_cart_url = $this->get_option('test') == 'yes' ? 'https://test.netgiro.is/api/Checkout/InsertCart' : 'https://api.netgiro.is/v1/Checkout/InsertCart';

            $this->title = sanitize_text_field($this->settings['title']);
            $this->description = $this->settings['description'];
            $this->application_id = sanitize_text_field($this->settings['application_id']);
            $this->secretkey = $this->settings['secretkey'];
            if (isset($this->settings['redirect_page_id'])) {
                $this->redirect_page_id = sanitize_text_field($this->settings['redirect_page_id']);
            }
            $this->cancel_page_id = sanitize_text_field($this->settings['cancel_page_id']);

            $this->round_numbers = 'yes';

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            add_action('woocommerce_api_wc_' . $this->id, array($this, 'netgiro_response'));
            add_action('woocommerce_api_wc_' . $this->id . "_callback", array($this, 'netgiro_callback'));
        }

        function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'netgiro_direct'),
                    'type' => 'checkbox',
                    'label' => __('Enable Netgíró Payment Module.', 'netgiro_direct'),
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => __('Title', 'netgiro'),
                    'type' => 'text',
                    'description' => __('Title of payment method on checkout page', 'netgiro'),
                    'default' => __('Netgíró', 'netgiro')
                ),
                'description' => array(
                    'title' => __('Lýsing', 'netgiro'),
                    'type' => 'textarea',
                    'description' => __('Description of payment method on checkout page.', 'netgiro'),
                    'default' => __('Reikningur verður sendur í netbanka og greiða þarf innan 14 daga eða með Netgíró raðgreiðslum.', 'netgiro')
                ),
                'test' => array(
                    'title' => __('Prófunarumhverfi', 'netgiro_valitor'),
                    'type' => 'checkbox',
                    'label' => __('Senda á prófunarumhverfi Netgíró', 'netgiro'),
                    'description' => __('If selected, you need to provide Application ID and Secret Key. Not the production keys for the merchant'),
                    'default' => 'option_is_enabled'
                ),
                'application_id' => array(
                    'title' => __('Application ID', 'netgiro'),
                    'type' => 'text',
                    'default' => '881E674F-7891-4C20-AFD8-56FE2624C4B5',
                    'description' => __('Available from https://partner.netgiro.is or provided by Netgíró')
                ),
                'secretkey' => array(
                    'title' => __('Secret Key', 'netgiro'),
                    'type' => 'textarea',
                    'description' => __('Available from https://partner.netgiro.is or provided by Netgíró', 'netgiro'),
                    'default' => 'YCFd6hiA8lUjZejVcIf/LhRXO4wTDxY0JhOXvQZwnMSiNynSxmNIMjMf1HHwdV6cMN48NX3ZipA9q9hLPb9C1ZIzMH5dvELPAHceiu7LbZzmIAGeOf/OUaDrk2Zq2dbGacIAzU6yyk4KmOXRaSLi8KW8t3krdQSX7Ecm8Qunc/A='
                ),
                'cancel_page_id' => array(
                    'title' => __('Cancel Page'),
                    'type' => 'select',
                    'options' => $this->get_pages('Select Page'),
                    'description' => "URL if payment cancelled"
                )
            );
        }

        /**
         *  Options for the admin interface
         **/
        public function admin_options()
        {
            echo '<h3>' . __('Netgíró Payment Gateway', 'netgiro_direct') . '</h3>';
            echo '<p>' . __('Verslaðu á netinu með Netgíró á einfaldan hátt.') . '</p>';
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
        }

        /**
         *  There are no payment fields for netgiro, but we want to show the description if set.
         **/
        function payment_fields()
        {
            if ($this->description) echo wpautop(wptexturize($this->description));
            ?>
            <input type="text" id="ng-msisdn" name="ng-msisdn" placeholder="Símanúmer"/>
            <?php
        }

        function validateItemArray($item)
        {
            if (empty($item['line_total'])) {
                $item['line_total'] = 0;
            }

            if (
                empty($item['product_id'])
                || empty($item['name'])
                || empty($item['qty'])
            ) {
                return FALSE;
            }

            if (
                !is_string($item['name'])
                || !is_numeric($item['line_total'])
                || !is_numeric($item['qty'])
            ) {
                return FALSE;
            }

            return TRUE;
        }

        function get_error_message()
        {
            return 'Villa kom upp við vinnslu beiðni þinnar. Vinsamlega reyndu aftur eða hafðu samband við þjónustuver Netgíró með tölvupósti á netgiro@netgiro.is';
        }

        private function get_request_body($order_id, $customer_id)
        {
            if (empty($order_id)) {
                return $this->get_error_message();
            }

            $order_id = sanitize_text_field($order_id);
            $order = new WC_Order($order_id);
            $txnid = $order_id . '_' . date("ymds");

            if (!is_numeric($order->get_total())) {
                return $this->get_error_message();
            }

            $round_numbers = $this->round_numbers;
            $payment_Cancelled_url = ($this->cancel_page_id == "" || $this->cancel_page_id == 0) ? get_site_url() . "/" : get_permalink($this->cancel_page_id);
            $payment_Confirmed_url = add_query_arg('wc-api', 'WC_netgiro_callback', home_url('/'));
            $payment_Successful_url = add_query_arg('wc-api', 'WC_netgiro_direct', home_url('/'));

            $total = round(number_format($order->get_total(), 0, '', ''));

            if ($round_numbers == 'yes') {
                $total = round($total);
            }

            if (!function_exists('get_plugin_data')) {
                require_once(ABSPATH . 'wp-admin/includes/plugin.php');
            }
            $plugin_data = get_plugin_data(__FILE__);
            $plugin_version = $plugin_data['Version'];

            // Netgiro arguments
            $netgiro_args = array(
                'amount' => $total,
                'description' => $order_id,
                'reference' => $txnid,
                'customerId' => $customer_id,
                'callbackUrl' => $payment_Confirmed_url,
                'ConfirmationType' => '0',
                'locationId' => '',
                'registerId' => '',
                'clientInfo' => 'System: Woocommerce ' . $plugin_version
            );

            // Woocommerce -> Netgiro Items
            foreach ($order->get_items() as $item) {
                $validationPass = $this->validateItemArray($item);

                if (!$validationPass) {
                    return $this->get_error_message();
                }

                $unitPrice = $order->get_item_subtotal($item, true, $round_numbers == 'yes');
                $amount = $order->get_line_subtotal($item, true, $round_numbers == 'yes');

                if ($round_numbers == 'yes') {
                    $unitPrice = round($unitPrice);
                    $amount = round($amount);
                }

                $items[] = array(
                    'productNo' => $item['product_id'],
                    'name' => $item['name'],
                    'description' => $item['description'],  /* TODO Could not find description */
                    'unitPrice' => $unitPrice,
                    'amount' => $amount,
                    'quantity' => $item['qty'] * 1000
                );
            }

            $netgiro_args['cartItemRequests'] = $items;
            var_dump($items);

            return $netgiro_args;
        }

        private function get_signature($nonce, $request_body)
        {
            $secretKey = sanitize_text_field($this->secretkey);
            $body = json_encode($request_body);
            var_dump($body);
            $str = $secretKey . $nonce . $this->insert_cart_url . $body;
            var_dump($str);
            $signature = hash('sha256', $str);
            var_dump($signature);

            return $signature;
        }

        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id): array
        {
            global $woocommerce;

            $customer_id = sanitize_text_field( $_POST['ng-msisdn'] );
            $nonce = wp_create_nonce();

            $netgiro_args = $this->get_request_body($order_id, $customer_id);
            var_dump($netgiro_args);
            $signature = $this->get_signature($nonce, $netgiro_args);
            var_dump($signature);

            if (!is_array($netgiro_args))
            {
                return $netgiro_args;
            }

            if (!wp_http_validate_url($this->insert_cart_url) && !wp_http_validate_url($order->get_cancel_order_url())) {
                return $this->get_error_message();
            }

            $response = wp_remote_post($this->insert_cart_url, array(
                'headers' => [
                    'netgiro_appkey' => $this->application_id,
                    'netgiro_nonce' => $nonce,
                    'netgiro_signature' => $signature,
                    'netgiro_referenceId' => getGUID(),
                    'Content-Type' => 'application/json; charset=utf-8'
                ],
                'body' => json_encode($netgiro_args),
                'method' => 'POST',
                'data_format' => 'body'
            ));
            var_dump($response);
            $jdecode = json_decode(wp_remote_retrieve_body($response), true);
            var_dump($jdecode);

            $insertCartStatus = $jdecode['Success'];
            var_dump($insertCartStatus);

                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );
        }

        function netgiro_response()
        {
            $this->handleNetgiroCall(true);
        }

        function netgiro_callback()
        {
            $this->handleNetgiroCall(false);
        }

        function handleNetgiroCall(bool $doRedirect)
        {
            global $woocommerce;

            $logger = wc_get_logger();

            if ((isset($_GET['ng_netgiroSignature']) && $_GET['ng_netgiroSignature'])
                && $_GET['ng_orderid'] && $_GET['ng_transactionid'] && $_GET['ng_signature']
            ) {

                $signature = sanitize_text_field($_GET['ng_netgiroSignature']);
                $orderId = sanitize_text_field($_GET['ng_orderid']);
                $order = new WC_Order($orderId);
                $secret_key = sanitize_text_field($this->secretkey);
                $invoice_number = sanitize_text_field($_REQUEST['ng_invoiceNumber']);
                $transactionId = sanitize_text_field($_REQUEST['ng_transactionid']);
                $totalAmount = sanitize_text_field($_REQUEST['ng_totalAmount']);
                $status = sanitize_text_field($_REQUEST['ng_status']);

                $str = $secret_key . $orderId . $transactionId . $invoice_number . $totalAmount . $status;
                $hash = hash('sha256', $str);

                // correct signature and order is success
                if ($hash == $signature && is_numeric($invoice_number)) {
                    $order->payment_complete();
                    $order->add_order_note('Netgíró greiðsla tókst<br/>Tilvísunarnúmer frá Netgíró: ' . $invoice_number);
                    $woocommerce->cart->empty_cart();
                } else {
                    $failed_message = 'Netgiro payment failed. Woocommerce order id: ' . $orderId . ' and Netgiro reference no.: ' . $invoice_number . ' does relate to signature: ' . $signature;

                    // Set order status to failed
                    if (is_bool($order) === false) {
                        $logger->debug($failed_message, array('source' => 'netgiro_response'));
                        $order->update_status('failed');
                        $order->add_order_note($failed_message);
                    } else {
                        $logger->debug('error netgiro_response - order not found: ' . $orderId, array('source' => 'netgiro_response'));
                    }

                    wc_add_notice("Ekki tókst að staðfesta Netgíró greiðslu! Vinsamlega hafðu samband við verslun og athugað stöðuna á pöntun þinni nr. " . $orderId, 'error');
                }

                if ($doRedirect === true) {
                    wp_redirect($this->get_return_url($order));
                }

                exit;
            }
        }


        // Get all pages for admin options
        function get_pages($title = false, $indent = true)
        {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title) $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while ($has_parent) {
                        $prefix .= ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }
    }
}
