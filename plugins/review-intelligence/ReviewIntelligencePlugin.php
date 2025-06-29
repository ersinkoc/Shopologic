<?php
namespace ReviewIntelligence;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\Hook;

/**
 * Review Intelligence Plugin
 * 
 * Smart review management with sentiment analysis and automated responses
 */
class ReviewIntelligencePlugin extends AbstractPlugin
{
    private $sentimentAnalyzer;
    private $fakeDetector;
    private $responseGenerator;
    private $insightsEngine;

    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
        $this->scheduleAnalysisTasks();
    }

    private function registerServices(): void
    {
        $this->sentimentAnalyzer = new Services\SentimentAnalyzer($this->api);
        $this->fakeDetector = new Services\FakeReviewDetector($this->api);
        $this->responseGenerator = new Services\ResponseGenerator($this->api);
        $this->insightsEngine = new Services\ReviewInsightsEngine($this->api);
    }

    private function registerHooks(): void
    {
        // Review submission and moderation
        Hook::addFilter('review.submitted', [$this, 'analyzeReview'], 5, 2);
        Hook::addAction('review.moderated', [$this, 'postModerationActions'], 10, 2);
        Hook::addFilter('review.display', [$this, 'enhanceReviewDisplay'], 10, 2);
        
        // Automated actions
        Hook::addAction('order.completed', [$this, 'scheduleReviewRequest'], 10, 1);
        Hook::addAction('review.published', [$this, 'generateAutoResponse'], 10, 1);
        
        // Product insights
        Hook::addFilter('product.display', [$this, 'addReviewInsights'], 10, 2);
        Hook::addFilter('product.admin_view', [$this, 'addDetailedAnalytics'], 10, 2);
        
        // Admin dashboards
        Hook::addAction('admin.reviews.dashboard', [$this, 'reviewsDashboard'], 10);
        Hook::addFilter('admin.review.actions', [$this, 'addIntelligenceActions'], 10, 2);
    }

    public function analyzeReview($review, $product): array
    {
        // Sentiment analysis
        $sentiment = $this->sentimentAnalyzer->analyze($review['content']);
        $review['sentiment_score'] = $sentiment['score'];
        $review['sentiment_label'] = $sentiment['label']; // positive, neutral, negative
        $review['emotion_tags'] = $sentiment['emotions']; // joy, anger, disappointment, etc.
        
        // Extract key phrases and topics
        $keyPhrases = $this->sentimentAnalyzer->extractKeyPhrases($review['content']);
        $review['key_phrases'] = $keyPhrases;
        $review['topics'] = $this->categorizeTopics($keyPhrases);
        
        // Fake review detection
        if ($this->getConfig('enable_fake_detection', true)) {
            $fakeAnalysis = $this->fakeDetector->analyze($review, $product);
            $review['authenticity_score'] = $fakeAnalysis['score'];
            $review['fake_indicators'] = $fakeAnalysis['indicators'];
            
            if ($fakeAnalysis['score'] < 0.3) { // Likely fake
                $review['status'] = 'flagged';
                $review['flag_reason'] = 'Low authenticity score';
                $this->notifyModerator($review, $fakeAnalysis);
            }
        }
        
        // Quality assessment
        $quality = $this->assessReviewQuality($review);
        $review['quality_score'] = $quality['score'];
        $review['helpful_aspects'] = $quality['helpful_aspects'];
        
        // Auto-moderation decisions
        if ($this->getConfig('auto_moderate', false)) {
            $review = $this->applyAutoModeration($review);
        }
        
        return $review;
    }

    public function postModerationActions($review, $decision): void
    {
        if ($decision === 'approved') {
            // Update product sentiment metrics
            $this->updateProductSentiment($review->product_id, $review);
            
            // Check for response opportunities
            if ($this->shouldGenerateResponse($review)) {
                Hook::doAction('review.published', $review);
            }
            
            // Extract insights
            $this->insightsEngine->processReview($review);
            
            // Update reviewer profile
            $this->updateReviewerProfile($review->customer_id, $review);
        } elseif ($decision === 'rejected') {
            // Track rejection reasons
            $this->trackRejectionReason($review);
            
            // Send feedback to customer if appropriate
            if ($review->rejection_reason === 'quality') {
                $this->sendQualityFeedback($review);
            }
        }
    }

    public function scheduleReviewRequest($order): void
    {
        if (!$this->getConfig('enable_review_requests', true)) {
            return;
        }
        
        $delayDays = $this->getConfig('review_request_delay_days', 7);
        
        $this->api->scheduler()->schedule("+{$delayDays} days", function() use ($order) {
            // Check if customer already reviewed
            $hasReviewed = $this->checkCustomerReviewed($order);
            
            if (!$hasReviewed) {
                $this->sendReviewRequest($order);
                
                // Schedule follow-up if needed
                $this->scheduleFollowUp($order);
            }
        });
    }

    public function generateAutoResponse($review): void
    {
        if (!$this->shouldGenerateResponse($review)) {
            return;
        }
        
        $responseTemplate = $this->selectResponseTemplate($review);
        $personalizedResponse = $this->responseGenerator->generate($review, $responseTemplate);
        
        // Create response draft
        $response = [
            'review_id' => $review->id,
            'content' => $personalizedResponse,
            'author' => 'store_owner',
            'status' => 'draft',
            'generated_at' => date('Y-m-d H:i:s')
        ];
        
        // Auto-publish or queue for approval
        if ($this->getConfig('auto_publish_responses', false) && $review->sentiment_label === 'positive') {
            $response['status'] = 'published';
            $this->publishResponse($response);
        } else {
            $this->queueResponseForApproval($response);
        }
    }

    public function addReviewInsights($productDisplay, $product): string
    {
        $insights = $this->insightsEngine->getProductInsights($product->id);
        
        if (empty($insights)) {
            return $productDisplay;
        }
        
        $insightsWidget = $this->api->view('review-intelligence/product-insights', [
            'overall_sentiment' => $insights['sentiment_summary'],
            'top_pros' => $insights['positive_themes'],
            'top_cons' => $insights['negative_themes'],
            'sentiment_trend' => $insights['sentiment_trend'],
            'reviewer_demographics' => $insights['demographics'],
            'competitive_position' => $insights['competitive_analysis']
        ]);
        
        return $productDisplay . $insightsWidget;
    }

    public function reviewsDashboard(): void
    {
        $metrics = [
            'total_reviews' => $this->getTotalReviews(),
            'average_rating' => $this->getAverageRating(),
            'sentiment_distribution' => $this->getSentimentDistribution(),
            'response_rate' => $this->getResponseRate(),
            'fake_detection_stats' => $this->getFakeDetectionStats(),
            'trending_topics' => $this->insightsEngine->getTrendingTopics(),
            'sentiment_trends' => $this->insightsEngine->getSentimentTrends(30),
            'top_reviewed_products' => $this->getTopReviewedProducts(),
            'reviewer_insights' => $this->getReviewerInsights(),
            'competitive_analysis' => $this->insightsEngine->getCompetitiveAnalysis()
        ];
        
        echo $this->api->view('review-intelligence/admin-dashboard', $metrics);
    }

    public function addIntelligenceActions($actions, $review): array
    {
        // Add sentiment indicator
        $actions['sentiment'] = [
            'label' => $review->sentiment_label,
            'score' => $review->sentiment_score,
            'class' => $this->getSentimentClass($review->sentiment_label)
        ];
        
        // Add authenticity indicator
        if (isset($review->authenticity_score)) {
            $actions['authenticity'] = [
                'score' => $review->authenticity_score,
                'status' => $this->getAuthenticityStatus($review->authenticity_score)
            ];
        }
        
        // Add quick response options
        if (!$review->has_response) {
            $actions['quick_responses'] = $this->getQuickResponseOptions($review);
        }
        
        // Add insight actions
        $actions['view_insights'] = [
            'url' => "/admin/reviews/{$review->id}/insights",
            'label' => 'View Detailed Analysis'
        ];
        
        return $actions;
    }

    private function categorizeTopics($keyPhrases): array
    {
        $categories = [
            'quality' => ['quality', 'durable', 'sturdy', 'cheap', 'flimsy', 'well-made'],
            'shipping' => ['shipping', 'delivery', 'arrived', 'packaging', 'damaged'],
            'value' => ['price', 'value', 'worth', 'expensive', 'affordable', 'money'],
            'service' => ['service', 'support', 'helpful', 'responsive', 'rude'],
            'features' => ['feature', 'function', 'works', 'design', 'size', 'color']
        ];
        
        $topics = [];
        
        foreach ($keyPhrases as $phrase) {
            foreach ($categories as $category => $keywords) {
                foreach ($keywords as $keyword) {
                    if (stripos($phrase, $keyword) !== false) {
                        $topics[] = $category;
                        break 2;
                    }
                }
            }
        }
        
        return array_unique($topics);
    }

    private function assessReviewQuality($review): array
    {
        $quality = ['score' => 0, 'helpful_aspects' => []];
        
        // Length check
        $wordCount = str_word_count($review['content']);
        if ($wordCount >= 50) {
            $quality['score'] += 0.3;
            $quality['helpful_aspects'][] = 'detailed';
        }
        
        // Specific details mentioned
        if (preg_match('/\b(size|color|model|version)\b/i', $review['content'])) {
            $quality['score'] += 0.2;
            $quality['helpful_aspects'][] = 'specific';
        }
        
        // Pros and cons mentioned
        if (preg_match('/\b(pros?|cons?|advantages?|disadvantages?)\b/i', $review['content'])) {
            $quality['score'] += 0.2;
            $quality['helpful_aspects'][] = 'balanced';
        }
        
        // Photos included
        if (!empty($review['photos'])) {
            $quality['score'] += 0.3;
            $quality['helpful_aspects'][] = 'visual';
        }
        
        return $quality;
    }

    private function shouldGenerateResponse($review): bool
    {
        // Always respond to negative reviews
        if ($review->sentiment_label === 'negative') {
            return true;
        }
        
        // Respond to detailed positive reviews
        if ($review->sentiment_label === 'positive' && $review->quality_score > 0.7) {
            return true;
        }
        
        // Respond to reviews with questions
        if (strpos($review->content, '?') !== false) {
            return true;
        }
        
        // Random response to maintain engagement
        return rand(1, 100) <= 30; // 30% chance
    }

    private function selectResponseTemplate($review): string
    {
        $templates = [
            'positive' => [
                'generic' => "Thank you for your wonderful review! We're thrilled you enjoyed {product_name}.",
                'detailed' => "Thank you for taking the time to share your detailed feedback about {product_name}. {specific_mention}",
                'loyal' => "We truly appreciate your continued support! Thank you for choosing us again."
            ],
            'negative' => [
                'apologetic' => "We're sorry to hear about your experience with {product_name}. {specific_issue_acknowledgment}",
                'solution' => "Thank you for your feedback. We'd like to make this right. Please contact us at {support_email}.",
                'improvement' => "We appreciate your honest feedback and are working to improve {mentioned_issue}."
            ],
            'neutral' => [
                'thankful' => "Thank you for sharing your thoughts on {product_name}.",
                'engaging' => "We appreciate your balanced review. {specific_comment}"
            ]
        ];
        
        $sentiment = $review->sentiment_label;
        $subtype = $this->determineResponseSubtype($review);
        
        return $templates[$sentiment][$subtype] ?? $templates[$sentiment]['generic'];
    }

    private function updateProductSentiment($productId, $review): void
    {
        $currentSentiment = $this->api->database()->table('product_sentiment')
            ->where('product_id', $productId)
            ->first();
            
        if (!$currentSentiment) {
            $this->api->database()->table('product_sentiment')->insert([
                'product_id' => $productId,
                'positive_count' => 0,
                'neutral_count' => 0,
                'negative_count' => 0,
                'average_sentiment' => 0
            ]);
            $currentSentiment = $this->api->database()->table('product_sentiment')
                ->where('product_id', $productId)
                ->first();
        }
        
        // Update counts
        $sentimentField = $review->sentiment_label . '_count';
        $this->api->database()->table('product_sentiment')
            ->where('product_id', $productId)
            ->increment($sentimentField);
            
        // Recalculate average
        $this->recalculateAverageSentiment($productId);
    }

    private function scheduleAnalysisTasks(): void
    {
        // Daily sentiment analysis aggregation
        $this->api->scheduler()->addJob('aggregate_sentiment', '0 2 * * *', function() {
            $this->insightsEngine->aggregateDailySentiment();
        });
        
        // Weekly trend analysis
        $this->api->scheduler()->addJob('analyze_trends', '0 3 * * 0', function() {
            $this->insightsEngine->analyzeTrends();
        });
        
        // Monthly competitive analysis
        $this->api->scheduler()->addJob('competitive_analysis', '0 4 1 * *', function() {
            $this->insightsEngine->performCompetitiveAnalysis();
        });
        
        // Real-time fake review checks
        $this->api->scheduler()->addJob('fake_review_batch', '*/30 * * * *', function() {
            $this->fakeDetector->batchAnalyze();
        });
    }

    private function registerRoutes(): void
    {
        $this->api->router()->post('/reviews/analyze', 'Controllers\ReviewController@analyze');
        $this->api->router()->get('/reviews/sentiment/{product_id}', 'Controllers\ReviewController@getProductSentiment');
        $this->api->router()->post('/reviews/moderate', 'Controllers\ReviewController@moderate');
        $this->api->router()->get('/reviews/insights', 'Controllers\ReviewController@getInsights');
        $this->api->router()->post('/reviews/response/generate', 'Controllers\ReviewController@generateResponse');
        $this->api->router()->get('/reviews/topics/trending', 'Controllers\ReviewController@getTrendingTopics');
    }

    public function install(): void
    {
        $this->runMigrations();
        $this->loadSentimentDictionary();
        $this->createResponseTemplates();
    }

    private function loadSentimentDictionary(): void
    {
        $dictionary = [
            'positive' => ['excellent', 'amazing', 'fantastic', 'love', 'perfect', 'great', 'wonderful', 'impressed'],
            'negative' => ['terrible', 'awful', 'horrible', 'hate', 'worst', 'disappointed', 'poor', 'bad'],
            'neutral' => ['okay', 'average', 'decent', 'fair', 'acceptable', 'normal', 'standard']
        ];
        
        foreach ($dictionary as $sentiment => $words) {
            foreach ($words as $word) {
                $this->api->database()->table('sentiment_dictionary')->insert([
                    'word' => $word,
                    'sentiment' => $sentiment,
                    'weight' => 1.0
                ]);
            }
        }
    }

    private function createResponseTemplates(): void
    {
        $templates = [
            ['type' => 'positive_generic', 'template' => 'Thank you for your review!'],
            ['type' => 'negative_apology', 'template' => 'We apologize for your experience.'],
            ['type' => 'question_response', 'template' => 'Thank you for your question.']
        ];

        foreach ($templates as $template) {
            $this->api->database()->table('response_templates')->insert($template);
        }
    }
}