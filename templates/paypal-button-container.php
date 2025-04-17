<?php
/**
 * PayPal Button Container Template
 *
 * @package WC_PayPal_Proxy_Handler
 */

defined('ABSPATH') || exit;

/**
 * This template is for reference only and is not directly used.
 * The actual output is generated dynamically in the handle_checkout_request method.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e('PayPal Checkout', 'wc-paypal-proxy-handler'); ?></title>
    <?php wp_head(); ?>
</head>
<body>
    <div class="paypal-button-container" id="paypal-button-container"></div>
    
    <script type="text/javascript">
        var wc_paypal_proxy_data = <?php echo wp_json_encode($data); ?>;
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>