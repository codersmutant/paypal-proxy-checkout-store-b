<?php
/**
 * Plugin Name: PayPal Proxy Checkout - Store B
 * Plugin URI: https://example.com/paypal-proxy-checkout-store-b
 * Description: Handles PayPal payments on behalf of external stores securely (Store B component).
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: paypal-proxy-checkout-store-b
 * Domain Path: /languages
 * WC requires at least: 5.0.0
 * WC tested up to: 8.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('PPCB_VERSION', '1.0.0');
define('PPCB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PPCB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PPCB_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * The code that runs during plugin activation.
 */
function activate_paypal_proxy_checkout_store_b() {
    // Verify WooCommerce is active
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('PayPal Proxy Checkout - Store B requires WooCommerce to be installed and active.', 'paypal-proxy-checkout-store-b'));
    }
    
    // Create necessary database tables if any
    // Add any custom capabilities
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'activate_paypal_proxy_checkout_store_b');

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_paypal_proxy_checkout_store_b() {
    // Clean up any temporary data
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'deactivate_paypal_proxy_checkout_store_b');

/**
 * Load plugin textdomain.
 */
function ppcb_load_textdomain() {
    load_plugin_textdomain('paypal-proxy-checkout-store-b', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'ppcb_load_textdomain');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once PPCB_PLUGIN_DIR . 'includes/class-paypal-proxy-checkout-store-b.php';

/**
 * Begins execution of the plugin.
 */
function run_paypal_proxy_checkout_store_b() {
    // Initialize the main plugin class
    $plugin = new PayPal_Proxy_Checkout_Store_B();
    $plugin->run();
}

// Initialize the plugin after WooCommerce has loaded
add_action('woocommerce_init', 'run_paypal_proxy_checkout_store_b');