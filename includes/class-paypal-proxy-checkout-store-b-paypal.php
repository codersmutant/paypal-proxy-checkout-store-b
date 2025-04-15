<?php
/**
 * The PayPal integration functionality of the plugin.
 *
 * @since      1.0.0
 * @package    PayPal_Proxy_Checkout_Store_B
 */

if (!defined('WPINC')) {
    die;
}

class PayPal_Proxy_Checkout_Store_B_PayPal {

    /**
     * Initialize the class.
     *
     * @since    1.0.0
     */
    public function __construct() {
    }

    /**
     * Initialize PayPal SDK.
     *
     * @since    1.0.0
     */
    public function init_paypal_sdk() {
        // This is just a placeholder - the actual SDK initialization happens client-side via JavaScript
    }

    /**
     * Create a PayPal order.
     *
     * @param array $order_data Order data from Store A.
     * @return array|WP_Error Order details or error.
     */
    public function create_order($order_data) {
        // Get settings
        $settings = PayPal_Proxy_Checkout_Store_B::get_settings();
        
        // Get auth token
        $auth_token = PayPal_Proxy_Checkout_Store_B_Security::get_paypal_auth_token();
        
        if (is_wp_error($auth_token)) {
            PayPal_Proxy_Checkout_Store_B::log('Failed to get PayPal auth token: ' . $auth_token->get_error_message(), 'error');
            return $auth_token;
        }
        
        // Prepare order data for PayPal
        $paypal_order = $this->prepare_paypal_order($order_data);
        
        // Create a URL for PayPal API
        $api_url = $settings['paypal_mode'] === 'live'
            ? 'https://api-m.paypal.com/v2/checkout/orders'
            : 'https://api-m.sandbox.paypal.com/v2/checkout/orders';
        
        // Make the request to PayPal
        $response = wp_remote_post($api_url, array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.1',
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $auth_token,
                'PayPal-Request-Id' => uniqid('ppcb_', true),
            ),
            'body' => json_encode($paypal_order),
        ));
        
        if (is_wp_error($response)) {
            PayPal_Proxy_Checkout_Store_B::log('PayPal API error: ' . $response->get_error_message(), 'error');
            return new WP_Error('paypal_api_error', $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            PayPal_Proxy_Checkout_Store_B::log('PayPal order creation error: ' . json_encode($body), 'error');
            return new WP_Error('paypal_error', $body['error']['message']);
        }
        
        if (!isset($body['id'])) {
            PayPal_Proxy_Checkout_Store_B::log('Missing PayPal order ID in response: ' . json_encode($body), 'error');
            return new WP_Error('missing_order_id', 'Missing PayPal order ID in response.');
        }
        
        // Create a local order to track the payment
        $order_id = $this->create_local_order($order_data, $body);
        
        if (is_wp_error($order_id)) {
            return $order_id;
        }
        
        // Log success
        PayPal_Proxy_Checkout_Store_B::log('PayPal order created: ' . $body['id'], 'info', array(
            'order_id' => $order_id,
            'paypal_order_id' => $body['id'],
        ));
        
        // Return the PayPal order details
        return array(
            'order_id' => $order_id,
            'paypal_order_id' => $body['id'],
            'checkout_url' => $this->get_paypal_checkout_url($body),
            'approve_url' => $this->get_approve_url($body),
        );
    }

    /**
     * Prepare order data for PayPal API.
     *
     * @param array $order_data Order data from Store A.
     * @return array PayPal order data.
     */
    private function prepare_paypal_order($order_data) {
        // Get the items
        $items = array();
        $total_value = 0;
        
        foreach ($order_data['items'] as $item) {
            // Use generic names for products instead of exposing actual names
    $product_name = "Product";
    
    // If Store B product ID exists, try to get the actual product name
    if (!empty($item['store_b_product_id'])) {
        $product = wc_get_product($item['store_b_product_id']);
        if ($product) {
            $product_name = $product->get_name();
        } else {
            $product_name = "Product #" . $item['store_b_product_id'];
        }
    }
    
    $items[] = array(
        'name' => $product_name,
        'unit_amount' => array(
            'currency_code' => $order_data['currency'],
            'value' => number_format($item['price'], 2, '.', ''),
        ),
        'quantity' => $item['quantity'],
        'sku' => $item['store_b_product_id'],
    );
    
    $total_value += $item['price'] * $item['quantity'];
}
        
        // Create the purchase unit
        $purchase_unit = array(
            'amount' => array(
                'currency_code' => $order_data['currency'],
                'value' => number_format($order_data['total'], 2, '.', ''),
                'breakdown' => array(
                    'item_total' => array(
                        'currency_code' => $order_data['currency'],
                        'value' => number_format($total_value, 2, '.', ''),
                    ),
                ),
            ),
            'items' => $items,
            'custom_id' => $order_data['store_a_id'] . '|' . $order_data['store_a_order_id'],
        );
        
        // Add shipping name and address if available
        if (isset($order_data['customer']) && isset($order_data['customer']['address'])) {
            $purchase_unit['shipping'] = array(
                'name' => array(
                    'full_name' => $order_data['customer']['first_name'] . ' ' . $order_data['customer']['last_name'],
                ),
                'address' => array(
                    'address_line_1' => $order_data['customer']['address']['line1'],
                    'address_line_2' => $order_data['customer']['address']['line2'],
                    'admin_area_2' => $order_data['customer']['address']['city'],
                    'admin_area_1' => $order_data['customer']['address']['state'],
                    'postal_code' => $order_data['customer']['address']['postal_code'],
                    'country_code' => $order_data['customer']['address']['country'],
                ),
            );
        }
        
        // Store original return URLs but don't send them to PayPal
    $store_a_return_url = $order_data['return_url'];
    $store_a_cancel_url = $order_data['cancel_url'];
    
    // Create proxy return URLs
    $proxy_return_url = add_query_arg(array(
        'store_a_id' => $order_data['store_a_id'],
        'store_a_order_id' => $order_data['store_a_order_id'],
        'store_a_return_url' => urlencode($store_a_return_url)
    ), home_url('/wc-api/ppca_return'));
    
    $proxy_cancel_url = add_query_arg(array(
        'store_a_id' => $order_data['store_a_id'],
        'store_a_order_id' => $order_data['store_a_order_id'],
        'store_a_cancel_url' => urlencode($store_a_cancel_url)
    ), home_url('/wc-api/ppca_cancel'));
    
    // Prepare the full PayPal order data
    $paypal_order = array(
        'intent' => 'CAPTURE',
        'purchase_units' => array($purchase_unit),
        'application_context' => array(
            'return_url' => $proxy_return_url,
            'cancel_url' => $proxy_cancel_url,
            'brand_name' => get_bloginfo('name'),
            'user_action' => 'PAY_NOW',
            'shipping_preference' => 'SET_PROVIDED_ADDRESS',
        ),
    );
        
        // Add payer info if available
        if (isset($order_data['customer']) && !empty($order_data['customer']['email'])) {
            $paypal_order['payer'] = array(
                'email_address' => $order_data['customer']['email'],
            );
            
            if (!empty($order_data['customer']['first_name']) && !empty($order_data['customer']['last_name'])) {
                $paypal_order['payer']['name'] = array(
                    'given_name' => $order_data['customer']['first_name'],
                    'surname' => $order_data['customer']['last_name'],
                );
            }
            
            if (!empty($order_data['customer']['phone'])) {
                $paypal_order['payer']['phone'] = array(
                    'phone_number' => array(
                        'national_number' => preg_replace('/[^0-9]/', '', $order_data['customer']['phone']),
                    ),
                );
            }
        }
        
        return $paypal_order;
    }
    
    /**
     * Create a local WooCommerce order to track the payment.
     *
     * @param array $order_data Order data from Store A.
     * @param array $paypal_response PayPal API response.
     * @return int|WP_Error Order ID or error.
     */
    private function create_local_order($order_data, $paypal_response) {
        // Create an order object
        $order = wc_create_order();
        
        // Add products
        foreach ($order_data['items'] as $item) {
            // Get the product
            $product = wc_get_product($item['store_b_product_id']);
            
            // If product doesn't exist, create a temporary one
            if (!$product) {
                $product = new WC_Product_Simple();
                $product->set_name($item['name']);
                $product->set_regular_price($item['price']);
                $product->set_status('private');
                $product->save();
            }
            
            // Add the item to the order
            $order->add_product($product, $item['quantity']);
        }
        
        // Set addresses
        if (isset($order_data['customer']) && isset($order_data['customer']['address'])) {
            $address = array(
                'first_name' => $order_data['customer']['first_name'],
                'last_name' => $order_data['customer']['last_name'],
                'email' => $order_data['customer']['email'],
                'phone' => $order_data['customer']['phone'],
                'address_1' => $order_data['customer']['address']['line1'],
                'address_2' => $order_data['customer']['address']['line2'],
                'city' => $order_data['customer']['address']['city'],
                'state' => $order_data['customer']['address']['state'],
                'postcode' => $order_data['customer']['address']['postal_code'],
                'country' => $order_data['customer']['address']['country'],
            );
            
            $order->set_address($address, 'billing');
            $order->set_address($address, 'shipping');
        }
        
        // Set payment method to PayPal
        $order->set_payment_method('paypal');
        $order->set_payment_method_title('PayPal');
        
        // Set order currency
        $order->set_currency($order_data['currency']);
        
        // Set order as 'on-hold' for now
        $order->set_status('on-hold', __('Awaiting PayPal payment.', 'paypal-proxy-checkout-store-b'));
        
        // Store proxy data
        $order->update_meta_data('_store_a_order_id', $order_data['store_a_order_id']);
        $order->update_meta_data('_store_a_id', $order_data['store_a_id']);
        $order->update_meta_data('_paypal_order_id', $paypal_response['id']);
        $order->update_meta_data('_paypal_status', 'CREATED');
        
        // Save the order
        $order->calculate_totals();
        $order->save();
        
        return $order->get_id();
    }
    
    /**
     * Get the PayPal checkout URL from the API response.
     *
     * @param array $paypal_response PayPal API response.
     * @return string Checkout URL.
     */
    private function get_paypal_checkout_url($paypal_response) {
        if (!isset($paypal_response['links']) || !is_array($paypal_response['links'])) {
            return '';
        }
        
        foreach ($paypal_response['links'] as $link) {
            if ($link['rel'] === 'approve') {
                return $link['href'];
            }
        }
        
        return '';
    }
    
    /**
     * Get the approve URL from the PayPal API response.
     *
     * @param array $paypal_response PayPal API response.
     * @return string Approve URL.
     */
    private function get_approve_url($paypal_response) {
        if (!isset($paypal_response['links']) || !is_array($paypal_response['links'])) {
            return '';
        }
        
        foreach ($paypal_response['links'] as $link) {
            if ($link['rel'] === 'approve') {
                return $link['href'];
            }
        }
        
        return '';
    }
    
    /**
     * Capture a PayPal order after approval.
     *
     * @param string $paypal_order_id PayPal order ID.
     * @return array|WP_Error Capture details or error.
     */
    public function capture_order($paypal_order_id) {
        // Get settings
        $settings = PayPal_Proxy_Checkout_Store_B::get_settings();
        
        // Get auth token
        $auth_token = PayPal_Proxy_Checkout_Store_B_Security::get_paypal_auth_token();
        
        if (is_wp_error($auth_token)) {
            return $auth_token;
        }
        
        // Create a URL for PayPal API
        $api_url = $settings['paypal_mode'] === 'live'
            ? "https://api-m.paypal.com/v2/checkout/orders/{$paypal_order_id}/capture"
            : "https://api-m.sandbox.paypal.com/v2/checkout/orders/{$paypal_order_id}/capture";
        
        // Make the request to PayPal
        $response = wp_remote_post($api_url, array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.1',
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $auth_token,
                'PayPal-Request-Id' => uniqid('ppcb_capture_', true),
            ),
            'body' => json_encode(array(
                // No additional parameters needed for basic capture
            )),
        ));
        
        if (is_wp_error($response)) {
            PayPal_Proxy_Checkout_Store_B::log('PayPal capture error: ' . $response->get_error_message(), 'error');
            return new WP_Error('paypal_api_error', $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            PayPal_Proxy_Checkout_Store_B::log('PayPal capture error: ' . json_encode($body), 'error');
            return new WP_Error('paypal_error', $body['error']['message']);
        }
        
        return $body;
    }
    
    /**
     * Handle PayPal IPN (Instant Payment Notification).
     */
    public function handle_ipn() {
        // Get the raw POST data
        $raw_post_data = file_get_contents('php://input');
        
        // Verify IPN
        $verified = $this->verify_ipn($raw_post_data);
        
        if (!$verified) {
            PayPal_Proxy_Checkout_Store_B::log('Invalid IPN received', 'error');
            status_header(400);
            exit;
        }
        
        // Parse the IPN data
        parse_str($raw_post_data, $ipn_data);
        
        // Process the IPN
        $this->process_ipn($ipn_data);
        
        // Send 200 OK response
        status_header(200);
        exit;
    }
    
    /**
     * Verify PayPal IPN.
     *
     * @param string $raw_post_data Raw POST data.
     * @return bool Whether the IPN is valid.
     */
    private function verify_ipn($raw_post_data) {
        // Get settings
        $settings = PayPal_Proxy_Checkout_Store_B::get_settings();
        
        // Add 'cmd=_notify-validate' to the post string
        $post_data = 'cmd=_notify-validate&' . $raw_post_data;
        
        // Send back to PayPal for validation
        $paypal_url = $settings['paypal_mode'] === 'live'
            ? 'https://ipnpb.paypal.com/cgi-bin/webscr'
            : 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr';
        
        $response = wp_remote_post($paypal_url, array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.1',
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => 'PayPal Proxy Checkout/1.0',
            ),
            'body' => $post_data,
        ));
        
        if (is_wp_error($response)) {
            PayPal_Proxy_Checkout_Store_B::log('PayPal IPN verification error: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        
        return ($body === 'VERIFIED');
    }
    
    /**
     * Process PayPal IPN data.
     *
     * @param array $ipn_data IPN data.
     */
    private function process_ipn($ipn_data) {
        // Check payment status
        if (!isset($ipn_data['payment_status'])) {
            PayPal_Proxy_Checkout_Store_B::log('Missing payment status in IPN', 'error');
            return;
        }
        
        // Get custom data which contains Store A info
        if (!isset($ipn_data['custom'])) {
            PayPal_Proxy_Checkout_Store_B::log('Missing custom data in IPN', 'error');
            return;
        }
        
        // Parse custom data (format: store_a_id|store_a_order_id)
        $custom_parts = explode('|', $ipn_data['custom']);
        if (count($custom_parts) !== 2) {
            PayPal_Proxy_Checkout_Store_B::log('Invalid custom data format in IPN', 'error');
            return;
        }
        
        list($store_a_id, $store_a_order_id) = $custom_parts;
        
        // Find the local order
        $orders = wc_get_orders(array(
            'meta_key' => '_store_a_order_id',
            'meta_value' => $store_a_order_id,
            'meta_compare' => '=',
            'limit' => 1,
        ));
        
        if (empty($orders)) {
            PayPal_Proxy_Checkout_Store_B::log('Order not found for IPN: ' . $store_a_order_id, 'error');
            return;
        }
        
        $order = $orders[0];
        
        // Process based on payment status
        switch ($ipn_data['payment_status']) {
            case 'Completed':
                $order->payment_complete($ipn_data['txn_id']);
                $order->update_meta_data('_paypal_status', 'COMPLETED');
                $order->add_order_note(__('Payment completed via PayPal IPN.', 'paypal-proxy-checkout-store-b'));
                
                // Notify Store A of payment completion
                $this->notify_store_a($order, 'completed', $ipn_data);
                break;
                
            case 'Pending':
                $order->update_status('on-hold');
                $order->update_meta_data('_paypal_status', 'PENDING');
                $pending_reason = isset($ipn_data['pending_reason']) ? $ipn_data['pending_reason'] : '';
                $order->add_order_note(sprintf(__('Payment pending via PayPal IPN. Reason: %s', 'paypal-proxy-checkout-store-b'), $pending_reason));
                
                // Notify Store A of pending payment
                $this->notify_store_a($order, 'pending', $ipn_data);
                break;
                
            case 'Failed':
            case 'Denied':
            case 'Expired':
                $order->update_status('failed');
                $order->update_meta_data('_paypal_status', 'FAILED');
                $order->add_order_note(__('Payment failed or denied via PayPal IPN.', 'paypal-proxy-checkout-store-b'));
                
                // Notify Store A of payment failure
                $this->notify_store_a($order, 'failed', $ipn_data);
                break;
                
            case 'Refunded':
                $order->update_status('refunded');
                $order->update_meta_data('_paypal_status', 'REFUNDED');
                $order->add_order_note(__('Payment refunded via PayPal IPN.', 'paypal-proxy-checkout-store-b'));
                
                // Notify Store A of refund
                $this->notify_store_a($order, 'refunded', $ipn_data);
                break;
                
            case 'Reversed':
            case 'Canceled_Reversal':
                $order->update_status('on-hold');
                $order->update_meta_data('_paypal_status', 'REVERSED');
                $order->add_order_note(__('Payment reversed or reversal canceled via PayPal IPN.', 'paypal-proxy-checkout-store-b'));
                
                // Notify Store A of reversal
                $this->notify_store_a($order, 'reversed', $ipn_data);
                break;
                
            default:
                $order->add_order_note(sprintf(__('Unhandled PayPal payment status: %s', 'paypal-proxy-checkout-store-b'), $ipn_data['payment_status']));
                break;
        }
        
        $order->save();
    }
    
    /**
     * Handle PayPal webhook.
     */
    public function handle_webhook() {
        // Get the raw POST data
        $raw_post_data = file_get_contents('php://input');
        
        // Get headers for verification
        $headers = array();
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) === 'HTTP_') {
                $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $headers[strtolower($header)] = $value;
            }
        }
        
        // Verify webhook signature
        $verified = PayPal_Proxy_Checkout_Store_B_Security::verify_paypal_webhook($raw_post_data, $headers);
        
        if (!$verified) {
            PayPal_Proxy_Checkout_Store_B::log('Invalid webhook received', 'error');
            status_header(400);
            exit;
        }
        
        // Parse webhook data
        $webhook_data = json_decode($raw_post_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            PayPal_Proxy_Checkout_Store_B::log('Invalid JSON in webhook data', 'error');
            status_header(400);
            exit;
        }
        
        // Process webhook
        $this->process_webhook($webhook_data);
        
        // Send 200 OK response
        status_header(200);
        exit;
    }
    
    /**
     * Process PayPal webhook data.
     *
     * @param array $webhook_data Webhook data.
     */
    private function process_webhook($webhook_data) {
        // Check event type
        if (!isset($webhook_data['event_type'])) {
            PayPal_Proxy_Checkout_Store_B::log('Missing event type in webhook', 'error');
            return;
        }
        
        // Log webhook event
        PayPal_Proxy_Checkout_Store_B::log('PayPal webhook received: ' . $webhook_data['event_type'], 'info');
        
        // Handle different event types
        switch ($webhook_data['event_type']) {
            case 'CHECKOUT.ORDER.APPROVED':
                $this->handle_order_approved($webhook_data);
                break;
                
            case 'CHECKOUT.ORDER.COMPLETED':
                $this->handle_order_completed($webhook_data);
                break;
                
            case 'PAYMENT.CAPTURE.COMPLETED':
                $this->handle_payment_completed($webhook_data);
                break;
                
            case 'PAYMENT.CAPTURE.DENIED':
            case 'PAYMENT.CAPTURE.DECLINED':
                $this->handle_payment_denied($webhook_data);
                break;
                
            case 'PAYMENT.CAPTURE.REFUNDED':
                $this->handle_payment_refunded($webhook_data);
                break;
                
            case 'PAYMENT.CAPTURE.REVERSED':
                $this->handle_payment_reversed($webhook_data);
                break;
                
            default:
                // Ignore other webhook events
                break;
        }
    }
    
    /**
     * Handle order approved webhook event.
     *
     * @param array $webhook_data Webhook data.
     */
    private function handle_order_approved($webhook_data) {
        if (!isset($webhook_data['resource']['id'])) {
            return;
        }
        
        $paypal_order_id = $webhook_data['resource']['id'];
        
        // Find the local order
        $orders = wc_get_orders(array(
            'meta_key' => '_paypal_order_id',
            'meta_value' => $paypal_order_id,
            'meta_compare' => '=',
            'limit' => 1,
        ));
        
        if (empty($orders)) {
            PayPal_Proxy_Checkout_Store_B::log('Order not found for webhook: ' . $paypal_order_id, 'error');
            return;
        }
        
        $order = $orders[0];
        
        // Update order status
        $order->update_status('processing');
        $order->update_meta_data('_paypal_status', 'APPROVED');
        $order->add_order_note(__('PayPal order approved via webhook.', 'paypal-proxy-checkout-store-b'));
        $order->save();
        
        // Capture the payment (if not already captured)
        $this->capture_order($paypal_order_id);
    }
    
    /**
     * Handle order completed webhook event.
     *
     * @param array $webhook_data Webhook data.
     */
    private function handle_order_completed($webhook_data) {
        if (!isset($webhook_data['resource']['id'])) {
            return;
        }
        
        $paypal_order_id = $webhook_data['resource']['id'];
        
        // Find the local order
        $orders = wc_get_orders(array(
            'meta_key' => '_paypal_order_id',
            'meta_value' => $paypal_order_id,
            'meta_compare' => '=',
            'limit' => 1,
        ));
        
        if (empty($orders)) {
            PayPal_Proxy_Checkout_Store_B::log('Order not found for webhook: ' . $paypal_order_id, 'error');
            return;
        }
        
        $order = $orders[0];
        
        // Update order status
        $order->update_status('completed');
        $order->update_meta_data('_paypal_status', 'COMPLETED');
        $order->add_order_note(__('PayPal order completed via webhook.', 'paypal-proxy-checkout-store-b'));
        $order->save();
        
        // Notify Store A of completion
        $transaction_id = '';
        if (isset($webhook_data['resource']['purchase_units'][0]['payments']['captures'][0]['id'])) {
            $transaction_id = $webhook_data['resource']['purchase_units'][0]['payments']['captures'][0]['id'];
        }
        
        $payment_amount = 0;
        if (isset($webhook_data['resource']['purchase_units'][0]['amount']['value'])) {
            $payment_amount = $webhook_data['resource']['purchase_units'][0]['amount']['value'];
        }
        
        $this->notify_store_a($order, 'completed', array(
            'txn_id' => $transaction_id,
            'payment_amount' => $payment_amount,
        ));
    }
    
    /**
     * Handle payment denied webhook event.
     *
     * @param array $webhook_data Webhook data.
     */
    private function handle_payment_denied($webhook_data) {
        if (!isset($webhook_data['resource']['supplementary_data']['related_ids']['order_id'])) {
            return;
        }
        
        $paypal_order_id = $webhook_data['resource']['supplementary_data']['related_ids']['order_id'];
        
        // Find the local order
        $orders = wc_get_orders(array(
            'meta_key' => '_paypal_order_id',
            'meta_value' => $paypal_order_id,
            'meta_compare' => '=',
            'limit' => 1,
        ));
        
        if (empty($orders)) {
            PayPal_Proxy_Checkout_Store_B::log('Order not found for webhook: ' . $paypal_order_id, 'error');
            return;
        }
        
        $order = $orders[0];
        
        // Update order status
        $order->update_status('failed');
        $order->update_meta_data('_paypal_status', 'DENIED');
        $order->add_order_note(__('Payment denied via PayPal webhook.', 'paypal-proxy-checkout-store-b'));
        $order->save();
        
        // Notify Store A of payment denial
        $this->notify_store_a($order, 'failed', array());
    }
    
    /**
     * Handle payment refunded webhook event.
     *
     * @param array $webhook_data Webhook data.
     */
    private function handle_payment_refunded($webhook_data) {
        if (!isset($webhook_data['resource']['supplementary_data']['related_ids']['order_id'])) {
            return;
        }
        
        $paypal_order_id = $webhook_data['resource']['supplementary_data']['related_ids']['order_id'];
        
        // Find the local order
        $orders = wc_get_orders(array(
            'meta_key' => '_paypal_order_id',
            'meta_value' => $paypal_order_id,
            'meta_compare' => '=',
            'limit' => 1,
        ));
        
        if (empty($orders)) {
            PayPal_Proxy_Checkout_Store_B::log('Order not found for webhook: ' . $paypal_order_id, 'error');
            return;
        }
        
        $order = $orders[0];
        
        // Update order status
        $order->update_status('refunded');
        $order->update_meta_data('_paypal_status', 'REFUNDED');
        $order->add_order_note(__('Payment refunded via PayPal webhook.', 'paypal-proxy-checkout-store-b'));
        $order->save();
        
        // Notify Store A of refund
        $this->notify_store_a($order, 'refunded', array());
    }
    
    /**
     * Handle payment reversed webhook event.
     *
     * @param array $webhook_data Webhook data.
     */
    private function handle_payment_reversed($webhook_data) {
        if (!isset($webhook_data['resource']['supplementary_data']['related_ids']['order_id'])) {
            return;
        }
        
        $paypal_order_id = $webhook_data['resource']['supplementary_data']['related_ids']['order_id'];
        
        // Find the local order
        $orders = wc_get_orders(array(
            'meta_key' => '_paypal_order_id',
            'meta_value' => $paypal_order_id,
            'meta_compare' => '=',
            'limit' => 1,
        ));
        
        if (empty($orders)) {
            PayPal_Proxy_Checkout_Store_B::log('Order not found for webhook: ' . $paypal_order_id, 'error');
            return;
        }
        
        $order = $orders[0];
        
        // Update order status
        $order->update_status('on-hold');
        $order->update_meta_data('_paypal_status', 'REVERSED');
        $order->add_order_note(__('Payment reversed via PayPal webhook.', 'paypal-proxy-checkout-store-b'));
        $order->save();
        
        // Notify Store A of reversal
        $this->notify_store_a($order, 'reversed', array());
    }
    
    /**
     * Notify Store A of payment status changes.
     *
     * @param WC_Order $order Order object.
     * @param string $status Payment status.
     * @param array $data Additional data.
     * @return bool Whether notification was successful.
     */
    private function notify_store_a($order, $status, $data) {
        // Get Store A details
        $store_a_id = $order->get_meta('_store_a_id');
        $store_a_order_id = $order->get_meta('_store_a_order_id');
        
        if (empty($store_a_id) || empty($store_a_order_id)) {
            PayPal_Proxy_Checkout_Store_B::log('Missing Store A details for notification', 'error');
            return false;
        }
        
        // Get Store A URL
        $settings = PayPal_Proxy_Checkout_Store_B::get_settings();
        $allowed_stores = $settings['allowed_store_a'];
        
        $store_a_url = '';
        foreach ($allowed_stores as $store) {
            if ($store['id'] === $store_a_id) {
                $store_a_url = $store['url'];
                break;
            }
        }
        
        if (empty($store_a_url)) {
            PayPal_Proxy_Checkout_Store_B::log('Store A URL not found: ' . $store_a_id, 'error');
            return false;
        }
        
        // Generate authentication token
        $auth_token = PayPal_Proxy_Checkout_Store_B_Security::generate_store_a_auth($store_a_id);
        
        if (is_wp_error($auth_token)) {
            PayPal_Proxy_Checkout_Store_B::log('Failed to generate auth token: ' . $auth_token->get_error_message(), 'error');
            return false;
        }
        
        // Prepare notification data
        $notification_data = array(
            'order_id' => $store_a_order_id,
            'payment_status' => $status,
            'paypal_order_id' => $order->get_meta('_paypal_order_id'),
        );
        
        // Add transaction ID if available
        if (isset($data['txn_id'])) {
            $notification_data['paypal_transaction_id'] = $data['txn_id'];
        }
        
        // Add payment amount if available
        if (isset($data['payment_amount'])) {
            $notification_data['payment_amount'] = $data['payment_amount'];
        }
        
        // Send notification to Store A
        $response = wp_remote_post($store_a_url . '/wp-json/paypal-proxy/v1/update-order', array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.1',
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-Paypal-Proxy-Auth' => $auth_token,
            ),
            'body' => json_encode($notification_data),
        ));
        
        if (is_wp_error($response)) {
            PayPal_Proxy_Checkout_Store_B::log('Failed to notify Store A: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            PayPal_Proxy_Checkout_Store_B::log('Store A returned error: ' . wp_remote_retrieve_body($response), 'error');
            return false;
        }
        
        PayPal_Proxy_Checkout_Store_B::log('Successfully notified Store A of payment status: ' . $status, 'info');
        return true;
    }
}