<header class="site-header">
    <div class="header-top">
        <div class="container">
            <div class="header-top-content">
                <div class="welcome-message">
                    Welcome to Shopologic!
                </div>
                <nav class="header-nav">
                    <ul>
                        <?php
                        // Get auth status from session
                        session_start();
                        $isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['user_email']);
                        $userName = $_SESSION['user_name'] ?? '';
                        ?>
                        
                        <?php if ($isLoggedIn): ?>
                            <li>
                                <span class="welcome-user">Welcome, <?php echo $this->e(explode(' ', $userName)[0]); ?>!</span>
                            </li>
                            <li><a href="/account">My Account</a></li>
                            <li><a href="/auth/logout">Logout</a></li>
                        <?php else: ?>
                            <li><a href="/auth/login">Login</a></li>
                            <li><a href="/auth/register">Register</a></li>
                        <?php endif; ?>
                        <li><a href="/cart">Cart (<span class="cart-count" id="header-cart-count">0</span>)</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
    
    <div class="header-main">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="/">
                        <h1>Shopologic</h1>
                    </a>
                </div>
                
                <div class="search-bar">
                    <form action="/search" method="get">
                        <input type="text" name="q" placeholder="Search products..." value="<?php echo $this->e($_GET['q'] ?? ''); ?>">
                        <button type="submit">Search</button>
                    </form>
                </div>
                
                <div class="header-actions">
                    <a href="/account/wishlist" class="wishlist-link">
                        <span class="icon">♥</span>
                        <span class="count">0</span>
                    </a>
                    <a href="/cart" class="cart-link">
                        <span class="icon">🛒</span>
                        <span class="count" id="header-cart-count-icon">0</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <nav class="main-navigation">
        <div class="container">
            <ul class="nav-menu">
                <li><a href="/">Home</a></li>
                <li><a href="/products">All Products</a></li>
                <li class="dropdown">
                    <a href="/categories" class="dropdown-toggle">Categories <span class="dropdown-arrow">▼</span></a>
                    <ul class="dropdown-menu">
                        <li><a href="/categories">Browse All Categories</a></li>
                        <li class="divider"></li>
                        <li><a href="/category/electronics">💻 Electronics</a></li>
                        <li><a href="/category/clothing">👕 Clothing</a></li>
                        <li><a href="/category/books">📚 Books</a></li>
                        <li><a href="/category/home">🏠 Home & Garden</a></li>
                        <li><a href="/category/sports">⚽ Sports & Outdoors</a></li>
                        <li><a href="/category/toys">🧸 Toys & Games</a></li>
                        <li><a href="/category/beauty">💄 Beauty & Personal Care</a></li>
                        <li><a href="/category/automotive">🚗 Automotive</a></li>
                    </ul>
                </li>
                <li><a href="/search">Search</a></li>
                <li><a href="/deals">Deals</a></li>
                <li><a href="/contact">Contact</a></li>
            </ul>
        </div>
    </nav>
    
    <?php $this->do_action('header.after_navigation'); ?>
</header>