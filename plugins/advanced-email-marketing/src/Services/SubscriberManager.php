<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Services;

use AdvancedEmailMarketing\Repositories\SubscriberRepository;
use Shopologic\Core\Events\EventDispatcher;

class SubscriberManager\n{
    private SubscriberRepository $subscriberRepository;
    private array $config;

    public function __construct(
        SubscriberRepository $subscriberRepository,
        array $config = []
    ) {
        $this->subscriberRepository = $subscriberRepository;
        $this->config = $config;
    }

    /**
     * Create new subscriber
     */
    public function createSubscriber(array $data): array
    {
        // Validate email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address');
        }

        // Check if subscriber already exists
        $existing = $this->subscriberRepository->findByEmail($data['email']);
        if ($existing) {
            return $this->resubscribe($existing['id'], $data);
        }

        // Set default values
        $data['status'] = $data['status'] ?? ($this->config['double_opt_in'] ?? true ? 'pending' : 'subscribed');
        $data['engagement_score'] = 50.0; // Start with neutral score
        $data['source'] = $data['source'] ?? 'manual';
        $data['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? null;
        $data['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $subscriber = $this->subscriberRepository->create($data);

        // Send confirmation email if double opt-in
        if ($data['status'] === 'pending') {
            $this->sendConfirmationEmail($subscriber);
        }

        // Dispatch event
        EventDispatcher::dispatch('subscriber.created', $subscriber);

        return $subscriber;
    }

    /**
     * Find or create subscriber from customer
     */
    public function findOrCreateFromCustomer(array $customer): array
    {
        $subscriber = $this->subscriberRepository->findByEmail($customer['email']);
        
        if ($subscriber) {
            // Update customer association
            $this->subscriberRepository->update($subscriber['id'], [
                'customer_id' => $customer['id'],
                'first_name' => $customer['first_name'] ?? $subscriber['first_name'],
                'last_name' => $customer['last_name'] ?? $subscriber['last_name']
            ]);
            return $subscriber;
        }

        return $this->createSubscriber([
            'email' => $customer['email'],
            'customer_id' => $customer['id'],
            'first_name' => $customer['first_name'] ?? '',
            'last_name' => $customer['last_name'] ?? '',
            'status' => 'subscribed', // Customer registration implies consent
            'source' => 'customer_registration'
        ]);
    }

    /**
     * Confirm subscription
     */
    public function confirmSubscription(string $token): bool
    {
        $subscriber = $this->subscriberRepository->findByConfirmationToken($token);
        if (!$subscriber) {
            return false;
        }

        $result = $this->subscriberRepository->update($subscriber['id'], [
            'status' => 'subscribed',
            'confirmed_at' => date('Y-m-d H:i:s'),
            'confirmation_token' => null
        ]);

        if ($result) {
            EventDispatcher::dispatch('subscriber.confirmed', $subscriber);
        }

        return $result;
    }

    /**
     * Unsubscribe subscriber
     */
    public function unsubscribe(int $subscriberId, string $reason = null): bool
    {
        $result = $this->subscriberRepository->update($subscriberId, [
            'status' => 'unsubscribed',
            'unsubscribed_at' => date('Y-m-d H:i:s'),
            'unsubscribe_reason' => $reason
        ]);

        if ($result) {
            $subscriber = $this->subscriberRepository->findById($subscriberId);
            EventDispatcher::dispatch('subscriber.unsubscribed', $subscriber);
        }

        return $result;
    }

    /**
     * Resubscribe subscriber
     */
    public function resubscribe(int $subscriberId, array $data = []): array
    {
        $updateData = array_merge($data, [
            'status' => 'subscribed',
            'unsubscribed_at' => null,
            'unsubscribe_reason' => null,
            'last_activity_at' => date('Y-m-d H:i:s')
        ]);

        $this->subscriberRepository->update($subscriberId, $updateData);
        $subscriber = $this->subscriberRepository->findById($subscriberId);

        EventDispatcher::dispatch('subscriber.resubscribed', $subscriber);

        return $subscriber;
    }

    /**
     * Update subscriber
     */
    public function updateSubscriber(int $subscriberId, array $data): bool
    {
        // Don't allow direct status changes through this method
        unset($data['status']);

        return $this->subscriberRepository->update($subscriberId, $data);
    }

    /**
     * Track behavior
     */
    public function trackBehavior(array $subscriber, string $event, array $data): void
    {
        $behaviorData = [
            'event' => $event,
            'data' => $data,
            'timestamp' => time()
        ];

        // Get existing behavior data
        $currentBehavior = json_decode($subscriber['behavior_data'] ?? '[]', true);
        $currentBehavior[] = $behaviorData;

        // Keep only last 100 events to prevent data bloat
        if (count($currentBehavior) > 100) {
            $currentBehavior = array_slice($currentBehavior, -100);
        }

        $this->subscriberRepository->update($subscriber['id'], [
            'behavior_data' => json_encode($currentBehavior),
            'last_activity_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Update engagement score
     */
    public function updateEngagementScore(array $subscriber): void
    {
        $score = $this->calculateEngagementScore($subscriber);
        
        $this->subscriberRepository->update($subscriber['id'], [
            'engagement_score' => $score
        ]);
    }

    /**
     * Update all engagement scores
     */
    public function updateAllEngagementScores(): void
    {
        $subscribers = $this->subscriberRepository->getAllActive();
        
        foreach ($subscribers as $subscriber) {
            $this->updateEngagementScore($subscriber);
        }
    }

    /**
     * Get engagement summary
     */
    public function getEngagementSummary(int $subscriberId): array
    {
        $subscriber = $this->subscriberRepository->findById($subscriberId);
        if (!$subscriber) {
            return [];
        }

        $emailStats = $this->subscriberRepository->getEmailStats($subscriberId);
        $behaviorData = json_decode($subscriber['behavior_data'] ?? '[]', true);

        return [
            'engagement_score' => $subscriber['engagement_score'],
            'engagement_level' => $this->getEngagementLevel($subscriber['engagement_score']),
            'total_emails_received' => $emailStats['total_received'],
            'emails_opened' => $emailStats['opened'],
            'emails_clicked' => $emailStats['clicked'],
            'open_rate' => $emailStats['total_received'] > 0 ? ($emailStats['opened'] / $emailStats['total_received']) * 100 : 0,
            'click_rate' => $emailStats['opened'] > 0 ? ($emailStats['clicked'] / $emailStats['opened']) * 100 : 0,
            'last_activity' => $subscriber['last_activity_at'],
            'recent_behaviors' => array_slice($behaviorData, -10),
            'subscription_date' => $subscriber['created_at'],
            'days_subscribed' => $this->getDaysSubscribed($subscriber['created_at'])
        ];
    }

    /**
     * Get total subscribers
     */
    public function getTotalSubscribers(): int
    {
        return $this->subscriberRepository->getTotalCount();
    }

    /**
     * Get active subscribers
     */
    public function getAllActiveSubscribers(): array
    {
        return $this->subscriberRepository->getActiveSubscribers();
    }

    /**
     * Bulk import subscribers
     */
    public function bulkImport(array $subscribersData, array $options = []): array
    {
        $imported = [];
        $errors = [];
        $skipped = [];

        foreach ($subscribersData as $index => $data) {
            try {
                // Validate required fields
                if (empty($data['email'])) {
                    $errors[] = [
                        'row' => $index + 1,
                        'error' => 'Email is required'
                    ];
                    continue;
                }

                // Check if already exists
                if ($this->subscriberRepository->findByEmail($data['email'])) {
                    if ($options['skip_existing'] ?? true) {
                        $skipped[] = $data['email'];
                        continue;
                    }
                }

                $subscriber = $this->createSubscriber(array_merge($data, [
                    'source' => $options['source'] ?? 'bulk_import'
                ]));

                $imported[] = $subscriber['email'];

            } catch (\RuntimeException $e) {
                $errors[] = [
                    'row' => $index + 1,
                    'email' => $data['email'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'total_processed' => count($subscribersData)
        ];
    }

    /**
     * Export subscribers
     */
    public function exportSubscribers(array $filters = []): array
    {
        return $this->subscriberRepository->getSubscribersForExport($filters);
    }

    /**
     * Mark as bounced
     */
    public function markAsBounced(int $subscriberId, string $bounceType): bool
    {
        $status = $bounceType === 'hard' ? 'bounced' : 'subscribed';
        
        return $this->subscriberRepository->update($subscriberId, [
            'status' => $status,
            'bounce_count' => $this->subscriberRepository->incrementBounceCount($subscriberId)
        ]);
    }

    /**
     * Mark as complained
     */
    public function markAsComplained(int $subscriberId): bool
    {
        return $this->subscriberRepository->update($subscriberId, [
            'status' => 'complained'
        ]);
    }

    /**
     * Get subscriber preferences
     */
    public function getSubscriberPreferences(int $subscriberId): array
    {
        $subscriber = $this->subscriberRepository->findById($subscriberId);
        if (!$subscriber) {
            return [];
        }

        return json_decode($subscriber['preferences'] ?? '{}', true);
    }

    /**
     * Update subscriber preferences
     */
    public function updateSubscriberPreferences(int $subscriberId, array $preferences): bool
    {
        return $this->subscriberRepository->update($subscriberId, [
            'preferences' => json_encode($preferences)
        ]);
    }

    /**
     * Calculate engagement score
     */
    private function calculateEngagementScore(array $subscriber): float
    {
        $emailStats = $this->subscriberRepository->getEmailStats($subscriber['id']);
        $behaviorData = json_decode($subscriber['behavior_data'] ?? '[]', true);

        $score = 0;

        // Email engagement (40% of score)
        if ($emailStats['total_received'] > 0) {
            $openRate = $emailStats['opened'] / $emailStats['total_received'];
            $clickRate = $emailStats['total_received'] > 0 ? $emailStats['clicked'] / $emailStats['total_received'] : 0;
            
            $score += ($openRate * 25) + ($clickRate * 15);
        }

        // Recency (30% of score)
        if ($subscriber['last_activity_at']) {
            $daysSinceActivity = (time() - strtotime($subscriber['last_activity_at'])) / (24 * 60 * 60);
            $recencyScore = max(0, 30 - ($daysSinceActivity * 0.5));
            $score += min(30, $recencyScore);
        }

        // Behavioral activity (30% of score)
        $recentBehaviors = array_filter($behaviorData, function($behavior) {
            return $behavior['timestamp'] > (time() - (30 * 24 * 60 * 60)); // Last 30 days
        });

        $behaviorScore = min(30, count($recentBehaviors) * 2);
        $score += $behaviorScore;

        return min(100, max(0, $score));
    }

    /**
     * Get engagement level
     */
    private function getEngagementLevel(float $score): string
    {
        if ($score >= 80) return 'high';
        if ($score >= 50) return 'medium';
        return 'low';
    }

    /**
     * Get days subscribed
     */
    private function getDaysSubscribed(string $subscriptionDate): int
    {
        return (int)((time() - strtotime($subscriptionDate)) / (24 * 60 * 60));
    }

    /**
     * Send confirmation email
     */
    private function sendConfirmationEmail(array $subscriber): void
    {
        $token = $this->generateConfirmationToken();
        
        // Store token
        $this->subscriberRepository->update($subscriber['id'], [
            'confirmation_token' => $token
        ]);

        // Send email (this would integrate with email sender)
        // For now, just dispatch an event
        EventDispatcher::dispatch('subscriber.confirmation_email', [
            'subscriber' => $subscriber,
            'confirmation_token' => $token
        ]);
    }

    /**
     * Generate confirmation token
     */
    private function generateConfirmationToken(): string
    {
        return hash('sha256', uniqid() . time() . rand());
    }
}