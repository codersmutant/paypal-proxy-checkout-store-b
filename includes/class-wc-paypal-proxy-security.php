<?php
/**
 * WooCommerce PayPal Proxy Security - TESTING MODE
 *
 * @package WC_PayPal_Proxy_Handler
 */

defined('ABSPATH') || exit;

/**
 * WC_PayPal_Proxy_Security Class
 */
class WC_PayPal_Proxy_Security {

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
     * Testing mode - set to true to bypass security checks
     *
     * @var bool
     */
    private $testing_mode = false; // ENABLED FOR TESTING

    /**
     * Constructor
     *
     * @param array $settings Plugin settings.
     */
    public function __construct($settings) {
        $this->settings = $settings;
        $this->debug = defined('WP_DEBUG') && WP_DEBUG;
        
        if ($this->debug && $this->testing_mode) {
            $this->log('TESTING MODE ENABLED - Security checks are bypassed');
        }
    }

    /**
     * Verify request from the client store
     *
     * @param array $data Request data.
     * @return bool
     */
    public function verify_request($data) {
        
        $this->log('Verification data: ' . json_encode([
    'order_id' => $data['order_id'],
    'nonce' => $data['nonce'],
    'hash' => $data['hash'],
    'store_name' => $data['store_name'] ?? 'Not provided'
        ]));
        
        
        // TESTING MODE - bypass security checks
        if ($this->testing_mode) {
            $this->log('Security verification bypassed due to testing mode');
            return true;
        }
        
        // Check required fields
        if (empty($data['order_id']) || empty($data['nonce']) || empty($data['hash'])) {
            $this->log('Missing required verification fields');
            return false;
        }
        
        
        /*
        // Get the HTTP referer
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        
        if (empty($referer)) {
            $this->log('Empty HTTP referer');
            return false;
        }
        
        // Parse the referer
        $referer_parts = parse_url($referer);
        $referer_domain = isset($referer_parts['host']) ? $referer_parts['host'] : '';
        
        if (empty($referer_domain)) {
            $this->log('Invalid HTTP referer format');
            return false;
        }
        */
        
        // Get allowed domains
        $allowed_domains = $this->get_allowed_domains();
        $domain_found = false;
        $api_key = '';
        
        // Check if the domain is allowed
        foreach ($allowed_domains as $domain => $key) {
            if (strpos($referer_domain, $domain) !== false || strpos($domain, $referer_domain) !== false) {
                $domain_found = true;
                $api_key = $key;
                break;
            }
        }
        
        if (!$domain_found) {
            $this->log('Domain not allowed: ' . $referer_domain);
            return false;
        }
        
        // Verify hash
        $expected_hash = hash_hmac('sha256', $data['order_id'] . $data['nonce'], $api_key);
        
        if (!hash_equals($expected_hash, $data['hash'])) {
            $this->log('Invalid hash for order #' . $data['order_id']);
            return false;
        }
        
       // Check for nonce reuse but be smarter about it
        $used_nonces = get_option('wc_paypal_proxy_used_nonces', array());
        
        // Create a unique key combining order ID and nonce
        $nonce_key = $data['order_id'] . '_' . $data['nonce'];
        
        if (isset($used_nonces[$data['order_id']]) && in_array($data['nonce'], $used_nonces[$data['order_id']])) {
            // For the same order, allow a limited number of retries (e.g., 5)
            if (count($used_nonces[$data['order_id']]) >= 100) {
                // Generate a new nonce and return guidance
                $this->log('Too many nonce attempts for order #' . $data['order_id']);
                return false;
            }
        }
        
        // Store nonce per order ID
        if (!isset($used_nonces[$data['order_id']])) {
            $used_nonces[$data['order_id']] = array();
        }
        $used_nonces[$data['order_id']][] = $data['nonce'];
        
        // Keep only the last 1000 orders to prevent database bloat
        if (count($used_nonces) > 1000) {
            $used_nonces = array_slice($used_nonces, -1000, 1000, true);
        }
        
        update_option('wc_paypal_proxy_used_nonces', $used_nonces);
        
        return true;
    }

    /**
     * Get allowed domains with their API keys
     *
     * @return array
     */
    private function get_allowed_domains() {
        $domains = array();
        
        // Get allowed domains from settings
        $api_keys = isset($this->settings['api_keys']) ? $this->settings['api_keys'] : array();
        
        if (!empty($api_keys) && is_array($api_keys)) {
            foreach ($api_keys as $domain => $key) {
                $domains[$domain] = $key;
            }
        }
        
        return $domains;
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
            
            $log_file = WP_CONTENT_DIR . '/paypal-proxy-logs/security.log';
            $timestamp = date('[Y-m-d H:i:s]');
            
            error_log($timestamp . ' ' . $message . "\n", 3, $log_file);
        }
    }
}