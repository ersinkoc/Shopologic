<?php

declare(strict_types=1);
namespace Shopologic\Plugins\LoyaltyGamification;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\Hook;

/**
 * Loyalty Gamification Plugin
 * 
 * Gamified loyalty system with points, badges, achievements, and challenges
 */
class LoyaltyGamificationPlugin extends AbstractPlugin
{
    private $loyaltyEngine;
    private $badgeManager;
    private $challengeManager;

    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
        $this->initializeDailyChallenges();
    }

    private function registerServices(): void
    {
        $this->loyaltyEngine = new Services\LoyaltyEngine($this->api);
        $this->badgeManager = new Services\BadgeManager($this->api);
        $this->challengeManager = new Services\ChallengeManager($this->api);
    }

    private function registerHooks(): void
    {
        // Award points for actions
        Hook::addAction('customer.registered', [$this, 'awardRegistrationBonus'], 10, 1);
        Hook::addAction('order.completed', [$this, 'awardPurchasePoints'], 10, 1);
        Hook::addAction('product.reviewed', [$this, 'awardReviewPoints'], 10, 2);
        Hook::addAction('customer.referral_success', [$this, 'awardReferralPoints'], 10, 2);
        Hook::addAction('social.shared', [$this, 'awardSocialPoints'], 10, 2);
        
        // Check for achievements after point awards
        Hook::addAction('loyalty.points_awarded', [$this, 'checkAchievements'], 10, 2);
        
        // Display loyalty widgets
        Hook::addFilter('customer.dashboard', [$this, 'addLoyaltyWidget'], 10, 2);
        Hook::addFilter('checkout.summary', [$this, 'addPointsPreview'], 10, 2);
    }

    public function awardRegistrationBonus($customer): void
    {
        $points = $this->getConfig('registration_bonus', 100);
        $this->loyaltyEngine->awardPoints($customer->id, $points, 'registration', 'Welcome bonus');
        
        Hook::doAction('loyalty.points_awarded', $customer->id, $points);
    }

    public function awardPurchasePoints($order): void
    {
        $pointsPerDollar = $this->getConfig('points_per_dollar', 10);
        $points = floor($order->total * $pointsPerDollar);
        
        $this->loyaltyEngine->awardPoints(
            $order->customer_id, 
            $points, 
            'purchase', 
            "Order #{$order->id}"
        );
        
        // Check for streak bonuses
        $this->checkPurchaseStreaks($order->customer_id);
        
        Hook::doAction('loyalty.points_awarded', $order->customer_id, $points);
    }

    public function awardReviewPoints($review, $product): void
    {
        $points = $this->getConfig('review_points', 50);
        $this->loyaltyEngine->awardPoints(
            $review->customer_id, 
            $points, 
            'review', 
            "Review for {$product->name}"
        );
        
        Hook::doAction('loyalty.points_awarded', $review->customer_id, $points);
    }

    public function awardReferralPoints($referrer, $referred): void
    {
        $points = $this->getConfig('referral_points', 500);
        $this->loyaltyEngine->awardPoints(
            $referrer->id, 
            $points, 
            'referral', 
            "Referred {$referred->email}"
        );
        
        Hook::doAction('loyalty.points_awarded', $referrer->id, $points);
    }

    public function awardSocialPoints($customer, $platform): void
    {
        $points = 25; // Fixed points for social sharing
        $this->loyaltyEngine->awardPoints(
            $customer->id, 
            $points, 
            'social', 
            "Shared on {$platform}"
        );
        
        Hook::doAction('loyalty.points_awarded', $customer->id, $points);
    }

    public function checkAchievements($customerId, $pointsAwarded): void
    {
        $totalPoints = $this->loyaltyEngine->getTotalPoints($customerId);
        $newBadges = [];
        
        // Point milestone badges
        $pointMilestones = [100, 500, 1000, 5000, 10000, 25000, 50000];
        foreach ($pointMilestones as $milestone) {
            if ($totalPoints >= $milestone && !$this->badgeManager->hasBadge($customerId, "points_{$milestone}")) {
                $badge = $this->badgeManager->awardBadge($customerId, "points_{$milestone}", [
                    'name' => "{$milestone} Points",
                    'description' => "Earned {$milestone} loyalty points",
                    'icon' => 'star',
                    'color' => $this->getBadgeColor($milestone)
                ]);
                $newBadges[] = $badge;
            }
        }
        
        // Purchase streaks
        $streak = $this->getPurchaseStreak($customerId);
        $streakMilestones = [3, 5, 10, 20, 50];
        foreach ($streakMilestones as $milestone) {
            if ($streak >= $milestone && !$this->badgeManager->hasBadge($customerId, "streak_{$milestone}")) {
                $badge = $this->badgeManager->awardBadge($customerId, "streak_{$milestone}", [
                    'name' => "{$milestone} Day Streak",
                    'description' => "Made purchases {$milestone} days in a row",
                    'icon' => 'fire',
                    'color' => 'orange'
                ]);
                $newBadges[] = $badge;
            }
        }
        
        // Notify about new badges
        if (!empty($newBadges) && $this->getConfig('badge_notifications', true)) {
            $this->sendBadgeNotifications($customerId, $newBadges);
        }
        
        // Update daily challenges
        $this->challengeManager->updateProgress($customerId, $pointsAwarded);
    }

    private function checkPurchaseStreaks($customerId): void
    {
        $orders = $this->api->database()->table('orders')
            ->where('customer_id', $customerId)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get();
            
        if (count($orders) >= 2) {
            $currentStreak = 1;
            for ($i = 1; $i < count($orders); $i++) {
                $daysDiff = (strtotime($orders[$i-1]->created_at) - strtotime($orders[$i]->created_at)) / 86400;
                if ($daysDiff <= 1) {
                    $currentStreak++;
                } else {
                    break;
                }
            }
            
            if ($currentStreak >= 3) {
                $bonusPoints = $currentStreak * 10;
                $this->loyaltyEngine->awardPoints(
                    $customerId, 
                    $bonusPoints, 
                    'streak_bonus', 
                    "{$currentStreak} day purchase streak"
                );
            }
        }
    }

    public function addLoyaltyWidget($dashboard, $customer): string
    {
        $points = $this->loyaltyEngine->getTotalPoints($customer->id);
        $badges = $this->badgeManager->getBadges($customer->id);
        $rank = $this->getCustomerRank($customer->id);
        $challenges = $this->challengeManager->getActiveChallenges($customer->id);
        
        return $dashboard . $this->api->view('loyalty/dashboard-widget', [
            'points' => $points,
            'badges' => $badges,
            'rank' => $rank,
            'challenges' => $challenges,
            'next_milestone' => $this->getNextMilestone($points)
        ]);
    }

    public function addPointsPreview($summary, $order): string
    {
        $pointsPerDollar = $this->getConfig('points_per_dollar', 10);
        $pointsToEarn = floor($order->total * $pointsPerDollar);
        
        return $summary . $this->api->view('loyalty/points-preview', [
            'points_to_earn' => $pointsToEarn,
            'points_per_dollar' => $pointsPerDollar
        ]);
    }

    private function initializeDailyChallenges(): void
    {
        $this->api->scheduler()->addJob('daily_challenges', '0 0 * * *', function() {
            $this->challengeManager->createDailyChallenges();
        });
    }

    private function registerRoutes(): void
    {
        $this->api->router()->get('/loyalty/profile', 'Controllers\LoyaltyController@getProfile');
        $this->api->router()->get('/loyalty/leaderboard', 'Controllers\LoyaltyController@getLeaderboard');
        $this->api->router()->post('/loyalty/redeem', 'Controllers\LoyaltyController@redeemPoints');
        $this->api->router()->get('/loyalty/challenges', 'Controllers\LoyaltyController@getChallenges');
        $this->api->router()->post('/loyalty/challenge/{id}/complete', 'Controllers\LoyaltyController@completeChallenge');
    }

    private function getBadgeColor($milestone): string
    {
        if ($milestone >= 50000) return 'purple';
        if ($milestone >= 25000) return 'gold';
        if ($milestone >= 10000) return 'silver';
        if ($milestone >= 5000) return 'bronze';
        return 'blue';
    }

    private function getPurchaseStreak($customerId): int
    {
        return $this->api->cache()->remember("streak_{$customerId}", 3600, function() use ($customerId) {
            $orders = $this->api->database()->table('orders')
                ->where('customer_id', $customerId)
                ->where('status', 'completed')
                ->orderBy('created_at', 'desc')
                ->get();
                
            $streak = 0;
            $lastDate = null;
            
            foreach ($orders as $order) {
                $orderDate = date('Y-m-d', strtotime($order->created_at));
                if ($lastDate === null) {
                    $streak = 1;
                    $lastDate = $orderDate;
                } else {
                    $daysDiff = (strtotime($lastDate) - strtotime($orderDate)) / 86400;
                    if ($daysDiff == 1) {
                        $streak++;
                        $lastDate = $orderDate;
                    } else {
                        break;
                    }
                }
            }
            
            return $streak;
        });
    }

    private function getCustomerRank($customerId): int
    {
        $customerPoints = $this->loyaltyEngine->getTotalPoints($customerId);
        $rank = $this->api->database()->table('loyalty_points')
            ->selectRaw('COUNT(DISTINCT customer_id) + 1 as rank')
            ->where('total_points', '>', $customerPoints)
            ->first();
            
        return $rank->rank ?? 1;
    }

    private function getNextMilestone($currentPoints): array
    {
        $milestones = [100, 500, 1000, 5000, 10000, 25000, 50000, 100000];
        
        foreach ($milestones as $milestone) {
            if ($currentPoints < $milestone) {
                return [
                    'points' => $milestone,
                    'remaining' => $milestone - $currentPoints
                ];
            }
        }
        
        return ['points' => 100000, 'remaining' => 0];
    }

    private function sendBadgeNotifications($customerId, $badges): void
    {
        foreach ($badges as $badge) {
            $this->api->notification()->send($customerId, [
                'type' => 'badge_earned',
                'title' => '🏆 New Badge Earned!',
                'message' => "You've earned the '{$badge['name']}' badge!",
                'data' => ['badge' => $badge]
            ]);
        }
    }

    public function install(): void
    {
        $this->runMigrations();
        $this->createDefaultBadges();
        $this->createDefaultChallenges();
    }

    private function createDefaultBadges(): void
    {
        $badges = [
            ['id' => 'first_purchase', 'name' => 'First Purchase', 'description' => 'Made your first purchase'],
            ['id' => 'loyal_customer', 'name' => 'Loyal Customer', 'description' => '10 successful orders'],
            ['id' => 'reviewer', 'name' => 'Product Reviewer', 'description' => 'Wrote 5 product reviews'],
            ['id' => 'social_butterfly', 'name' => 'Social Butterfly', 'description' => 'Shared 10 products on social media']
        ];

        foreach ($badges as $badge) {
            $this->api->database()->table('loyalty_badges')->insert($badge);
        }
    }

    private function createDefaultChallenges(): void
    {
        $challenges = [
            ['name' => 'Daily Shopper', 'description' => 'Make a purchase today', 'reward' => 100, 'type' => 'daily'],
            ['name' => 'Review Master', 'description' => 'Write 3 reviews this week', 'reward' => 300, 'type' => 'weekly'],
            ['name' => 'Social Sharer', 'description' => 'Share 5 products this month', 'reward' => 500, 'type' => 'monthly']
        ];

        foreach ($challenges as $challenge) {
            $this->api->database()->table('loyalty_challenges')->insert($challenge);
        }
    }

    /**
     * Register Services
     */
    protected function registerServices(): void
    {
        // TODO: Implement registerServices
    }

    /**
     * Register EventListeners
     */
    protected function registerEventListeners(): void
    {
        // TODO: Implement registerEventListeners
    }

    /**
     * Register Hooks
     */
    protected function registerHooks(): void
    {
        // TODO: Implement registerHooks
    }

    /**
     * Register Routes
     */
    protected function registerRoutes(): void
    {
        // TODO: Implement registerRoutes
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