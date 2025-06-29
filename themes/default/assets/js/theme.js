/**
 * Shopologic Default Theme JavaScript
 */

(function() {
    'use strict';

    // Theme object
    const ShopologicTheme = {
        // Initialize theme
        init: function() {
            this.initMobileMenu();
            this.initSearch();
            this.initMiniCart();
            this.initProductCards();
            this.initNewsletter();
            this.initSmoothScroll();
            
            // Trigger custom event
            document.dispatchEvent(new CustomEvent('shopologic-theme-init'));
        },

        // Mobile menu toggle
        initMobileMenu: function() {
            const menuToggle = document.createElement('button');
            menuToggle.className = 'mobile-menu-toggle';
            menuToggle.innerHTML = '<span></span><span></span><span></span>';
            menuToggle.setAttribute('aria-label', 'Toggle navigation');
            
            const mainNav = document.querySelector('.main-navigation');
            if (mainNav) {
                mainNav.prepend(menuToggle);
                
                menuToggle.addEventListener('click', function() {
                    mainNav.classList.toggle('is-open');
                    menuToggle.classList.toggle('is-active');
                });
            }
        },

        // Enhanced search functionality
        initSearch: function() {
            const searchForm = document.querySelector('.search-form');
            const searchInput = document.querySelector('.search-input');
            
            if (searchForm && searchInput) {
                // Auto-complete placeholder
                const placeholders = [
                    'Search for products...',
                    'Find what you need...',
                    'Discover amazing deals...'
                ];
                let currentIndex = 0;
                
                setInterval(function() {
                    currentIndex = (currentIndex + 1) % placeholders.length;
                    searchInput.setAttribute('placeholder', placeholders[currentIndex]);
                }, 3000);
                
                // Search suggestions (would connect to API)
                searchInput.addEventListener('input', debounce(function(e) {
                    const query = e.target.value;
                    if (query.length > 2) {
                        // Fetch suggestions
                        console.log('Fetching suggestions for:', query);
                    }
                }, 300));
            }
        },

        // Mini cart interactions
        initMiniCart: function() {
            const miniCart = document.querySelector('.mini-cart');
            
            if (miniCart) {
                // Add hover preview
                let cartPreview = null;
                
                miniCart.addEventListener('mouseenter', function() {
                    // Show cart preview
                    if (!cartPreview) {
                        cartPreview = document.createElement('div');
                        cartPreview.className = 'cart-preview';
                        cartPreview.innerHTML = '<div class="loading">Loading cart...</div>';
                        miniCart.appendChild(cartPreview);
                        
                        // Fetch cart contents
                        setTimeout(function() {
                            cartPreview.innerHTML = `
                                <div class="cart-preview-items">
                                    <p>Your cart is empty</p>
                                </div>
                                <div class="cart-preview-actions">
                                    <a href="/cart" class="btn btn-primary">View Cart</a>
                                </div>
                            `;
                        }, 500);
                    }
                });
                
                miniCart.addEventListener('mouseleave', function() {
                    if (cartPreview) {
                        setTimeout(function() {
                            if (cartPreview) {
                                cartPreview.remove();
                                cartPreview = null;
                            }
                        }, 300);
                    }
                });
            }
        },

        // Product card interactions
        initProductCards: function() {
            const productCards = document.querySelectorAll('.product-card');
            
            productCards.forEach(function(card) {
                // Quick view on hover
                const quickView = document.createElement('div');
                quickView.className = 'quick-view';
                quickView.innerHTML = '<button class="quick-view-btn">Quick View</button>';
                card.appendChild(quickView);
                
                // Add to cart AJAX
                const addToCartBtn = card.querySelector('.add-to-cart');
                if (addToCartBtn) {
                    addToCartBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        const btn = this;
                        const originalText = btn.textContent;
                        
                        // Show loading state
                        btn.textContent = 'Adding...';
                        btn.disabled = true;
                        
                        // Simulate AJAX request
                        setTimeout(function() {
                            btn.textContent = 'Added!';
                            btn.classList.add('success');
                            
                            // Update mini cart count
                            const cartCount = document.querySelector('.cart-count');
                            if (cartCount) {
                                const currentCount = parseInt(cartCount.textContent) || 0;
                                cartCount.textContent = currentCount + 1;
                            }
                            
                            // Reset button
                            setTimeout(function() {
                                btn.textContent = originalText;
                                btn.disabled = false;
                                btn.classList.remove('success');
                            }, 2000);
                        }, 1000);
                    });
                }
            });
        },

        // Newsletter form
        initNewsletter: function() {
            const newsletterForms = document.querySelectorAll('.newsletter-form');
            
            newsletterForms.forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const emailInput = form.querySelector('input[type="email"]');
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalText = submitBtn.textContent;
                    
                    // Validate email
                    if (!emailInput.value || !emailInput.validity.valid) {
                        emailInput.classList.add('error');
                        return;
                    }
                    
                    // Show loading
                    submitBtn.textContent = 'Subscribing...';
                    submitBtn.disabled = true;
                    
                    // Simulate AJAX
                    setTimeout(function() {
                        submitBtn.textContent = 'Subscribed!';
                        submitBtn.classList.add('success');
                        emailInput.value = '';
                        
                        // Show success message
                        const successMsg = document.createElement('div');
                        successMsg.className = 'newsletter-success';
                        successMsg.textContent = 'Thank you for subscribing!';
                        form.appendChild(successMsg);
                        
                        // Reset
                        setTimeout(function() {
                            submitBtn.textContent = originalText;
                            submitBtn.disabled = false;
                            submitBtn.classList.remove('success');
                            successMsg.remove();
                        }, 3000);
                    }, 1500);
                });
            });
        },

        // Smooth scroll for anchor links
        initSmoothScroll: function() {
            document.addEventListener('click', function(e) {
                const link = e.target.closest('a[href^="#"]');
                if (link) {
                    e.preventDefault();
                    const targetId = link.getAttribute('href').slice(1);
                    const target = document.getElementById(targetId);
                    
                    if (target) {
                        const headerHeight = document.querySelector('.site-header').offsetHeight;
                        const targetPosition = target.offsetTop - headerHeight - 20;
                        
                        window.scrollTo({
                            top: targetPosition,
                            behavior: 'smooth'
                        });
                    }
                }
            });
        }
    };

    // Utility functions
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            ShopologicTheme.init();
        });
    } else {
        ShopologicTheme.init();
    }

    // Export for use in other scripts
    window.ShopologicTheme = ShopologicTheme;
})();