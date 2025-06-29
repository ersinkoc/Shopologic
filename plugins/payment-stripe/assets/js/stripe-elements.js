/**
 * Stripe Elements Integration for Shopologic
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        initializeStripePayment();
    });

    function initializeStripePayment() {
        // Check if Stripe is loaded and we have the necessary data
        if (typeof Stripe === 'undefined' || !window.stripePublishableKey) {
            console.error('Stripe.js not loaded or publishable key missing');
            return;
        }

        // Initialize Stripe
        const stripe = Stripe(window.stripePublishableKey);
        const elements = stripe.elements();

        // Custom styling for Stripe Elements
        const style = {
            base: {
                color: '#32325d',
                fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                fontSmoothing: 'antialiased',
                fontSize: '16px',
                '::placeholder': {
                    color: '#aab7c4'
                }
            },
            invalid: {
                color: '#fa755a',
                iconColor: '#fa755a'
            }
        };

        // Create card element
        const cardElement = elements.create('card', {
            style: style,
            hidePostalCode: false
        });

        // Mount card element
        const cardMount = document.getElementById('card-element');
        if (cardMount) {
            cardElement.mount('#card-element');
        }

        // Handle real-time validation errors
        cardElement.on('change', function(event) {
            const displayError = document.getElementById('card-errors');
            if (event.error) {
                displayError.textContent = event.error.message;
                displayError.style.display = 'block';
            } else {
                displayError.textContent = '';
                displayError.style.display = 'none';
            }
        });

        // Listen for payment form submission
        const paymentForm = document.querySelector('[data-payment-method="stripe"]');
        if (paymentForm) {
            const submitButton = paymentForm.querySelector('[type="submit"]');
            
            paymentForm.addEventListener('submit', async function(event) {
                event.preventDefault();
                
                // Disable submit button
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Processing...';
                }

                try {
                    // Create payment intent
                    const paymentIntentResponse = await createPaymentIntent();
                    
                    if (!paymentIntentResponse.success) {
                        throw new Error(paymentIntentResponse.error || 'Failed to create payment intent');
                    }

                    // Collect billing details
                    const billingDetails = collectBillingDetails();

                    // Confirm payment
                    const result = await stripe.confirmCardPayment(
                        paymentIntentResponse.client_secret,
                        {
                            payment_method: {
                                card: cardElement,
                                billing_details: billingDetails
                            },
                            setup_future_usage: document.getElementById('save-payment-method')?.checked ? 'off_session' : null,
                            return_url: window.stripeOrderData.returnUrl
                        }
                    );

                    if (result.error) {
                        // Show error to customer
                        showError(result.error.message);
                    } else {
                        // Payment succeeded
                        if (result.paymentIntent.status === 'succeeded') {
                            // Redirect to success page
                            window.location.href = window.stripeOrderData.returnUrl + '&payment_intent=' + result.paymentIntent.id;
                        } else if (result.paymentIntent.status === 'requires_action') {
                            // 3D Secure or other action required
                            // Stripe.js will handle this automatically
                        }
                    }
                } catch (error) {
                    showError(error.message);
                } finally {
                    // Re-enable submit button
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = 'Complete Payment';
                    }
                }
            });
        }

        // Create payment intent via API
        async function createPaymentIntent() {
            const response = await fetch('/api/payments/stripe/payment-intent', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCSRFToken()
                },
                body: JSON.stringify({
                    order_id: window.stripeOrderData.orderId,
                    amount: window.stripeOrderData.amount,
                    currency: window.stripeOrderData.currency
                })
            });

            return await response.json();
        }

        // Collect billing details from form
        function collectBillingDetails() {
            const details = {
                name: document.getElementById('billing-name')?.value || '',
                email: document.getElementById('billing-email')?.value || window.stripeOrderData.customerEmail,
                phone: document.getElementById('billing-phone')?.value || '',
                address: {
                    line1: document.getElementById('billing-line1')?.value || '',
                    line2: document.getElementById('billing-line2')?.value || '',
                    city: document.getElementById('billing-city')?.value || '',
                    state: document.getElementById('billing-state')?.value || '',
                    postal_code: document.getElementById('billing-postal')?.value || '',
                    country: document.getElementById('billing-country')?.value || ''
                }
            };

            // Remove empty values
            Object.keys(details.address).forEach(key => {
                if (!details.address[key]) {
                    delete details.address[key];
                }
            });

            if (Object.keys(details.address).length === 0) {
                delete details.address;
            }

            return details;
        }

        // Show error message
        function showError(message) {
            const errorElement = document.getElementById('card-errors');
            if (errorElement) {
                errorElement.textContent = message;
                errorElement.style.display = 'block';
            }
            
            // Also show a toast notification if available
            if (window.showNotification) {
                window.showNotification('error', message);
            }
        }

        // Get CSRF token
        function getCSRFToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        }

        // Handle saved payment methods
        const savedMethodsContainer = document.getElementById('saved-payment-methods');
        if (savedMethodsContainer) {
            loadSavedPaymentMethods();
        }

        async function loadSavedPaymentMethods() {
            try {
                const response = await fetch('/api/payments/stripe/methods', {
                    headers: {
                        'X-CSRF-Token': getCSRFToken()
                    }
                });

                if (response.ok) {
                    const methods = await response.json();
                    displaySavedMethods(methods);
                }
            } catch (error) {
                console.error('Failed to load saved payment methods:', error);
            }
        }

        function displaySavedMethods(methods) {
            if (!methods || methods.length === 0) {
                return;
            }

            const container = document.getElementById('saved-payment-methods');
            let html = '<h4>Saved Payment Methods</h4><div class="saved-methods-list">';

            methods.forEach(method => {
                html += `
                    <label class="saved-method">
                        <input type="radio" name="payment_method_id" value="${method.id}">
                        <span class="method-details">
                            <span class="card-brand">${method.card.brand}</span>
                            ending in ${method.card.last4}
                            <span class="expires">Expires ${method.card.exp_month}/${method.card.exp_year}</span>
                        </span>
                    </label>
                `;
            });

            html += '</div>';
            container.innerHTML = html;

            // Add event listeners to saved methods
            container.querySelectorAll('input[name="payment_method_id"]').forEach(input => {
                input.addEventListener('change', function() {
                    if (this.checked) {
                        // Hide new card form
                        document.getElementById('card-element').style.display = 'none';
                    }
                });
            });

            // Add "Use new card" option
            const newCardOption = document.createElement('label');
            newCardOption.className = 'saved-method';
            newCardOption.innerHTML = `
                <input type="radio" name="payment_method_id" value="new" checked>
                <span class="method-details">Use a new card</span>
            `;
            container.querySelector('.saved-methods-list').appendChild(newCardOption);

            newCardOption.querySelector('input').addEventListener('change', function() {
                if (this.checked) {
                    document.getElementById('card-element').style.display = 'block';
                }
            });
        }
    }
})();