/**
 * PayPal Proxy Handler JavaScript for Store B.
 *
 * @since      1.0.0
 * @package    PayPal_Proxy_Checkout_Store_B
 */

(function() {
    'use strict';
    
    // PayPal proxy handler
    window.PayPalProxyHandler = {
        
        /**
         * Initialize the proxy handler.
         *
         * @param {Object} config Configuration options
         */
        init: function(config) {
            this.config = config || {};
            this.storeAId = config.storeAId || '';
            this.apiUrl = config.apiUrl || '';
            this.authToken = config.authToken || '';
            
            // Set up event listeners
            this.setupEventListeners();
        },
        
        /**
         * Set up event listeners for cross-domain communication.
         */
        setupEventListeners: function() {
            // Listen for postMessage events from Store A
            window.addEventListener('message', this.handleMessage.bind(this), false);
        },
        
        /**
         * Handle incoming postMessage events.
         *
         * @param {MessageEvent} event The message event
         */
        handleMessage: function(event) {
            // Verify origin if needed
            
            // Process the message
            var message = event.data;
            
            // Check if it's a message for this handler
            if (!message || message.type !== 'paypalProxyCheckout') {
                return;
            }
            
            // Handle different actions
            switch (message.action) {
                case 'createOrder':
                    this.createOrder(message.data, event.source);
                    break;
                    
                case 'getOrderStatus':
                    this.getOrderStatus(message.data, event.source);
                    break;
                    
                case 'captureOrder':
                    this.captureOrder(message.data, event.source);
                    break;
                    
                default:
                    this.sendResponse(event.source, {
                        type: 'paypalProxyCheckout',
                        action: 'error',
                        error: 'Unknown action: ' + message.action
                    });
                    break;
            }
        },
        
        /**
         * Create a PayPal order.
         *
         * @param {Object} data Order data
         * @param {Window} source Source window to respond to
         */
        createOrder: function(data, source) {
            var self = this;
            
            // Add Store A ID
            data.store_a_id = this.storeAId;
            
            // Make API request to create order
            this.apiRequest('POST', this.apiUrl + '/create-order', data)
                .then(function(response) {
                    self.sendResponse(source, {
                        type: 'paypalProxyCheckout',
                        action: 'orderCreated',
                        orderData: response
                    });
                })
                .catch(function(error) {
                    self.sendResponse(source, {
                        type: 'paypalProxyCheckout',
                        action: 'error',
                        error: error.message || 'Failed to create order'
                    });
                });
        },
        
        /**
         * Get order status.
         *
         * @param {Object} data Order data
         * @param {Window} source Source window to respond to
         */
        getOrderStatus: function(data, source) {
            var self = this;
            
            // Add Store A ID
            data.store_a_id = this.storeAId;
            
            // Make API request to get order status
            this.apiRequest('POST', this.apiUrl + '/order-status', data)
                .then(function(response) {
                    self.sendResponse(source, {
                        type: 'paypalProxyCheckout',
                        action: 'orderStatus',
                        statusData: response
                    });
                })
                .catch(function(error) {
                    self.sendResponse(source, {
                        type: 'paypalProxyCheckout',
                        action: 'error',
                        error: error.message || 'Failed to get order status'
                    });
                });
        },
        
        /**
         * Capture a PayPal order.
         *
         * @param {Object} data Order data
         * @param {Window} source Source window to respond to
         */
        captureOrder: function(data, source) {
            var self = this;
            
            // Add Store A ID
            data.store_a_id = this.storeAId;
            
            // Make API request to capture order
            this.apiRequest('POST', this.apiUrl + '/capture-order', data)
                .then(function(response) {
                    self.sendResponse(source, {
                        type: 'paypalProxyCheckout',
                        action: 'orderCaptured',
                        captureData: response
                    });
                })
                .catch(function(error) {
                    self.sendResponse(source, {
                        type: 'paypalProxyCheckout',
                        action: 'error',
                        error: error.message || 'Failed to capture order'
                    });
                });
        },
        
        /**
         * Make an API request.
         *
         * @param {string} method HTTP method
         * @param {string} url API URL
         * @param {Object} data Request data
         * @returns {Promise} Promise that resolves with the response
         */
        apiRequest: function(method, url, data) {
            return new Promise(function(resolve, reject) {
                var xhr = new XMLHttpRequest();
                xhr.open(method, url, true);
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.setRequestHeader('X-Paypal-Proxy-Auth', this.authToken);
                
                xhr.onload = function() {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            resolve(response);
                        } catch (e) {
                            reject(new Error('Invalid JSON response'));
                        }
                    } else {
                        reject(new Error('Request failed with status: ' + xhr.status));
                    }
                };
                
                xhr.onerror = function() {
                    reject(new Error('Network error'));
                };
                
                xhr.send(JSON.stringify(data));
            }.bind(this));
        },
        
        /**
         * Send a response to the source window.
         *
         * @param {Window} target Target window
         * @param {Object} message Message to send
         */
        sendResponse: function(target, message) {
            if (target && target.postMessage) {
                target.postMessage(message, '*');
            }
        }
    };
    
})();