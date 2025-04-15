<?php
/**
 * Admin settings page template.
 *
 * @since      1.0.0
 * @package    PayPal_Proxy_Checkout_Store_B
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get plugin settings
$settings = PayPal_Proxy_Checkout_Store_B::get_settings();
?>

<div class="wrap ppcb-settings-wrap">
    <h1><?php echo esc_html__('PayPal Proxy Checkout Settings (Store B)', 'paypal-proxy-checkout-store-b'); ?></h1>
    
    <form method="post" action="options.php" id="ppcb-settings-form">
        <?php settings_fields('paypal_proxy_checkout_store_b_settings'); ?>
        
        <div class="ppcb-settings-section">
            <h2><?php echo esc_html__('PayPal API Credentials', 'paypal-proxy-checkout-store-b'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="ppcb-paypal-mode"><?php echo esc_html__('Environment', 'paypal-proxy-checkout-store-b'); ?></label>
                    </th>
                    <td>
                        <select id="ppcb-paypal-mode" name="paypal_proxy_checkout_store_b_settings[paypal_mode]">
                            <option value="sandbox" <?php selected($settings['paypal_mode'], 'sandbox'); ?>><?php echo esc_html__('Sandbox (Testing)', 'paypal-proxy-checkout-store-b'); ?></option>
                            <option value="live" <?php selected($settings['paypal_mode'], 'live'); ?>><?php echo esc_html__('Live (Production)', 'paypal-proxy-checkout-store-b'); ?></option>
                        </select>
                        <p class="description"><?php echo esc_html__('Select PayPal environment.', 'paypal-proxy-checkout-store-b'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ppcb-client-id"><?php echo esc_html__('Client ID', 'paypal-proxy-checkout-store-b'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="ppcb-client-id" name="paypal_proxy_checkout_store_b_settings[paypal_client_id]" value="<?php echo esc_attr($settings['paypal_client_id']); ?>" class="regular-text">
                        <p class="description"><?php echo esc_html__('Enter your PayPal Client ID.', 'paypal-proxy-checkout-store-b'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ppcb-client-secret"><?php echo esc_html__('Client Secret', 'paypal-proxy-checkout-store-b'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="ppcb-client-secret" name="paypal_proxy_checkout_store_b_settings[paypal_client_secret]" value="<?php echo esc_attr($settings['paypal_client_secret']); ?>" class="regular-text">
                        <p class="description"><?php echo esc_html__('Enter your PayPal Client Secret.', 'paypal-proxy-checkout-store-b'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ppcb-webhook-id"><?php echo esc_html__('Webhook ID', 'paypal-proxy-checkout-store-b'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="ppcb-webhook-id" name="paypal_proxy_checkout_store_b_settings[paypal_webhook_id]" value="<?php echo esc_attr($settings['paypal_webhook_id']); ?>" class="regular-text">
                        <p class="description"><?php echo esc_html__('Enter your PayPal Webhook ID.', 'paypal-proxy-checkout-store-b'); ?></p>
                        <p class="description"><?php echo esc_html__('Webhook URL:', 'paypal-proxy-checkout-store-b'); ?> <code><?php echo esc_html(home_url('/wc-api/paypal_webhook')); ?></code></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php echo esc_html__('Test Credentials', 'paypal-proxy-checkout-store-b'); ?>
                    </th>
                    <td>
                        <button type="button" id="ppcb-test-credentials" class="button button-secondary"><?php echo esc_html__('Test PayPal API Credentials', 'paypal-proxy-checkout-store-b'); ?></button>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="ppcb-settings-section">
            <h2><?php echo esc_html__('Allowed Store A Connections', 'paypal-proxy-checkout-store-b'); ?></h2>
            <p><?php echo esc_html__('Configure external stores that can use this site as a payment proxy.', 'paypal-proxy-checkout-store-b'); ?></p>
            
            <table class="widefat" id="ppcb-store-a-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Label', 'paypal-proxy-checkout-store-b'); ?></th>
                        <th><?php echo esc_html__('Store ID', 'paypal-proxy-checkout-store-b'); ?></th>
                        <th><?php echo esc_html__('URL', 'paypal-proxy-checkout-store-b'); ?></th>
                        <th><?php echo esc_html__('Token Key', 'paypal-proxy-checkout-store-b'); ?></th>
                        <th><?php echo esc_html__('Actions', 'paypal-proxy-checkout-store-b'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $allowed_stores = isset($settings['allowed_store_a']) ? $settings['allowed_store_a'] : array();
                    
                    if (!empty($allowed_stores)) {
                        foreach ($allowed_stores as $index => $store) {
                            ?>
                            <tr class="ppcb-store-row">
                                <td>
                                    <input type="text" name="paypal_proxy_checkout_store_b_settings[allowed_store_a][<?php echo $index; ?>][label]" value="<?php echo esc_attr($store['label']); ?>" class="ppcb-store-label">
                                </td>
                                <td>
                                    <input type="text" name="paypal_proxy_checkout_store_b_settings[allowed_store_a][<?php echo $index; ?>][id]" value="<?php echo esc_attr($store['id']); ?>" class="ppcb-store-id">
                                </td>
                                <td>
                                    <input type="url" name="paypal_proxy_checkout_store_b_settings[allowed_store_a][<?php echo $index; ?>][url]" value="<?php echo esc_attr($store['url']); ?>" class="ppcb-store-url">
                                </td>
                                <td>
                                    <input type="text" name="paypal_proxy_checkout_store_b_settings[allowed_store_a][<?php echo $index; ?>][token_key]" value="<?php echo esc_attr($store['token_key']); ?>" class="ppcb-store-token">
                                    <button type="button" class="button button-small ppcb-generate-token"><?php echo esc_html__('Generate', 'paypal-proxy-checkout-store-b'); ?></button>
                                </td>
                                <td>
                                    <button type="button" class="button button-secondary ppcb-remove-store"><?php echo esc_html__('Remove', 'paypal-proxy-checkout-store-b'); ?></button>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                    <tr class="ppcb-no-stores <?php echo (!empty($allowed_stores)) ? 'hidden' : ''; ?>">
                        <td colspan="5"><?php echo esc_html__('No stores configured. Click "Add Store" to allow a new store.', 'paypal-proxy-checkout-store-b'); ?></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5">
                            <button type="button" id="ppcb-add-store" class="button button-secondary"><?php echo esc_html__('Add Store', 'paypal-proxy-checkout-store-b'); ?></button>
                        </td>
                    </tr>
                </tfoot>
            </table>
            
            <script type="text/template" id="ppcb-store-template">
                <tr class="ppcb-store-row">
                    <td>
                        <input type="text" name="paypal_proxy_checkout_store_b_settings[allowed_store_a][{{index}}][label]" value="" placeholder="<?php echo esc_attr__('Store Name', 'paypal-proxy-checkout-store-b'); ?>" class="ppcb-store-label">
                    </td>
                    <td>
                        <input type="text" name="paypal_proxy_checkout_store_b_settings[allowed_store_a][{{index}}][id]" value="" placeholder="<?php echo esc_attr__('Unique ID', 'paypal-proxy-checkout-store-b'); ?>" class="ppcb-store-id">
                    </td>
                    <td>
                        <input type="url" name="paypal_proxy_checkout_store_b_settings[allowed_store_a][{{index}}][url]" value="" placeholder="<?php echo esc_attr__('https://example.com', 'paypal-proxy-checkout-store-b'); ?>" class="ppcb-store-url">
                    </td>
                    <td>
                        <input type="text" name="paypal_proxy_checkout_store_b_settings[allowed_store_a][{{index}}][token_key]" value="" placeholder="<?php echo esc_attr__('Shared Secret Key', 'paypal-proxy-checkout-store-b'); ?>" class="ppcb-store-token">
                        <button type="button" class="button button-small ppcb-generate-token"><?php echo esc_html__('Generate', 'paypal-proxy-checkout-store-b'); ?></button>
                    </td>
                    <td>
                        <button type="button" class="button button-secondary ppcb-remove-store"><?php echo esc_html__('Remove', 'paypal-proxy-checkout-store-b'); ?></button>
                    </td>
                </tr>
            </script>
        </div>
        
        <div class="ppcb-settings-section">
            <h2><?php echo esc_html__('Logging Settings', 'paypal-proxy-checkout-store-b'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="ppcb-log-level"><?php echo esc_html__('Log Level', 'paypal-proxy-checkout-store-b'); ?></label>
                    </th>
                    <td>
                        <select id="ppcb-log-level" name="paypal_proxy_checkout_store_b_settings[log_level]">
                            <option value="error" <?php selected($settings['log_level'], 'error'); ?>><?php echo esc_html__('Error Only', 'paypal-proxy-checkout-store-b'); ?></option>
                            <option value="info" <?php selected($settings['log_level'], 'info'); ?>><?php echo esc_html__('Info (Recommended)', 'paypal-proxy-checkout-store-b'); ?></option>
                            <option value="debug" <?php selected($settings['log_level'], 'debug'); ?>><?php echo esc_html__('Debug (Verbose)', 'paypal-proxy-checkout-store-b'); ?></option>
                        </select>
                        <p class="description"><?php echo esc_html__('Select the level of detail for logs.', 'paypal-proxy-checkout-store-b'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php echo esc_html__('View Logs', 'paypal-proxy-checkout-store-b'); ?>
                    </th>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=paypal-proxy-checkout-store-b-logs')); ?>" class="button button-secondary"><?php echo esc_html__('View Logs', 'paypal-proxy-checkout-store-b'); ?></a>
                    </td>
                </tr>
            </table>
        </div>
        
        <?php submit_button(); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Counter for new store rows
    var storeCounter = <?php echo !empty($allowed_stores) ? count($allowed_stores) : 0; ?>;
    
    // Test PayPal credentials
    $('#ppcb-test-credentials').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var mode = $('#ppcb-paypal-mode').val();
        var clientId = $('#ppcb-client-id').val();
        var clientSecret = $('#ppcb-client-secret').val();
        
        if (!clientId || !clientSecret) {
            alert('<?php echo esc_js(__('Please enter both Client ID and Client Secret before testing.', 'paypal-proxy-checkout-store-b')); ?>');
            return;
        }
        
        $button.prop('disabled', true).text('<?php echo esc_js(__('Testing...', 'paypal-proxy-checkout-store-b')); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ppcb_test_paypal_credentials',
                nonce: ppcbAdmin.nonce,
                mode: mode,
                client_id: clientId,
                client_secret: clientSecret
            },
            success: function(response) {
                $button.prop('disabled', false).text('<?php echo esc_js(__('Test PayPal API Credentials', 'paypal-proxy-checkout-store-b')); ?>');
                
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                $button.prop('disabled', false).text('<?php echo esc_js(__('Test PayPal API Credentials', 'paypal-proxy-checkout-store-b')); ?>');
                alert('<?php echo esc_js(__('Error occurred while testing credentials.', 'paypal-proxy-checkout-store-b')); ?>');
            }
        });
    });
    
    // Add Store A
    $('#ppcb-add-store').on('click', function() {
        // Hide the "no stores" message
        $('#ppcb-store-a-table tbody .ppcb-no-stores').addClass('hidden');
        
        // Get the template HTML content and replace the placeholder index
        var templateHTML = $('#ppcb-store-template').html().replace(/\{\{index\}\}/g, storeCounter);
        
        // Append the new row to the table body
        $('#ppcb-store-a-table tbody').append(templateHTML);
        
        // Increment the counter for next row
        storeCounter++;
    });
    
    // Remove Store A
    $(document).on('click', '.ppcb-remove-store', function() {
        $(this).closest('tr').remove();
        
        // Show the "no stores" message if there are no stores
        if ($('#ppcb-store-a-table tbody tr').not('.ppcb-no-stores').length === 0) {
            $('#ppcb-store-a-table tbody .ppcb-no-stores').removeClass('hidden');
        }
    });
    
    // Generate token
    $(document).on('click', '.ppcb-generate-token', function() {
        var tokenField = $(this).siblings('.ppcb-store-token');
        tokenField.val(generateRandomToken(32));
    });
    
    // Generate a random token
    function generateRandomToken(length) {
        var result = '';
        var characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        var charactersLength = characters.length;
        
        for (var i = 0; i < length; i++) {
            result += characters.charAt(Math.floor(Math.random() * charactersLength));
        }
        
        return result;
    }
    
    // Form submission validation
    $('#ppcb-settings-form').on('submit', function(e) {
        var clientId = $('#ppcb-client-id').val();
        var clientSecret = $('#ppcb-client-secret').val();
        
        if (!clientId || !clientSecret) {
            e.preventDefault();
            alert('<?php echo esc_js(__('PayPal Client ID and Client Secret are required.', 'paypal-proxy-checkout-store-b')); ?>');
            return false;
        }
        
        return true;
    });
});
</script>