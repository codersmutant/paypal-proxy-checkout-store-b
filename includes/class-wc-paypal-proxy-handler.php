<?php
/**
 * WooCommerce PayPal Proxy Handler
 *
 * @package WC_PayPal_Proxy_Handler
 */

defined('ABSPATH') || exit;

/**
 * WC_PayPal_Proxy_Handler Class
 */
class WC_PayPal_Proxy_Handler {

    /**
     * PayPal SDK integration
     *
     * @var WC_PayPal_SDK_Integration
     */
    private $paypal_sdk;

    /**
     * Security class
     *
     * @var WC_PayPal_Proxy_Security
     */
    private $security;

    /**
     * Plugin settings
     *
     * @var array
     */
    private $settings;

    /**
     * Debug mode
     *
     * @var bool
     */
    private $debug;

    /**
     * Constructor
     */
    public function __construct() {
        // Load settings
        $this->settings = get_option('wc_paypal_proxy_handler_settings', array());
        $this->debug = defined('WP_DEBUG') && WP_DEBUG;
        
        // Initialize dependencies
        $this->paypal_sdk = new WC_PayPal_SDK_Integration($this->settings);
        $this->security = new WC_PayPal_Proxy_Security($this->settings);
        
        // Register REST API endpoints
        add_action('rest_api_init', array($this, 'register_endpoints'));
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Register REST API endpoints
     */
    public function register_endpoints() {
        register_rest_route('wc-paypal-proxy/v1', '/checkout', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_checkout_request'),
            'permission_callback' => '__return_true',
        ));
        
        register_rest_route('wc-paypal-proxy/v1', '/create-order', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_create_order'),
            'permission_callback' => '__return_true',
        ));
        
        register_rest_route('wc-paypal-proxy/v1', '/capture-order', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_capture_order'),
            'permission_callback' => '__return_true',
        ));
        
        register_rest_route('wc-paypal-proxy/v1', '/refund', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_refund_request'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        // Only enqueue scripts on our custom endpoint
        if (!isset($_GET['rest_route']) || strpos($_GET['rest_route'], '/wc-paypal-proxy/v1/checkout') === false) {
            return;
        }
        
        // Enqueue PayPal JS SDK
        $client_id = $this->settings['paypal_client_id'] ?? '';
        $is_sandbox = ($this->settings['paypal_sandbox'] ?? 'yes') === 'yes';
        
        wp_enqueue_script(
            'paypal-js-sdk',
            add_query_arg(
                array(
                    'client-id' => $client_id,
                    'currency'  => 'USD', // Will be overridden by checkout data
                    'intent'    => 'capture',
                ),
                'https://www.paypal.com/sdk/js'
            ),
            array(),
            null,
            true
        );
        
        // Enqueue our custom JS
        wp_enqueue_script(
            'paypal-button-renderer',
            WC_PAYPAL_PROXY_HANDLER_PLUGIN_URL . 'assets/js/paypal-button-renderer.js',
            array('jquery', 'paypal-js-sdk'),
            WC_PAYPAL_PROXY_HANDLER_VERSION,
            true
        );
        
        // Add inline styles
        wp_add_inline_style(
            'wp-block-library',
            '
            body {
                background-color: transparent;
                padding: 0;
                margin: 0;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            }
            .paypal-button-container {
                width: 100%;
                max-width: 500px;
                margin: 0 auto;
            }
            .paypal-message {
                text-align: center;
                margin: 10px 0;
                padding: 10px;
                border-radius: 4px;
            }
            .paypal-message.error {
                background-color: #ffebee;
                color: #c62828;
                border: 1px solid #ffcdd2;
            }
            .paypal-message.success {
                background-color: #e8f5e9;
                color: #2e7d32;
                border: 1px solid #c8e6c9;
            }
            .paypal-message.info {
                background-color: #e3f2fd;
                color: #1565c0;
                border: 1px solid #bbdefb;
            }
            '
        );
    }

    /**
     * Handle checkout request
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_checkout_request($request) {
    // Get the encrypted data
    $encrypted_data = $request->get_param('data');
    
    if (empty($encrypted_data)) {
        return $this->render_error(__('Missing checkout data', 'wc-paypal-proxy-handler'));
    }
    
    // Decode and validate the data
    try {
        // TESTING MODE: Allow invalid data (bypass validation)
        if ($encrypted_data === 'abc123') {
            $this->log('Test mode: Using dummy data');
            $data = [
                'order_id' => '123',
                'currency' => 'USD',
                'amount' => 10.00,
                'return_url' => 'https://example.com/success',
                'cancel_url' => 'https://example.com/cancel',
                'store_name' => 'Test Store',
            ];
        } else {
            $data = json_decode(base64_decode($encrypted_data), true);
            
            if (!$data || !is_array($data)) {
                throw new Exception(__('Invalid checkout data format', 'wc-paypal-proxy-handler'));
            }
            
            // Verify the data
            if (!$this->security->verify_request($data)) {
                throw new Exception(__('Invalid checkout request', 'wc-paypal-proxy-handler'));
            }
        }
        
        // Extract order details
        $order_id = isset($data['order_id']) ? sanitize_text_field($data['order_id']) : '';
        $currency = isset($data['currency']) ? sanitize_text_field($data['currency']) : 'USD';
        $amount = isset($data['amount']) ? floatval($data['amount']) : 0;
        $return_url = isset($data['return_url']) ? esc_url_raw($data['return_url']) : '';
        $cancel_url = isset($data['cancel_url']) ? esc_url_raw($data['cancel_url']) : '';
        $store_name = isset($data['store_name']) ? sanitize_text_field($data['store_name']) : '';
        
        
        // Extract product information for logging
        $products = isset($data['products']) ? $data['products'] : array();
        
        // Log the product information
        if (!empty($products)) {
            $this->log('Products in order #' . $order_id . ' from ' . $store_name . ':');
            
            foreach ($products as $product) {
                $this->log(sprintf(
                    'Product: %s (Store A ID: %s, Store B ID: %s), Quantity: %s, Price: %s, Total: %s',
                    isset($product['name']) ? $product['name'] : 'Unknown',
                    isset($product['store_a_id']) ? $product['store_a_id'] : 'N/A',
                    isset($product['store_b_id']) ? $product['store_b_id'] : 'N/A',
                    isset($product['quantity']) ? $product['quantity'] : 0,
                    isset($product['price']) ? $product['price'] : 0,
                    isset($product['total']) ? $product['total'] : 0
                ));
            }
        }

        
        
        // Validate required fields
        if (empty($order_id) || empty($amount) || $amount <= 0) {
            throw new Exception(__('Missing required checkout fields', 'wc-paypal-proxy-handler'));
        }
        
        // Set up data for JS
        $checkout_data = array(
            'order_id'    => $order_id,
            'currency'    => $currency,
            'amount'      => $amount,
            'return_url'  => $return_url,
            'cancel_url'  => $cancel_url,
            'store_name'  => $store_name,
            'create_url'  => rest_url('wc-paypal-proxy/v1/create-order'),
            'capture_url' => rest_url('wc-paypal-proxy/v1/capture-order'),
            'nonce'       => wp_create_nonce('wc-paypal-proxy-' . $order_id),
            'data'        => $encrypted_data,
            'products'    => $products,
        );
        
        // Log the checkout request
        $this->log('Checkout request for order #' . $order_id . ' received from ' . $store_name);
        
        // IMPORTANT CHANGE: Output direct HTML instead of returning a WP_REST_Response
        $this->render_checkout_page_direct($checkout_data);
        exit; // Stop WordPress from trying to further process the response
        
    } catch (Exception $e) {
        $this->log('Checkout error: ' . $e->getMessage());
        
        // IMPORTANT CHANGE: Output error directly
        $this->render_error_direct($e->getMessage());
        exit; // Stop WordPress from trying to further process the response
    }
}




/**
 * Get product name from Store B
 *
 * @param int $product_id Product ID in Store B
 * @return string Product name or empty string if not found
 */
private function get_store_b_product_name($product_id) {
    if (empty($product_id)) {
        return '';
    }
    
    $product = wc_get_product($product_id);
    
    if (!$product) {
        return '';
    }
    
    return $product->get_name();
}

/**
 * Render checkout page directly to output
 *
 * @param array $data Checkout data.
 */
private function render_checkout_page_direct($data) {
    // Set proper content type
    header('Content-Type: text/html; charset=UTF-8');
    
    // Output the HTML
    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . esc_html__('PayPal Checkout', 'wc-paypal-proxy-handler') . '</title>';
    wp_head();
    echo '</head>';
    echo '<body>';
    
    // Output container for PayPal buttons
    echo '<div class="paypal-button-container" id="paypal-button-container"></div>';
    
    // Add data for JavaScript
    echo '<script type="text/javascript">';
    echo 'var wc_paypal_proxy_data = ' . wp_json_encode($data) . ';';
    echo '</script>';
    
    wp_footer();
    echo '</body>';
    echo '</html>';
}

/**
 * Render error page directly to output
 *
 * @param string $message Error message.
 */
private function render_error_direct($message) {
    // Set proper content type
    header('Content-Type: text/html; charset=UTF-8');
    
    // Output the HTML
    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . esc_html__('PayPal Checkout Error', 'wc-paypal-proxy-handler') . '</title>';
    wp_head();
    echo '</head>';
    echo '<body>';
    
    // Output error message
    echo '<div class="paypal-message error">';
    echo esc_html($message);
    echo '</div>';
    
    wp_footer();
    echo '</body>';
    echo '</html>';
}

 /**
 * Handle create order request
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
public function handle_create_order($request) {
    // Get the parameters
    $params = $request->get_params();
    
    // Validate nonce
    if (!isset($params['nonce']) || !wp_verify_nonce($params['nonce'], 'wc-paypal-proxy-' . $params['order_id'])) {
        return new WP_Error('invalid_nonce', __('Invalid nonce', 'wc-paypal-proxy-handler'), array('status' => 403));
    }
    
    // Required parameters
    $order_id = isset($params['order_id']) ? sanitize_text_field($params['order_id']) : '';
    $amount = isset($params['amount']) ? floatval($params['amount']) : 0;
    $currency = isset($params['currency']) ? sanitize_text_field($params['currency']) : 'USD';
    
    // Extract product data
    $products = array();
    
    // Verify the encrypted data
    if (isset($params['data'])) {
        try {
            $data = json_decode(base64_decode($params['data']), true);
            
            if (!$this->security->verify_request($data)) {
                throw new Exception(__('Invalid data signature', 'wc-paypal-proxy-handler'));
            }
            
            // Extract products from the data
            if (isset($data['products']) && is_array($data['products'])) {
                $products = $data['products'];
                
                // Process all products (both mapped and unmapped)
                foreach ($products as $key => $product) {
                    if (!empty($product['store_b_id'])) {
                        // This is a mapped product - use Store B name
                        $store_b_name = $this->get_store_b_product_name($product['store_b_id']);
                        
                        if (!empty($store_b_name)) {
                            $products[$key]['name'] = $store_b_name;
                            $this->log(sprintf(
                                'Using Store B name for mapped product: "%s"',
                                $store_b_name
                            ));
                        }
                    } else {
                        // This is an unmapped product - keep Store A name and details
                        $this->log(sprintf(
                            'Using Store A name for unmapped product: "%s"',
                            $product['name']
                        ));
                    }
                }
                
                $this->log('Processed ' . count($products) . ' products for PayPal order #' . $order_id);
            }
                        
        } catch (Exception $e) {
            return new WP_Error('invalid_data', $e->getMessage(), array('status' => 400));
        }
    }
    
    // Create the PayPal order
    try {
        // Pass products to create_order method
        $order_result = $this->paypal_sdk->create_order($order_id, $amount, $currency, $products);
        
        $this->log('PayPal order created for store order #' . $order_id);
        
        return new WP_REST_Response($order_result, 200);
        
    } catch (Exception $e) {
        $this->log('Error creating PayPal order: ' . $e->getMessage());
        return new WP_Error('create_order_error', $e->getMessage(), array('status' => 400));
    }
}

    /**
     * Handle capture order request
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_capture_order($request) {
        // Get the parameters
        $params = $request->get_params();
        
        // Validate nonce
        if (!isset($params['nonce']) || !wp_verify_nonce($params['nonce'], 'wc-paypal-proxy-' . $params['order_id'])) {
            return new WP_Error('invalid_nonce', __('Invalid nonce', 'wc-paypal-proxy-handler'), array('status' => 403));
        }
        
        // Required parameters
        $order_id = isset($params['order_id']) ? sanitize_text_field($params['order_id']) : '';
        $paypal_order_id = isset($params['paypal_order_id']) ? sanitize_text_field($params['paypal_order_id']) : '';
        
        // Verify the encrypted data
        if (isset($params['data'])) {
            try {
                $data = json_decode(base64_decode($params['data']), true);
                
                if (!$this->security->verify_request($data)) {
                    throw new Exception(__('Invalid data signature', 'wc-paypal-proxy-handler'));
                }
                
            } catch (Exception $e) {
                return new WP_Error('invalid_data', $e->getMessage(), array('status' => 400));
            }
        }
        
        // Capture the PayPal order
        try {
            $capture_result = $this->paypal_sdk->capture_order($paypal_order_id);
            
            // Log the capture
            $this->log('PayPal order ' . $paypal_order_id . ' captured for store order #' . $order_id);
            
            // Send webhook to the original store
            $this->send_webhook($data, 'completed', $capture_result);
            
            return new WP_REST_Response($capture_result, 200);
            
        } catch (Exception $e) {
            $this->log('Error capturing PayPal order: ' . $e->getMessage());
            
            // Send webhook for failed payment
            $this->send_webhook($data, 'failed', array('error' => $e->getMessage()));
            
            return new WP_Error('capture_order_error', $e->getMessage(), array('status' => 400));
        }
    }

    /**
     * Handle refund request
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_refund_request($request) {
        // Get the parameters
        $params = $request->get_params();
        
        // Required parameters
        $order_id = isset($params['order_id']) ? sanitize_text_field($params['order_id']) : '';
        $transaction_id = isset($params['transaction_id']) ? sanitize_text_field($params['transaction_id']) : '';
        $amount = isset($params['amount']) ? floatval($params['amount']) : 0;
        $reason = isset($params['reason']) ? sanitize_text_field($params['reason']) : '';
        $currency = isset($params['currency']) ? sanitize_text_field($params['currency']) : 'USD';
        $nonce = isset($params['nonce']) ? sanitize_text_field($params['nonce']) : '';
        $hash = isset($params['hash']) ? sanitize_text_field($params['hash']) : '';
        
        // Validate parameters
        if (empty($order_id) || empty($transaction_id) || empty($amount) || $amount <= 0) {
            return new WP_Error('missing_parameters', __('Missing required parameters', 'wc-paypal-proxy-handler'), array('status' => 400));
        }
        
        // Verify hash
        $stored_api_keys = isset($this->settings['api_keys']) ? $this->settings['api_keys'] : array();
        $valid_hash = false;
        
        foreach ($stored_api_keys as $domain => $api_key) {
            $expected_hash = hash_hmac('sha256', $order_id . $nonce . $amount, $api_key);
            
            if (hash_equals($expected_hash, $hash)) {
                $valid_hash = true;
                break;
            }
        }
        
        if (!$valid_hash) {
            $this->log('Invalid hash for refund request for order #' . $order_id);
            return new WP_Error('invalid_hash', __('Invalid refund request', 'wc-paypal-proxy-handler'), array('status' => 403));
        }
        
        // Process the refund through PayPal
        try {
            $refund_result = $this->paypal_sdk->refund_payment($transaction_id, $amount, $currency, $reason);
            
            // Log the refund
            $this->log('Refunded ' . $amount . ' ' . $currency . ' for order #' . $order_id);
            
            // Send webhook to the original store
            $this->send_webhook(
                array(
                    'order_id' => $order_id,
                    'return_url' => isset($params['return_url']) ? $params['return_url'] : '',
                ),
                'refunded',
                array(
                    'transaction_id' => $refund_result['id'],
                    'amount' => $amount,
                    'reason' => $reason,
                )
            );
            
            return new WP_REST_Response(array(
                'success' => true,
                'id' => $refund_result['id'],
                'status' => $refund_result['status'],
            ), 200);
            
        } catch (Exception $e) {
            $this->log('Error processing refund: ' . $e->getMessage());
            return new WP_Error('refund_error', $e->getMessage(), array('status' => 400));
        }
    }
    
    /**
     * Send webhook to the original store
     *
     * @param array  $data    Original request data.
     * @param string $status  Payment status.
     * @param array  $details Additional details.
     */
    private function send_webhook($data, $status, $details = array()) {
        // Check if we have the return URL
        if (empty($data['return_url'])) {
            $this->log('Cannot send webhook: missing return URL');
            return;
        }
        
        // Get the domain from the return URL
        $return_url_parts = parse_url($data['return_url']);
        $domain = $return_url_parts['host'];
        
        // Get the API key for this domain
        $api_key = '';
        $stored_api_keys = isset($this->settings['api_keys']) ? $this->settings['api_keys'] : array();
        
        foreach ($stored_api_keys as $stored_domain => $key) {
            if (strpos($stored_domain, $domain) !== false || strpos($domain, $stored_domain) !== false) {
                $api_key = $key;
                break;
            }
        }
        
        if (empty($api_key)) {
            $this->log('Cannot send webhook: no API key found for domain ' . $domain);
            return;
        }
        
        // Prepare webhook data
        $order_id = isset($data['order_id']) ? $data['order_id'] : '';
        $nonce = wp_create_nonce('wc-paypal-proxy-webhook-' . $order_id);
        $hash = hash_hmac('sha256', $order_id . $status . $nonce, $api_key);
        
        $webhook_data = array(
            'order_id' => $order_id,
            'status' => $status,
            'nonce' => $nonce,
            'hash' => $hash,
        );
        
        // Add transaction ID if available
        if (isset($details['id'])) {
            $webhook_data['transaction_id'] = $details['id'];
        } elseif (isset($details['transaction_id'])) {
            $webhook_data['transaction_id'] = $details['transaction_id'];
        }
        
        // Add amount if available
        if (isset($details['amount'])) {
            $webhook_data['amount'] = $details['amount'];
        }
        
        // Add reason if available
        if (isset($details['reason'])) {
            $webhook_data['reason'] = $details['reason'];
        }
        
        // Add error if available
        if (isset($details['error'])) {
            $webhook_data['error'] = $details['error'];
        }
        
        // Determine webhook URL
        $store_url = trailingslashit($return_url_parts['scheme'] . '://' . $return_url_parts['host']);
        $webhook_url = $store_url . '?rest_route=/wc-paypal-proxy/v1/webhook';
        
        // Send webhook
        $response = wp_remote_post(
            $webhook_url,
            array(
                'body' => $webhook_data,
                'timeout' => 15,
            )
        );
        
        // Log the result
        if (is_wp_error($response)) {
            $this->log('Error sending webhook: ' . $response->get_error_message());
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            
            if ($response_code >= 200 && $response_code < 300) {
                $this->log('Webhook sent successfully for order #' . $order_id . ' with status ' . $status);
            } else {
                $this->log('Error sending webhook. Response code: ' . $response_code);
            }
        }
        
        // Fallback to legacy webhook URL if REST API fails
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 400) {
            $legacy_webhook_url = $store_url . '?wc-paypal-proxy-webhook=yes';
            $webhook_data['payload'] = base64_encode(json_encode($webhook_data));
            
            $legacy_response = wp_remote_post(
                $legacy_webhook_url,
                array(
                    'body' => $webhook_data,
                    'timeout' => 15,
                )
            );
            
            if (is_wp_error($legacy_response)) {
                $this->log('Error sending legacy webhook: ' . $legacy_response->get_error_message());
            } else {
                $response_code = wp_remote_retrieve_response_code($legacy_response);
                
                if ($response_code >= 200 && $response_code < 300) {
                    $this->log('Legacy webhook sent successfully for order #' . $order_id);
                } else {
                    $this->log('Error sending legacy webhook. Response code: ' . $response_code);
                }
            }
        }
    }
    
    /**
     * Render checkout page
     *
     * @param array $data Checkout data.
     * @return string
     */
    private function render_checkout_page($data) {
        // Start output buffering
        ob_start();
        
        // Output the HTML
        echo '<!DOCTYPE html>';
        echo '<html lang="en">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>' . esc_html__('PayPal Checkout', 'wc-paypal-proxy-handler') . '</title>';
        wp_head();
        echo '</head>';
        echo '<body>';
        
        // Output container for PayPal buttons
        echo '<div class="paypal-button-container" id="paypal-button-container"></div>';
        
        // Add data for JavaScript
        echo '<script type="text/javascript">';
        echo 'var wc_paypal_proxy_data = ' . wp_json_encode($data) . ';';
        echo '</script>';
        
        wp_footer();
        echo '</body>';
        echo '</html>';
        
        // Get the buffered content
        $content = ob_get_clean();
        
        // Return the content
        return $content;
    }
    
    /**
     * Render error page
     *
     * @param string $message Error message.
     * @return string
     */
    private function render_error($message) {
        // Start output buffering
        ob_start();
        
        // Output the HTML
        echo '<!DOCTYPE html>';
        echo '<html lang="en">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>' . esc_html__('PayPal Checkout Error', 'wc-paypal-proxy-handler') . '</title>';
        wp_head();
        echo '</head>';
        echo '<body>';
        
        // Output error message
        echo '<div class="paypal-message error">';
        echo esc_html($message);
        echo '</div>';
        
        wp_footer();
        echo '</body>';
        echo '</html>';
        
        // Get the buffered content
        $content = ob_get_clean();
        
        // Return the content
        return $content;
    }
    
    /**
     * Log messages
     *
     * @param string $message Log message.
     */
    private function log($message) {
        if ($this->debug) {
            if (!file_exists(WP_CONTENT_DIR . '/paypal-proxy-logs')) {
                mkdir(WP_CONTENT_DIR . '/paypal-proxy-logs', 0755, true);
            }
            
            $log_file = WP_CONTENT_DIR . '/paypal-proxy-logs/debug.log';
            $timestamp = date('[Y-m-d H:i:s]');
            
            error_log($timestamp . ' ' . $message . "\n", 3, $log_file);
        }
    }
}