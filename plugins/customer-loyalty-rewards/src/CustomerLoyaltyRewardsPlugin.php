<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CustomerLoyaltyRewards;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\PluginInterface;
use Shopologic\Core\Plugin\Hook;
use Shopologic\Core\Container\ContainerInterface;
use CustomerLoyaltyRewards\Services\{
    LoyaltyManager,
    RewardsEngine,
    TierManager,
    ReferralSystem,
    GamificationEngine,
    PointsCalculator,
    CampaignManager,
    AnalyticsService,;
    NotificationService;
};
use CustomerLoyaltyRewards\Repositories\{
    MemberRepository,
    TierRepository,
    PointsTransactionRepository,
    RewardRepository,
    ReferralRepository,
    CampaignRepository,
    BadgeRepository,;
    ChallengeRepository;
};
use CustomerLoyaltyRewards\Controllers\{
    MemberController,
    PointsController,
    RewardsController,
    TierController,
    ReferralController,
    CampaignController,
    AnalyticsController,;
    ChallengeController;
};

class CustomerLoyaltyRewardsPlugin extends AbstractPlugin implements PluginInterface
{
    protected string $name = 'customer-loyalty-rewards';
    protected string $version = '1.0.0';
    protected string $description = 'Comprehensive loyalty and rewards system';
    protected string $author = 'Shopologic Team';
    protected array $dependencies = ['shopologic/commerce', 'shopologic/customers', 'shopologic/notifications'];

    private LoyaltyManager $loyaltyManager;
    private RewardsEngine $rewardsEngine;
    private TierManager $tierManager;
    private ReferralSystem $referralSystem;
    private GamificationEngine $gamificationEngine;
    private PointsCalculator $pointsCalculator;
    private CampaignManager $campaignManager;
    private AnalyticsService $analyticsService;
    private NotificationService $notificationService;

    /**
     * Plugin installation
     */
    public function install(): void
    {
        // Run database migrations
        $this->runMigrations();
        
        // Create default tiers
        $this->createDefaultTiers();
        
        // Create default rewards
        $this->createDefaultRewards();
        
        // Create default badges
        $this->createDefaultBadges();
        
        // Set default configuration
        $this->setDefaultConfiguration();
        
        // Create necessary directories
        $this->createDirectories();
    }

    /**
     * Plugin activation
     */
    public function activate(): void
    {
        // Register services
        $this->registerServices();
        
        // Register hooks and filters
        $this->registerHooks();
        
        // Register API routes
        $this->registerRoutes();
        
        // Schedule background tasks
        $this->scheduleBackgroundTasks();
        
        // Initialize campaigns
        $this->initializeCampaigns();
        
        // Setup default challenges
        $this->setupDefaultChallenges();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {
        // Unschedule background tasks
        $this->unscheduleBackgroundTasks();
        
        // Save analytics data
        $this->saveAnalyticsData();
        
        // Clear caches
        $this->clearCaches();
        
        // Pause active campaigns
        $this->pauseActiveCampaigns();
    }

    /**
     * Plugin uninstallation
     */
    public function uninstall(): void
    {
        // Note: Database cleanup is optional and user-configurable
        if ($this->getConfig('cleanup_on_uninstall', false)) {
            $this->cleanupDatabase();
        }
        
        // Remove configuration
        $this->removeConfiguration();
        
        // Clean up files
        $this->cleanupFiles();
    }

    /**
     * Plugin update
     */
    public function update(string $previousVersion): void
    {
        // Run version-specific updates
        if (version_compare($previousVersion, '1.0.0', '<')) {
            $this->updateTo100();
        }
        
        // Update database schema if needed
        $this->runMigrations();
        
        // Update configuration schema
        $this->updateConfiguration();
        
        // Migrate existing data if needed
        $this->migrateExistingData($previousVersion);
    }

    /**
     * Plugin boot - called when plugin is loaded
     */
    public function boot(): void
    {
        // Initialize core services
        $this->initializeServices();
        
        // Register event listeners
        $this->registerEventListeners();
        
        // Load plugin configuration
        $this->loadConfiguration();
        
        // Initialize member portal
        $this->initializeMemberPortal();
    }

    /**
     * Register services with the container
     */
    protected function registerServices(): void
    {
        $container = $this->getContainer();
        
        // Register repositories
        $container->singleton(MemberRepository::class);
        $container->singleton(TierRepository::class);
        $container->singleton(PointsTransactionRepository::class);
        $container->singleton(RewardRepository::class);
        $container->singleton(ReferralRepository::class);
        $container->singleton(CampaignRepository::class);
        $container->singleton(BadgeRepository::class);
        $container->singleton(ChallengeRepository::class);
        
        // Register core services
        $container->singleton(PointsCalculator::class, function ($container) {
            return new PointsCalculator(
                $this->getConfig('earning_rules', []),
                $this->getConfig('redemption_rules', [])
            );
        });
        
        $container->singleton(LoyaltyManager::class, function ($container) {
            return new LoyaltyManager(
                $container->get(MemberRepository::class),
                $container->get(PointsTransactionRepository::class),
                $container->get(PointsCalculator::class)
            );
        });
        
        $container->singleton(TierManager::class, function ($container) {
            return new TierManager(
                $container->get(TierRepository::class),
                $container->get(MemberRepository::class),
                $this->getConfig('tier_system', [])
            );
        });
        
        $container->singleton(RewardsEngine::class, function ($container) {
            return new RewardsEngine(
                $container->get(RewardRepository::class),
                $container->get(LoyaltyManager::class)
            );
        });
        
        $container->singleton(ReferralSystem::class, function ($container) {
            return new ReferralSystem(
                $container->get(ReferralRepository::class),
                $container->get(LoyaltyManager::class),
                $this->getConfig('referral_system', [])
            );
        });
        
        $container->singleton(GamificationEngine::class, function ($container) {
            return new GamificationEngine(
                $container->get(BadgeRepository::class),
                $container->get(ChallengeRepository::class),
                $container->get(LoyaltyManager::class),
                $this->getConfig('gamification', [])
            );
        });
        
        $container->singleton(CampaignManager::class, function ($container) {
            return new CampaignManager(
                $container->get(CampaignRepository::class),
                $container->get(LoyaltyManager::class)
            );
        });
        
        $container->singleton(AnalyticsService::class, function ($container) {
            return new AnalyticsService(
                $container->get(MemberRepository::class),
                $container->get(PointsTransactionRepository::class),
                $this->getConfig('analytics', [])
            );
        });
        
        $container->singleton(NotificationService::class, function ($container) {
            return new NotificationService(
                $this->getConfig('communication', [])
            );
        });
        
        // Register controllers
        $container->singleton(MemberController::class);
        $container->singleton(PointsController::class);
        $container->singleton(RewardsController::class);
        $container->singleton(TierController::class);
        $container->singleton(ReferralController::class);
        $container->singleton(CampaignController::class);
        $container->singleton(AnalyticsController::class);
        $container->singleton(ChallengeController::class);
    }

    /**
     * Initialize services
     */
    protected function initializeServices(): void
    {
        $container = $this->getContainer();
        
        $this->loyaltyManager = $container->get(LoyaltyManager::class);
        $this->rewardsEngine = $container->get(RewardsEngine::class);
        $this->tierManager = $container->get(TierManager::class);
        $this->referralSystem = $container->get(ReferralSystem::class);
        $this->gamificationEngine = $container->get(GamificationEngine::class);
        $this->pointsCalculator = $container->get(PointsCalculator::class);
        $this->campaignManager = $container->get(CampaignManager::class);
        $this->analyticsService = $container->get(AnalyticsService::class);
        $this->notificationService = $container->get(NotificationService::class);
    }

    /**
     * Register hooks and filters
     */
    protected function registerHooks(): void
    {
        // Order lifecycle hooks
        Hook::addAction('order.completed', [$this, 'awardOrderPoints'], 10);
        Hook::addAction('order.cancelled', [$this, 'handleOrderCancellation'], 10);
        Hook::addAction('order.refunded', [$this, 'handleOrderRefund'], 10);
        
        // Customer lifecycle hooks
        Hook::addAction('customer.registered', [$this, 'handleNewMember'], 5);
        Hook::addAction('customer.birthday', [$this, 'sendBirthdayRewards'], 10);
        Hook::addAction('customer.updated', [$this, 'updateMemberProfile'], 10);
        
        // Product interaction hooks
        Hook::addAction('product.reviewed', [$this, 'awardReviewPoints'], 10);
        Hook::addAction('product.shared', [$this, 'awardSocialSharePoints'], 10);
        Hook::addAction('product.wishlisted', [$this, 'awardWishlistPoints'], 10);
        
        // Referral hooks
        Hook::addAction('referral.completed', [$this, 'awardReferralBonus'], 5);
        Hook::addAction('referral.clicked', [$this, 'trackReferralClick'], 10);
        
        // Checkout filters
        Hook::addFilter('checkout.discount', [$this, 'applyLoyaltyDiscount'], 15);
        Hook::addFilter('product.price', [$this, 'applyTierPricing'], 20);
        Hook::addFilter('shipping.cost', [$this, 'applyTierShippingDiscount'], 15);
        
        // Member portal filters
        Hook::addFilter('member.dashboard_data', [$this, 'addLoyaltyData'], 10);
        Hook::addFilter('member.available_rewards', [$this, 'getAvailableRewards'], 10);
        
        // Admin hooks
        Hook::addAction('admin_menu', [$this, 'registerAdminMenu']);
        Hook::addAction('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        Hook::addAction('admin_footer', [$this, 'addAdminNotifications']);
        
        // Frontend hooks
        Hook::addAction('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
        Hook::addAction('wp_footer', [$this, 'addLoyaltyWidgets']);
        
        // AJAX hooks
        Hook::addAction('wp_ajax_redeem_points', [$this, 'handlePointsRedemption']);
        Hook::addAction('wp_ajax_share_referral', [$this, 'handleReferralShare']);
        Hook::addAction('wp_ajax_check_challenge_progress', [$this, 'handleChallengeProgress']);
    }

    /**
     * Register API routes
     */
    protected function registerRoutes(): void
    {
        // Member management
        $this->registerRoute('GET', '/api/v1/loyalty/members', 'MemberController@index');
        $this->registerRoute('GET', '/api/v1/loyalty/members/{id}', 'MemberController@show');
        $this->registerRoute('PUT', '/api/v1/loyalty/members/{id}', 'MemberController@update');
        
        // Points management
        $this->registerRoute('POST', '/api/v1/loyalty/points/award', 'PointsController@award');
        $this->registerRoute('POST', '/api/v1/loyalty/points/redeem', 'PointsController@redeem');
        $this->registerRoute('GET', '/api/v1/loyalty/points/history', 'PointsController@history');
        $this->registerRoute('GET', '/api/v1/loyalty/points/balance/{memberId}', 'PointsController@balance');
        
        // Rewards management
        $this->registerRoute('GET', '/api/v1/loyalty/rewards', 'RewardsController@index');
        $this->registerRoute('POST', '/api/v1/loyalty/rewards', 'RewardsController@create');
        $this->registerRoute('GET', '/api/v1/loyalty/rewards/{id}', 'RewardsController@show');
        $this->registerRoute('PUT', '/api/v1/loyalty/rewards/{id}', 'RewardsController@update');
        $this->registerRoute('DELETE', '/api/v1/loyalty/rewards/{id}', 'RewardsController@delete');
        
        // Tier management
        $this->registerRoute('GET', '/api/v1/loyalty/tiers', 'TierController@index');
        $this->registerRoute('POST', '/api/v1/loyalty/tiers', 'TierController@create');
        $this->registerRoute('PUT', '/api/v1/loyalty/tiers/{id}', 'TierController@update');
        
        // Referral system
        $this->registerRoute('POST', '/api/v1/loyalty/referrals', 'ReferralController@create');
        $this->registerRoute('GET', '/api/v1/loyalty/referrals/{memberId}', 'ReferralController@getMemberReferrals');
        $this->registerRoute('POST', '/api/v1/loyalty/referrals/{code}/track', 'ReferralController@trackClick');
        
        // Campaign management
        $this->registerRoute('GET', '/api/v1/loyalty/campaigns', 'CampaignController@index');
        $this->registerRoute('POST', '/api/v1/loyalty/campaigns', 'CampaignController@create');
        $this->registerRoute('PUT', '/api/v1/loyalty/campaigns/{id}', 'CampaignController@update');
        $this->registerRoute('POST', '/api/v1/loyalty/campaigns/{id}/activate', 'CampaignController@activate');
        
        // Analytics
        $this->registerRoute('GET', '/api/v1/loyalty/analytics', 'AnalyticsController@dashboard');
        $this->registerRoute('GET', '/api/v1/loyalty/analytics/members', 'AnalyticsController@memberAnalytics');
        $this->registerRoute('GET', '/api/v1/loyalty/analytics/rewards', 'AnalyticsController@rewardAnalytics');
        $this->registerRoute('GET', '/api/v1/loyalty/analytics/roi', 'AnalyticsController@roiReport');
        
        // Gamification
        $this->registerRoute('POST', '/api/v1/loyalty/challenges/complete', 'ChallengeController@complete');
        $this->registerRoute('GET', '/api/v1/loyalty/badges/{memberId}', 'ChallengeController@getMemberBadges');
        $this->registerRoute('GET', '/api/v1/loyalty/leaderboard', 'ChallengeController@leaderboard');
    }

    /**
     * Handle new member registration
     */
    public function handleNewMember($customer): void
    {
        if (!$this->getConfig('program_settings.auto_enroll', true)) {
            return;
        }
        
        // Create loyalty member
        $member = $this->loyaltyManager->createMember($customer->getId(), [
            'email' => $customer->getEmail(),
            'first_name' => $customer->getFirstName(),
            'last_name' => $customer->getLastName(),
            'phone' => $customer->getPhone(),
            'birthday' => $customer->getBirthday()
        ]);
        
        // Award welcome bonus
        $welcomeBonus = $this->getConfig('program_settings.welcome_bonus', 100);
        if ($welcomeBonus > 0) {
            $this->loyaltyManager->awardPoints(
                $member->getId(),
                $welcomeBonus,
                'welcome_bonus',
                'Welcome bonus for joining loyalty program'
            );
        }
        
        // Send welcome notification
        if ($this->getConfig('communication.welcome_email', true)) {
            $this->notificationService->sendWelcomeEmail($member);
        }
        
        // Assign default tier
        $this->tierManager->assignInitialTier($member->getId());
        
        // Track analytics
        $this->analyticsService->trackMemberRegistration($member);
    }

    /**
     * Award points for completed orders
     */
    public function awardOrderPoints($order): void
    {
        $customerId = $order->getCustomerId();
        $member = $this->loyaltyManager->findMemberByCustomerId($customerId);
        
        if (!$member) {
            return;
        }
        
        // Calculate points based on order total
        $orderTotal = $order->getTotal();
        $pointsPerDollar = $this->getConfig('earning_rules.points_per_dollar', 1);
        $basePoints = (int) floor($orderTotal * $pointsPerDollar);
        
        // Apply tier multiplier
        $tier = $this->tierManager->getMemberTier($member->getId());
        $multiplier = $tier ? $tier->getPointsMultiplier() : 1.0;
        $totalPoints = (int) floor($basePoints * $multiplier);
        
        // Award points
        $this->loyaltyManager->awardPoints(
            $member->getId(),
            $totalPoints,
            'order_purchase',
            "Points earned from order #{$order->getOrderNumber()}",
            $order->getId()
        );
        
        // Check for tier upgrade
        $this->tierManager->checkTierUpgrade($member->getId());
        
        // Update member statistics
        $this->loyaltyManager->updateMemberStats($member->getId(), [
            'total_orders' => $member->getTotalOrders() + 1,
            'total_spent' => $member->getTotalSpent() + $orderTotal,
            'last_order_date' => now()
        ]);
        
        // Check challenge progress
        $this->gamificationEngine->updateChallengeProgress($member->getId(), 'purchase', [
            'amount' => $orderTotal,
            'order_id' => $order->getId()
        ]);
        
        // Send notification
        if ($this->getConfig('communication.points_earned_notification', true)) {
            $this->notificationService->sendPointsEarnedNotification($member, $totalPoints, 'order');
        }
    }

    /**
     * Award points for product reviews
     */
    public function awardReviewPoints($review): void
    {
        $customerId = $review->getCustomerId();
        $member = $this->loyaltyManager->findMemberByCustomerId($customerId);
        
        if (!$member) {
            return;
        }
        
        $reviewPoints = $this->getConfig('earning_rules.review_points', 50);
        
        if ($reviewPoints > 0) {
            $this->loyaltyManager->awardPoints(
                $member->getId(),
                $reviewPoints,
                'product_review',
                "Points earned for reviewing product #{$review->getProductId()}",
                $review->getId()
            );
            
            // Update challenge progress
            $this->gamificationEngine->updateChallengeProgress($member->getId(), 'review', [
                'product_id' => $review->getProductId(),
                'rating' => $review->getRating()
            ]);
        }
    }

    /**
     * Award referral bonus
     */
    public function awardReferralBonus($referral): void
    {
        $referrerMember = $this->loyaltyManager->findMemberByCustomerId($referral->getReferrerId());
        $refereeMember = $this->loyaltyManager->findMemberByCustomerId($referral->getRefereeId());
        
        if (!$referrerMember || !$refereeMember) {
            return;
        }
        
        $referralPoints = $this->getConfig('earning_rules.referral_points', 500);
        
        // Award points to referrer
        if ($referralPoints > 0) {
            $this->loyaltyManager->awardPoints(
                $referrerMember->getId(),
                $referralPoints,
                'referral_bonus',
                "Referral bonus for referring {$refereeMember->getFirstName()}",
                $referral->getId()
            );
        }
        
        // Handle referee reward based on configuration
        $refereeRewardType = $this->getConfig('referral_system.referee_reward_type', 'discount');
        $this->referralSystem->processRefereeReward($refereeMember->getId(), $refereeRewardType);
        
        // Update referral statistics
        $this->referralSystem->updateReferralStats($referral->getId());
        
        // Send notifications
        $this->notificationService->sendReferralSuccessNotification($referrerMember, $refereeMember);
    }

    /**
     * Apply loyalty discount during checkout
     */
    public function applyLoyaltyDiscount($discount, $cart): array
    {
        $customerId = $cart->getCustomerId();
        if (!$customerId) {
            return $discount;
        }
        
        $member = $this->loyaltyManager->findMemberByCustomerId($customerId);
        if (!$member) {
            return $discount;
        }
        
        // Check if member is redeeming points
        $pointsToRedeem = $cart->getMetadata('redeem_points', 0);
        if ($pointsToRedeem <= 0) {
            return $discount;
        }
        
        // Validate points balance
        if (!$this->loyaltyManager->canRedeemPoints($member->getId(), $pointsToRedeem)) {
            return $discount;
        }
        
        // Calculate discount value
        $pointValue = $this->getConfig('redemption_rules.points_value', 1) / 100; // Convert cents to dollars
        $discountAmount = $pointsToRedeem * $pointValue;
        
        // Apply maximum redemption limit
        $maxRedemptionPercent = $this->getConfig('redemption_rules.max_redemption_per_order', 50);
        $maxDiscountAmount = ($cart->getSubtotal() * $maxRedemptionPercent) / 100;
        
        $discountAmount = min($discountAmount, $maxDiscountAmount);
        
        // Add loyalty discount
        $discount['loyalty_points'] = [
            'label' => 'Loyalty Points Redemption',
            'amount' => $discountAmount,
            'type' => 'fixed',
            'points_redeemed' => $pointsToRedeem
        ];
        
        return $discount;
    }

    /**
     * Apply tier-based pricing
     */
    public function applyTierPricing($price, $product, $context): float
    {
        if (!isset($context['customer_id'])) {
            return $price;
        }
        
        $member = $this->loyaltyManager->findMemberByCustomerId($context['customer_id']);
        if (!$member) {
            return $price;
        }
        
        $tier = $this->tierManager->getMemberTier($member->getId());
        if (!$tier || !$tier->hasDiscountBenefit()) {
            return $price;
        }
        
        $discountPercent = $tier->getProductDiscountPercent();
        return $price * (1 - $discountPercent / 100);
    }

    /**
     * Scheduled task: Process points expiry
     */
    public function processPointsExpiry(): void
    {
        if (!$this->getConfig('redemption_rules.expiry_enabled', false)) {
            return;
        }
        
        $expiryMonths = $this->getConfig('redemption_rules.expiry_months', 12);
        $expiringPoints = $this->loyaltyManager->findExpiringPoints($expiryMonths);
        
        foreach ($expiringPoints as $transaction) {
            // Send reminder notification
            if ($transaction->isExpiringWithinDays(30)) {
                $member = $this->loyaltyManager->findMemberById($transaction->getMemberId());
                $this->notificationService->sendPointsExpiryReminder($member, $transaction);
            }
            
            // Expire points
            if ($transaction->isExpired()) {
                $this->loyaltyManager->expirePoints($transaction->getId());
            }
        }
    }

    /**
     * Scheduled task: Update member tiers
     */
    public function updateMemberTiers(): void
    {
        if (!$this->getConfig('tier_system.enabled', true)) {
            return;
        }
        
        $members = $this->loyaltyManager->getAllActiveMembers();
        
        foreach ($members as $member) {
            $this->tierManager->evaluateAndUpdateTier($member->getId());
        }
    }

    /**
     * Scheduled task: Process challenge progress
     */
    public function processChallengeProgress(): void
    {
        if (!$this->getConfig('gamification.challenges_enabled', true)) {
            return;
        }
        
        $this->gamificationEngine->processAllChallenges();
    }

    /**
     * Scheduled task: Send birthday rewards
     */
    public function sendBirthdayRewards(): void
    {
        if (!$this->getConfig('communication.birthday_greetings', true)) {
            return;
        }
        
        $birthdayMembers = $this->loyaltyManager->getTodaysBirthdayMembers();
        $birthdayPoints = $this->getConfig('earning_rules.birthday_points', 200);
        
        foreach ($birthdayMembers as $member) {
            // Award birthday points
            if ($birthdayPoints > 0) {
                $this->loyaltyManager->awardPoints(
                    $member->getId(),
                    $birthdayPoints,
                    'birthday_bonus',
                    'Happy Birthday bonus points!'
                );
            }
            
            // Send birthday notification
            $this->notificationService->sendBirthdayGreeting($member);
        }
    }

    /**
     * Scheduled task: Process reward availability
     */
    public function processRewardAvailability(): void
    {
        $newRewards = $this->rewardsEngine->checkNewRewardsAvailability();
        
        foreach ($newRewards as $memberReward) {
            if ($this->getConfig('communication.reward_availability_notification', true)) {
                $this->notificationService->sendNewRewardNotification(
                    $memberReward['member'],
                    $memberReward['reward']
                );
            }
        }
    }

    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        add_menu_page(
            'Loyalty Program',
            'Loyalty',
            'loyalty.view',
            'loyalty',
            [$this, 'renderLoyaltyDashboard'],
            'dashicons-heart',
            26
        );
        
        add_submenu_page(
            'loyalty',
            'Dashboard',
            'Dashboard',
            'loyalty.view',
            'loyalty-dashboard',
            [$this, 'renderLoyaltyDashboard']
        );
        
        add_submenu_page(
            'loyalty',
            'Members',
            'Members',
            'members.manage',
            'loyalty-members',
            [$this, 'renderMembers']
        );
        
        add_submenu_page(
            'loyalty',
            'Rewards',
            'Rewards',
            'rewards.configure',
            'loyalty-rewards',
            [$this, 'renderRewards']
        );
        
        add_submenu_page(
            'loyalty',
            'Tiers',
            'Tiers',
            'tiers.manage',
            'loyalty-tiers',
            [$this, 'renderTiers']
        );
        
        add_submenu_page(
            'loyalty',
            'Campaigns',
            'Campaigns',
            'campaigns.manage',
            'loyalty-campaigns',
            [$this, 'renderCampaigns']
        );
        
        add_submenu_page(
            'loyalty',
            'Referrals',
            'Referrals',
            'referrals.manage',
            'loyalty-referrals',
            [$this, 'renderReferrals']
        );
        
        add_submenu_page(
            'loyalty',
            'Analytics',
            'Analytics',
            'analytics.view',
            'loyalty-analytics',
            [$this, 'renderAnalytics']
        );
        
        add_submenu_page(
            'loyalty',
            'Settings',
            'Settings',
            'loyalty.manage',
            'loyalty-settings',
            [$this, 'renderSettings']
        );
    }

    /**
     * Create default tiers
     */
    private function createDefaultTiers(): void
    {
        $defaultTiers = [
            [
                'name' => 'Bronze',
                'minimum_spending' => 0,
                'points_multiplier' => 1.0,
                'benefits' => ['Basic rewards access'],
                'color' => '#CD7F32'
            ],
            [
                'name' => 'Silver',
                'minimum_spending' => 500,
                'points_multiplier' => 1.25,
                'benefits' => ['25% bonus points', 'Free shipping on orders over $50'],
                'color' => '#C0C0C0'
            ],
            [
                'name' => 'Gold',
                'minimum_spending' => 1500,
                'points_multiplier' => 1.5,
                'benefits' => ['50% bonus points', 'Free shipping', 'Early sale access'],
                'color' => '#FFD700'
            ],
            [
                'name' => 'Platinum',
                'minimum_spending' => 5000,
                'points_multiplier' => 2.0,
                'benefits' => ['Double points', 'Free shipping', 'Exclusive rewards', 'Priority support'],
                'color' => '#E5E4E2'
            ]
        ];
        
        foreach ($defaultTiers as $tierData) {
            $this->tierManager->createTier($tierData);
        }
    }

    /**
     * Create default rewards
     */
    private function createDefaultRewards(): void
    {
        $defaultRewards = [
            [
                'name' => '$5 Off',
                'type' => 'discount',
                'points_cost' => 500,
                'discount_amount' => 5.00,
                'description' => '$5 discount on any purchase'
            ],
            [
                'name' => '$10 Off',
                'type' => 'discount',
                'points_cost' => 1000,
                'discount_amount' => 10.00,
                'description' => '$10 discount on any purchase'
            ],
            [
                'name' => 'Free Shipping',
                'type' => 'free_shipping',
                'points_cost' => 200,
                'description' => 'Free shipping on your next order'
            ]
        ];
        
        foreach ($defaultRewards as $rewardData) {
            $this->rewardsEngine->createReward($rewardData);
        }
    }

    /**
     * Create default badges
     */
    private function createDefaultBadges(): void
    {
        $defaultBadges = [
            [
                'name' => 'First Purchase',
                'description' => 'Completed your first purchase',
                'icon' => 'shopping-cart',
                'criteria' => ['event' => 'first_purchase']
            ],
            [
                'name' => 'Reviewer',
                'description' => 'Left your first product review',
                'icon' => 'star',
                'criteria' => ['event' => 'first_review']
            ],
            [
                'name' => 'Referral Expert',
                'description' => 'Referred 5 friends successfully',
                'icon' => 'users',
                'criteria' => ['event' => 'referrals', 'count' => 5]
            ],
            [
                'name' => 'Big Spender',
                'description' => 'Spent over $1000',
                'icon' => 'dollar-sign',
                'criteria' => ['event' => 'total_spent', 'amount' => 1000]
            ]
        ];
        
        foreach ($defaultBadges as $badgeData) {
            $this->gamificationEngine->createBadge($badgeData);
        }
    }

    /**
     * Set default configuration
     */
    private function setDefaultConfiguration(): void
    {
        $defaults = [
            'program_settings' => [
                'program_name' => 'Loyalty Rewards',
                'currency_name' => 'Points',
                'auto_enroll' => true,
                'welcome_bonus' => 100
            ],
            'earning_rules' => [
                'points_per_dollar' => 1,
                'review_points' => 50,
                'referral_points' => 500,
                'birthday_points' => 200,
                'social_share_points' => 25
            ],
            'redemption_rules' => [
                'minimum_redemption' => 100,
                'points_value' => 1, // 1 cent per point
                'max_redemption_per_order' => 50,
                'expiry_enabled' => false,
                'expiry_months' => 12
            ],
            'tier_system' => [
                'enabled' => true,
                'calculation_method' => 'annual_spending',
                'tier_benefits' => ['point_multiplier', 'exclusive_rewards']
            ],
            'referral_system' => [
                'enabled' => true,
                'referrer_reward_type' => 'points',
                'referee_reward_type' => 'discount',
                'sharing_channels' => ['email', 'facebook', 'twitter']
            ],
            'gamification' => [
                'badges_enabled' => true,
                'challenges_enabled' => true,
                'leaderboard_enabled' => false,
                'achievements_enabled' => true,
                'progress_tracking' => true
            ],
            'communication' => [
                'welcome_email' => true,
                'points_earned_notification' => true,
                'tier_upgrade_notification' => true,
                'reward_availability_notification' => true,
                'expiry_reminder' => true,
                'birthday_greetings' => true
            ]
        ];
        
        foreach ($defaults as $key => $value) {
            if (!$this->hasConfig($key)) {
                $this->setConfig($key, $value);
            }
        }
    }

    /**
     * Create necessary directories
     */
    private function createDirectories(): void
    {
        $dirs = [
            $this->getPluginPath() . '/exports',
            $this->getPluginPath() . '/reports',
            $this->getPluginPath() . '/badges',
            $this->getPluginPath() . '/campaigns'
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
        }
    }

    /**
     * Register EventListeners
     */
    protected function registerEventListeners(): void
    {
        // TODO: Implement registerEventListeners
    }

    /**
     * Register Permissions
     */
    protected function registerPermissions(): void
    {
        // TODO: Implement registerPermissions
    }

    /**
     * Register ScheduledJobs
     */
    protected function registerScheduledJobs(): void
    {
        // TODO: Implement registerScheduledJobs
    }
}