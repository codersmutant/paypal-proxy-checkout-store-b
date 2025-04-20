/**
 * PayPal Button Renderer
 * 
 * Renders the PayPal buttons inside the iframe and handles payment processing
 */
(function($) {
    'use strict';

    // Initialize the PayPal Button Renderer
    const PayPalButtonRenderer = {
        
        /**
         * Initialize
         */
        init: function() {
            console.log('PayPal Button Renderer initializing...');
            
            // Check if we have the necessary data
            if (typeof wc_paypal_proxy_data === 'undefined') {
                console.error('Missing checkout data');
                this.showError('Missing checkout data');
                return;
            }
            
            console.log('Checkout data found:', wc_paypal_proxy_data);
            
            // Set up the message listener for parent window communication
            window.addEventListener('message', this.handleMessage.bind(this));
            
            // Store the checkout data
            this.data = wc_paypal_proxy_data;
            
            // Render the PayPal buttons when the SDK is loaded
            this.waitForPayPal().then(() => {
                console.log('PayPal SDK loaded, rendering buttons...');
                this.renderButtons();
                
                // Notify the parent window that the iframe is ready
                this.sendMessage({
                    type: 'iframe_ready'
                });
                
                // Adjust the iframe height
                this.adjustIframeHeight();
            }).catch(error => {
                console.error('Failed to load PayPal SDK:', error);
                this.showError('Failed to load PayPal SDK: ' + error.message);
            });
        },
        
        /**
         * Wait for PayPal SDK to be loaded
         */
        waitForPayPal: function() {
            console.log('Waiting for PayPal SDK to load...');
            return new Promise((resolve, reject) => {
                // If PayPal is already available, resolve immediately
                if (typeof paypal !== 'undefined') {
                    console.log('PayPal SDK already available');
                    resolve();
                    return;
                }
                
                // Check every 100ms for PayPal to become available
                let attempts = 0;
                const maxAttempts = 100; // 10 seconds maximum wait
                
                const checkPayPal = setInterval(() => {
                    attempts++;
                    
                    if (typeof paypal !== 'undefined') {
                        console.log('PayPal SDK loaded after ' + attempts + ' attempts');
                        clearInterval(checkPayPal);
                        resolve();
                    } else if (attempts >= maxAttempts) {
                        console.error('PayPal SDK not loaded after ' + maxAttempts + ' attempts');
                        clearInterval(checkPayPal);
                        reject(new Error('PayPal SDK not loaded after 10 seconds'));
                    }
                    
                    if (attempts % 10 === 0) {
                        console.log('Still waiting for PayPal SDK... (attempt ' + attempts + ')');
                    }
                }, 100);
            });
        },
        
        /**
         * Render PayPal buttons
         */
        renderButtons: function() {
            // Get the container
            const container = document.getElementById('paypal-button-container');
            
            if (!container) {
                console.error('PayPal button container not found');
                this.showError('PayPal button container not found');
                return;
            }
            
            console.log('Container found, rendering PayPal buttons...');
            
            try {
                // Render the buttons
                paypal.Buttons({
                    // Button style
                    style: {
                        layout: 'vertical',
                        color: 'gold',
                        shape: 'rect',
                        label: 'paypal'
                    },
                    
                    // Create order
                    createOrder: (data, actions) => {
                        console.log('createOrder called');
                        return this.createOrder();
                    },
                    
                    // Capture the order
                    onApprove: (data, actions) => {
                        console.log('onApprove called with data:', data);
                        return this.captureOrder(data);
                    },
                    
                    // Handle errors
                    onError: (err) => {
                        console.error('PayPal button error:', err);
                        this.showError('PayPal error: ' + err.message);
                        
                        // Notify the parent window about the error
                        this.sendMessage({
                            type: 'payment_failed',
                            error: err.message
                        });
                    },
                    
                    // Handle cancellation
                    onCancel: () => {
                        console.log('Payment cancelled by user');
                        
                        // Notify the parent window about the cancellation
                        this.sendMessage({
                            type: 'payment_cancelled',
                            cancel_url: this.data.cancel_url
                        });
                    }
                }).render('#paypal-button-container').then(() => {
                    console.log('PayPal buttons rendered successfully');
                }).catch(err => {
                    console.error('Error rendering PayPal buttons:', err);
                    this.showError('Error rendering PayPal buttons: ' + err.message);
                });
            } catch (error) {
                console.error('Exception rendering PayPal buttons:', error);
                this.showError('Exception rendering PayPal buttons: ' + error.message);
            }
        },
        
        /**
         * Create order in PayPal
         */
        createOrder: function() {
            console.log('Creating PayPal order');
            
            // Show loading message
            this.showMessage('Creating your PayPal order...', 'info');
            
            // Send request to create the order
            return fetch(this.data.create_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    order_id: this.data.order_id,
                    amount: this.data.amount,
                    currency: this.data.currency,
                    nonce: this.data.nonce,
                    data: this.data.data
                })
            })
            .then(response => {
                console.log('Create order response status:', response.status);
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('PayPal order created:', data);
                
                // Hide the loading message
                this.hideMessage();
                
                // Return the order ID
                return data.id;
            })
            .catch(error => {
                console.error('Error creating order:', error);
                this.showError('Error creating order: ' + error.message);
                throw error;
            });
        },
        
        /**
         * Capture order in PayPal
         */
        captureOrder: function(data) {
            console.log('Capturing PayPal order:', data.orderID);
            
            // Show loading message
            this.showMessage('Processing your payment...', 'info');
            
            // Send request to capture the order
            return fetch(this.data.capture_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    order_id: this.data.order_id,
                    paypal_order_id: data.orderID,
                    nonce: this.data.nonce,
                    data: this.data.data
                })
            })
            .then(response => {
                console.log('Capture order response status:', response.status);
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(captureData => {
                console.log('Payment captured:', captureData);
                
                // Show success message
                this.showMessage('Payment successful! Redirecting...', 'success');
                
                // Notify the parent window about the success
                this.sendMessage({
                    type: 'payment_completed',
                    transaction_id: captureData.id,
                    redirect_url: this.data.return_url
                });
                
                return captureData;
            })
            .catch(error => {
                console.error('Error capturing payment:', error);
                this.showError('Error capturing payment: ' + error.message);
                
                // Notify the parent window about the error
                this.sendMessage({
                    type: 'payment_failed',
                    error: error.message
                });
                
                throw error;
            });
        },
        
        /**
         * Handle messages from the parent window
         */
        handleMessage: function(event) {
            console.log('Received message from parent window:', event.origin, event.data);
            
            // Verify the origin
            if (!this.isValidOrigin(event.origin)) {
                console.warn('Received message from unauthorized origin:', event.origin);
                return;
            }
            
            // Process the message
            const message = event.data;
            
            if (!message || typeof message !== 'object') {
                return;
            }
            
            // Handle different message types
            switch (message.type) {
                case 'order_info':
                    // Additional order information from the parent window
                    if (message.order_id && message.order_id === this.data.order_id) {
                        console.log('Received additional order information');
                    }
                    break;
            }
        },
        
        /**
         * Send message to the parent window
         */
        sendMessage: function(message) {
            if (window.parent) {
                console.log('Sending message to parent:', message);
                try {
                    window.parent.postMessage(message, '*');
                } catch (error) {
                    console.error('Error sending message to parent:', error);
                }
            }
        },
        
        /**
         * Validate message origin
         */
        isValidOrigin: function(origin) {
            // For testing, allow all origins
            console.log('Origin validation check for:', origin);
            return true;
        },
        
        /**
         * Adjust iframe height
         */
        adjustIframeHeight: function() {
            // Get the content height
            const height = document.body.scrollHeight;
            console.log('Adjusting iframe height to:', height);
            
            // Send the height to the parent window
            this.sendMessage({
                type: 'iframe_height',
                height: height
            });
            
            // Set up a resize observer to adjust height when content changes
            if (typeof ResizeObserver !== 'undefined') {
                console.log('Setting up ResizeObserver');
                const resizeObserver = new ResizeObserver(entries => {
                    for (const entry of entries) {
                        const height = entry.target.scrollHeight;
                        console.log('Content resized, new height:', height);
                        
                        this.sendMessage({
                            type: 'iframe_height',
                            height: height
                        });
                    }
                });
                
                resizeObserver.observe(document.body);
            } else {
                console.warn('ResizeObserver not supported');
            }
        },
        
        /**
         * Show message
         */
        showMessage: function(message, type = 'info') {
            console.log('Showing message:', type, message);
            
            // Remove any existing messages
            this.hideMessage();
            
            // Create the message element
            const messageElement = document.createElement('div');
            messageElement.className = 'paypal-message ' + type;
            messageElement.textContent = message;
            messageElement.id = 'paypal-message';
            
            // Insert it before the button container
            const container = document.getElementById('paypal-button-container');
            container.parentNode.insertBefore(messageElement, container);
            
            // Adjust iframe height
            this.adjustIframeHeight();
        },
        
        /**
         * Show error message
         */
        showError: function(message) {
            console.error('Error:', message);
            this.showMessage(message, 'error');
        },
        
        /**
         * Hide message
         */
        hideMessage: function() {
            const messageElement = document.getElementById('paypal-message');
            
            if (messageElement) {
                console.log('Hiding message');
                messageElement.remove();
                this.adjustIframeHeight();
            }
        },
        
        /**
         * Log message to console
         */
        log: function(message) {
            if (typeof console !== 'undefined' && console.log) {
                console.log('PayPal Proxy: ' + message);
            }
        }
    };
    
    // Initialize when the document is ready
    $(document).ready(function() {
        console.log('Document ready, initializing PayPal Button Renderer...');
        PayPalButtonRenderer.init();
    });
    
})(jQuery);