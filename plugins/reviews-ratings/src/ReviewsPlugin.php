<?php
declare(strict_types=1);

namespace Shopologic\Plugins\ReviewsRatings;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\PluginInterface;
use Shopologic\Core\Plugin\Hook;
use ReviewsRatings\Services\ReviewService;
use ReviewsRatings\Services\RatingCalculator;
use ReviewsRatings\Services\ReviewModerator;

class ReviewsPlugin extends AbstractPlugin implements PluginInterface
{
    protected string $name = 'reviews-ratings';
    protected string $version = '1.0.0';
    
    /**
     * Plugin installation
     */
    public function install(): bool
    {
        // Run database migrations
        $this->runMigrations();
        
        // Set default configuration
        $this->setDefaultConfig();
        
        // Create default email templates
        $this->createEmailTemplates();
        
        return true;
    }
    
    /**
     * Plugin activation
     */
    public function activate(): bool
    {
        // Schedule review request emails
        $this->scheduleReviewRequests();
        
        // Initialize rating calculations
        $this->initializeRatings();
        
        return true;
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate(): bool
    {
        // Pause scheduled tasks
        $this->pauseScheduledTasks();
        
        return true;
    }
    
    /**
     * Plugin uninstall
     */
    public function uninstall(): bool
    {
        if ($this->confirmDataRemoval()) {
            // Remove database tables
            $this->dropTables();
            
            // Remove configuration
            $this->removeConfig();
            
            // Remove uploaded media
            $this->removeMediaFiles();
        }
        
        return true;
    }
    
    /**
     * Plugin update
     */
    public function update(string $previousVersion): bool
    {
        // Run update migrations
        $this->runUpdateMigrations($previousVersion);
        
        // Update configuration schema
        $this->updateConfigSchema($previousVersion);
        
        return true;
    }
    
    /**
     * Plugin boot
     */
    public function boot(): void
    {
        // Register services
        $this->registerServices();
        
        // Register hooks
        $this->registerHooks();
        
        // Register API routes
        $this->registerRoutes();
        
        // Register widgets
        $this->registerWidgets();
        
        // Register email templates
        $this->registerEmailTemplates();
    }
    
    /**
     * Register plugin services
     */
    protected function registerServices(): void
    {
        // Review service
        $this->container->singleton(ReviewService::class, function ($container) {
            return new ReviewService(
                $container->get('db'),
                $container->get('events'),
                $this->getConfig()
            );
        });
        
        // Rating calculator
        $this->container->singleton(RatingCalculator::class, function ($container) {
            return new RatingCalculator(
                $container->get('db'),
                $container->get('cache')
            );
        });
        
        // Review moderator
        $this->container->singleton(ReviewModerator::class, function ($container) {
            return new ReviewModerator(
                $this->getConfig('moderation_mode'),
                $this->getConfig('spam_keywords')
            );
        });
    }
    
    /**
     * Register plugin hooks
     */
    protected function registerHooks(): void
    {
        // Display reviews on product page
        Hook::addAction('product.display', [$this, 'displayProductReviews'], 20);
        
        // Add review schema markup
        Hook::addFilter('product.schema', [$this, 'addReviewSchema'], 10);
        
        // Schedule review request after order
        Hook::addAction('order.completed', [$this, 'scheduleReviewRequest'], 30);
        
        // Add admin menu
        Hook::addAction('admin.menu', [$this, 'addAdminMenu'], 50);
        
        // Handle review form submission
        Hook::addAction('product.review_form', [$this, 'renderReviewForm'], 10);
        
        // Update product rating when review is added
        Hook::addAction('review.created', [$this, 'updateProductRating'], 10);
        
        // Send notification emails
        Hook::addAction('review.created', [$this, 'sendReviewNotifications'], 20);
        
        // Add review tab to product edit
        Hook::addAction('admin.product.tabs', [$this, 'addProductReviewTab'], 30);
    }
    
    /**
     * Display product reviews
     */
    public function displayProductReviews(array $data): void
    {
        $product = $data['product'];
        $reviewService = $this->container->get(ReviewService::class);
        
        // Get reviews for product
        $reviews = $reviewService->getProductReviews($product->id, [
            'status' => 'approved',
            'sort' => 'helpful',
            'limit' => 10
        ]);
        
        // Get rating summary
        $ratingSummary = $reviewService->getRatingSummary($product->id);
        
        // Render reviews widget
        echo $this->render('reviews/widget', [
            'product' => $product,
            'reviews' => $reviews,
            'rating_summary' => $ratingSummary,
            'can_review' => $this->canUserReview($product),
            'config' => $this->getConfig()
        ]);
    }
    
    /**
     * Add review schema markup
     */
    public function addReviewSchema(array $schema): array
    {
        if (!$this->getConfig('enable_rich_snippets')) {
            return $schema;
        }
        
        $product = $schema['product'] ?? null;
        if (!$product) {
            return $schema;
        }
        
        $reviewService = $this->container->get(ReviewService::class);
        $ratingSummary = $reviewService->getRatingSummary($product->id);
        
        if ($ratingSummary['count'] > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $ratingSummary['average'],
                'reviewCount' => $ratingSummary['count'],
                'bestRating' => $this->getConfig('rating_scale'),
                'worstRating' => 1
            ];
            
            // Add sample reviews
            $topReviews = $reviewService->getProductReviews($product->id, [
                'status' => 'approved',
                'sort' => 'helpful',
                'limit' => 3
            ]);
            
            $schema['review'] = [];
            foreach ($topReviews as $review) {
                $schema['review'][] = [
                    '@type' => 'Review',
                    'reviewRating' => [
                        '@type' => 'Rating',
                        'ratingValue' => $review->rating,
                        'bestRating' => $this->getConfig('rating_scale')
                    ],
                    'author' => [
                        '@type' => 'Person',
                        'name' => $review->author_name
                    ],
                    'datePublished' => $review->created_at->format('Y-m-d'),
                    'reviewBody' => $review->content
                ];
            }
        }
        
        return $schema;
    }
    
    /**
     * Schedule review request email
     */
    public function scheduleReviewRequest(array $data): void
    {
        $order = $data['order'];
        
        if (!$this->shouldRequestReview($order)) {
            return;
        }
        
        $delay = $this->getConfig('review_request_delay', 14) * 86400; // Days to seconds
        $scheduledDate = time() + $delay;
        
        // Schedule review request
        $this->api->scheduleTask('reviews.send_request', [
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'products' => $order->items->pluck('product_id')->toArray()
        ], $scheduledDate);
    }
    
    /**
     * Check if user can review product
     */
    protected function canUserReview($product): bool
    {
        $user = $this->api->getCurrentUser();
        
        if (!$user) {
            return false;
        }
        
        $reviewService = $this->container->get(ReviewService::class);
        
        // Check if already reviewed
        if ($reviewService->hasUserReviewed($user->id, $product->id)) {
            return false;
        }
        
        // Check if purchase required
        if ($this->getConfig('require_purchase')) {
            return $reviewService->hasUserPurchased($user->id, $product->id);
        }
        
        return true;
    }
    
    /**
     * Update product rating
     */
    public function updateProductRating(array $data): void
    {
        $review = $data['review'];
        $calculator = $this->container->get(RatingCalculator::class);
        
        // Recalculate product rating
        $calculator->updateProductRating($review->product_id);
        
        // Clear cache
        $this->api->clearCache(['product', 'reviews'], $review->product_id);
    }
    
    /**
     * Send review notifications
     */
    public function sendReviewNotifications(array $data): void
    {
        if (!$this->getConfig('email_notifications')) {
            return;
        }
        
        $review = $data['review'];
        
        // Notify store admin
        $this->api->sendEmail('admin', 'new_review_notification', [
            'review' => $review,
            'product' => $review->product,
            'moderate_url' => $this->api->adminUrl('reviews/moderate/' . $review->id)
        ]);
        
        // If review has media, process it
        if ($review->hasMedia()) {
            $this->processReviewMedia($review);
        }
    }
    
    /**
     * Check if should request review
     */
    protected function shouldRequestReview($order): bool
    {
        // Don't request for guest orders if email not provided
        if (!$order->customer_id && !$order->customer_email) {
            return false;
        }
        
        // Check if customer opted out
        if ($order->customer && $order->customer->hasOptedOut('review_requests')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Run database migrations
     */
    protected function runMigrations(): void
    {
        $migrations = [
            'create_reviews_table.php',
            'create_review_votes_table.php',
            'create_review_reports_table.php',
            'create_review_responses_table.php',
            'create_review_invitations_table.php'
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
            'enable_reviews' => true,
            'enable_ratings' => true,
            'rating_scale' => 5,
            'require_purchase' => true,
            'moderation_mode' => 'auto',
            'allow_anonymous' => false,
            'review_request_delay' => 14,
            'enable_rich_snippets' => true,
            'enable_media_uploads' => true,
            'max_media_files' => 5,
            'min_review_length' => 20,
            'enable_qa' => true
        ];
        
        foreach ($defaults as $key => $value) {
            if ($this->getConfig($key) === null) {
                $this->setConfig($key, $value);
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