<?php
/**
 * Admin logs page template.
 *
 * @since      1.0.0
 * @package    PayPal_Proxy_Checkout_Store_B
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get logs
$logs = get_option('paypal_proxy_checkout_store_b_logs', array());
?>

<div class="wrap ppcb-logs-wrap">
    <h1><?php echo esc_html__('PayPal Proxy Checkout Logs', 'paypal-proxy-checkout-store-b'); ?></h1>
    
    <div class="ppcb-logs-controls">
        <button type="button" id="ppcb-clear-logs" class="button button-secondary"><?php echo esc_html__('Clear All Logs', 'paypal-proxy-checkout-store-b'); ?></button>
        <span class="ppcb-logs-count"><?php printf(__('Total logs: %d', 'paypal-proxy-checkout-store-b'), count($logs)); ?></span>
    </div>
    
    <div class="ppcb-logs-section">
        <?php if (empty($logs)) : ?>
            <p class="ppcb-no-logs"><?php echo esc_html__('No logs found.', 'paypal-proxy-checkout-store-b'); ?></p>
        <?php else : ?>
            <table class="widefat ppcb-logs-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Time', 'paypal-proxy-checkout-store-b'); ?></th>
                        <th><?php echo esc_html__('Level', 'paypal-proxy-checkout-store-b'); ?></th>
                        <th><?php echo esc_html__('Message', 'paypal-proxy-checkout-store-b'); ?></th>
                        <th><?php echo esc_html__('Context', 'paypal-proxy-checkout-store-b'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log) : ?>
                        <?php
                        $level_class = 'ppcb-log-' . $log['level'];
                        $context = isset($log['context']) && !empty($log['context']) ? json_encode($log['context'], JSON_PRETTY_PRINT) : '';
                        ?>
                        <tr class="<?php echo esc_attr($level_class); ?>">
                            <td><?php echo esc_html($log['time']); ?></td>
                            <td><?php echo esc_html(ucfirst($log['level'])); ?></td>
                            <td><?php echo esc_html($log['message']); ?></td>
                            <td>
                                <?php if (!empty($context)) : ?>
                                    <pre class="ppcb-log-context"><?php echo esc_html($context); ?></pre>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Clear logs
    $('#ppcb-clear-logs').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('<?php echo esc_js(__('Are you sure you want to clear all logs?', 'paypal-proxy-checkout-store-b')); ?>')) {
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ppcb_clear_logs',
                nonce: ppcbAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Error occurred while clearing logs.', 'paypal-proxy-checkout-store-b')); ?>');
            }
        });
    });
});
</script>