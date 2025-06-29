<?php
declare(strict_types=1);

namespace LoyaltyRewards;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\PluginInterface;
use Shopologic\Core\Hook\HookSystem;
use LoyaltyRewards\Services\LoyaltyService;
use LoyaltyRewards\Services\RewardsService;
use LoyaltyRewards\Services\TierService;
use LoyaltyRewards\Services\ReferralService;

/**
 * Loyalty & Rewards Program Plugin
 * 
 * Complete loyalty program with points, tiers, rewards, referrals, and VIP benefits
 * to increase customer retention and lifetime value
 */
class LoyaltyPlugin extends AbstractPlugin implements PluginInterface
{
    protected string $name = 'loyalty-rewards';
    protected string $version = '1.0.0';
    
    /**
     * Plugin installation
     */
    public function install(): bool
    {
        $this->runMigrations();
        $this->setDefaultConfig();
        $this->createDefaultTiers();
        $this->createDefaultRewards();
        return true;
    }
    
    /**
     * Plugin activation
     */
    public function activate(): bool
    {
        $this->initializeLoyaltyProgram();
        $this->schedulePointsExpiry();
        return true;
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate(): bool
    {
        $this->pauseLoyaltyProgram();
        return true;
    }
    
    /**
     * Plugin uninstall
     */
    public function uninstall(): bool
    {
        if ($this->confirmDataRemoval()) {
            $this->dropTables();
            $this->removeConfig();
        }
        return true;
    }
    
    /**
     * Plugin update
     */
    public function update(string $previousVersion): bool
    {
        $this->runUpdateMigrations($previousVersion);
        return true;
    }
    
    /**
     * Plugin boot
     */
    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
        $this->registerCronJobs();
        $this->registerWidgets();
        $this->registerPermissions();
    }
    
    /**
     * Register plugin services
     */
    protected function registerServices(): void
    {
        // Loyalty service
        $this->container->singleton(LoyaltyService::class, function ($container) {
            return new LoyaltyService(
                $container->get('db'),
                $container->get('events'),
                $this->getConfig()
            );
        });
        
        // Rewards service
        $this->container->singleton(RewardsService::class, function ($container) {
            return new RewardsService(
                $container->get('db'),
                $container->get(LoyaltyService::class),
                $this->getConfig()
            );
        });
        
        // Tier service
        $this->container->singleton(TierService::class, function ($container) {
            return new TierService(
                $container->get('db'),
                $this->getConfig('tier_system_enabled', true)
            );
        });
        
        // Referral service
        $this->container->singleton(ReferralService::class, function ($container) {
            return new ReferralService(
                $container->get('db'),
                $container->get(LoyaltyService::class),
                $this->getConfig('referral_program_enabled', true)
            );
        });
    }
    
    /**
     * Register plugin hooks
     */
    protected function registerHooks(): void
    {
        // Point earning events
        HookSystem::addAction('user.registered', [$this, 'awardSignupPoints'], 25);
        HookSystem::addAction('order.completed', [$this, 'awardPurchasePoints'], 15);
        HookSystem::addAction('review.created', [$this, 'awardReviewPoints'], 20);
        HookSystem::addAction('user.birthday', [$this, 'awardBirthdayPoints'], 10);
        HookSystem::addAction('social.shared', [$this, 'awardSocialPoints'], 10);
        
        // Display hooks
        HookSystem::addAction('page.account', [$this, 'displayLoyaltyDashboard'], 10);
        HookSystem::addAction('cart.totals', [$this, 'displayPointsRedemption'], 30);
        HookSystem::addFilter('checkout.discount', [$this, 'applyPointsDiscount'], 10);
        
        // Tier management
        HookSystem::addAction('loyalty.points_awarded', [$this, 'checkTierUpgrade'], 10);
        HookSystem::addAction('loyalty.tier_upgraded', [$this, 'applyTierBenefits'], 10);
        
        // Referral tracking
        HookSystem::addAction('user.registration.via_referral', [$this, 'processReferralSignup'], 10);
        HookSystem::addAction('order.first_purchase.via_referral', [$this, 'processReferralPurchase'], 10);
    }
    
    /**
     * Register API routes
     */
    protected function registerRoutes(): void
    {
        // Loyalty balance and history
        $this->registerRoute('GET', '/api/v1/loyalty/balance', 
            'LoyaltyRewards\\Controllers\\LoyaltyController@getBalance');
        $this->registerRoute('GET', '/api/v1/loyalty/history', 
            'LoyaltyRewards\\Controllers\\LoyaltyController@getHistory');
        
        // Points redemption
        $this->registerRoute('POST', '/api/v1/loyalty/redeem', 
            'LoyaltyRewards\\Controllers\\LoyaltyController@redeemPoints');
        
        // Rewards catalog
        $this->registerRoute('GET', '/api/v1/loyalty/rewards', 
            'LoyaltyRewards\\Controllers\\RewardsController@getAvailable');
        $this->registerRoute('POST', '/api/v1/loyalty/rewards/{id}/claim', 
            'LoyaltyRewards\\Controllers\\RewardsController@claimReward');
        
        // Tier information
        $this->registerRoute('GET', '/api/v1/loyalty/tiers', 
            'LoyaltyRewards\\Controllers\\TierController@getTiers');
        $this->registerRoute('GET', '/api/v1/loyalty/tier/progress', 
            'LoyaltyRewards\\Controllers\\TierController@getProgress');
        
        // Referral system
        $this->registerRoute('GET', '/api/v1/loyalty/referral/code', 
            'LoyaltyRewards\\Controllers\\ReferralController@getReferralCode');
        $this->registerRoute('GET', '/api/v1/loyalty/referral/stats', 
            'LoyaltyRewards\\Controllers\\ReferralController@getReferralStats');
        
        // VIP benefits
        $this->registerRoute('GET', '/api/v1/loyalty/vip/benefits', 
            'LoyaltyRewards\\Controllers\\VipController@getBenefits');
    }
    
    /**
     * Register cron jobs
     */
    protected function registerCronJobs(): void
    {
        // Process daily point awards
        $this->scheduleJob('0 2 * * *', [$this, 'processDailyAwards']);
        
        // Check birthday rewards
        $this->scheduleJob('0 9 * * *', [$this, 'processBirthdayRewards']);
        
        // Expire old points if configured
        if ($this->getConfig('points_expire', false)) {
            $this->scheduleJob('0 3 * * *', [$this, 'expireOldPoints']);
        }
        
        // Process tier recalculations
        $this->scheduleJob('0 4 * * *', [$this, 'recalculateTiers']);
        
        // Send loyalty engagement emails
        $this->scheduleJob('0 10 * * MON', [$this, 'sendEngagementEmails']);
    }
    
    /**
     * Register dashboard widgets
     */
    protected function registerWidgets(): void
    {
        $this->registerWidget('loyalty_overview', Widgets\LoyaltyOverviewWidget::class);
        $this->registerWidget('recent_rewards', Widgets\RecentRewardsWidget::class);
        $this->registerWidget('tier_distribution', Widgets\TierDistributionWidget::class);
    }
    
    /**
     * Register permissions
     */
    protected function registerPermissions(): void
    {
        $this->addPermission('loyalty.view', 'View loyalty program');
        $this->addPermission('loyalty.manage', 'Manage loyalty program');
        $this->addPermission('loyalty.points.adjust', 'Adjust customer points');
        $this->addPermission('loyalty.rewards.manage', 'Manage rewards catalog');
    }
    
    /**
     * Award signup points to new user
     */
    public function awardSignupPoints(array $data): void
    {
        $user = $data['user'];
        $points = $this->getConfig('signup_points', 100);
        
        if ($points > 0) {
            $loyaltyService = $this->container->get(LoyaltyService::class);
            $loyaltyService->awardPoints($user->id, $points, 'signup', [
                'description' => 'Welcome bonus for signing up'
            ]);
        }
    }
    
    /**
     * Award purchase points for completed order
     */
    public function awardPurchasePoints(array $data): void
    {
        $order = $data['order'];
        $loyaltyService = $this->container->get(LoyaltyService::class);
        
        // Calculate points based on purchase amount
        $pointsPerDollar = $this->getConfig('points_per_dollar', 1);
        $points = floor($order->total * $pointsPerDollar);
        
        // Apply tier multiplier
        $tierService = $this->container->get(TierService::class);
        $multiplier = $tierService->getPointsMultiplier($order->customer_id);
        $points = floor($points * $multiplier);
        
        if ($points > 0) {
            $loyaltyService->awardPoints($order->customer_id, $points, 'purchase', [
                'order_id' => $order->id,
                'order_total' => $order->total,
                'tier_multiplier' => $multiplier
            ]);
        }
    }
    
    /**
     * Award review points
     */
    public function awardReviewPoints(array $data): void
    {
        $review = $data['review'];
        $points = $this->getConfig('review_points', 50);
        
        if ($points > 0) {
            $loyaltyService = $this->container->get(LoyaltyService::class);
            $loyaltyService->awardPoints($review->customer_id, $points, 'review', [
                'review_id' => $review->id,
                'product_id' => $review->product_id
            ]);
        }
    }
    
    /**
     * Award birthday points
     */
    public function awardBirthdayPoints(array $data): void
    {
        $user = $data['user'];
        $points = $this->getConfig('birthday_points', 200);
        
        if ($points > 0) {
            $loyaltyService = $this->container->get(LoyaltyService::class);
            $loyaltyService->awardPoints($user->id, $points, 'birthday', [
                'birthday' => $user->birth_date
            ]);
        }
    }
    
    /**
     * Award social sharing points
     */
    public function awardSocialPoints(array $data): void
    {
        $userId = $data['user_id'];
        $platform = $data['platform'];
        $points = $this->getConfig('social_share_points', 25);
        
        if ($points > 0) {
            $loyaltyService = $this->container->get(LoyaltyService::class);
            
            // Check daily limit for social sharing
            $dailyLimit = $this->getConfig('social_share_daily_limit', 3);
            $todayShares = $loyaltyService->getTodayPointsCount($userId, 'social_share');
            
            if ($todayShares < $dailyLimit) {
                $loyaltyService->awardPoints($userId, $points, 'social_share', [
                    'platform' => $platform,
                    'shared_content' => $data['content_type'] ?? 'unknown'
                ]);
            }
        }
    }
    
    /**
     * Display loyalty dashboard on account page
     */
    public function displayLoyaltyDashboard(): void
    {
        $user = $this->api->getCurrentUser();
        if (!$user) return;
        
        $loyaltyService = $this->container->get(LoyaltyService::class);
        $tierService = $this->container->get(TierService::class);
        $rewardsService = $this->container->get(RewardsService::class);
        
        $data = [
            'balance' => $loyaltyService->getBalance($user->id),
            'tier' => $tierService->getCurrentTier($user->id),
            'tier_progress' => $tierService->getTierProgress($user->id),
            'available_rewards' => $rewardsService->getAvailableRewards($user->id),
            'recent_history' => $loyaltyService->getRecentHistory($user->id, 10)
        ];
        
        echo $this->render('dashboard/loyalty-overview', $data);
    }
    
    /**
     * Display points redemption option in cart
     */
    public function displayPointsRedemption(): void
    {
        $user = $this->api->getCurrentUser();
        if (!$user) return;
        
        $loyaltyService = $this->container->get(LoyaltyService::class);
        $balance = $loyaltyService->getBalance($user->id);
        
        if ($balance > 0) {
            $pointValue = $this->getConfig('point_value', 0.01);
            $maxDiscount = $balance * $pointValue;
            
            echo $this->render('cart/points-redemption', [
                'balance' => $balance,
                'point_value' => $pointValue,
                'max_discount' => $maxDiscount
            ]);
        }
    }
    
    /**
     * Apply points discount to checkout
     */
    public function applyPointsDiscount($discount, array $data): float
    {
        $pointsToRedeem = $data['points_to_redeem'] ?? 0;
        
        if ($pointsToRedeem > 0) {
            $user = $this->api->getCurrentUser();
            if ($user) {
                $loyaltyService = $this->container->get(LoyaltyService::class);
                
                if ($loyaltyService->canRedeemPoints($user->id, $pointsToRedeem)) {
                    $pointValue = $this->getConfig('point_value', 0.01);
                    $discount += $pointsToRedeem * $pointValue;
                }
            }
        }
        
        return $discount;
    }
    
    /**
     * Check for tier upgrade after points award
     */
    public function checkTierUpgrade(array $data): void
    {
        $userId = $data['user_id'];
        $tierService = $this->container->get(TierService::class);
        
        $tierService->checkAndUpgradeTier($userId);
    }
    
    /**
     * Apply tier benefits after upgrade
     */
    public function applyTierBenefits(array $data): void
    {
        $userId = $data['user_id'];
        $newTier = $data['new_tier'];
        $tierService = $this->container->get(TierService::class);
        
        $tierService->applyTierBenefits($userId, $newTier);
    }
    
    /**
     * Process referral signup
     */
    public function processReferralSignup(array $data): void
    {
        $newUser = $data['new_user'];
        $referrerCode = $data['referrer_code'];
        
        $referralService = $this->container->get(ReferralService::class);
        $referralService->processSignup($newUser->id, $referrerCode);
    }
    
    /**
     * Process referral purchase
     */
    public function processReferralPurchase(array $data): void
    {
        $order = $data['order'];
        $referralService = $this->container->get(ReferralService::class);
        
        $referralService->processFirstPurchase($order);
    }
    
    /**
     * Process daily point awards (cron job)
     */
    public function processDailyAwards(): void
    {
        $loyaltyService = $this->container->get(LoyaltyService::class);
        $processed = $loyaltyService->processDailyAwards();
        
        $this->logger->info('Processed daily loyalty awards', ['count' => $processed]);
    }
    
    /**
     * Process birthday rewards (cron job)
     */
    public function processBirthdayRewards(): void
    {
        $loyaltyService = $this->container->get(LoyaltyService::class);
        $birthdayUsers = $loyaltyService->getTodayBirthdayUsers();
        
        foreach ($birthdayUsers as $user) {
            $this->awardBirthdayPoints(['user' => $user]);
        }
        
        $this->logger->info('Processed birthday rewards', ['count' => count($birthdayUsers)]);
    }
    
    /**
     * Expire old points (cron job)
     */
    public function expireOldPoints(): void
    {
        $expiryDays = $this->getConfig('points_expiry_days', 365);
        $loyaltyService = $this->container->get(LoyaltyService::class);
        
        $expired = $loyaltyService->expireOldPoints($expiryDays);
        $this->logger->info('Expired old loyalty points', ['expired_points' => $expired]);
    }
    
    /**
     * Recalculate tiers (cron job)
     */
    public function recalculateTiers(): void
    {
        $tierService = $this->container->get(TierService::class);
        $recalculated = $tierService->recalculateAllTiers();
        
        $this->logger->info('Recalculated customer tiers', ['count' => $recalculated]);
    }
    
    /**
     * Send loyalty engagement emails (cron job)
     */
    public function sendEngagementEmails(): void
    {
        $loyaltyService = $this->container->get(LoyaltyService::class);
        $sent = $loyaltyService->sendEngagementEmails();
        
        $this->logger->info('Sent loyalty engagement emails', ['count' => $sent]);
    }
    
    /**
     * Initialize loyalty program
     */
    protected function initializeLoyaltyProgram(): void
    {
        $loyaltyService = $this->container->get(LoyaltyService::class);
        $loyaltyService->initialize();
    }
    
    /**
     * Pause loyalty program
     */
    protected function pauseLoyaltyProgram(): void
    {
        // Disable point earning
        $this->setConfig('loyalty_enabled', false);
    }
    
    /**
     * Schedule points expiry checking
     */
    protected function schedulePointsExpiry(): void
    {
        if ($this->getConfig('points_expire', false)) {
            $this->enableCronJob('expireOldPoints');
        }
    }
    
    /**
     * Create default loyalty tiers
     */
    protected function createDefaultTiers(): void
    {
        $tiers = [
            [
                'name' => 'Bronze',
                'min_points' => 0,
                'points_multiplier' => 1.0,
                'benefits' => json_encode(['Free shipping on orders over $75']),
                'color' => '#CD7F32'
            ],
            [
                'name' => 'Silver',
                'min_points' => 1000,
                'points_multiplier' => 1.25,
                'benefits' => json_encode(['Free shipping on orders over $50', '5% birthday discount']),
                'color' => '#C0C0C0'
            ],
            [
                'name' => 'Gold',
                'min_points' => 5000,
                'points_multiplier' => 1.5,
                'benefits' => json_encode(['Free shipping on all orders', '10% birthday discount', 'Priority support']),
                'color' => '#FFD700'
            ],
            [
                'name' => 'Platinum',
                'min_points' => 15000,
                'points_multiplier' => 2.0,
                'benefits' => json_encode(['Free shipping', '15% birthday discount', 'Priority support', 'Early access to sales']),
                'color' => '#E5E4E2'
            ]
        ];
        
        foreach ($tiers as $tier) {
            $this->api->database()->table('loyalty_tiers')->insert($tier);
        }
    }
    
    /**
     * Create default rewards
     */
    protected function createDefaultRewards(): void
    {
        $rewards = [
            [
                'name' => '$5 Discount',
                'description' => '$5 off your next purchase',
                'type' => 'discount',
                'value' => 5.00,
                'points_cost' => 500,
                'is_active' => true
            ],
            [
                'name' => '$10 Discount',
                'description' => '$10 off your next purchase',
                'type' => 'discount',
                'value' => 10.00,
                'points_cost' => 1000,
                'is_active' => true
            ],
            [
                'name' => 'Free Shipping',
                'description' => 'Free shipping on your next order',
                'type' => 'free_shipping',
                'value' => 0,
                'points_cost' => 200,
                'is_active' => true
            ],
            [
                'name' => '20% Off Coupon',
                'description' => '20% discount on your next purchase',
                'type' => 'percentage_discount',
                'value' => 20,
                'points_cost' => 2000,
                'is_active' => true
            ]
        ];
        
        foreach ($rewards as $reward) {
            $this->api->database()->table('loyalty_rewards')->insert($reward);
        }
    }
    
    /**
     * Run database migrations
     */
    protected function runMigrations(): void
    {
        $migrations = [
            'create_loyalty_accounts_table.php',
            'create_loyalty_transactions_table.php',
            'create_loyalty_tiers_table.php',
            'create_loyalty_rewards_table.php',
            'create_loyalty_redemptions_table.php',
            'create_loyalty_referrals_table.php',
            'create_loyalty_settings_table.php'
        ];
        
        foreach ($migrations as $migration) {
            $this->api->runMigration($this->getPath('migrations/' . $migration));
        }
    }
    
    /**
     * Set default configuration
     */
    protected function setDefaultConfig(): void
    {
        $defaults = [
            'loyalty_enabled' => true,
            'signup_points' => 100,
            'points_per_dollar' => 1,
            'review_points' => 50,
            'birthday_points' => 200,
            'social_share_points' => 25,
            'social_share_daily_limit' => 3,
            'point_value' => 0.01,
            'points_expire' => false,
            'points_expiry_days' => 365,
            'tier_system_enabled' => true,
            'referral_program_enabled' => true,
            'referrer_signup_points' => 250,
            'referrer_purchase_points' => 500,
            'referee_signup_points' => 100,
            'min_redemption_points' => 100,
            'max_redemption_percent' => 50
        ];
        
        foreach ($defaults as $key => $value) {
            if ($this->getConfig($key) === null) {
                $this->setConfig($key, $value);
            }
        }
    }
}