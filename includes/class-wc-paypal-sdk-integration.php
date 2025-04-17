<?php
/**
 * WooCommerce PayPal SDK Integration
 *
 * @package WC_PayPal_Proxy_Handler
 */

defined('ABSPATH') || exit;

use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use PayPalCheckoutSdk\Payments\CapturesRefundRequest;

/**
 * WC_PayPal_SDK_Integration Class
 */
class WC_PayPal_SDK_Integration {

    /**
     * PayPal HTTP client
     *
     * @var PayPalHttpClient
     */
    private $client;

    /**
     * Debug mode
     *
     * @var bool
     */
    private $debug;

    /**
     * Constructor
     *
     * @param array $settings Plugin settings.
     */
    public function __construct($settings) {
        $this->debug = defined('WP_DEBUG') && WP_DEBUG;
        
        // Initialize PayPal client
        $client_id = isset($settings['paypal_client_id']) ? $settings['paypal_client_id'] : '';
        $client_secret = isset($settings['paypal_client_secret']) ? $settings['paypal_client_secret'] : '';
        $is_sandbox = isset($settings['paypal_sandbox']) && $settings['paypal_sandbox'] === 'yes';
        
        if (empty($client_id) || empty($client_secret)) {
            $this->log('PayPal client not initialized: Missing client ID or secret');
            return;
        }
        
        // Create environment
        if ($is_sandbox) {
            $environment = new SandboxEnvironment($client_id, $client_secret);
            $this->log('Using PayPal Sandbox environment');
        } else {
            $environment = new ProductionEnvironment($client_id, $client_secret);
            $this->log('Using PayPal Production environment');
        }
        
        // Create client
        $this->client = new PayPalHttpClient($environment);
    }

    /**
     * Create PayPal order
     *
     * @param string $order_id Store order ID.
     * @param float  $amount   Order amount.
     * @param string $currency Order currency.
     * @return array
     * @throws Exception If order creation fails.
     */
    public function create_order($order_id, $amount, $currency = 'USD') {
        if (!$this->client) {
            throw new Exception(__('PayPal client not initialized', 'wc-paypal-proxy-handler'));
        }
        
        $this->log('Creating PayPal order for store order #' . $order_id);
        
        // Format amount with 2 decimal places
        $amount = number_format($amount, 2, '.', '');
        
        // Create order request
        $request = new OrdersCreateRequest();
        $request->prefer('return=representation');
        
        // Set up order
        $request->body = array(
            'intent' => 'CAPTURE',
            'purchase_units' => array(
                array(
                    'reference_id' => $order_id,
                    'amount' => array(
                        'currency_code' => $currency,
                        'value' => $amount,
                    ),
                    'description' => 'Order #' . $order_id,
                ),
            ),
            'application_context' => array(
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
                'return_url' => get_site_url(),
                'cancel_url' => get_site_url(),
            ),
        );
        
        try {
            // Send request
            $response = $this->client->execute($request);
            
            // Log response
            $this->log('PayPal order created: ' . $response->result->id);
            
            // Return order details
            return array(
                'id' => $response->result->id,
                'status' => $response->result->status,
            );
            
        } catch (Exception $e) {
            $this->log('Error creating PayPal order: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Capture PayPal order
     *
     * @param string $paypal_order_id PayPal order ID.
     * @return array
     * @throws Exception If capture fails.
     */
    public function capture_order($paypal_order_id) {
        if (!$this->client) {
            throw new Exception(__('PayPal client not initialized', 'wc-paypal-proxy-handler'));
        }
        
        $this->log('Capturing PayPal order: ' . $paypal_order_id);
        
        // Create capture request
        $request = new OrdersCaptureRequest($paypal_order_id);
        $request->prefer('return=representation');
        
        try {
            // Send request
            $response = $this->client->execute($request);
            
            // Get the capture ID from the first capture
            $capture_id = $response->result->purchase_units[0]->payments->captures[0]->id;
            
            // Log response
            $this->log('PayPal order captured: ' . $paypal_order_id . ' (Capture ID: ' . $capture_id . ')');
            
            // Return capture details
            return array(
                'id' => $capture_id,
                'order_id' => $paypal_order_id,
                'status' => $response->result->status,
            );
            
        } catch (Exception $e) {
            $this->log('Error capturing PayPal order: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Refund payment
     *
     * @param string $capture_id Capture ID to refund.
     * @param float  $amount     Refund amount.
     * @param string $currency   Refund currency.
     * @param string $reason     Refund reason.
     * @return array
     * @throws Exception If refund fails.
     */
    public function refund_payment($capture_id, $amount, $currency = 'USD', $reason = '') {
        if (!$this->client) {
            throw new Exception(__('PayPal client not initialized', 'wc-paypal-proxy-handler'));
        }
        
        $this->log('Refunding payment: ' . $capture_id . ' (' . $amount . ' ' . $currency . ')');
        
        // Format amount with 2 decimal places
        $amount = number_format($amount, 2, '.', '');
        
        // Create refund request
        $request = new CapturesRefundRequest($capture_id);
        $request->prefer('return=representation');
        
        // Set up refund
        $request->body = array(
            'amount' => array(
                'currency_code' => $currency,
                'value' => $amount,
            ),
            'note_to_payer' => $reason,
        );
        
        try {
            // Send request
            $response = $this->client->execute($request);
            
            // Log response
            $this->log('Payment refunded: ' . $response->result->id);
            
            // Return refund details
            return array(
                'id' => $response->result->id,
                'status' => $response->result->status,
            );
            
        } catch (Exception $e) {
            $this->log('Error refunding payment: ' . $e->getMessage());
            throw $e;
        }
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
            
            $log_file = WP_CONTENT_DIR . '/paypal-proxy-logs/paypal-sdk.log';
            $timestamp = date('[Y-m-d H:i:s]');
            
            error_log($timestamp . ' ' . $message . "\n", 3, $log_file);
        }
    }
}