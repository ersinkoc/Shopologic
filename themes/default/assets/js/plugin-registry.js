// Shopologic Plugin Registry
window.ShopologicPlugins = {
    "analytics": {
        "google_analytics": {
            "enabled": true,
            "id": "GA_MEASUREMENT_ID"
        }
    },
    "features": {
        "product_reviews": true,
        "wishlist": true,
        "live_chat": false,
        "social_proof": true,
        "multi_currency": true,
        "search_autocomplete": true,
        "recommendation_engine": true
    },
    "integrations": {
        "search_autocomplete": true,
        "recommendation_engine": true,
        "email_marketing": true,
        "inventory_tracking": true,
        "price_optimization": true,
        "customer_segmentation": true
    },
    "hooks": [
        "product.viewed",
        "product.added_to_cart",
        "cart.updated",
        "order.created",
        "search.query",
        "customer.login"
    ]
};

// Plugin event tracking functions
window.ShopologicPlugins.trackEvent = function(event, data) {
    // Send AJAX request to track plugin events
    console.log('[Shopologic Plugin]', event, data);
};

window.ShopologicPlugins.trackProductView = function(productId) {
    this.trackEvent('product.viewed', {product_id: productId});
};

window.ShopologicPlugins.trackAddToCart = function(productId, quantity) {
    this.trackEvent('product.added_to_cart', {product_id: productId, quantity: quantity});
};
