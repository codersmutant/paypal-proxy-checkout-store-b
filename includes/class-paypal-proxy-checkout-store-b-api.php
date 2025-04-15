<?php
/**
 * The API functionality of the plugin.
 *
 * @since      1.0.0
 * @package    PayPal_Proxy_Checkout_Store_B
 */

if (!defined('WPINC')) {
    die;
}

class PayPal_Proxy_Checkout_Store_B_API {

    /**
     * Initialize the class.
     *
     * @since    1.0.0
     */
    public function __construct() {
    }

    /**
     * Register REST API endpoints.
     *
     * @since    1.0.0
     */
    public function register_endpoints() {
        // Endpoint for Store A to create a PayPal order
        register_rest_route('paypal-proxy/v1', '/create-order', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_order'),
            'permission_callback' => array($this, 'verify_store_a_request'),
        ));
        
        // Endpoint for Store A to test connection
        register_rest_route('paypal-proxy/v1', '/test-connection', array(
            'methods' => 'POST',
            'callback' => array($this, 'test_connection'),
            'permission_callback' => array($this, 'verify_store_a_request'),
        ));
        
        // Endpoint for Store A to get order status
        register_rest_route('paypal-proxy/v1', '/order-status', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_order_status'),
            'permission_callback' => array($this, 'verify_store_a_request'),
        ));
        
        // Endpoint for Store A to handle order status changes
        register_rest_route('paypal-proxy/v1', '/order-status-change', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_order_status_change'),
            'permission_callback' => array($this, 'verify_store_a_request'),
        ));
        
        // Endpoint to provide PayPal button JS
        register_rest_route('paypal-proxy/v1', '/button.js', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_button_js'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Verify that the request is coming from an authorized Store A.
     *
     * @param WP_REST_Request $request The request.
     * @return bool|WP_Error True if authorized, WP_Error otherwise.
     */
    public function verify_store_a_request($request) {
        // Get the Store A ID
        $data = json_decode($request->get_body(), true);
        
        if (!isset($data['store_a_id'])) {
            return new WP_Error(
                'missing_store_a_id',
                __('Store A ID is required.', 'paypal-proxy-checkout-store-b'),
                array('status' => 400)
            );
        }
        
        $store_a_id = $data['store_a_id'];
        
        // Get the authorization header
        $auth_header = $request->get_header('X-Paypal-Proxy-Auth');
        
        if (empty($auth_header)) {
            return new WP_Error(
                'missing_auth',
                __('Missing authorization header.', 'paypal-proxy-checkout-store-b'),
                array('status' => 401)
            );
        }
        
        // Verify the authorization
        return PayPal_Proxy_Checkout_Store_B_Security::verify_store_a_auth($auth_header, $store_a_id);
    }
    
    /**
     * Create a PayPal order.
     *
     * @param WP_REST_Request $request The request.
     * @return WP_REST_Response|WP_Error The response.
     */
    public function create_order($request) {
        // Get order data
        $order_data = json_decode($request->get_body(), true);
        
        // Validate required fields
        $required_fields = array('store_a_order_id', 'store_a_id', 'items', 'currency', 'total');
        foreach ($required_fields as $field) {
            if (!isset($order_data[$field])) {
                return new WP_Error(
                    'missing_field',
                    sprintf(__('Missing required field: %s', 'paypal-proxy-checkout-store-b'), $field),
                    array('status' => 400)
                );
            }
        }
        
        // Create PayPal order
        $paypal = new PayPal_Proxy_Checkout_Store_B_PayPal();
        $result = $paypal->create_order($order_data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Return success response
        return rest_ensure_response(array(
            'success' => true,
            'order_id' => $result['order_id'],
            'paypal_order_id' => $result['paypal_order_id'],
            'checkout_url' => $result['checkout_url'],
        ));
    }
    
    /**
     * Test connection with Store A.
     *
     * @param WP_REST_Request $request The request.
     * @return WP_REST_Response|WP_Error The response.
     */
    public function test_connection($request) {
        $data = json_decode($request->get_body(), true);
        
        // Log connection test
        PayPal_Proxy_Checkout_Store_B::log('Connection test from Store A: ' . $data['store_a_id'], 'info');
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => __('Connection successful!', 'paypal-proxy-checkout-store-b'),
            'timestamp' => time(),
        ));
    }
    
    /**
     * Get order status.
     *
     * @param WP_REST_Request $request The request.
     * @return WP_REST_Response|WP_Error The response.
     */
    public function get_order_status($request) {
        $data = json_decode($request->get_body(), true);
        
        // Validate required fields
        if (!isset($data['store_a_order_id']) || !isset($data['store_a_id'])) {
            return new WP_Error(
                'missing_field',
                __('Missing required field: store_a_order_id or store_a_id', 'paypal-proxy-checkout-store-b'),
                array('status' => 400)
            );
        }
        
        // Find the order
        $orders = wc_get_orders(array(
            'meta_key' => '_store_a_order_id',
            'meta_value' => $data['store_a_order_id'],
            'meta_compare' => '=',
            'limit' => 1,
        ));
        
        if (empty($orders)) {
            return new WP_Error(
                'order_not_found',
                __('Order not found.', 'paypal-proxy-checkout-store-b'),
                array('status' => 404)
            );
        }
        
        $order = $orders[0];
        
        // Verify Store A ID
        $store_a_id = $order->get_meta('_store_a_id');
        if ($store_a_id !== $data['store_a_id']) {
            return new WP_Error(
                'unauthorized',
                __('Unauthorized access to this order.', 'paypal-proxy-checkout-store-b'),
                array('status' => 403)
            );
        }
        
        // Get order status
        $order_status = $order->get_status();
        $paypal_status = $order->get_meta('_paypal_status');
        $paypal_order_id = $order->get_meta('_paypal_order_id');
        
        // Return order status
        return rest_ensure_response(array(
            'success' => true,
            'order_id' => $order->get_id(),
            'store_a_order_id' => $data['store_a_order_id'],
            'order_status' => $order_status,
            'paypal_status' => $paypal_status,
            'paypal_order_id' => $paypal_order_id,
        ));
    }
    
    /**
     * Handle order status change from Store A.
     *
     * @param WP_REST_Request $request The request.
     * @return WP_REST_Response|WP_Error The response.
     */
    public function handle_order_status_change($request) {
        $data = json_decode($request->get_body(), true);
        
        // Validate required fields
        if (!isset($data['store_a_order_id']) || !isset($data['store_a_id']) || !isset($data['new_status'])) {
            return new WP_Error(
                'missing_field',
                __('Missing required field: store_a_order_id, store_a_id, or new_status', 'paypal-proxy-checkout-store-b'),
                array('status' => 400)
            );
        }
        
        // Find the order
        $orders = wc_get_orders(array(
            'meta_key' => '_store_a_order_id',
            'meta_value' => $data['store_a_order_id'],
            'meta_compare' => '=',
            'limit' => 1,
        ));
        
        if (empty($orders)) {
            return new WP_Error(
                'order_not_found',
                __('Order not found.', 'paypal-proxy-checkout-store-b'),
                array('status' => 404)
            );
        }
        
        $order = $orders[0];
        
        // Verify Store A ID
        $store_a_id = $order->get_meta('_store_a_id');
        if ($store_a_id !== $data['store_a_id']) {
            return new WP_Error(
                'unauthorized',
                __('Unauthorized access to this order.', 'paypal-proxy-checkout-store-b'),
                array('status' => 403)
            );
        }
        
        // Handle based on new status
        $new_status = $data['new_status'];
        
        switch ($new_status) {
            case 'cancelled':
                // Only cancel if payment is not completed
                $paypal_status = $order->get_meta('_paypal_status');
                if ($paypal_status !== 'COMPLETED') {
                    $order->update_status('cancelled', __('Order cancelled by Store A.', 'paypal-proxy-checkout-store-b'));
                } else {
                    $order->add_order_note(__('Cancellation requested by Store A, but payment already completed. Manual refund may be required.', 'paypal-proxy-checkout-store-b'));
                }
                break;
                
            case 'refunded':
                // Only mark as refunded if payment is completed
                $paypal_status = $order->get_meta('_paypal_status');
                if ($paypal_status === 'COMPLETED') {
                    $order->update_status('refunded', __('Refund requested by Store A.', 'paypal-proxy-checkout-store-b'));
                    
                    // Note: Actual PayPal refund would need to be handled manually or via additional API
                    $order->add_order_note(__('Manual PayPal refund required.', 'paypal-proxy-checkout-store-b'));
                } else {
                    $order->add_order_note(__('Refund requested by Store A, but payment not completed.', 'paypal-proxy-checkout-store-b'));
                }
                break;
                
            default:
                $order->add_order_note(sprintf(__('Status change requested by Store A: %s', 'paypal-proxy-checkout-store-b'), $new_status));
                break;
        }
        
        $order->save();
        
        // Return success response
        return rest_ensure_response(array(
            'success' => true,
            'message' => sprintf(__('Order status updated to: %s', 'paypal-proxy-checkout-store-b'), $new_status),
        ));
    }
    
    /**
     * Provide PayPal button JavaScript.
     *
     * @param WP_REST_Request $request The request.
     * @return WP_REST_Response|WP_Error The response.
     */
    public function get_button_js($request) {
        // Get settings
        $settings = PayPal_Proxy_Checkout_Store_B::get_settings();
        
        // Set JavaScript headers
        header('Content-Type: application/javascript');
        
        // Add security headers
        PayPal_Proxy_Checkout_Store_B_Security::add_security_headers();
        
        // Generate the JavaScript content
        $js = $this->generate_button_js($settings);
        
        // Output the JavaScript
        echo $js;
        exit;
    }
    
    /**
     * Generate PayPal button JavaScript.
     *
     * @param array $settings Plugin settings.
     * @return string JavaScript code.
     */
    private function generate_button_js($settings) {
        ob_start();
        ?>
// PayPal Proxy Checkout Button Script
(function() {
    'use strict';
    
    // PayPal SDK script
    var CLIENT_ID = <?php echo json_encode($settings['paypal_client_id']); ?>;
    var PAYPAL_MODE = <?php echo json_encode($settings['paypal_mode']); ?>;
    
    // Load PayPal SDK
    function loadPayPalScript(callback) {
        if (typeof paypal !== 'undefined') {
            callback();
            return;
        }
        
        var script = document.createElement('script');
        script.src = 'https://www.paypal.com/sdk/js?client-id=' + CLIENT_ID + '&currency=USD&intent=capture';
        script.async = true;
        script.onload = callback;
        document.head.appendChild(script);
    }
    
    // Initialize PayPal button
    window.initPayPalProxyButton = function(containerId, options) {
        loadPayPalScript(function() {
            var container = document.getElementById(containerId);
            if (!container) {
                console.error('PayPal button container not found:', containerId);
                return;
            }
            
            paypal.Buttons({
                // When the button is clicked
                createOrder: function(data, actions) {
                    container.classList.add('loading');
                    
                    return new Promise(function(resolve, reject) {
                        if (typeof options.createOrder === 'function') {
                            options.createOrder(data, {
                                resolve: resolve,
                                reject: reject
                            });
                        } else {
                            reject('createOrder callback is required');
                        }
                    }).finally(function() {
                        container.classList.remove('loading');
                    });
                },
                
                // When payment is approved
                onApprove: function(data, actions) {
                    container.classList.add('processing');
                    
                    if (typeof options.onApprove === 'function') {
                        options.onApprove(data, actions);
                    }
                    
                    return true;
                },
                
                // When user cancels
                onCancel: function(data) {
                    if (typeof options.onCancel === 'function') {
                        options.onCancel(data);
                    }
                },
                
                // When error occurs
                onError: function(err) {
                    console.error('PayPal error:', err);
                    
                    if (typeof options.onError === 'function') {
                        options.onError(err);
                    }
                }
            }).render('#' + containerId);
        });
    };
})();
        <?php
        return ob_get_clean();
    }
}