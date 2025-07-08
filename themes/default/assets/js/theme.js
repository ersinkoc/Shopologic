// Default Theme JavaScript

(function() {
    'use strict';

    // Shopping Cart functionality
    const cart = {
        items: [],
        total: 0,
        
        init() {
            this.loadFromStorage();
            this.updateUI();
            this.bindEvents();
        },
        
        loadFromStorage() {
            const savedCart = localStorage.getItem('shopologic_cart');
            if (savedCart) {
                const data = JSON.parse(savedCart);
                this.items = data.items || [];
                this.total = data.total || 0;
            }
        },
        
        saveToStorage() {
            localStorage.setItem('shopologic_cart', JSON.stringify({
                items: this.items,
                total: this.total
            }));
        },
        
        addItem(product) {
            const existingItem = this.items.find(item => item.id === product.id);
            
            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                this.items.push({
                    ...product,
                    quantity: 1
                });
            }
            
            this.calculateTotal();
            this.saveToStorage();
            this.updateUI();
            this.showNotification('Product added to cart');
        },
        
        removeItem(productId) {
            this.items = this.items.filter(item => item.id !== productId);
            this.calculateTotal();
            this.saveToStorage();
            this.updateUI();
        },
        
        updateQuantity(productId, quantity) {
            const item = this.items.find(item => item.id === productId);
            if (item) {
                item.quantity = Math.max(1, quantity);
                this.calculateTotal();
                this.saveToStorage();
                this.updateUI();
            }
        },
        
        calculateTotal() {
            this.total = this.items.reduce((sum, item) => {
                return sum + (item.price * item.quantity);
            }, 0);
        },
        
        updateUI() {
            // Update cart count
            const cartCount = document.querySelector('.cart-count');
            if (cartCount) {
                const itemCount = this.items.reduce((sum, item) => sum + item.quantity, 0);
                cartCount.textContent = itemCount;
                cartCount.style.display = itemCount > 0 ? 'flex' : 'none';
            }
            
            // Update cart dropdown or page if exists
            this.updateCartDisplay();
        },
        
        updateCartDisplay() {
            const cartDisplay = document.querySelector('.cart-items');
            if (!cartDisplay) return;
            
            if (this.items.length === 0) {
                cartDisplay.innerHTML = '<p class="empty-cart">Your cart is empty</p>';
                return;
            }
            
            const itemsHTML = this.items.map(item => `
                <div class="cart-item" data-product-id="${item.id}">
                    <img src="${item.image}" alt="${item.name}" class="cart-item-image">
                    <div class="cart-item-details">
                        <h4>${item.name}</h4>
                        <p class="cart-item-price">$${item.price.toFixed(2)}</p>
                    </div>
                    <div class="cart-item-quantity">
                        <button class="quantity-btn minus" data-product-id="${item.id}">-</button>
                        <input type="number" value="${item.quantity}" min="1" class="quantity-input" data-product-id="${item.id}">
                        <button class="quantity-btn plus" data-product-id="${item.id}">+</button>
                    </div>
                    <button class="cart-item-remove" data-product-id="${item.id}">×</button>
                </div>
            `).join('');
            
            cartDisplay.innerHTML = itemsHTML;
            
            // Update total
            const cartTotal = document.querySelector('.cart-total-amount');
            if (cartTotal) {
                cartTotal.textContent = `$${this.total.toFixed(2)}`;
            }
        },
        
        bindEvents() {
            // Add to cart buttons
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('btn-add-to-cart')) {
                    e.preventDefault();
                    const productCard = e.target.closest('.product-card');
                    if (productCard) {
                        const product = {
                            id: productCard.dataset.productId,
                            name: productCard.querySelector('.product-title').textContent,
                            price: parseFloat(productCard.querySelector('.product-price').textContent.replace('$', '')),
                            image: productCard.querySelector('.product-image').src
                        };
                        this.addItem(product);
                    }
                }
                
                // Remove from cart
                if (e.target.classList.contains('cart-item-remove')) {
                    const productId = e.target.dataset.productId;
                    this.removeItem(productId);
                }
                
                // Quantity buttons
                if (e.target.classList.contains('quantity-btn')) {
                    const productId = e.target.dataset.productId;
                    const input = document.querySelector(`.quantity-input[data-product-id="${productId}"]`);
                    let quantity = parseInt(input.value);
                    
                    if (e.target.classList.contains('plus')) {
                        quantity += 1;
                    } else if (e.target.classList.contains('minus')) {
                        quantity -= 1;
                    }
                    
                    this.updateQuantity(productId, quantity);
                }
            });
            
            // Quantity input change
            document.addEventListener('change', (e) => {
                if (e.target.classList.contains('quantity-input')) {
                    const productId = e.target.dataset.productId;
                    const quantity = parseInt(e.target.value);
                    this.updateQuantity(productId, quantity);
                }
            });
        },
        
        showNotification(message) {
            const notification = document.createElement('div');
            notification.className = 'notification';
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.add('show');
            }, 10);
            
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }
    };

    // Mobile menu toggle
    const mobileMenu = {
        init() {
            const menuToggle = document.querySelector('.mobile-menu-toggle');
            const navMenu = document.querySelector('.nav-menu');
            
            if (menuToggle && navMenu) {
                menuToggle.addEventListener('click', () => {
                    navMenu.classList.toggle('active');
                    menuToggle.classList.toggle('active');
                });
            }
        }
    };

    // Product image gallery
    const productGallery = {
        init() {
            const mainImage = document.querySelector('.product-main-image');
            const thumbnails = document.querySelectorAll('.product-thumbnail');
            
            thumbnails.forEach(thumb => {
                thumb.addEventListener('click', () => {
                    if (mainImage) {
                        mainImage.src = thumb.dataset.fullImage;
                        thumbnails.forEach(t => t.classList.remove('active'));
                        thumb.classList.add('active');
                    }
                });
            });
        }
    };

    // Search functionality
    const search = {
        init() {
            const searchForm = document.querySelector('.search-form');
            const searchInput = document.querySelector('.search-input');
            
            if (searchForm) {
                searchForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const query = searchInput.value.trim();
                    if (query) {
                        window.location.href = `/search?q=${encodeURIComponent(query)}`;
                    }
                });
            }
        }
    };

    // Initialize all modules when DOM is ready
    document.addEventListener('DOMContentLoaded', () => {
        cart.init();
        mobileMenu.init();
        productGallery.init();
        search.init();
        
        // Lazy load images
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.add('loaded');
                        observer.unobserve(img);
                    }
                });
            });
            
            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    });

    // Make cart available globally
    window.ShopologicCart = cart;
})();