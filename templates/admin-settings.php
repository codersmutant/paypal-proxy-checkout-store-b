<?php
/**
 * Admin settings template for PayPal Proxy Handler
 *
 * @package WC_PayPal_Proxy_Handler
 */

defined('ABSPATH') || exit;
?>

<div class="wrap">
    <h1><?php esc_html_e('PayPal Proxy Handler Settings', 'wc-paypal-proxy-handler'); ?></h1>
    
    <form method="post">
        <?php wp_nonce_field('wc_paypal_proxy_handler_settings', 'wc_paypal_proxy_handler_settings_nonce'); ?>
        
        <h2><?php esc_html_e('PayPal API Settings', 'wc-paypal-proxy-handler'); ?></h2>
        <p><?php esc_html_e('Enter your PayPal API credentials. These are required to process payments.', 'wc-paypal-proxy-handler'); ?></p>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="paypal_client_id"><?php esc_html_e('PayPal Client ID', 'wc-paypal-proxy-handler'); ?></label>
                </th>
                <td>
                    <input type="text" name="paypal_client_id" id="paypal_client_id" value="<?php echo esc_attr($settings['paypal_client_id']); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('Enter your PayPal Client ID from the PayPal Developer Dashboard.', 'wc-paypal-proxy-handler'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="paypal_client_secret"><?php esc_html_e('PayPal Client Secret', 'wc-paypal-proxy-handler'); ?></label>
                </th>
                <td>
                    <input type="password" name="paypal_client_secret" id="paypal_client_secret" value="<?php echo esc_attr($settings['paypal_client_secret']); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('Enter your PayPal Client Secret from the PayPal Developer Dashboard.', 'wc-paypal-proxy-handler'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="paypal_sandbox"><?php esc_html_e('Sandbox Mode', 'wc-paypal-proxy-handler'); ?></label>
                </th>
                <td>
                    <label for="paypal_sandbox">
                        <input type="checkbox" name="paypal_sandbox" id="paypal_sandbox" <?php checked('yes', $settings['paypal_sandbox']); ?>>
                        <?php esc_html_e('Enable Sandbox Mode', 'wc-paypal-proxy-handler'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Check this box to use PayPal Sandbox for testing.', 'wc-paypal-proxy-handler'); ?></p>
                </td>
            </tr>
        </table>
        
        <h2><?php esc_html_e('Security Settings', 'wc-paypal-proxy-handler'); ?></h2>
        <p><?php esc_html_e('Configure which domains are allowed to send requests to this proxy and their API keys.', 'wc-paypal-proxy-handler'); ?></p>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="allowed_domains"><?php esc_html_e('Allowed Domains', 'wc-paypal-proxy-handler'); ?></label>
                </th>
                <td>
                    <textarea name="allowed_domains" id="allowed_domains" rows="5" class="large-text"><?php echo esc_textarea($settings['allowed_domains']); ?></textarea>
                    <p class="description"><?php esc_html_e('Enter one domain per line. Only requests from these domains will be processed.', 'wc-paypal-proxy-handler'); ?></p>
                </td>
            </tr>
        </table>
        
        <h3><?php esc_html_e('API Keys', 'wc-paypal-proxy-handler'); ?></h3>
        <p><?php esc_html_e('Manage API keys for each domain that will connect to this proxy.', 'wc-paypal-proxy-handler'); ?></p>
        
        <table class="widefat" id="api-keys-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Domain', 'wc-paypal-proxy-handler'); ?></th>
                    <th><?php esc_html_e('API Key', 'wc-paypal-proxy-handler'); ?></th>
                    <th><?php esc_html_e('Actions', 'wc-paypal-proxy-handler'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $api_keys = isset($settings['api_keys']) ? $settings['api_keys'] : array();
                $index = 0;
                
                if (!empty($api_keys)) {
                    foreach ($api_keys as $domain => $key) {
                        ?>
                        <tr>
                            <td>
                                <input type="text" name="api_key_domain[]" value="<?php echo esc_attr($domain); ?>" class="regular-text">
                            </td>
                            <td>
                                <input type="text" name="api_key_value[]" value="<?php echo esc_attr($key); ?>" class="regular-text">
                            </td>
                            <td>
                                <button type="button" class="button remove-api-key"><?php esc_html_e('Remove', 'wc-paypal-proxy-handler'); ?></button>
                            </td>
                        </tr>
                        <?php
                        $index++;
                    }
                }
                
                // Always add an empty row for new entries
                ?>
                <tr>
                    <td>
                        <input type="text" name="api_key_domain[]" value="" class="regular-text" placeholder="<?php esc_attr_e('example.com', 'wc-paypal-proxy-handler'); ?>">
                    </td>
                    <td>
                        <input type="text" name="api_key_value[]" value="" class="regular-text" placeholder="<?php esc_attr_e('Leave blank to auto-generate', 'wc-paypal-proxy-handler'); ?>">
                    </td>
                    <td>
                        <button type="button" class="button remove-api-key"><?php esc_html_e('Remove', 'wc-paypal-proxy-handler'); ?></button>
                    </td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3">
                        <button type="button" class="button add-api-key"><?php esc_html_e('Add API Key', 'wc-paypal-proxy-handler'); ?></button>
                    </td>
                </tr>
            </tfoot>
        </table>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Add API key
                $('.add-api-key').on('click', function() {
                    var row = '<tr>' +
                        '<td><input type="text" name="api_key_domain[]" value="" class="regular-text" placeholder="<?php esc_attr_e('example.com', 'wc-paypal-proxy-handler'); ?>"></td>' +
                        '<td><input type="text" name="api_key_value[]" value="" class="regular-text" placeholder="<?php esc_attr_e('Leave blank to auto-generate', 'wc-paypal-proxy-handler'); ?>"></td>' +
                        '<td><button type="button" class="button remove-api-key"><?php esc_html_e('Remove', 'wc-paypal-proxy-handler'); ?></button></td>' +
                        '</tr>';
                    $('#api-keys-table tbody').append(row);
                });
                
                // Remove API key
                $(document).on('click', '.remove-api-key', function() {
                    $(this).closest('tr').remove();
                });
            });
        </script>
        
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'wc-paypal-proxy-handler'); ?>">
        </p>
    </form>
    
    <div class="card">
        <h2><?php esc_html_e('Usage Instructions', 'wc-paypal-proxy-handler'); ?></h2>
        <p><?php esc_html_e('To use this proxy with another WooCommerce site:', 'wc-paypal-proxy-handler'); ?></p>
        <ol>
            <li><?php esc_html_e('Add the domain of the client site to the Allowed Domains list above.', 'wc-paypal-proxy-handler'); ?></li>
            <li><?php esc_html_e('Create an API key for the domain using the API Keys section.', 'wc-paypal-proxy-handler'); ?></li>
            <li><?php esc_html_e('Install the PayPal Proxy Client plugin on the client site.', 'wc-paypal-proxy-handler'); ?></li>
            <li><?php esc_html_e('Configure the client plugin with this site\'s URL and the API key.', 'wc-paypal-proxy-handler'); ?></li>
        </ol>
        <p><?php esc_html_e('The client site will then be able to use PayPal payments through this proxy.', 'wc-paypal-proxy-handler'); ?></p>
    </div>
</div>