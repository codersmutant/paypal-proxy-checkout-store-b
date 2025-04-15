<?php
/**
 * The checkout iframe template.
 *
 * @since      1.0.0
 * @package    PayPal_Proxy_Checkout_Store_B
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get settings
$settings = PayPal_Proxy_Checkout_Store_B::get_settings();

// Add security headers
PayPal_Proxy_Checkout_Store_B_Security::add_security_headers();

// Get parameters
$store_a_id = isset($_GET['store_a_id']) ? sanitize_text_field($_GET['store_a_id']) : '';
$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;
$currency = isset($_GET['currency']) ? sanitize_text_field($_GET['currency']) : 'USD';
$return_url = isset($_GET['return_url']) ? esc_url_raw($_GET['return_url']) : '';
$cancel_url = isset($_GET['cancel_url']) ? esc_url_raw($_GET['cancel_url']) : '';

// Verify Store A
$allowed_stores = $settings['allowed_store_a'];
$store_a = null;
foreach ($allowed_stores as $store) {
    if ($store['id'] === $store_a_id) {
        $store_a = $store;
        break;
    }
}

if (!$store_a) {
    wp_die(__('Unauthorized Store A.', 'paypal-proxy-checkout-store-b'));
}

// PayPal environment
$paypal_mode = $settings['paypal_mode'];
$client_id = $settings['paypal_client_id'];

// Generate unique container ID
$container_id = 'ppcb-paypal-button-' . uniqid();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html__('PayPal Checkout', 'paypal-proxy-checkout-store-b'); ?></title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            background-color: transparent;
            margin: 0;
            padding: 0;
        }
        
        .ppcb-container {
            width: 100%;
            max-width: 750px;
            margin: 0 auto;
            padding: 20px;
            box-sizing: border-box;
        }
        
        .ppcb-paypal-button {
            margin: 20px 0;
            min-height: 55px;
        }
        
        .ppcb-amount {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .ppcb-currency {
            font-size: 14px;
            margin-left: 5px;
        }
        
        .ppcb-loading {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100px;
        }
        
        .ppcb-spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top: 4px solid #3498db;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .ppcb-error {
            color: #e74c3c;
            background-color: #fceaea;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: none;
        }
        
        .ppcb-success {
            color: #27ae60;
            background-color: #e8f8f5;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="ppcb-container">
        <div class="ppcb-amount">
            <?php echo esc_html(number_format($amount, 2)); ?> <span class="ppcb-currency"><?php echo esc_html($currency); ?></span>
        </div>
        
        <div class="ppcb-error" id="ppcb-error"></div>
        <div class="ppcb-success" id="ppcb-success"></div>
        
        <div id="<?php echo esc_attr($container_id); ?>" class="ppcb-paypal-button"></div>
        
        <div class="ppcb-loading" id="ppcb-loading">
            <div class="ppcb-spinner"></div>
        </div>
    </div>
    
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo esc_attr($client_id); ?>&currency=<?php echo esc_attr($currency); ?>&intent=capture"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var container = document.getElementById('<?php echo esc_js($container_id); ?>');
            var loadingEl = document.getElementById('ppcb-loading');
            var errorEl = document.getElementById('ppcb-error');
            var successEl = document.getElementById('ppcb-success');
            
            // Hide loading indicator
            loadingEl.style.display = 'none';
            
            // Initialize PayPal buttons
            paypal.Buttons({
                style: {
                    layout: 'vertical',
                    color: 'blue',
                    shape: 'rect',
                    label: 'paypal'
                },
                
                // Create order on PayPal
                createOrder: function() {
                    loadingEl.style.display = 'flex';
                    errorEl.style.display = 'none';
                    
                    return fetch('<?php echo esc_js(rest_url('paypal-proxy/v1/create-order')); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Paypal-Proxy-Auth': '<?php echo esc_js(PayPal_Proxy_Checkout_Store_B_Security::generate_store_a_auth($store_a_id)); ?>',
                        },
                        body: JSON.stringify({
                            store_a_id: '<?php echo esc_js($store_a_id); ?>',
                            store_a_order_id: '<?php echo esc_js(isset($_GET['order_id']) ? sanitize_text_field($_GET['order_id']) : ''); ?>',
                            currency: '<?php echo esc_js($currency); ?>',
                            total: <?php echo json_encode($amount); ?>,
                            items: [
                                {
                                    name: '<?php echo esc_js(isset($_GET['item_name']) ? sanitize_text_field($_GET['item_name']) : __('Order Payment', 'paypal-proxy-checkout-store-b')); ?>',
                                    quantity: 1,
                                    price: <?php echo json_encode($amount); ?>,
                                    store_b_product_id: <?php echo json_encode(isset($_GET['product_id']) ? absint($_GET['product_id']) : 0); ?>
                                }
                            ],
                            return_url: '<?php echo esc_js($return_url); ?>',
                            cancel_url: '<?php echo esc_js($cancel_url); ?>',
                            customer: <?php echo json_encode(isset($_GET['customer']) ? json_decode(base64_decode($_GET['customer']), true) : array()); ?>
                        })
                    })
                    .then(function(response) {
                        loadingEl.style.display = 'none';
                        
                        if (!response.ok) {
                            throw new Error('<?php echo esc_js(__('Network response was not ok', 'paypal-proxy-checkout-store-b')); ?>');
                        }
                        
                        return response.json();
                    })
                    .then(function(orderData) {
                        if (!orderData.success || !orderData.paypal_order_id) {
                            throw new Error(orderData.message || '<?php echo esc_js(__('Failed to create PayPal order', 'paypal-proxy-checkout-store-b')); ?>');
                        }
                        
                        return orderData.paypal_order_id;
                    })
                    .catch(function(error) {
                        loadingEl.style.display = 'none';
                        errorEl.textContent = error.message;
                        errorEl.style.display = 'block';
                        console.error('Error:', error);
                        throw error;
                    });
                },
                
                // Handle approval
                onApprove: function(data, actions) {
                    loadingEl.style.display = 'flex';
                    
                    // Display success message
                    successEl.textContent = '<?php echo esc_js(__('Payment approved! Processing...', 'paypal-proxy-checkout-store-b')); ?>';
                    successEl.style.display = 'block';
                    
                    // Redirect to return URL with order ID
                    var redirectUrl = '<?php echo esc_js($return_url); ?>';
                    if (redirectUrl.indexOf('?') > -1) {
                        redirectUrl += '&';
                    } else {
                        redirectUrl += '?';
                    }
                    redirectUrl += 'paypal_order_id=' + encodeURIComponent(data.orderID);
                    
                    // Redirect after a short delay
                    setTimeout(function() {
                        window.top.location.href = redirectUrl;
                    }, 1500);
                },
                
                // Handle cancelation
                onCancel: function() {
                    // Redirect to cancel URL
                    window.top.location.href = '<?php echo esc_js($cancel_url); ?>';
                },
                
                // Handle errors
                onError: function(err) {
                    loadingEl.style.display = 'none';
                    errorEl.textContent = '<?php echo esc_js(__('PayPal checkout error:', 'paypal-proxy-checkout-store-b')); ?> ' + err;
                    errorEl.style.display = 'block';
                    console.error('PayPal Error:', err);
                }
            }).render('#<?php echo esc_js($container_id); ?>');
        });
    </script>
</body>
</html>