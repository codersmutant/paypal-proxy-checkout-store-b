<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 * @package    PayPal_Proxy_Checkout_Store_B
 */

if (!defined('WPINC')) {
    die;
}

class PayPal_Proxy_Checkout_Store_B_Admin {

    /**
     * Initialize the class.
     *
     * @since    1.0.0
     */
    public function __construct() {
    }

    /**
     * Add the options page to the WooCommerce settings menu.
     *
     * @since    1.0.0
     */
    public function add_menu_page() {
        add_submenu_page(
            'woocommerce',
            __('PayPal Proxy Checkout', 'paypal-proxy-checkout-store-b'),
            __('PayPal Proxy', 'paypal-proxy-checkout-store-b'),
            'manage_woocommerce',
            'paypal-proxy-checkout-store-b',
            array($this, 'display_settings_page')
        );
        
        // Add logs page
        add_submenu_page(
            'woocommerce',
            __('PayPal Proxy Logs', 'paypal-proxy-checkout-store-b'),
            __('PayPal Proxy Logs', 'paypal-proxy-checkout-store-b'),
            'manage_woocommerce',
            'paypal-proxy-checkout-store-b-logs',
            array($this, 'display_logs_page')
        );
    }

    /**
     * Display the settings page content.
     *
     * @since    1.0.0
     */
    public function display_settings_page() {
        require_once PPCB_PLUGIN_DIR . 'templates/admin/settings-page.php';
    }
    
    /**
     * Display the logs page content.
     *
     * @since    1.0.0
     */
    public function display_logs_page() {
        require_once PPCB_PLUGIN_DIR . 'templates/admin/logs-page.php';
    }

    /**
     * Register and enqueue admin-specific scripts and styles.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        $screen = get_current_screen();
        if ($screen->id !== 'woocommerce_page_paypal-proxy-checkout-store-b' && 
            $screen->id !== 'woocommerce_page_paypal-proxy-checkout-store-b-logs') {
            return;
        }

        // CSS
        wp_enqueue_style(
            'paypal-proxy-checkout-store-b-admin',
            PPCB_PLUGIN_URL . 'assets/css/admin-style.css',
            array(),
            PPCB_VERSION
        );

        // JavaScript
        wp_enqueue_script(
            'paypal-proxy-checkout-store-b-admin',
            PPCB_PLUGIN_URL . 'assets/js/admin-script.js',
            array('jquery'),
            PPCB_VERSION,
            true
        );

        // Localize script with settings and nonce
        wp_localize_script(
            'paypal-proxy-checkout-store-b-admin',
            'ppcbAdmin',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('paypal_proxy_checkout_store_b_nonce'),
                'settings' => PayPal_Proxy_Checkout_Store_B::get_settings(),
            )
        );
    }

    /**
     * Register plugin settings.
     *
     * @since    1.0.0
     */
    public function register_settings() {
        register_setting(
            'paypal_proxy_checkout_store_b_settings',
            'paypal_proxy_checkout_store_b_settings',
            array($this, 'sanitize_settings')
        );
    }

    /**
     * Sanitize settings before saving.
     *
     * @since    1.0.0
     * @param    array    $input    The input array to sanitize.
     * @return   array              The sanitized input.
     */
    public function sanitize_settings($input) {
        $sanitized_input = array();

        if (isset($input['paypal_mode'])) {
            $sanitized_input['paypal_mode'] = ($input['paypal_mode'] === 'live') ? 'live' : 'sandbox';
        }

        if (isset($input['paypal_client_id'])) {
            $sanitized_input['paypal_client_id'] = sanitize_text_field($input['paypal_client_id']);
        }

        if (isset($input['paypal_client_secret'])) {
            // Don't sanitize the client secret as it may contain special characters
            $sanitized_input['paypal_client_secret'] = $input['paypal_client_secret'];
        }

        if (isset($input['paypal_webhook_id'])) {
            $sanitized_input['paypal_webhook_id'] = sanitize_text_field($input['paypal_webhook_id']);
        }

        if (isset($input['log_level'])) {
            $sanitized_input['log_level'] = in_array($input['log_level'], array('debug', 'info', 'error')) ? $input['log_level'] : 'error';
        }

        if (isset($input['allowed_store_a']) && is_array($input['allowed_store_a'])) {
            $sanitized_input['allowed_store_a'] = array();
            foreach ($input['allowed_store_a'] as $store) {
                if (isset($store['id']) && isset($store['url']) && isset($store['token_key'])) {
                    $sanitized_input['allowed_store_a'][] = array(
                        'id' => sanitize_text_field($store['id']),
                        'url' => esc_url_raw($store['url']),
                        'token_key' => sanitize_text_field($store['token_key']),
                        'label' => isset($store['label']) ? sanitize_text_field($store['label']) : '',
                    );
                }
            }
        }

        return $sanitized_input;
    }

    /**
     * Add settings link to the plugins page.
     *
     * @since    1.0.0
     * @param    array    $links    Plugin action links.
     * @return   array              Modified plugin action links.
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=paypal-proxy-checkout-store-b') . '">' . __('Settings', 'paypal-proxy-checkout-store-b') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Add meta box to orders to show proxy information.
     */
    public function add_order_meta_box() {
        add_meta_box(
            'ppcb_order_meta_box',
            __('PayPal Proxy Information', 'paypal-proxy-checkout-store-b'),
            array($this, 'render_order_meta_box'),
            'shop_order',
            'side',
            'default'
        );
    }
    
    /**
     * Render the order meta box.
     * 
     * @param WP_Post $post Post object.
     */
    public function render_order_meta_box($post) {
        $order = wc_get_order($post->ID);
        
        // Get proxy data
        $store_a_order_id = $order->get_meta('_store_a_order_id');
        $store_a_id = $order->get_meta('_store_a_id');
        $paypal_order_id = $order->get_meta('_paypal_order_id');
        $paypal_status = $order->get_meta('_paypal_status');
        
        // Only show if this is a proxy order
        if (empty($store_a_order_id) || empty($store_a_id)) {
            echo '<p>' . __('This is not a proxy order.', 'paypal-proxy-checkout-store-b') . '</p>';
            return;
        }
        
        // Get Store A info
        $store_a_label = $this->get_store_a_label($store_a_id);
        
        ?>
        <div class="ppcb-meta-box">
            <p><strong><?php _e('Store A:', 'paypal-proxy-checkout-store-b'); ?></strong> <?php echo esc_html($store_a_label); ?></p>
            <p><strong><?php _e('Store A Order ID:', 'paypal-proxy-checkout-store-b'); ?></strong> <?php echo esc_html($store_a_order_id); ?></p>
            <?php if (!empty($paypal_order_id)) : ?>
                <p><strong><?php _e('PayPal Order ID:', 'paypal-proxy-checkout-store-b'); ?></strong> <?php echo esc_html($paypal_order_id); ?></p>
            <?php endif; ?>
            <?php if (!empty($paypal_status)) : ?>
                <p><strong><?php _e('PayPal Status:', 'paypal-proxy-checkout-store-b'); ?></strong> <?php echo esc_html($paypal_status); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Get Store A label based on ID.
     * 
     * @param string $store_a_id Store A ID.
     * @return string Store A label or ID if not found.
     */
    private function get_store_a_label($store_a_id) {
        $settings = PayPal_Proxy_Checkout_Store_B::get_settings();
        $allowed_stores = $settings['allowed_store_a'];
        
        foreach ($allowed_stores as $store) {
            if ($store['id'] === $store_a_id && !empty($store['label'])) {
                return $store['label'];
            }
        }
        
        return $store_a_id;
    }
    
    /**
     * Handle AJAX request to clear logs.
     */
    public static function clear_logs() {
        // Check for nonce security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'paypal_proxy_checkout_store_b_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'paypal-proxy-checkout-store-b')));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'paypal-proxy-checkout-store-b')));
        }
        
        // Clear logs
        update_option('paypal_proxy_checkout_store_b_logs', array());
        
        wp_send_json_success(array('message' => __('Logs cleared successfully.', 'paypal-proxy-checkout-store-b')));
    }
    
    /**
     * Handle AJAX request to test PayPal credentials.
     */
    public static function test_paypal_credentials() {
        // Check for nonce security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'paypal_proxy_checkout_store_b_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'paypal-proxy-checkout-store-b')));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'paypal-proxy-checkout-store-b')));
        }
        
        // Get settings
        $settings = PayPal_Proxy_Checkout_Store_B::get_settings();
        $mode = $_POST['mode'] === 'live' ? 'live' : 'sandbox';
        $client_id = sanitize_text_field($_POST['client_id']);
        $client_secret = $_POST['client_secret']; // Don't sanitize as it may contain special characters
        
        // Test the credentials by getting an auth token
        $api_url = $mode === 'live' 
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
            PayPal_Proxy_Checkout_Store_B::log('PayPal API error: ' . $response->get_error_message(), 'error');
            wp_send_json_error(array('message' => __('Failed to connect to PayPal: ', 'paypal-proxy-checkout-store-b') . $response->get_error_message()));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            PayPal_Proxy_Checkout_Store_B::log('PayPal auth error: ' . $body['error_description'], 'error');
            wp_send_json_error(array('message' => __('PayPal authentication failed: ', 'paypal-proxy-checkout-store-b') . $body['error_description']));
        }
        
        if (isset($body['access_token'])) {
            PayPal_Proxy_Checkout_Store_B::log('PayPal credentials verified successfully', 'info');
            wp_send_json_success(array('message' => __('PayPal credentials verified successfully!', 'paypal-proxy-checkout-store-b')));
        }
        
        wp_send_json_error(array('message' => __('Unknown response from PayPal.', 'paypal-proxy-checkout-store-b')));
    }
}

// Register AJAX handlers
add_action('wp_ajax_ppcb_clear_logs', array('PayPal_Proxy_Checkout_Store_B_Admin', 'clear_logs'));
add_action('wp_ajax_ppcb_test_paypal_credentials', array('PayPal_Proxy_Checkout_Store_B_Admin', 'test_paypal_credentials'));