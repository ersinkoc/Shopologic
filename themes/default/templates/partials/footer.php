<footer class="site-footer">
    <div class="footer-widgets">
        <div class="container">
            <div class="footer-columns">
                <div class="footer-column">
                    <h3>About Shopologic</h3>
                    <p>Your trusted e-commerce platform for quality products at great prices. Shop with confidence!</p>
                    <div class="social-links">
                        <a href="#" aria-label="Facebook">📘</a>
                        <a href="#" aria-label="Twitter">🐦</a>
                        <a href="#" aria-label="Instagram">📷</a>
                        <a href="#" aria-label="YouTube">📺</a>
                    </div>
                </div>
                
                <div class="footer-column">
                    <h3>Customer Service</h3>
                    <ul>
                        <li><a href="<?php echo $this->url('help'); ?>">Help Center</a></li>
                        <li><a href="<?php echo $this->url('shipping'); ?>">Shipping Info</a></li>
                        <li><a href="<?php echo $this->url('returns'); ?>">Returns</a></li>
                        <li><a href="<?php echo $this->url('track-order'); ?>">Track Order</a></li>
                        <li><a href="<?php echo $this->url('contact'); ?>">Contact Us</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Information</h3>
                    <ul>
                        <li><a href="<?php echo $this->url('about'); ?>">About Us</a></li>
                        <li><a href="<?php echo $this->url('privacy'); ?>">Privacy Policy</a></li>
                        <li><a href="<?php echo $this->url('terms'); ?>">Terms of Service</a></li>
                        <li><a href="<?php echo $this->url('sitemap'); ?>">Sitemap</a></li>
                        <li><a href="<?php echo $this->url('careers'); ?>">Careers</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Newsletter</h3>
                    <p>Subscribe to get special offers and updates!</p>
                    <form action="<?php echo $this->url('newsletter/subscribe'); ?>" method="post" class="newsletter-form">
                        <?php echo $this->csrf_field(); ?>
                        <input type="email" name="email" placeholder="Your email address" required>
                        <button type="submit">Subscribe</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="footer-bottom">
        <div class="container">
            <div class="footer-bottom-content">
                <div class="copyright">
                    &copy; <?php echo date('Y'); ?> Shopologic. All rights reserved.
                </div>
                <div class="payment-methods">
                    <span>We accept:</span>
                    <img src="<?php echo $this->theme_asset('images/payment-methods.png'); ?>" alt="Payment Methods">
                </div>
            </div>
        </div>
    </div>
    
    <?php $this->do_action('footer.bottom'); ?>
</footer>