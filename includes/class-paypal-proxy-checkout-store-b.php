<?php
/**
 * The core plugin class.
 *
 * @since      1.0.0
 * @package    PayPal_Proxy_Checkout_Store_B
 */

if (!defined('WPINC')) {
    die;
}

class PayPal_Proxy_Checkout_Store_B {

    /**
     * The loader that's responsible for maintaining and registering all hooks.
     *
     * @since    1.0.0
     * @access   protected
     * @var      array    $loader    The actions and filters registered in the plugin.
     */
    protected $loader = array();

    /**
     * Define the core functionality of the plugin.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_api_hooks();
        $this->define_paypal_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        // Admin class
        require_once PPCB_PLUGIN_DIR . 'includes/class-paypal-proxy-checkout-store-b-admin.php';
        
        // API class
        require_once PPCB_PLUGIN_DIR . 'includes/class-paypal-proxy-checkout-store-b-api.php';
        
        // PayPal integration class
        require_once PPCB_PLUGIN_DIR . 'includes/class-paypal-proxy-checkout-store-b-paypal.php';
        
        // Security class
        require_once PPCB_PLUGIN_DIR . 'includes/class-paypal-proxy-checkout-store-b-security.php';
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        $admin = new PayPal_Proxy_Checkout_Store_B_Admin();
        
        // Admin menu
        $this->loader['admin_menu'] = array(
            'hook' => 'admin_menu',
            'callback' => array($admin, 'add_menu_page'),
        );
        
        // Admin scripts
        $this->loader['admin_enqueue_scripts'] = array(
            'hook' => 'admin_enqueue_scripts',
            'callback' => array($admin, 'enqueue_scripts'),
        );
        
        // Admin settings
        $this->loader['admin_init'] = array(
            'hook' => 'admin_init',
            'callback' => array($admin, 'register_settings'),
        );
        
        // Add settings link on plugin page
        $this->loader['plugin_action_links'] = array(
            'hook' => 'plugin_action_links_' . PPCB_PLUGIN_BASENAME,
            'callback' => array($admin, 'add_settings_link'),
        );
        
        // Add meta box to orders to show proxy info
        $this->loader['add_meta_boxes'] = array(
            'hook' => 'add_meta_boxes',
            'callback' => array($admin, 'add_order_meta_box'),
        );
    }

    /**
     * Register all of the hooks related to the API functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_api_hooks() {
        $api = new PayPal_Proxy_Checkout_Store_B_API();
        
        // Register API endpoints
        $this->loader['rest_api_init'] = array(
            'hook' => 'rest_api_init',
            'callback' => array($api, 'register_endpoints'),
        );
    }

    /**
     * Register all of the hooks related to PayPal integration.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_paypal_hooks() {
        $paypal = new PayPal_Proxy_Checkout_Store_B_PayPal();
        
        // Initialize PayPal SDK
        $this->loader['init'] = array(
            'hook' => 'init',
            'callback' => array($paypal, 'init_paypal_sdk'),
        );
        
        // Handle PayPal IPN (Instant Payment Notification)
        $this->loader['woocommerce_api_paypal_ipn'] = array(
            'hook' => 'woocommerce_api_paypal_ipn',
            'callback' => array($paypal, 'handle_ipn'),
        );
        
        // Handle PayPal webhook
        $this->loader['woocommerce_api_paypal_webhook'] = array(
            'hook' => 'woocommerce_api_paypal_webhook',
            'callback' => array($paypal, 'handle_webhook'),
        );
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        foreach ($this->loader as $hook) {
            $priority = isset($hook['priority']) ? $hook['priority'] : 10;
            $args = isset($hook['args']) ? $hook['args'] : 1;
            add_action($hook['hook'], $hook['callback'], $priority, $args);
        }
    }

    /**
     * Retrieve the plugin settings
     *
     * @since     1.0.0
     * @return    array    The plugin settings.
     */
    public static function get_settings() {
        $default_settings = array(
            'paypal_mode' => 'sandbox', // 'sandbox' or 'live'
            'paypal_client_id' => '',
            'paypal_client_secret' => '',
            'paypal_webhook_id' => '',
            'allowed_store_a' => array(), // array of allowed Store A details
            'log_level' => 'error', // 'debug', 'info', 'error'
        );
        
        $settings = get_option('paypal_proxy_checkout_store_b_settings', $default_settings);
        return wp_parse_args($settings, $default_settings);
    }

    /**
     * Update a specific setting
     *
     * @since     1.0.0
     * @param     string    $key     Setting key.
     * @param     mixed     $value   Setting value.
     * @return    boolean            Whether the setting was updated.
     */
    public static function update_setting($key, $value) {
        $settings = self::get_settings();
        $settings[$key] = $value;
        return update_option('paypal_proxy_checkout_store_b_settings', $settings);
    }

    /**
     * Log an event or error
     *
     * @since     1.0.0
     * @param     string    $message   Message to log
     * @param     string    $level     Log level (debug, info, error)
     * @param     array     $context   Additional context data
     */
    public static function log($message, $level = 'info', $context = array()) {
        $settings = self::get_settings();
        $log_levels = array(
            'debug' => 0,
            'info' => 1,
            'error' => 2
        );
        
        // Only log if the level is high enough
        if ($log_levels[$level] >= $log_levels[$settings['log_level']]) {
            $log_entry = array(
                'time' => current_time('mysql'),
                'level' => $level,
                'message' => $message,
                'context' => $context
            );
            
            // Get existing logs
            $logs = get_option('paypal_proxy_checkout_store_b_logs', array());
            
            // Add new log entry
            array_unshift($logs, $log_entry);
            
            // Keep only the latest 1000 logs
            if (count($logs) > 1000) {
                $logs = array_slice($logs, 0, 1000);
            }
            
            // Save logs
            update_option('paypal_proxy_checkout_store_b_logs', $logs);
        }
    }
}