/**
 * Admin JavaScript for PayPal Proxy Checkout (Store B).
 *
 * @since      1.0.0
 * @package    PayPal_Proxy_Checkout_Store_B
 */

(function($) {
    'use strict';

    // Initialize admin settings page
    function initAdminSettings() {
        // Test PayPal credentials
        $('#ppcb-test-credentials').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var mode = $('#ppcb-paypal-mode').val();
            var clientId = $('#ppcb-client-id').val();
            var clientSecret = $('#ppcb-client-secret').val();
            
            if (!clientId || !clientSecret) {
                alert('Please enter both Client ID and Client Secret before testing.');
                return;
            }
            
            $button.prop('disabled', true).text('Testing...');
            
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
                    $button.prop('disabled', false).text('Test PayPal API Credentials');
                    
                    if (response.success) {
                        alert(response.data.message);
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text('Test PayPal API Credentials');
                    alert('Error occurred while testing credentials.');
                }
            });
        });
        
        // Form validation for PayPal credentials
        $('#ppcb-settings-form').on('submit', function(e) {
            var clientId = $('#ppcb-client-id').val();
            var clientSecret = $('#ppcb-client-secret').val();
            
            if (!clientId || !clientSecret) {
                e.preventDefault();
                alert('PayPal Client ID and Client Secret are required.');
                return false;
            }
            
            return true;
        });
        
        // Initialize Store A table handling
        initStoreTable();
        
        // Clear logs button
        $('#ppcb-clear-logs').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to clear all logs?')) {
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
                    alert('Error occurred while clearing logs.');
                }
            });
        });
    }
    
    // Initialize Store A table
    function initStoreTable() {
        // Add Store A
        $('#ppcb-add-store').on('click', function() {
            var template = $('#ppcb-store-template').html();
            $('#ppcb-store-a-table tbody .ppcb-no-stores').addClass('hidden');
            $('#ppcb-store-a-table tbody').append(template);
            updateStoreData();
        });
        
        // Remove Store A
        $(document).on('click', '.ppcb-remove-store', function() {
            $(this).closest('tr').remove();
            if ($('#ppcb-store-a-table tbody tr').length === 1) {
                $('#ppcb-store-a-table tbody .ppcb-no-stores').removeClass('hidden');
            }
            updateStoreData();
        });
        
        // Generate token
        $(document).on('click', '.ppcb-generate-token', function() {
            var tokenField = $(this).siblings('.ppcb-store-token');
            tokenField.val(generateRandomToken(32));
            updateStoreData();
        });
        
        // Update store data when values change
        $(document).on('change', '.ppcb-store-label, .ppcb-store-id, .ppcb-store-url, .ppcb-store-token', function() {
            updateStoreData();
        });
    }
    
    // Update the hidden input with the current store data
    function updateStoreData() {
        var stores = [];
        $('#ppcb-store-a-table tbody tr').each(function() {
            var $row = $(this);
            if ($row.hasClass('ppcb-no-stores')) {
                return; // Skip the "no stores" row
            }
            
            var label = $row.find('.ppcb-store-label').val();
            var id = $row.find('.ppcb-store-id').val();
            var url = $row.find('.ppcb-store-url').val();
            var tokenKey = $row.find('.ppcb-store-token').val();
            
            if (id && url && tokenKey) {
                stores.push({
                    label: label || '',
                    id: id,
                    url: url,
                    token_key: tokenKey
                });
            }
        });
        
        $('#ppcb-store-data').val(JSON.stringify(stores));
    }
    
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
    
    // Initialize when document is ready
    $(document).ready(function() {
        initAdminSettings();
    });
    
})(jQuery);