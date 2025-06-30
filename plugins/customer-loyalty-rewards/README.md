# ⭐ Customer Loyalty & Rewards Plugin

![Quality Badge](https://img.shields.io/badge/Quality-57%25%20(F)-red)


Comprehensive loyalty program management with dynamic tier progression, sophisticated rewards system, and advanced customer engagement features.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

This plugin is part of the enhanced Shopologic ecosystem featuring 47 advanced models, cross-plugin integration, real-time events, performance monitoring, and automated testing.

## 🚀 Quick Start

```bash
# Initialize plugin ecosystem
php bootstrap_plugins.php

# Run with integration demonstration
php bootstrap_plugins.php --demo
```

## ✨ Key Features

### 🏆 Dynamic Tier System
- **Automatic Tier Progression** - Smart qualification tracking and upgrades
- **Flexible Tier Rules** - Points, spending, frequency-based qualifications
- **Tier Benefits** - Customizable perks and reward multipliers
- **Bonus Point Awards** - Welcome bonuses for tier upgrades
- **Tier Protection** - Grace periods and downgrade protection

### 💎 Advanced Rewards System
- **Multiple Reward Types** - Discounts, gift cards, experiences, products
- **Smart Restrictions** - Time limits, usage constraints, customer segments
- **Dynamic Pricing** - Variable point costs based on availability
- **Gift Card Integration** - Digital gift card generation and management
- **Expiration Management** - Flexible expiration policies

### 📊 Comprehensive Analytics
- **Member Engagement** - Activity tracking and behavior analysis
- **Reward Performance** - Redemption rates and ROI tracking
- **Tier Analytics** - Progression patterns and retention metrics
- **Point Lifecycle** - Earning, burning, and expiration analytics

## 🏗️ Plugin Architecture

### Models
- **`LoyaltyTier.php`** - Dynamic tier management with progression logic
- **`PointTransaction.php`** - Comprehensive point transaction tracking
- **`Reward.php`** - Advanced reward system with multiple types
- **`RewardRedemption.php`** - Redemption tracking with gift card support
- **`TierUpgrade.php`** - Tier progression history and qualification tracking
- **`LoyaltyMember.php`** - Member profile and engagement management

### Services
- **`LoyaltyManager.php`** - Central loyalty program orchestration
- **Point Calculation** - Dynamic point earning algorithms
- **Tier Management** - Qualification tracking and upgrades
- **Reward Processing** - Redemption validation and fulfillment

### Controllers
- **`LoyaltyController.php`** - REST API endpoints for loyalty operations

### Repositories
- **`LoyaltyMemberRepository.php`** - Member data access layer
- **`PointTransactionRepository.php`** - Transaction history and analytics

## 🔗 Cross-Plugin Integration

### Provider Interface
Implements `LoyaltyProviderInterface` for seamless integration:

```php
interface LoyaltyProviderInterface {
    public function getPointBalance(int $customerId): int;
    public function awardPoints(int $customerId, int $points, string $reason, array $metadata = []): bool;
    public function redeemPoints(int $customerId, int $points, string $reason, array $metadata = []): bool;
    public function getAvailableRewards(int $customerId): array;
    public function getMemberTier(int $customerId): ?LoyaltyTier;
}
```

### Integration Examples

```php
// Get loyalty provider
$loyaltyProvider = $integrationManager->getLoyaltyProvider();

// Award points for purchase
$loyaltyProvider->awardPoints(12345, 100, 'Purchase reward', ['order_id' => 'ORD-123']);

// Check point balance
$balance = $loyaltyProvider->getPointBalance(12345);

// Get available rewards
$rewards = $loyaltyProvider->getAvailableRewards(12345);
```

## 🏆 Advanced Features

### Dynamic Tier Management

```php
// Check tier qualification
$tier = LoyaltyTier::find(1);
$member = LoyaltyMember::find(12345);
$qualifies = $tier->memberQualifies($member);

// Get qualification progress
$nextTier = $tier->getNextTier();
$progress = $nextTier->getQualificationProgress($member);

// Automatic tier upgrades
if ($nextTier && $nextTier->memberQualifies($member)) {
    $upgrade = TierUpgrade::create([
        'loyalty_member_id' => $member->id,
        'previous_tier_id' => $tier->id,
        'new_tier_id' => $nextTier->id,
        'qualification_met_at' => now(),
        'bonus_points_awarded' => $nextTier->welcome_bonus_points
    ]);
    
    // Award welcome bonus
    $upgrade->awardBonusPoints(500, 'Welcome to Gold tier!');
}
```

### Point Transaction Management

```php
// Award points with detailed tracking
$transaction = PointTransaction::create([
    'loyalty_member_id' => 12345,
    'type' => 'earned',
    'points' => 100,
    'reason' => 'purchase',
    'reference_type' => 'order',
    'reference_id' => 'ORD-123',
    'metadata' => ['order_total' => 299.99]
]);

// Process refunds with reversal
$originalTransaction = PointTransaction::find(1);
$reversal = $originalTransaction->createReversal('Order refund');

// Get transaction history
$transactions = PointTransaction::getCustomerHistory(12345, 30); // Last 30 days
```

### Sophisticated Reward System

```php
// Check reward eligibility
$reward = Reward::find(1);
$member = LoyaltyMember::find(12345);
$canRedeem = $reward->canBeRedeemedBy($member);

// Calculate dynamic discount
$orderAmount = 199.99;
$discountAmount = $reward->calculateDiscountForOrder($orderAmount);

// Process reward redemption
$redemption = RewardRedemption::create([
    'loyalty_member_id' => $member->id,
    'reward_id' => $reward->id,
    'points_redeemed' => $reward->point_cost,
    'gift_card_code' => $reward->generateGiftCardCode()
]);

// Track redemption analytics
$reward->recordRedemption($redemption);
```

## ⚡ Real-Time Events

### Event Listeners

```php
// Tier upgrade celebrations
$eventDispatcher->listen('loyalty.tier_upgraded', function($event) {
    $data = $event->getData();
    // Send congratulations email
    $marketingProvider = $integrationManager->getMarketingProvider();
    $marketingProvider->sendTransactionalEmail('tier_upgrade_congratulations', 
        ['customer_id' => $data['customer_id']], $data);
});

// Point earning events
$eventDispatcher->listen('loyalty.points_earned', function($event) {
    $data = $event->getData();
    // Check for tier qualification
    $member = LoyaltyMember::find($data['customer_id']);
    $member->checkTierUpgradeEligibility();
});
```

### Event Dispatching

```php
// Dispatch loyalty events
$eventDispatcher->dispatch('loyalty.tier_upgraded', [
    'customer_id' => 12345,
    'previous_tier' => 'silver',
    'new_tier' => 'gold',
    'bonus_points' => 500,
    'upgrade_date' => now()->toISOString()
]);
```

## 📈 Performance Monitoring

### Health Checks

```php
// Register loyalty-specific health checks
$healthMonitor->registerHealthCheck('loyalty', 'points_system_integrity', function() {
    // Verify point calculation accuracy
    return $this->verifyPointSystemIntegrity();
});

$healthMonitor->registerHealthCheck('loyalty', 'tier_qualification_accuracy', function() {
    // Check tier qualification logic
    return $this->validateTierQualifications();
});
```

### Metrics Tracking

```php
// Record loyalty metrics
$healthMonitor->recordResponseTime('loyalty', 'points_calculation', 25.1);
$healthMonitor->recordMemoryUsage('loyalty', 8.2);
$healthMonitor->recordDatabaseQueryTime('loyalty', 'SELECT * FROM point_transactions', 15.7);
```

## 🧪 Automated Testing

### Test Coverage
- **Unit Tests** - Point calculations and tier logic
- **Integration Tests** - Cross-plugin reward workflows
- **Performance Tests** - Large-scale point processing
- **Security Tests** - Point manipulation protection

### Example Tests

```php
class LoyaltyTestSuite extends PluginTestSuite
{
    public function getUnitTests(): array
    {
        return [
            'test_point_calculation' => [$this, 'testPointCalculation'],
            'test_tier_qualification' => [$this, 'testTierQualification'],
            'test_reward_eligibility' => [$this, 'testRewardEligibility']
        ];
    }
    
    public function testPointCalculation(): void
    {
        $member = new LoyaltyMember(['current_points' => 1000]);
        $points = $member->calculatePointsForPurchase(100.00);
        Assert::assertEquals(100, $points); // 1 point per dollar
    }
}
```

## 🛠️ Configuration

### Plugin Settings

```json
{
    "points_per_dollar": 10,
    "points_expiry_months": 24,
    "tier_qualification_period": "rolling_12_months",
    "enable_tier_protection": true,
    "tier_protection_period_months": 3,
    "welcome_bonus_points": 500,
    "referral_bonus_points": 250,
    "birthday_bonus_points": 100
}
```

### Database Tables
- `loyalty_members` - Member profiles and status
- `loyalty_tiers` - Tier configuration and benefits
- `point_transactions` - Complete transaction history
- `rewards` - Reward catalog and settings
- `reward_redemptions` - Redemption tracking
- `tier_upgrades` - Tier progression history

## 📚 API Endpoints

### REST API
- `GET /api/v1/loyalty/members/{id}` - Get member profile
- `POST /api/v1/loyalty/points/award` - Award points
- `POST /api/v1/loyalty/points/redeem` - Redeem points
- `GET /api/v1/loyalty/rewards` - List available rewards
- `POST /api/v1/loyalty/rewards/redeem` - Redeem reward
- `GET /api/v1/loyalty/tiers` - Get tier information

### Usage Examples

```bash
# Get member profile
curl -X GET /api/v1/loyalty/members/12345 \
  -H "Authorization: Bearer {token}"

# Award points
curl -X POST /api/v1/loyalty/points/award \
  -H "Content-Type: application/json" \
  -d '{"customer_id": 12345, "points": 100, "reason": "purchase"}'

# Redeem reward
curl -X POST /api/v1/loyalty/rewards/redeem \
  -H "Content-Type: application/json" \
  -d '{"customer_id": 12345, "reward_id": 5}'
```

## 🔧 Installation & Setup

### Requirements
- PHP 8.3+
- PostgreSQL database
- Shopologic Core Framework

### Installation

```bash
# Activate plugin
php cli/plugin.php activate customer-loyalty-rewards

# Run migrations
php cli/migrate.php up

# Initialize plugin ecosystem
php bootstrap_plugins.php
```

## 📖 Documentation

- **Program Design Guide** - Loyalty program strategy and setup
- **API Documentation** - Complete integration reference
- **Analytics Guide** - Reporting and insights configuration
- **Marketing Integration** - Cross-plugin workflow examples

## 🚀 Production Ready

This plugin is part of the enhanced Shopologic ecosystem and is production-ready with:
- ✅ Comprehensive model layer with sophisticated business logic
- ✅ Cross-plugin integration via standardized interfaces
- ✅ Real-time event system with middleware support
- ✅ Performance monitoring and health checks
- ✅ Automated testing framework
- ✅ Complete documentation and examples

---

**Customer Loyalty & Rewards** - Advanced loyalty program management for Shopologic