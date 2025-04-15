<?php
/**
 * The security functionality of the plugin.
 *
 * @since      1.0.0
 * @package    PayPal_Proxy_Checkout_Store_B
 */

if (!defined('WPINC')) {
    die;
}

class PayPal_Proxy_Checkout_Store_B_Security {

    /**
     * Verify the authentication token from Store A.
     *
     * @param string $auth_header The authentication header.
     * @param string $store_a_id The Store A ID.
     * @return bool|WP_Error True if authenticated, WP_Error otherwise.
     */
    public static function verify_store_a_auth($auth_header, $store_a_id) {
        // Get settings
        $settings = PayPal_Proxy_Checkout_Store_B::get_settings();
        $allowed_stores = $settings['allowed_store_a'];
        
        // Find the Store A in allowed stores
        $store_a = null;
        foreach ($allowed_stores as $store) {
            if ($store['id'] === $store_a_id) {
                $store_a = $store;
                break;
            }
        }
        
        if (empty($store_a)) {
            PayPal_Proxy_Checkout_Store_B::log('Store A not allowed: ' . $store_a_id, 'error');
            return new WP_Error('unauthorized_store', 'Store A is not authorized.');
        }
        
        // Auth header format: hash|timestamp
        $parts = explode('|', $auth_header);
        
        if (count($parts) !== 2) {
            PayPal_Proxy_Checkout_Store_B::log('Invalid auth format from Store A: ' . $auth_header, 'error');
            return new WP_Error('invalid_auth_format', 'Invalid authentication format.');
        }
        
        list($received_hash, $timestamp) = $parts;
        
        // Check if the timestamp is recent (within 5 minutes)
        $now = time();
        if ($now - intval($timestamp) > 300) {
            PayPal_Proxy_Checkout_Store_B::log('Expired auth from Store A: ' . $timestamp, 'error');
            return new WP_Error('expired_auth', 'Authentication has expired.');
        }
        
        // Generate expected hash
        $expected_hash = hash_hmac('sha256', $store_a_id . '|' . $timestamp, $store_a['token_key']);
        
        // Verify hash
        if (!hash_equals($expected_hash, $received_hash)) {
            PayPal_Proxy_Checkout_Store_B::log('Invalid auth hash from Store A', 'error');
            return new WP_Error('invalid_auth', 'Invalid authentication.');
        }
        
        return true;
    }
    
    /**
     * Generate an authentication token for Store A.
     * 
     * @param string $store_a_id Store A ID.
     * @return string|WP_Error Authentication token or WP_Error.
     */
    public static function generate_store_a_auth($store_a_id) {
        // Get settings
        $settings = PayPal_Proxy_Checkout_Store_B::get_settings();
        $allowed_stores = $settings['allowed_store_a'];
        
        // Find the Store A in allowed stores
        $store_a = null;
        foreach ($allowed_stores as $store) {
            if ($store['id'] === $store_a_id) {
                $store_a = $store;
                break;
            }
        }
        
        if (empty($store_a)) {
            return new WP_Error('store_not_found', 'Store A not found.');
        }
        
        $timestamp = time();
        $hash = hash_hmac('sha256', $store_a_id . '|' . $timestamp, $store_a['token_key']);
        
        return $hash . '|' . $timestamp;
    }
    
    /**
     * Verify PayPal webhook signature.
     * 
     * @param string $payload The webhook payload.
     * @param array $headers The request headers.
     * @return bool Whether the webhook is valid.
     */
    public static function verify_paypal_webhook($payload, $headers) {
        $settings = PayPal_Proxy_Checkout_Store_B::get_settings();
        
        // Get webhook ID
        $webhook_id = $settings['paypal_webhook_id'];
        
        if (empty($webhook_id)) {
            PayPal_Proxy_Checkout_Store_B::log('PayPal webhook ID not configured', 'error');
            return false;
        }
        
        // Check for required headers
        if (!isset($headers['paypal-auth-algo']) || !isset($headers['paypal-cert-url']) || !isset($headers['paypal-transmission-id']) || !isset($headers['paypal-transmission-sig']) || !isset($headers['paypal-transmission-time'])) {
            PayPal_Proxy_Checkout_Store_B::log('Missing PayPal webhook headers', 'error');
            return false;
        }
        
        // Initialize PayPal REST SDK (this is simplified, in production you would use PayPal's PHP SDK)
        $api_url = $settings['paypal_mode'] === 'live' 
            ? 'https://api-m.paypal.com/v1/notifications/verify-webhook-signature' 
            : 'https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature';
        
        // Get an auth token
        $auth_token = self::get_paypal_auth_token();
        
        if (is_wp_error($auth_token)) {
            PayPal_Proxy_Checkout_Store_B::log('Failed to get PayPal auth token: ' . $auth_token->get_error_message(), 'error');
            return false;
        }
        
        // Prepare the request body
        $body = array(
            'auth_algo' => $headers['paypal-auth-algo'],
            'cert_url' => $headers['paypal-cert-url'],
            'transmission_id' => $headers['paypal-transmission-id'],
            'transmission_sig' => $headers['paypal-transmission-sig'],
            'transmission_time' => $headers['paypal-transmission-time'],
            'webhook_id' => $webhook_id,
            'webhook_event' => json_decode($payload, true)
        );
        
        // Make the request
        $response = wp_remote_post($api_url, array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.1',
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $auth_token,
            ),
            'body' => json_encode($body),
        ));
        
        if (is_wp_error($response)) {
            PayPal_Proxy_Checkout_Store_B::log('PayPal webhook verification error: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['verification_status']) && $body['verification_status'] === 'SUCCESS') {
            return true;
        }
        
        PayPal_Proxy_Checkout_Store_B::log('PayPal webhook verification failed: ' . json_encode($body), 'error');
        return false;
    }
    
    /**
     * Get PayPal authentication token.
     * 
     * @return string|WP_Error Access token or WP_Error.
     */
    public static function get_paypal_auth_token() {
        $settings = PayPal_Proxy_Checkout_Store_B::get_settings();
        $client_id = $settings['paypal_client_id'];
        $client_secret = $settings['paypal_client_secret'];
        
        if (empty($client_id) || empty($client_secret)) {
            return new WP_Error('missing_credentials', 'PayPal API credentials are missing.');
        }
        
        $api_url = $settings['paypal_mode'] === 'live' 
            ? 'https://api-m.paypal.com/v1/oauth2/token' 
            : 'https://api-m.sandbox.paypal.com/v1/oauth2/token';
        
        $response = wp_remote_post($api_url, array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.1',
            'headers' => array(
                'Accept' => 'application/json',
                'Accept-Language' => 'en_US',
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
            ),
            'body' => 'grant_type=client_credentials',
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('paypal_api_error', $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return new WP_Error('paypal_auth_error', $body['error_description']);
        }
        
        if (isset($body['access_token'])) {
            return $body['access_token'];
        }
        
        return new WP_Error('unknown_response', 'Unknown response from PayPal.');
    }
    
    /**
     * Add security headers to proxied responses.
     * 
     * @return void
     */
    public static function add_security_headers() {
        // Add headers to prevent referrer information from being passed
        header('Referrer-Policy: no-referrer');
        
        // Add Content Security Policy headers to restrict origins
        header("Content-Security-Policy: default-src 'self' https://www.paypal.com https://*.paypal.com; script-src 'self' 'unsafe-inline' https://www.paypal.com https://*.paypal.com; frame-src 'self' https://www.paypal.com https://*.paypal.com;");
        
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Enable XSS protection
        header('X-XSS-Protection: 1; mode=block');
    }
}