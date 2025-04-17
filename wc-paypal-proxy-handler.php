<?php
/**
 * Plugin Name: WooCommerce PayPal Proxy Handler
 * Plugin URI: https://example.com/wc-paypal-proxy-handler
 * Description: Handles PayPal payments as a proxy for other WooCommerce sites.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: wc-paypal-proxy-handler
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 8.0.0
 *
 * @package WC_PayPal_Proxy_Handler
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('WC_PAYPAL_PROXY_HANDLER_VERSION', '1.0.0');
define('WC_PAYPAL_PROXY_HANDLER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_PAYPAL_PROXY_HANDLER_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Check if WooCommerce is active
 */
function wc_paypal_proxy_handler_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'wc_paypal_proxy_handler_woocommerce_missing_notice');
        return false;
    }
    return true;
}

/**
 * WooCommerce missing notice
 */
function wc_paypal_proxy_handler_woocommerce_missing_notice() {
    echo '<div class="error"><p><strong>' . 
         sprintf(esc_html__('PayPal Proxy Handler requires WooCommerce to be installed and active. You can download %s here.', 'wc-paypal-proxy-handler'), 
         '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>') . 
         '</strong></p></div>';
}

/**
 * Check for PayPal SDK
 */
function wc_paypal_proxy_handler_check_paypal_sdk() {
    // Check if the PayPal SDK is available
    $composer_autoload = WC_PAYPAL_PROXY_HANDLER_PLUGIN_DIR . 'vendor/autoload.php';
    
    if (!file_exists($composer_autoload)) {
        add_action('admin_notices', 'wc_paypal_proxy_handler_paypal_sdk_missing_notice');
        return false;
    }
    
    // Load the Composer autoloader
    require_once $composer_autoload;
    
    // Check for specific PayPal classes
    if (!class_exists('\\PayPalCheckoutSdk\\Core\\PayPalHttpClient')) {
        add_action('admin_notices', 'wc_paypal_proxy_handler_paypal_sdk_missing_notice');
        return false;
    }
    
    return true;
}

/**
 * PayPal SDK missing notice
 */
function wc_paypal_proxy_handler_paypal_sdk_missing_notice() {
    echo '<div class="error"><p><strong>' . 
         esc_html__('PayPal Proxy Handler requires the PayPal Checkout SDK. Please run "composer require paypal/paypal-checkout-sdk" in the plugin directory.', 'wc-paypal-proxy-handler') . 
         '</strong></p></div>';
}

/**
 * Initialize the plugin
 */
function wc_paypal_proxy_handler_init() {
    // Check if WooCommerce is active
    if (!wc_paypal_proxy_handler_check_woocommerce()) {
        return;
    }
    
    // Check for PayPal SDK
    if (!wc_paypal_proxy_handler_check_paypal_sdk()) {
        return;
    }
    
    // Include required files
    require_once WC_PAYPAL_PROXY_HANDLER_PLUGIN_DIR . 'includes/class-wc-paypal-proxy-handler.php';
    require_once WC_PAYPAL_PROXY_HANDLER_PLUGIN_DIR . 'includes/class-wc-paypal-sdk-integration.php';
    require_once WC_PAYPAL_PROXY_HANDLER_PLUGIN_DIR . 'includes/class-wc-paypal-proxy-security.php';
    
    // Initialize the handler
    new WC_PayPal_Proxy_Handler();
    
    // Load plugin text domain
    add_action('init', 'wc_paypal_proxy_handler_load_textdomain');
}
add_action('plugins_loaded', 'wc_paypal_proxy_handler_init');

/**
 * Load plugin text domain
 */
function wc_paypal_proxy_handler_load_textdomain() {
    load_plugin_textdomain('wc-paypal-proxy-handler', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

/**
 * Add settings link to plugin page
 */
function wc_paypal_proxy_handler_plugin_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=paypal_proxy_handler') . '">' . __('Settings', 'wc-paypal-proxy-handler') . '</a>',
    );
    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wc_paypal_proxy_handler_plugin_links');

/**
 * Register admin page
 */
function wc_paypal_proxy_handler_admin_page() {
    add_options_page(
        __('PayPal Proxy Handler', 'wc-paypal-proxy-handler'),
        __('PayPal Proxy', 'wc-paypal-proxy-handler'),
        'manage_options',
        'paypal-proxy-handler',
        'wc_paypal_proxy_handler_admin_page_content'
    );
}
add_action('admin_menu', 'wc_paypal_proxy_handler_admin_page');

/**
 * Admin page content
 */
function wc_paypal_proxy_handler_admin_page_content() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Save settings
    if (isset($_POST['wc_paypal_proxy_handler_settings_nonce']) && 
        wp_verify_nonce(wp_unslash($_POST['wc_paypal_proxy_handler_settings_nonce']), 'wc_paypal_proxy_handler_settings')) {
        
        $settings = array(
            'paypal_client_id'       => sanitize_text_field($_POST['paypal_client_id'] ?? ''),
            'paypal_client_secret'   => sanitize_text_field($_POST['paypal_client_secret'] ?? ''),
            'paypal_sandbox'         => isset($_POST['paypal_sandbox']) ? 'yes' : 'no',
            'allowed_domains'        => sanitize_textarea_field($_POST['allowed_domains'] ?? ''),
            'api_keys'               => array(),
        );
        
        // Process API keys
        if (isset($_POST['api_key_domain']) && is_array($_POST['api_key_domain'])) {
            foreach ($_POST['api_key_domain'] as $index => $domain) {
                if (empty($domain)) {
                    continue;
                }
                
                $key = isset($_POST['api_key_value'][$index]) ? $_POST['api_key_value'][$index] : '';
                
                // Generate a key if empty
                if (empty($key)) {
                    $key = wp_generate_password(32, false);
                }
                
                $settings['api_keys'][sanitize_text_field($domain)] = sanitize_text_field($key);
            }
        }
        
        update_option('wc_paypal_proxy_handler_settings', $settings);
        echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully.', 'wc-paypal-proxy-handler') . '</p></div>';
    }
    
    // Get current settings
    $settings = get_option('wc_paypal_proxy_handler_settings', array(
        'paypal_client_id'     => '',
        'paypal_client_secret' => '',
        'paypal_sandbox'       => 'yes',
        'allowed_domains'      => '',
        'api_keys'             => array(),
    ));
    
    // Admin template
    include WC_PAYPAL_PROXY_HANDLER_PLUGIN_DIR . 'templates/admin-settings.php';
}

// Register activation hook
register_activation_hook(__FILE__, 'wc_paypal_proxy_handler_activate');

/**
 * Plugin activation
 */
function wc_paypal_proxy_handler_activate() {
    // Check requirements
    if (!wc_paypal_proxy_handler_check_woocommerce()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('PayPal Proxy Handler requires WooCommerce to be installed and active.', 'wc-paypal-proxy-handler'),
            'Plugin Activation Error',
            array('back_link' => true)
        );
    }
}