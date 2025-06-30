<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Controllers;

use Shopologic\Core\Http\Controller;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use AdvancedEmailMarketing\Services\{
    DeliverabilityManager,;
    EmailSender,;
    AnalyticsService;
};
use AdvancedEmailMarketing\Repositories\{;
    EmailSendRepository,;
    SubscriberRepository;
};

class DeliverabilityController extends Controller
{
    private DeliverabilityManager $deliverabilityManager;
    private EmailSender $emailSender;
    private AnalyticsService $analyticsService;
    private EmailSendRepository $emailSendRepository;
    private SubscriberRepository $subscriberRepository;

    public function __construct()
    {
        $this->deliverabilityManager = app(DeliverabilityManager::class);
        $this->emailSender = app(EmailSender::class);
        $this->analyticsService = app(AnalyticsService::class);
        $this->emailSendRepository = app(EmailSendRepository::class);
        $this->subscriberRepository = app(SubscriberRepository::class);
    }

    /**
     * Get deliverability status
     */
    public function status(Request $request): Response
    {
        $provider = $request->query('provider');
        $period = $request->query('period', 'last_7_days');
        
        $status = $this->deliverabilityManager->getDeliverabilityStatus($provider, $period);
        
        return $this->json([
            'status' => 'success',
            'data' => [
                'overall_health' => $status['overall_health'],
                'reputation_score' => $status['reputation_score'],
                'delivery_rate' => $status['delivery_rate'],
                'inbox_placement' => $status['inbox_placement'],
                'bounce_rates' => $status['bounce_rates'],
                'complaint_rates' => $status['complaint_rates'],
                'authentication' => $status['authentication'],
                'blocklist_status' => $status['blocklist_status'],
                'recommendations' => $status['recommendations']
            ]
        ]);
    }

    /**
     * Run deliverability tests
     */
    public function runTests(Request $request): Response
    {
        $this->validate($request, [
            'test_types' => 'array',
            'test_email' => 'email',
            'campaign_id' => 'integer'
        ]);
        
        $testTypes = $request->input('test_types', ['spam_score', 'authentication', 'content']);
        $testEmail = $request->input('test_email');
        $campaignId = $request->input('campaign_id');
        
        try {
            $results = $this->deliverabilityManager->runDeliverabilityTests($testTypes, $testEmail, $campaignId);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Deliverability tests completed',
                'data' => $results
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get spam score analysis
     */
    public function spamScore(Request $request): Response
    {
        $this->validate($request, [
            'content' => 'required|string',
            'subject' => 'required|string'
        ]);
        
        $content = $request->input('content');
        $subject = $request->input('subject');
        
        $analysis = $this->deliverabilityManager->analyzeSpamScore($subject, $content);
        
        return $this->json([
            'status' => 'success',
            'data' => [
                'spam_score' => $analysis['score'],
                'rating' => $analysis['rating'],
                'issues' => $analysis['issues'],
                'suggestions' => $analysis['suggestions']
            ]
        ]);
    }

    /**
     * Get authentication status
     */
    public function authentication(Request $request): Response
    {
        $domain = $request->query('domain');
        
        $authentication = $this->deliverabilityManager->checkAuthentication($domain);
        
        return $this->json([
            'status' => 'success',
            'data' => [
                'spf' => $authentication['spf'],
                'dkim' => $authentication['dkim'],
                'dmarc' => $authentication['dmarc'],
                'bimi' => $authentication['bimi'],
                'setup_instructions' => $authentication['setup_instructions']
            ]
        ]);
    }

    /**
     * Get bounce analysis
     */
    public function bounceAnalysis(Request $request): Response
    {
        $period = $request->query('period', 'last_30_days');
        $type = $request->query('type'); // hard, soft, all
        
        $analysis = $this->deliverabilityManager->getBounceAnalysis($period, $type);
        
        return $this->json([
            'status' => 'success',
            'data' => [
                'summary' => $analysis['summary'],
                'trends' => $analysis['trends'],
                'top_reasons' => $analysis['top_reasons'],
                'affected_domains' => $analysis['affected_domains'],
                'recommendations' => $analysis['recommendations']
            ]
        ]);
    }

    /**
     * Get complaint analysis
     */
    public function complaintAnalysis(Request $request): Response
    {
        $period = $request->query('period', 'last_30_days');
        
        $analysis = $this->deliverabilityManager->getComplaintAnalysis($period);
        
        return $this->json([
            'status' => 'success',
            'data' => [
                'complaint_rate' => $analysis['complaint_rate'],
                'trends' => $analysis['trends'],
                'campaigns_affected' => $analysis['campaigns_affected'],
                'common_patterns' => $analysis['common_patterns'],
                'actions_taken' => $analysis['actions_taken']
            ]
        ]);
    }

    /**
     * Get blocklist status
     */
    public function blocklistStatus(Request $request): Response
    {
        $checkAll = (bool)$request->query('check_all', false);
        
        $status = $this->deliverabilityManager->checkBlocklistStatus($checkAll);
        
        return $this->json([
            'status' => 'success',
            'data' => [
                'checked_lists' => $status['checked_lists'],
                'listings' => $status['listings'],
                'last_check' => $status['last_check'],
                'removal_instructions' => $status['removal_instructions']
            ]
        ]);
    }

    /**
     * Get inbox placement report
     */
    public function inboxPlacement(Request $request): Response
    {
        $campaignId = $request->query('campaign_id');
        $provider = $request->query('provider');
        
        $placement = $this->deliverabilityManager->getInboxPlacementReport($campaignId, $provider);
        
        return $this->json([
            'status' => 'success',
            'data' => [
                'inbox_rate' => $placement['inbox_rate'],
                'spam_rate' => $placement['spam_rate'],
                'missing_rate' => $placement['missing_rate'],
                'by_provider' => $placement['by_provider'],
                'recommendations' => $placement['recommendations']
            ]
        ]);
    }

    /**
     * Get list hygiene report
     */
    public function listHygiene(Request $request): Response
    {
        $segmentId = $request->query('segment_id');
        
        $hygiene = $this->deliverabilityManager->getListHygieneReport($segmentId);
        
        return $this->json([
            'status' => 'success',
            'data' => [
                'quality_score' => $hygiene['quality_score'],
                'issues' => $hygiene['issues'],
                'recommendations' => $hygiene['recommendations'],
                'cleanup_candidates' => $hygiene['cleanup_candidates']
            ]
        ]);
    }

    /**
     * Process list cleanup
     */
    public function processListCleanup(Request $request): Response
    {
        $this->validate($request, [
            'cleanup_type' => 'required|in:bounced,unengaged,invalid,all',
            'dry_run' => 'boolean'
        ]);
        
        $cleanupType = $request->input('cleanup_type');
        $dryRun = (bool)$request->input('dry_run', true);
        
        try {
            $result = $this->deliverabilityManager->processListCleanup($cleanupType, $dryRun);
            
            return $this->json([
                'status' => 'success',
                'message' => $dryRun ? 'Cleanup simulation completed' : 'List cleanup completed',
                'data' => [
                    'processed' => $result['processed'],
                    'cleaned' => $result['cleaned'],
                    'details' => $result['details'],
                    'dry_run' => $dryRun
                ]
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get sender reputation
     */
    public function senderReputation(Request $request): Response
    {
        $domain = $request->query('domain');
        $ip = $request->query('ip');
        
        $reputation = $this->deliverabilityManager->getSenderReputation($domain, $ip);
        
        return $this->json([
            'status' => 'success',
            'data' => [
                'overall_score' => $reputation['overall_score'],
                'domain_reputation' => $reputation['domain_reputation'],
                'ip_reputation' => $reputation['ip_reputation'],
                'sending_history' => $reputation['sending_history'],
                'feedback_loops' => $reputation['feedback_loops']
            ]
        ]);
    }

    /**
     * Get engagement tracking
     */
    public function engagementTracking(Request $request): Response
    {
        $period = $request->query('period', 'last_30_days');
        
        $tracking = $this->deliverabilityManager->getEngagementTracking($period);
        
        return $this->json([
            'status' => 'success',
            'data' => [
                'open_rates' => $tracking['open_rates'],
                'click_rates' => $tracking['click_rates'],
                'engagement_trends' => $tracking['engagement_trends'],
                'device_breakdown' => $tracking['device_breakdown'],
                'client_breakdown' => $tracking['client_breakdown']
            ]
        ]);
    }

    /**
     * Test email deliverability
     */
    public function testDeliverability(Request $request): Response
    {
        $this->validate($request, [
            'test_addresses' => 'required|array',
            'test_addresses.*' => 'email',
            'content' => 'required|string',
            'subject' => 'required|string'
        ]);
        
        try {
            $testAddresses = $request->input('test_addresses');
            $content = $request->input('content');
            $subject = $request->input('subject');
            
            $results = $this->deliverabilityManager->testDeliverability($testAddresses, $subject, $content);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Deliverability test completed',
                'data' => $results
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get warmup status
     */
    public function warmupStatus(Request $request): Response
    {
        $ip = $request->query('ip');
        
        $warmup = $this->deliverabilityManager->getWarmupStatus($ip);
        
        return $this->json([
            'status' => 'success',
            'data' => [
                'status' => $warmup['status'],
                'progress' => $warmup['progress'],
                'current_volume' => $warmup['current_volume'],
                'target_volume' => $warmup['target_volume'],
                'schedule' => $warmup['schedule'],
                'recommendations' => $warmup['recommendations']
            ]
        ]);
    }

    /**
     * Get provider-specific settings
     */
    public function providerSettings(Request $request): Response
    {
        $provider = $request->query('provider');
        
        $settings = $this->deliverabilityManager->getProviderSettings($provider);
        
        return $this->json([
            'status' => 'success',
            'data' => $settings
        ]);
    }

    /**
     * Update provider settings
     */
    public function updateProviderSettings(Request $request): Response
    {
        $this->validate($request, [
            'provider' => 'required|string',
            'settings' => 'required|array'
        ]);
        
        try {
            $provider = $request->input('provider');
            $settings = $request->input('settings');
            
            $updated = $this->deliverabilityManager->updateProviderSettings($provider, $settings);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Provider settings updated successfully',
                'data' => $updated
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}