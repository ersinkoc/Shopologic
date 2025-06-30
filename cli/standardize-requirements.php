#!/usr/bin/env php
<?php

declare(strict_types=1);

// ANSI color codes
const COLOR_RED = "\033[31m";
const COLOR_GREEN = "\033[32m";
const COLOR_YELLOW = "\033[33m";
const COLOR_BLUE = "\033[34m";
const COLOR_RESET = "\033[0m";

function printColored(string $text, string $color): void {
    echo $color . $text . COLOR_RESET;
}

function printHeader(string $text): void {
    echo "\n";
    printColored("=== {$text} ===\n", COLOR_BLUE);
}

class RequirementsStandardizer {
    private array $stats = [
        'processed' => 0,
        'updated' => 0,
        'errors' => 0,
        'backups' => 0
    ];
    
    private array $pluginMappings = [
        // Core mappings
        'core' => 'shopologic/core',
        'core-commerce' => 'shopologic/commerce',
        
        // Plugin mappings (add shopologic prefix)
        'analytics' => 'shopologic/analytics',
        'inventory-management' => 'shopologic/inventory',
        'shipping-manager' => 'shopologic/shipping',
        'tax-compliance' => 'shopologic/tax',
        'payment-processing' => 'shopologic/payment',
        'catalog' => 'shopologic/catalog',
        'customer-loyalty' => 'shopologic/loyalty',
        'multi-language' => 'shopologic/i18n',
        'multi-currency' => 'shopologic/currency',
        'reporting' => 'shopologic/reporting',
        'advanced-search' => 'shopologic/search',
        'cache-manager' => 'shopologic/cache',
        'security-hardening' => 'shopologic/security',
        'api-gateway' => 'shopologic/api',
        'webhook-manager' => 'shopologic/webhooks',
        'email-templates' => 'shopologic/email',
        'media-library' => 'shopologic/media',
        'social-commerce' => 'shopologic/social',
        'order-management' => 'shopologic/orders',
        'customer-support' => 'shopologic/support',
        'abandoned-cart' => 'shopologic/cart-recovery',
        'wishlist' => 'shopologic/wishlist',
        'product-reviews' => 'shopologic/reviews',
        'affiliate-program' => 'shopologic/affiliates',
        'subscription-commerce' => 'shopologic/subscriptions',
        'marketplace' => 'shopologic/marketplace',
        'mobile-app-bridge' => 'shopologic/mobile',
        'pwa-support' => 'shopologic/pwa',
        'graphql-api' => 'shopologic/graphql',
        'elasticsearch' => 'shopologic/search-elastic',
        'redis-cache' => 'shopologic/cache-redis',
        'cdn-integration' => 'shopologic/cdn',
        'backup-restore' => 'shopologic/backup',
        'import-export' => 'shopologic/data-transfer',
        'bulk-operations' => 'shopologic/bulk-ops',
        'workflow-automation' => 'shopologic/workflows',
        'custom-fields' => 'shopologic/fields',
        'role-permissions' => 'shopologic/permissions',
        'audit-log' => 'shopologic/audit',
        'notification-center' => 'shopologic/notifications',
        'dashboard-widgets' => 'shopologic/dashboard',
        'theme-customizer' => 'shopologic/themes',
        'page-builder' => 'shopologic/pages',
        'seo-tools' => 'shopologic/seo',
        'sitemap-generator' => 'shopologic/sitemap',
        'redirects-manager' => 'shopologic/redirects',
        'forms-builder' => 'shopologic/forms',
        'surveys-feedback' => 'shopologic/feedback',
        'knowledge-base' => 'shopologic/kb',
        'blog-cms' => 'shopologic/blog',
        'event-calendar' => 'shopologic/events',
        'store-locator' => 'shopologic/locations',
        'gift-cards' => 'shopologic/gift-cards',
        'bundle-products' => 'shopologic/bundles',
        'variant-manager' => 'shopologic/variants',
        'stock-alerts' => 'shopologic/stock-alerts',
        'price-alerts' => 'shopologic/price-alerts',
        'compare-products' => 'shopologic/compare',
        'quick-view' => 'shopologic/quick-view',
        'size-guide' => 'shopologic/size-guide',
        'product-videos' => 'shopologic/media-video',
        '360-view' => 'shopologic/media-360',
        'ar-preview' => 'shopologic/ar',
        'chatbot' => 'shopologic/chat',
        'live-chat' => 'shopologic/chat-live',
        'help-desk' => 'shopologic/helpdesk',
        'faq-manager' => 'shopologic/faq',
        'testimonials' => 'shopologic/testimonials',
        'referral-program' => 'shopologic/referrals',
        'points-rewards' => 'shopologic/rewards',
        'vip-tiers' => 'shopologic/vip',
        'flash-sales' => 'shopologic/flash-sales',
        'daily-deals' => 'shopologic/deals',
        'coupon-manager' => 'shopologic/coupons',
        'discount-rules' => 'shopologic/discounts',
        'free-shipping' => 'shopologic/shipping-free',
        'shipping-zones' => 'shopologic/shipping-zones',
        'pickup-locations' => 'shopologic/pickup',
        'delivery-slots' => 'shopologic/delivery',
        'tracking-info' => 'shopologic/tracking',
        'return-rma' => 'shopologic/returns',
        'exchange-manager' => 'shopologic/exchanges',
        'warranty-tracker' => 'shopologic/warranty',
        'invoice-generator' => 'shopologic/invoices',
        'packing-slips' => 'shopologic/packing',
        'barcode-scanner' => 'shopologic/barcode',
        'qr-codes' => 'shopologic/qr',
        'pos-integration' => 'shopologic/pos',
        'erp-connector' => 'shopologic/erp',
        'crm-integration' => 'shopologic/crm',
        'accounting-sync' => 'shopologic/accounting',
        'warehouse-management' => 'shopologic/warehouse',
        'dropshipping' => 'shopologic/dropship',
        'print-on-demand' => 'shopologic/pod',
        'digital-downloads' => 'shopologic/downloads',
        'licensing-keys' => 'shopologic/licenses',
        'memberships' => 'shopologic/memberships',
        'courses-lms' => 'shopologic/courses',
        'appointments' => 'shopologic/appointments',
        'bookings' => 'shopologic/bookings',
        'rentals' => 'shopologic/rentals',
        'auctions' => 'shopologic/auctions',
        'quotations' => 'shopologic/quotes',
        'b2b-features' => 'shopologic/b2b',
        'wholesale' => 'shopologic/wholesale',
        'vendors-multi' => 'shopologic/vendors',
        'commissions' => 'shopologic/commissions',
        'payouts' => 'shopologic/payouts',
        'tax-exemptions' => 'shopologic/tax-exempt',
        'eu-vat' => 'shopologic/vat-eu',
        'sales-tax' => 'shopologic/tax-sales',
        'crypto-payments' => 'shopologic/crypto',
        'buy-now-pay-later' => 'shopologic/bnpl',
        'recurring-billing' => 'shopologic/billing',
        'split-payments' => 'shopologic/split-pay',
        'fraud-detection' => 'shopologic/fraud',
        'age-verification' => 'shopologic/age-verify',
        'gdpr-compliance' => 'shopologic/gdpr',
        'cookie-consent' => 'shopologic/cookies',
        'terms-conditions' => 'shopologic/legal',
        'privacy-center' => 'shopologic/privacy',
        'data-export' => 'shopologic/data-export',
        'data-anonymization' => 'shopologic/anonymize',
        'activity-feed' => 'shopologic/activity',
        'social-login' => 'shopologic/auth-social',
        'two-factor-auth' => 'shopologic/auth-2fa',
        'sso-integration' => 'shopologic/auth-sso',
        'password-policy' => 'shopologic/auth-password',
        'ip-blocking' => 'shopologic/security-ip',
        'rate-limiting' => 'shopologic/security-rate',
        'captcha' => 'shopologic/security-captcha',
        'ssl-manager' => 'shopologic/security-ssl',
        'performance-monitor' => 'shopologic/monitor',
        'error-tracking' => 'shopologic/errors',
        'uptime-monitor' => 'shopologic/uptime',
        'analytics-dashboard' => 'shopologic/analytics-dash',
        'conversion-tracking' => 'shopologic/conversions',
        'heatmaps' => 'shopologic/heatmaps',
        'session-replay' => 'shopologic/sessions',
        'custom-reports' => 'shopologic/reports-custom',
        'scheduled-reports' => 'shopologic/reports-scheduled',
        'data-visualization' => 'shopologic/charts',
        'export-formats' => 'shopologic/export',
        'api-docs' => 'shopologic/docs-api',
        'developer-tools' => 'shopologic/dev-tools',
        'code-editor' => 'shopologic/editor',
        'database-manager' => 'shopologic/database',
        'file-manager' => 'shopologic/files',
        'image-optimizer' => 'shopologic/images',
        'lazy-loading' => 'shopologic/performance',
        'minification' => 'shopologic/optimize',
        'browser-caching' => 'shopologic/cache-browser',
        'gzip-compression' => 'shopologic/compress',
        'amp-pages' => 'shopologic/amp',
        'structured-data' => 'shopologic/schema',
        'rich-snippets' => 'shopologic/snippets',
        'meta-tags' => 'shopologic/meta',
        'canonical-urls' => 'shopologic/canonical',
        'breadcrumbs' => 'shopologic/breadcrumbs',
        'xml-feeds' => 'shopologic/feeds',
        'product-feeds' => 'shopologic/feeds-products',
        'price-comparison' => 'shopologic/comparison',
        'affiliate-feeds' => 'shopologic/feeds-affiliate',
        'social-sharing' => 'shopologic/share',
        'wishlist-sharing' => 'shopologic/share-wishlist',
        'gift-registry' => 'shopologic/registry',
        'product-questions' => 'shopologic/qa',
        'community-forum' => 'shopologic/forum',
        'user-generated' => 'shopologic/ugc',
        'influencer-tools' => 'shopologic/influencers',
        'email-campaigns' => 'shopologic/campaigns',
        'push-notifications' => 'shopologic/push',
        'sms-marketing' => 'shopologic/sms',
        'whatsapp-integration' => 'shopologic/whatsapp',
        'messenger-integration' => 'shopologic/messenger',
        'telegram-bot' => 'shopologic/telegram',
        'voice-commerce' => 'shopologic/voice',
        'smart-recommendations' => 'shopologic/recommendations',
        'upsell-crosssell' => 'shopologic/upsell',
        'abandoned-browse' => 'shopologic/browse-recovery',
        'exit-intent' => 'shopologic/exit-intent',
        'countdown-timers' => 'shopologic/timers',
        'stock-countdown' => 'shopologic/stock-urgency',
        'social-proof' => 'shopologic/social-proof',
        'trust-badges' => 'shopologic/trust',
        'security-badges' => 'shopologic/badges',
        'payment-logos' => 'shopologic/payment-logos',
        'shipping-badges' => 'shopologic/shipping-badges',
        'eco-badges' => 'shopologic/eco',
        'accessibility' => 'shopologic/a11y',
        'translation-manager' => 'shopologic/translate',
        'currency-converter' => 'shopologic/convert',
        'geo-targeting' => 'shopologic/geo',
        'country-blocker' => 'shopologic/geo-block',
        'maintenance-mode' => 'shopologic/maintenance',
        'coming-soon' => 'shopologic/coming-soon',
        'waitlist' => 'shopologic/waitlist',
        'pre-orders' => 'shopologic/preorders',
        'backorders' => 'shopologic/backorders',
        'request-quote' => 'shopologic/quotes-request',
        'minimum-order' => 'shopologic/order-minimum',
        'maximum-order' => 'shopologic/order-maximum',
        'order-limits' => 'shopologic/order-limits',
        'quantity-breaks' => 'shopologic/quantity',
        'tiered-pricing' => 'shopologic/pricing-tiers',
        'customer-pricing' => 'shopologic/pricing-custom',
        'hide-prices' => 'shopologic/pricing-hide',
        'call-for-price' => 'shopologic/pricing-call',
        'make-an-offer' => 'shopologic/offers',
        'price-match' => 'shopologic/price-match',
        'best-price-guarantee' => 'shopologic/guarantee',
        'warranty-upsell' => 'shopologic/warranty-sell',
        'insurance-upsell' => 'shopologic/insurance',
        'gift-wrapping' => 'shopologic/gift-wrap',
        'gift-messages' => 'shopologic/gift-message',
        'order-notes' => 'shopologic/order-notes',
        'delivery-instructions' => 'shopologic/delivery-notes',
        'signature-required' => 'shopologic/signature',
        'age-verification-delivery' => 'shopologic/age-delivery',
        'contactless-delivery' => 'shopologic/contactless',
        'white-glove-delivery' => 'shopologic/white-glove',
        'installation-service' => 'shopologic/installation',
        'assembly-service' => 'shopologic/assembly',
        'removal-service' => 'shopologic/removal',
        'trade-in' => 'shopologic/trade-in',
        'recycling' => 'shopologic/recycle',
        'carbon-offset' => 'shopologic/carbon',
        'charity-donation' => 'shopologic/charity',
        'round-up' => 'shopologic/round-up',
        'loyalty-integration' => 'shopologic/loyalty-api',
        'gamification' => 'shopologic/games',
        'spin-wheel' => 'shopologic/spin',
        'scratch-cards' => 'shopologic/scratch',
        'treasure-hunt' => 'shopologic/treasure',
        'daily-rewards' => 'shopologic/daily',
        'streak-rewards' => 'shopologic/streaks',
        'challenges' => 'shopologic/challenges',
        'leaderboards' => 'shopologic/leaderboard',
        'badges-achievements' => 'shopologic/achievements',
        'virtual-try-on' => 'shopologic/try-on',
        'size-recommendation' => 'shopologic/size-rec',
        'fit-finder' => 'shopologic/fit',
        'color-matcher' => 'shopologic/color',
        'style-quiz' => 'shopologic/style',
        'personal-shopper' => 'shopologic/shopper',
        'outfit-builder' => 'shopologic/outfits',
        'room-planner' => 'shopologic/room',
        'configurator-3d' => 'shopologic/configure',
        'custom-designer' => 'shopologic/designer',
        'monogram' => 'shopologic/monogram',
        'engraving' => 'shopologic/engrave',
        'custom-printing' => 'shopologic/print',
        'photo-products' => 'shopologic/photos',
        'user-content' => 'shopologic/content',
        'social-gallery' => 'shopologic/gallery',
        'lookbook' => 'shopologic/lookbook',
        'shoppable-images' => 'shopologic/shop-images',
        'video-shopping' => 'shopologic/shop-video',
        'live-shopping' => 'shopologic/shop-live',
        'virtual-showroom' => 'shopologic/showroom',
        'metaverse' => 'shopologic/metaverse',
        'nft-integration' => 'shopologic/nft',
        'blockchain' => 'shopologic/blockchain',
        'smart-contracts' => 'shopologic/contracts',
        'decentralized' => 'shopologic/defi',
        'token-rewards' => 'shopologic/tokens',
        'staking' => 'shopologic/stake',
        'yield-farming' => 'shopologic/yield',
        'liquidity-pool' => 'shopologic/liquidity',
        'dao-governance' => 'shopologic/dao',
        'voting' => 'shopologic/vote',
        'proposals' => 'shopologic/proposals',
        'treasury' => 'shopologic/treasury'
    ];
    
    public function standardizeAllPlugins(string $pluginsPath): void {
        printHeader("Starting Requirements Standardization");
        
        if (!is_dir($pluginsPath)) {
            printColored("Error: Plugins directory not found: {$pluginsPath}\n", COLOR_RED);
            return;
        }
        
        $pluginDirs = glob($pluginsPath . '/*', GLOB_ONLYDIR);
        
        foreach ($pluginDirs as $pluginDir) {
            $this->processPlugin($pluginDir);
        }
        
        $this->printSummary();
    }
    
    private function processPlugin(string $pluginDir): void {
        $pluginName = basename($pluginDir);
        $manifestPath = $pluginDir . '/plugin.json';
        
        if (!file_exists($manifestPath)) {
            printColored("Skipping {$pluginName}: No plugin.json found\n", COLOR_YELLOW);
            return;
        }
        
        $this->stats['processed']++;
        
        $content = file_get_contents($manifestPath);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            printColored("Error in {$pluginName}: Invalid JSON\n", COLOR_RED);
            $this->stats['errors']++;
            return;
        }
        
        echo "Processing {$pluginName}... ";
        
        // Backup original
        $backupPath = $manifestPath . '.bak';
        if (!file_exists($backupPath)) {
            file_put_contents($backupPath, $content);
            $this->stats['backups']++;
        }
        
        $updated = false;
        
        // Standardize requirements
        if (isset($data['requirements'])) {
            $newRequirements = $this->standardizeRequirements($data['requirements']);
            if ($newRequirements !== $data['requirements']) {
                $data['requirements'] = $newRequirements;
                $updated = true;
            }
        }
        
        if ($updated) {
            $newContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
            file_put_contents($manifestPath, $newContent);
            printColored("Updated\n", COLOR_GREEN);
            $this->stats['updated']++;
        } else {
            echo "No changes needed\n";
        }
    }
    
    private function standardizeRequirements(array $requirements): array {
        $standardized = [
            'php' => '>=8.3',
            'core' => '>=1.0.0'
        ];
        
        $dependencies = [];
        
        // Process each requirement
        foreach ($requirements as $key => $value) {
            if ($key === 'php') {
                // Keep PHP requirement as is
                $standardized['php'] = $value;
            } elseif ($key === 'core') {
                // Keep core requirement
                $standardized['core'] = $value;
            } elseif ($key === 'dependencies') {
                // Process nested dependencies
                if (is_array($value)) {
                    foreach ($value as $depName => $depVersion) {
                        $mappedName = $this->mapDependencyName($depName);
                        $dependencies[$mappedName] = $this->standardizeVersion($depVersion);
                    }
                }
            } else {
                // This is a dependency at root level
                $mappedName = $this->mapDependencyName($key);
                $dependencies[$mappedName] = $this->standardizeVersion($value);
            }
        }
        
        // Only add dependencies section if there are dependencies
        if (!empty($dependencies)) {
            // Sort dependencies alphabetically for consistency
            ksort($dependencies);
            $standardized['dependencies'] = $dependencies;
        }
        
        return $standardized;
    }
    
    private function mapDependencyName(string $name): string {
        // If already has shopologic prefix, keep it
        if (str_starts_with($name, 'shopologic/')) {
            return $name;
        }
        
        // Map known dependencies
        if (isset($this->pluginMappings[$name])) {
            return $this->pluginMappings[$name];
        }
        
        // For unknown dependencies, add shopologic prefix
        return 'shopologic/' . $name;
    }
    
    private function standardizeVersion(string $version): string {
        // If it's using caret notation, keep it
        if (str_starts_with($version, '^')) {
            return $version;
        }
        
        // Convert >= to ^ for better compatibility
        if (str_starts_with($version, '>=')) {
            $versionNumber = substr($version, 2);
            return '^' . $versionNumber;
        }
        
        // If no prefix, add caret
        if (preg_match('/^\d+\.\d+/', $version)) {
            return '^' . $version;
        }
        
        return $version;
    }
    
    private function printSummary(): void {
        printHeader("Standardization Summary");
        
        echo "Total plugins processed: {$this->stats['processed']}\n";
        printColored("Updated: {$this->stats['updated']}\n", COLOR_GREEN);
        
        if ($this->stats['errors'] > 0) {
            printColored("Errors: {$this->stats['errors']}\n", COLOR_RED);
        }
        
        if ($this->stats['backups'] > 0) {
            printColored("Backups created: {$this->stats['backups']}\n", COLOR_BLUE);
        }
        
        if ($this->stats['updated'] > 0) {
            echo "\n";
            printColored("Requirements have been standardized!\n", COLOR_GREEN);
            echo "All plugins now use:\n";
            echo "- Consistent dependency naming (shopologic/* prefix)\n";
            echo "- Caret version constraints (^) for compatibility\n";
            echo "- Proper separation of system and plugin dependencies\n";
        }
    }
}

// Run the standardizer
$standardizer = new RequirementsStandardizer();
$pluginsPath = __DIR__ . '/../plugins';
$standardizer->standardizeAllPlugins($pluginsPath);