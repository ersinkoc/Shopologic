<?php

declare(strict_types=1);

namespace Shopologic\Tests\E2E;

use Shopologic\Tests\E2E\TestFramework\E2ETestCase;
use Shopologic\Tests\E2E\TestFramework\Browser;
use Shopologic\Tests\E2E\TestFramework\Actions;
use Shopologic\Tests\E2E\TestFramework\Assertions;

/**
 * Customer Journey E2E Test
 * 
 * Tests the complete customer journey from browsing to checkout
 */
class CustomerJourneyTest extends E2ETestCase
{
    /**
     * Test complete customer purchase journey
     */
    public function testCompletePurchaseJourney(): void
    {
        $browser = $this->createBrowser();
        
        // 1. Visit homepage
        $browser->visit('/');
        $this->assertPageContains($browser, 'Welcome to Shopologic');
        $this->assertElementExists($browser, '.header-search');
        $this->assertElementExists($browser, '.header-cart');
        
        // 2. Search for products
        $browser->type('.header-search input', 'laptop');
        $browser->press('.header-search button');
        $browser->waitForElement('.search-results');
        
        $this->assertPageContains($browser, 'Search results for "laptop"');
        $this->assertElementCount($browser, '.product-card', '>', 0);
        
        // 3. View product details
        $browser->click('.product-card:first-child a');
        $browser->waitForElement('.product-details');
        
        $this->assertElementExists($browser, '.product-title');
        $this->assertElementExists($browser, '.product-price');
        $this->assertElementExists($browser, '.add-to-cart-button');
        
        // Store product details for verification
        $productName = $browser->getText('.product-title');
        $productPrice = $browser->getText('.product-price');
        
        // 4. Add to cart
        $browser->click('.add-to-cart-button');
        $browser->waitForElement('.cart-notification');
        
        $this->assertPageContains($browser, 'Added to cart');
        $this->assertElementTextContains($browser, '.header-cart-count', '1');
        
        // 5. View cart
        $browser->click('.header-cart');
        $browser->waitForElement('.cart-page');
        
        $this->assertPageContains($browser, 'Shopping Cart');
        $this->assertPageContains($browser, $productName);
        $this->assertPageContains($browser, $productPrice);
        
        // 6. Proceed to checkout
        $browser->click('.checkout-button');
        $browser->waitForElement('.checkout-page');
        
        // 7. Fill shipping information
        $browser->type('#email', 'test@example.com');
        $browser->type('#first_name', 'John');
        $browser->type('#last_name', 'Doe');
        $browser->type('#address_1', '123 Main St');
        $browser->type('#city', 'New York');
        $browser->select('#state', 'NY');
        $browser->type('#postal_code', '10001');
        $browser->select('#country', 'US');
        $browser->type('#phone', '+1234567890');
        
        $browser->click('.continue-to-shipping');
        $browser->waitForElement('.shipping-methods');
        
        // 8. Select shipping method
        $browser->click('input[name="shipping_method"][value="standard"]');
        $browser->click('.continue-to-payment');
        $browser->waitForElement('.payment-methods');
        
        // 9. Enter payment information (test card)
        $browser->click('input[name="payment_method"][value="stripe"]');
        $browser->waitForElement('.stripe-card-element');
        
        // Fill Stripe test card
        $browser->switchToFrame('.stripe-iframe');
        $browser->type('[name="cardnumber"]', '4242424242424242');
        $browser->type('[name="exp-date"]', '12/25');
        $browser->type('[name="cvc"]', '123');
        $browser->type('[name="postal"]', '10001');
        $browser->switchToMainFrame();
        
        // 10. Review and place order
        $browser->click('.place-order-button');
        $browser->waitForElement('.order-confirmation', 30); // Allow time for payment processing
        
        // 11. Verify order confirmation
        $this->assertPageContains($browser, 'Order Confirmed');
        $this->assertElementExists($browser, '.order-number');
        $this->assertPageContains($browser, $productName);
        $this->assertPageContains($browser, 'test@example.com');
        
        // Store order number for verification
        $orderNumber = $browser->getText('.order-number');
        
        // 12. Check order status
        $browser->click('.view-order-button');
        $browser->waitForElement('.order-details');
        
        $this->assertPageContains($browser, $orderNumber);
        $this->assertPageContains($browser, 'Processing');
        $this->assertElementExists($browser, '.order-timeline');
    }
    
    /**
     * Test guest checkout journey
     */
    public function testGuestCheckoutJourney(): void
    {
        $browser = $this->createBrowser();
        
        // Add product to cart
        $this->addProductToCart($browser);
        
        // Go to checkout as guest
        $browser->visit('/checkout');
        $browser->waitForElement('.checkout-page');
        
        // Verify guest checkout is available
        $this->assertElementExists($browser, '.guest-checkout-form');
        $this->assertNotElementExists($browser, '.account-required-message');
        
        // Complete checkout as guest
        $this->fillCheckoutForm($browser, [
            'email' => 'guest@example.com',
            'create_account' => false
        ]);
        
        // Verify order completion
        $browser->waitForElement('.order-confirmation');
        $this->assertPageContains($browser, 'Order Confirmed');
        $this->assertPageContains($browser, 'guest@example.com');
    }
    
    /**
     * Test customer account creation during checkout
     */
    public function testAccountCreationDuringCheckout(): void
    {
        $browser = $this->createBrowser();
        
        // Add product to cart
        $this->addProductToCart($browser);
        
        // Go to checkout
        $browser->visit('/checkout');
        $browser->waitForElement('.checkout-page');
        
        // Fill checkout with account creation
        $email = 'newcustomer' . time() . '@example.com';
        
        $browser->type('#email', $email);
        $browser->check('#create_account');
        $browser->type('#password', 'SecurePass123!');
        $browser->type('#password_confirmation', 'SecurePass123!');
        
        $this->fillCheckoutForm($browser, ['email' => $email]);
        
        // Verify order and account creation
        $browser->waitForElement('.order-confirmation');
        $this->assertPageContains($browser, 'Order Confirmed');
        $this->assertPageContains($browser, 'Account created successfully');
        
        // Verify can access account
        $browser->click('.my-account-link');
        $browser->waitForElement('.account-dashboard');
        
        $this->assertPageContains($browser, 'My Account');
        $this->assertPageContains($browser, $email);
    }
    
    /**
     * Test cart abandonment and recovery
     */
    public function testCartAbandonmentRecovery(): void
    {
        $browser = $this->createBrowser();
        
        // Create cart with items
        $this->addProductToCart($browser);
        
        // Get cart ID from cookie
        $cartId = $browser->getCookie('cart_session_id');
        
        // Navigate away (simulate abandonment)
        $browser->visit('/about');
        
        // Return after some time
        sleep(2); // Simulate time passing
        
        // Visit cart again
        $browser->visit('/cart');
        $browser->waitForElement('.cart-page');
        
        // Verify cart is recovered
        $this->assertElementCount($browser, '.cart-item', '>', 0);
        $this->assertPageContains($browser, 'Your cart');
        
        // Verify abandonment recovery features
        $this->assertElementExists($browser, '.save-for-later-button');
        $this->assertElementExists($browser, '.email-cart-button');
    }
    
    /**
     * Test product search and filtering journey
     */
    public function testProductSearchAndFiltering(): void
    {
        $browser = $this->createBrowser();
        
        // Visit category page
        $browser->visit('/category/electronics');
        $browser->waitForElement('.category-page');
        
        // Apply filters
        $browser->click('.filter-price-range');
        $browser->type('#price_min', '100');
        $browser->type('#price_max', '500');
        $browser->click('.apply-filters');
        
        $browser->waitForElement('.filtered-results');
        
        // Verify filters applied
        $this->assertPageContains($browser, 'Price: $100 - $500');
        $this->assertElementExists($browser, '.active-filters');
        
        // Apply additional filter
        $browser->check('input[name="brand[]"][value="apple"]');
        $browser->click('.apply-filters');
        
        $browser->waitForElement('.filtered-results');
        
        // Sort results
        $browser->select('.sort-dropdown', 'price_asc');
        $browser->waitForElement('.sorted-results');
        
        // Verify sorting
        $prices = $browser->getTexts('.product-price');
        $this->assertPricesAreSorted($prices, 'asc');
        
        // Clear filters
        $browser->click('.clear-filters');
        $browser->waitForElement('.category-page');
        
        $this->assertNotElementExists($browser, '.active-filters');
    }
    
    /**
     * Test wishlist functionality
     */
    public function testWishlistJourney(): void
    {
        $browser = $this->createBrowser();
        
        // Login first
        $this->loginCustomer($browser);
        
        // Browse products
        $browser->visit('/products');
        $browser->waitForElement('.products-grid');
        
        // Add items to wishlist
        $browser->click('.product-card:first-child .add-to-wishlist');
        $browser->waitForElement('.wishlist-notification');
        
        $this->assertPageContains($browser, 'Added to wishlist');
        
        // Add another item
        $browser->click('.product-card:nth-child(2) .add-to-wishlist');
        $browser->waitForElement('.wishlist-notification');
        
        // View wishlist
        $browser->click('.header-wishlist');
        $browser->waitForElement('.wishlist-page');
        
        $this->assertPageContains($browser, 'My Wishlist');
        $this->assertElementCount($browser, '.wishlist-item', 2);
        
        // Move item from wishlist to cart
        $browser->click('.wishlist-item:first-child .move-to-cart');
        $browser->waitForElement('.cart-notification');
        
        $this->assertPageContains($browser, 'Moved to cart');
        $this->assertElementCount($browser, '.wishlist-item', 1);
        
        // Share wishlist
        $browser->click('.share-wishlist');
        $browser->waitForElement('.share-modal');
        
        $this->assertElementExists($browser, '.share-link');
        $this->assertElementExists($browser, '.share-email');
    }
    
    /**
     * Test multi-currency shopping
     */
    public function testMultiCurrencyShopping(): void
    {
        $browser = $this->createBrowser();
        
        // Visit homepage
        $browser->visit('/');
        
        // Change currency
        $browser->click('.currency-selector');
        $browser->click('option[value="EUR"]');
        $browser->waitForElement('.currency-updated');
        
        // Verify prices updated
        $this->assertElementTextContains($browser, '.product-price', '€');
        
        // Add product to cart
        $this->addProductToCart($browser);
        
        // Verify cart shows correct currency
        $browser->visit('/cart');
        $browser->waitForElement('.cart-page');
        
        $this->assertElementTextContains($browser, '.cart-total', '€');
        
        // Proceed to checkout
        $browser->click('.checkout-button');
        $browser->waitForElement('.checkout-page');
        
        // Verify checkout maintains currency
        $this->assertElementTextContains($browser, '.order-total', '€');
    }
    
    /**
     * Test mobile responsive journey
     */
    public function testMobileShoppingJourney(): void
    {
        // Create mobile browser
        $browser = $this->createBrowser([
            'viewport' => ['width' => 375, 'height' => 667],
            'userAgent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) Mobile/15E148'
        ]);
        
        // Visit homepage
        $browser->visit('/');
        $browser->waitForElement('.mobile-menu-toggle');
        
        // Open mobile menu
        $browser->click('.mobile-menu-toggle');
        $browser->waitForElement('.mobile-menu');
        
        $this->assertElementVisible($browser, '.mobile-menu');
        
        // Navigate to category
        $browser->click('.mobile-menu .category-link');
        $browser->waitForElement('.category-page');
        
        // Verify mobile-optimized layout
        $this->assertElementExists($browser, '.mobile-filter-toggle');
        $this->assertElementCss($browser, '.products-grid', 'grid-template-columns', '1fr');
        
        // Open filters
        $browser->click('.mobile-filter-toggle');
        $browser->waitForElement('.mobile-filters');
        
        $this->assertElementVisible($browser, '.mobile-filters');
        
        // Add to cart from mobile
        $browser->click('.product-card:first-child');
        $browser->waitForElement('.product-details');
        
        $browser->click('.mobile-add-to-cart');
        $browser->waitForElement('.mobile-cart-notification');
        
        // Complete mobile checkout
        $browser->click('.mobile-cart-icon');
        $browser->waitForElement('.mobile-cart');
        
        $browser->click('.mobile-checkout');
        $this->completeMobileCheckout($browser);
    }
    
    /**
     * Helper: Add product to cart
     */
    private function addProductToCart(Browser $browser): void
    {
        $browser->visit('/products');
        $browser->waitForElement('.products-grid');
        $browser->click('.product-card:first-child .quick-add-to-cart');
        $browser->waitForElement('.cart-notification');
    }
    
    /**
     * Helper: Fill checkout form
     */
    private function fillCheckoutForm(Browser $browser, array $overrides = []): void
    {
        $data = array_merge([
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'address_1' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'US',
            'phone' => '+1234567890'
        ], $overrides);
        
        foreach ($data as $field => $value) {
            if ($field !== 'create_account') {
                $browser->type("#$field", $value);
            }
        }
        
        // Continue through checkout steps
        $browser->click('.continue-to-shipping');
        $browser->waitForElement('.shipping-methods');
        $browser->click('input[value="standard"]');
        
        $browser->click('.continue-to-payment');
        $browser->waitForElement('.payment-methods');
        
        // Use test payment
        $browser->click('input[value="test"]');
        $browser->click('.place-order-button');
    }
    
    /**
     * Helper: Login customer
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
     * Helper: Complete mobile checkout
     */
    private function completeMobileCheckout(Browser $browser): void
    {
        // Simplified mobile checkout flow
        $browser->waitForElement('.mobile-checkout-form');
        
        $browser->type('#mobile_email', 'mobile@example.com');
        $browser->type('#mobile_phone', '+1234567890');
        
        // Use mobile payment options
        $browser->click('.apple-pay-button');
        
        // Simulate payment completion
        $browser->waitForElement('.mobile-order-confirmation');
    }
    
    /**
     * Helper: Assert prices are sorted
     */
    private function assertPricesAreSorted(array $prices, string $direction): void
    {
        $numericPrices = array_map(function($price) {
            return (float) preg_replace('/[^0-9.]/', '', $price);
        }, $prices);
        
        $sorted = $numericPrices;
        if ($direction === 'asc') {
            sort($sorted);
        } else {
            rsort($sorted);
        }
        
        $this->assertEquals($sorted, $numericPrices, 'Prices are not sorted correctly');
    }
}