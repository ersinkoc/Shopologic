<?php

declare(strict_types=1);

namespace Shopologic\Tests\E2E;

use Shopologic\Tests\E2E\TestFramework\E2ETestCase;
use Shopologic\Tests\E2E\TestFramework\Browser;

/**
 * Checkout Flow E2E Test
 * 
 * Tests various checkout scenarios and edge cases
 */
class CheckoutFlowTest extends E2ETestCase
{
    /**
     * Test standard checkout flow
     */
    public function testStandardCheckoutFlow(): void
    {
        $browser = $this->createBrowser();
        
        // Add multiple products to cart
        $this->addMultipleProductsToCart($browser, 3);
        
        // Go to cart
        $browser->visit('/cart');
        $browser->waitForElement('.cart-page');
        
        // Apply coupon code
        $browser->type('#coupon_code', 'SAVE10');
        $browser->click('.apply-coupon');
        $browser->waitForElement('.coupon-applied');
        
        $this->assertPageContains($browser, 'Coupon applied');
        $this->assertElementExists($browser, '.discount-amount');
        
        // Update quantities
        $browser->clear('.cart-item:first-child input[name="quantity"]');
        $browser->type('.cart-item:first-child input[name="quantity"]', '2');
        $browser->click('.update-cart');
        $browser->waitForElement('.cart-updated');
        
        // Proceed to checkout
        $browser->click('.checkout-button');
        $browser->waitForElement('.checkout-page');
        
        // Complete checkout
        $this->completeCheckout($browser);
        
        // Verify order confirmation
        $browser->waitForElement('.order-confirmation');
        $this->assertPageContains($browser, 'Order Confirmed');
        $this->assertPageContains($browser, 'SAVE10'); // Coupon should be shown
    }
    
    /**
     * Test express checkout
     */
    public function testExpressCheckout(): void
    {
        $browser = $this->createBrowser();
        
        // Add product and go directly to express checkout
        $browser->visit('/products/laptop-pro');
        $browser->waitForElement('.product-details');
        
        // Click express checkout (PayPal/Apple Pay)
        $browser->click('.paypal-express-button');
        $browser->waitForElement('.paypal-checkout-modal');
        
        // Simulate PayPal login
        $browser->type('#paypal_email', 'buyer@example.com');
        $browser->type('#paypal_password', 'password');
        $browser->click('.paypal-login-button');
        
        // Confirm payment
        $browser->waitForElement('.paypal-confirm');
        $browser->click('.confirm-payment-button');
        
        // Should redirect to order confirmation
        $browser->waitForElement('.order-confirmation');
        $this->assertPageContains($browser, 'Order Confirmed');
        $this->assertPageContains($browser, 'Payment Method: PayPal Express');
    }
    
    /**
     * Test checkout with multiple shipping addresses
     */
    public function testMultipleShippingAddresses(): void
    {
        $browser = $this->createBrowser();
        
        // Login as customer with saved addresses
        $this->loginCustomer($browser);
        
        // Add products to cart
        $this->addMultipleProductsToCart($browser, 2);
        
        // Go to checkout
        $browser->visit('/checkout');
        $browser->waitForElement('.checkout-page');
        
        // Should see saved addresses
        $this->assertElementExists($browser, '.saved-addresses');
        $this->assertElementCount($browser, '.address-card', '>', 1);
        
        // Select different shipping address
        $browser->click('.address-card:nth-child(2) .use-address');
        $browser->waitForElement('.address-selected');
        
        // Add new address
        $browser->click('.add-new-address');
        $browser->waitForElement('.address-form-modal');
        
        $this->fillAddressForm($browser, [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'address_1' => '456 Park Ave',
            'city' => 'Los Angeles',
            'state' => 'CA',
            'postal_code' => '90001'
        ]);
        
        $browser->click('.save-address-button');
        $browser->waitForElement('.address-saved');
        
        // Continue with checkout
        $this->continueCheckout($browser);
    }
    
    /**
     * Test checkout with validation errors
     */
    public function testCheckoutValidationErrors(): void
    {
        $browser = $this->createBrowser();
        
        // Add product to cart
        $this->addProductToCart($browser);
        
        // Go to checkout
        $browser->visit('/checkout');
        $browser->waitForElement('.checkout-page');
        
        // Try to submit with empty fields
        $browser->click('.continue-to-shipping');
        $browser->waitForElement('.validation-errors');
        
        $this->assertPageContains($browser, 'Email is required');
        $this->assertPageContains($browser, 'First name is required');
        $this->assertPageContains($browser, 'Address is required');
        
        // Fill partial information
        $browser->type('#email', 'invalid-email');
        $browser->type('#postal_code', '123'); // Invalid format
        $browser->click('.continue-to-shipping');
        
        $browser->waitForElement('.validation-errors');
        $this->assertPageContains($browser, 'Invalid email format');
        $this->assertPageContains($browser, 'Invalid postal code');
        
        // Fix errors and continue
        $browser->clear('#email');
        $browser->type('#email', 'valid@example.com');
        $browser->clear('#postal_code');
        $browser->type('#postal_code', '10001');
        
        $this->fillCheckoutForm($browser);
        $browser->click('.continue-to-shipping');
        $browser->waitForElement('.shipping-methods');
    }
    
    /**
     * Test checkout with inventory issues
     */
    public function testCheckoutInventoryIssues(): void
    {
        $browser = $this->createBrowser();
        
        // Add limited stock product
        $browser->visit('/products/limited-edition-watch');
        $browser->waitForElement('.product-details');
        
        // Try to add more than available
        $browser->type('#quantity', '10');
        $browser->click('.add-to-cart-button');
        $browser->waitForElement('.inventory-error');
        
        $this->assertPageContains($browser, 'Only 3 items available');
        
        // Add available quantity
        $browser->clear('#quantity');
        $browser->type('#quantity', '3');
        $browser->click('.add-to-cart-button');
        $browser->waitForElement('.cart-notification');
        
        // Go to checkout
        $browser->visit('/checkout');
        $browser->waitForElement('.checkout-page');
        
        // Simulate another customer buying stock during checkout
        // (This would be done through API or database manipulation in real test)
        
        // Try to complete checkout
        $this->fillCheckoutForm($browser);
        $browser->click('.place-order-button');
        
        // Should show inventory error
        $browser->waitForElement('.checkout-error');
        $this->assertPageContains($browser, 'Some items are no longer available');
        $this->assertElementExists($browser, '.update-cart-link');
    }
    
    /**
     * Test checkout with payment failures
     */
    public function testCheckoutPaymentFailures(): void
    {
        $browser = $this->createBrowser();
        
        // Add product to cart
        $this->addProductToCart($browser);
        
        // Go through checkout
        $browser->visit('/checkout');
        $this->fillCheckoutForm($browser);
        
        // Select credit card payment
        $browser->click('input[value="credit_card"]');
        $browser->waitForElement('.credit-card-form');
        
        // Test declined card
        $browser->type('#card_number', '4000000000000002'); // Test declined card
        $browser->type('#card_expiry', '12/25');
        $browser->type('#card_cvc', '123');
        
        $browser->click('.place-order-button');
        $browser->waitForElement('.payment-error');
        
        $this->assertPageContains($browser, 'Payment declined');
        $this->assertElementExists($browser, '.try-again-button');
        
        // Test expired card
        $browser->clear('#card_number');
        $browser->type('#card_number', '4000000000000069'); // Test expired card
        
        $browser->click('.place-order-button');
        $browser->waitForElement('.payment-error');
        
        $this->assertPageContains($browser, 'Card expired');
        
        // Test successful payment
        $browser->clear('#card_number');
        $browser->type('#card_number', '4242424242424242'); // Test successful card
        
        $browser->click('.place-order-button');
        $browser->waitForElement('.order-confirmation', 10);
    }
    
    /**
     * Test checkout with shipping restrictions
     */
    public function testCheckoutShippingRestrictions(): void
    {
        $browser = $this->createBrowser();
        
        // Add restricted product (e.g., hazardous material)
        $browser->visit('/products/lithium-battery-pack');
        $browser->click('.add-to-cart-button');
        
        // Go to checkout
        $browser->visit('/checkout');
        $browser->waitForElement('.checkout-page');
        
        // Fill address in restricted location
        $browser->type('#country', 'CA'); // Canada
        $browser->type('#state', 'ON');
        $this->fillCheckoutForm($browser, ['country' => 'CA', 'state' => 'ON']);
        
        $browser->click('.continue-to-shipping');
        $browser->waitForElement('.shipping-restriction-notice');
        
        $this->assertPageContains($browser, 'Cannot ship to this location');
        $this->assertElementExists($browser, '.change-address-link');
        
        // Change to allowed location
        $browser->click('.change-address-link');
        $browser->select('#country', 'US');
        $browser->select('#state', 'NY');
        
        $browser->click('.continue-to-shipping');
        $browser->waitForElement('.shipping-methods');
        
        // Should only show allowed shipping methods
        $this->assertNotElementExists($browser, 'input[value="air_shipping"]');
    }
    
    /**
     * Test checkout session timeout
     */
    public function testCheckoutSessionTimeout(): void
    {
        $browser = $this->createBrowser();
        
        // Add product to cart
        $this->addProductToCart($browser);
        
        // Start checkout
        $browser->visit('/checkout');
        $browser->waitForElement('.checkout-page');
        
        // Fill partial information
        $browser->type('#email', 'test@example.com');
        
        // Simulate session timeout (wait or manipulate session)
        // In real test, this would involve waiting or clearing session
        sleep(2); // Simulate delay
        
        // Try to continue
        $browser->click('.continue-to-shipping');
        
        // Should redirect to cart or show session expired message
        $browser->waitForElement('.session-expired-notice');
        $this->assertPageContains($browser, 'Session expired');
        $this->assertElementExists($browser, '.restart-checkout-button');
    }
    
    /**
     * Test checkout with gift options
     */
    public function testCheckoutGiftOptions(): void
    {
        $browser = $this->createBrowser();
        
        // Add products to cart
        $this->addMultipleProductsToCart($browser, 2);
        
        // Go to checkout
        $browser->visit('/checkout');
        $browser->waitForElement('.checkout-page');
        
        // Enable gift options
        $browser->check('#is_gift');
        $browser->waitForElement('.gift-options');
        
        // Add gift message
        $browser->type('#gift_message', 'Happy Birthday! Hope you enjoy this gift.');
        
        // Select gift wrap
        $browser->click('input[name="gift_wrap"][value="premium"]');
        $this->assertPageContains($browser, 'Premium Gift Wrap (+$5.99)');
        
        // Mark individual items as gifts
        $browser->check('.cart-item:first-child .mark-as-gift');
        $browser->waitForElement('.gift-recipient-form');
        
        $browser->type('.gift-recipient-name', 'John Smith');
        $browser->type('.gift-recipient-email', 'john@example.com');
        
        // Complete checkout
        $this->fillCheckoutForm($browser);
        $this->continueCheckout($browser);
        
        // Verify gift options in confirmation
        $browser->waitForElement('.order-confirmation');
        $this->assertPageContains($browser, 'Gift Order');
        $this->assertPageContains($browser, 'Premium Gift Wrap');
    }
    
    /**
     * Test subscription product checkout
     */
    public function testSubscriptionCheckout(): void
    {
        $browser = $this->createBrowser();
        
        // Add subscription product
        $browser->visit('/products/monthly-coffee-subscription');
        $browser->waitForElement('.product-details');
        
        // Select subscription options
        $browser->select('#subscription_frequency', 'monthly');
        $browser->type('#subscription_quantity', '2');
        
        $browser->click('.add-to-cart-button');
        $browser->waitForElement('.cart-notification');
        
        // Go to checkout
        $browser->visit('/checkout');
        $browser->waitForElement('.checkout-page');
        
        // Should see subscription terms
        $this->assertElementExists($browser, '.subscription-terms');
        $this->assertPageContains($browser, 'Billed monthly');
        $this->assertPageContains($browser, 'Cancel anytime');
        
        // Must agree to subscription terms
        $browser->click('.place-order-button');
        $browser->waitForElement('.validation-error');
        
        $this->assertPageContains($browser, 'Please agree to subscription terms');
        
        // Agree and complete
        $browser->check('#agree_subscription_terms');
        $this->fillCheckoutForm($browser);
        $this->continueCheckout($browser);
        
        // Verify subscription in confirmation
        $browser->waitForElement('.order-confirmation');
        $this->assertPageContains($browser, 'Subscription Order');
        $this->assertPageContains($browser, 'Next billing date');
    }
    
    /**
     * Test B2B checkout with tax exemption
     */
    public function testB2BCheckoutTaxExemption(): void
    {
        $browser = $this->createBrowser();
        
        // Login as B2B customer
        $this->loginB2BCustomer($browser);
        
        // Add products to cart
        $this->addMultipleProductsToCart($browser, 5);
        
        // Go to checkout
        $browser->visit('/checkout');
        $browser->waitForElement('.checkout-page');
        
        // Should see B2B options
        $this->assertElementExists($browser, '.b2b-checkout-options');
        
        // Enter tax exemption certificate
        $browser->type('#tax_exempt_id', 'TX-EXEMPT-12345');
        $browser->attach('#tax_exempt_certificate', __DIR__ . '/fixtures/tax-exempt.pdf');
        
        // Add purchase order number
        $browser->type('#purchase_order', 'PO-2024-001');
        
        // Select NET payment terms
        $browser->click('input[name="payment_terms"][value="net30"]');
        
        // Complete checkout
        $this->fillCheckoutForm($browser);
        $browser->click('.place-order-button');
        
        // Verify no tax charged
        $browser->waitForElement('.order-confirmation');
        $this->assertPageContains($browser, 'Tax Exempt');
        $this->assertElementTextContains($browser, '.tax-amount', '$0.00');
        $this->assertPageContains($browser, 'Payment Terms: NET 30');
    }
    
    /**
     * Helper: Add product to cart
     */
    private function addProductToCart(Browser $browser): void
    {
        $browser->visit('/products');
        $browser->waitForElement('.product-grid');
        $browser->click('.product-card:first-child .add-to-cart');
        $browser->waitForElement('.cart-notification');
    }
    
    /**
     * Helper: Add multiple products to cart
     */
    private function addMultipleProductsToCart(Browser $browser, int $count): void
    {
        $browser->visit('/products');
        $browser->waitForElement('.product-grid');
        
        for ($i = 1; $i <= $count; $i++) {
            $browser->click(".product-card:nth-child({$i}) .add-to-cart");
            $browser->waitForElement('.cart-notification');
            sleep(1); // Brief pause between adds
        }
    }
    
    /**
     * Helper: Fill checkout form
     */
    private function fillCheckoutForm(Browser $browser, array $data = []): void
    {
        $defaults = [
            'email' => 'customer@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'address_1' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'US',
            'phone' => '+12125551234'
        ];
        
        $data = array_merge($defaults, $data);
        
        foreach ($data as $field => $value) {
            if ($browser->elementExists("#$field")) {
                $browser->clear("#$field");
                $browser->type("#$field", $value);
            }
        }
    }
    
    /**
     * Helper: Complete checkout flow
     */
    private function completeCheckout(Browser $browser): void
    {
        $this->fillCheckoutForm($browser);
        
        $browser->click('.continue-to-shipping');
        $browser->waitForElement('.shipping-methods');
        
        $browser->click('input[name="shipping_method"][value="standard"]');
        $browser->click('.continue-to-payment');
        
        $browser->waitForElement('.payment-methods');
        $browser->click('input[name="payment_method"][value="test"]');
        
        $browser->click('.place-order-button');
    }
    
    /**
     * Helper: Continue checkout from current step
     */
    private function continueCheckout(Browser $browser): void
    {
        if ($browser->elementExists('.continue-to-shipping')) {
            $browser->click('.continue-to-shipping');
            $browser->waitForElement('.shipping-methods');
        }
        
        if ($browser->elementExists('.continue-to-payment')) {
            $browser->click('input[name="shipping_method"]:first-child');
            $browser->click('.continue-to-payment');
            $browser->waitForElement('.payment-methods');
        }
        
        if ($browser->elementExists('.place-order-button')) {
            $browser->click('input[name="payment_method"][value="test"]');
            $browser->click('.place-order-button');
        }
    }
    
    /**
     * Helper: Fill address form
     */
    private function fillAddressForm(Browser $browser, array $data): void
    {
        foreach ($data as $field => $value) {
            $browser->type("#address_$field", $value);
        }
    }
    
    /**
     * Helper: Login as customer
     */
    private function loginCustomer(Browser $browser): void
    {
        $browser->visit('/login');
        $browser->type('#email', 'customer@example.com');
        $browser->type('#password', 'password');
        $browser->click('.login-button');
        $browser->waitForElement('.account-dashboard');
    }
    
    /**
     * Helper: Login as B2B customer
     */
    private function loginB2BCustomer(Browser $browser): void
    {
        $browser->visit('/login');
        $browser->type('#email', 'b2b@company.com');
        $browser->type('#password', 'b2bpassword');
        $browser->click('.login-button');
        $browser->waitForElement('.b2b-dashboard');
    }
}