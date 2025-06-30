<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Controllers;

use Shopologic\Core\Http\Controller;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use AdvancedEmailMarketing\Services\{
    CampaignManager,
    EmailSender,
    AnalyticsService,;
    SegmentationService,;
    TemplateManager;
};
use AdvancedEmailMarketing\Repositories\{
    CampaignRepository,;
    SegmentRepository,;
    TemplateRepository;
};

class CampaignController extends Controller
{
    private CampaignManager $campaignManager;
    private EmailSender $emailSender;
    private AnalyticsService $analyticsService;
    private SegmentationService $segmentationService;
    private TemplateManager $templateManager;
    private CampaignRepository $campaignRepository;
    private SegmentRepository $segmentRepository;
    private TemplateRepository $templateRepository;

    public function __construct()
    {
        $this->campaignManager = app(CampaignManager::class);
        $this->emailSender = app(EmailSender::class);
        $this->analyticsService = app(AnalyticsService::class);
        $this->segmentationService = app(SegmentationService::class);
        $this->templateManager = app(TemplateManager::class);
        $this->campaignRepository = app(CampaignRepository::class);
        $this->segmentRepository = app(SegmentRepository::class);
        $this->templateRepository = app(TemplateRepository::class);
    }

    /**
     * List campaigns
     */
    public function index(Request $request): Response
    {
        $filters = [
            'status' => $request->query('status'),
            'type' => $request->query('type'),
            'from_date' => $request->query('from_date'),
            'to_date' => $request->query('to_date'),
            'search' => $request->query('search')
        ];
        
        $page = (int)$request->query('page', 1);
        $perPage = (int)$request->query('per_page', 20);
        
        $campaigns = $this->campaignRepository->getWithPagination($filters, $page, $perPage);
        
        // Add analytics data to each campaign
        foreach ($campaigns['data'] as &$campaign) {
            $campaign['analytics'] = $this->analyticsService->getCampaignSummary($campaign['id']);
        }
        
        return $this->json([
            'status' => 'success',
            'data' => $campaigns['data'],
            'pagination' => $campaigns['pagination']
        ]);
    }

    /**
     * Get campaign details
     */
    public function show(Request $request, int $id): Response
    {
        $campaign = $this->campaignRepository->findById($id);
        
        if (!$campaign) {
            return $this->json([
                'status' => 'error',
                'message' => 'Campaign not found'
            ], 404);
        }
        
        // Add related data
        $campaign['template'] = $this->templateRepository->findById($campaign['template_id']);
        $campaign['segments'] = $this->segmentRepository->getByCampaignId($id);
        $campaign['analytics'] = $this->analyticsService->getCampaignMetrics($id);
        
        return $this->json([
            'status' => 'success',
            'data' => $campaign
        ]);
    }

    /**
     * Create new campaign
     */
    public function create(Request $request): Response
    {
        $this->validate($request, [
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'from_name' => 'required|string|max:100',
            'from_email' => 'required|email',
            'reply_to' => 'email',
            'template_id' => 'required|integer',
            'segment_ids' => 'array',
            'type' => 'required|in:regular,ab_test,automated',
            'content' => 'array',
            'settings' => 'array'
        ]);
        
        try {
            $campaignData = $request->all();
            
            // Validate template exists
            $template = $this->templateRepository->findById($campaignData['template_id']);
            if (!$template) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Template not found'
                ], 400);
            }
            
            // Validate segments exist
            if (!empty($campaignData['segment_ids'])) {
                foreach ($campaignData['segment_ids'] as $segmentId) {
                    if (!$this->segmentRepository->findById($segmentId)) {
                        return $this->json([
                            'status' => 'error',
                            'message' => "Segment {$segmentId} not found"
                        ], 400);
                    }
                }
            }
            
            $campaign = $this->campaignManager->createCampaign($campaignData);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Campaign created successfully',
                'data' => $campaign
            ], 201);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update campaign
     */
    public function update(Request $request, int $id): Response
    {
        $this->validate($request, [
            'name' => 'string|max:255',
            'subject' => 'string|max:255',
            'from_name' => 'string|max:100',
            'from_email' => 'email',
            'reply_to' => 'email',
            'template_id' => 'integer',
            'segment_ids' => 'array',
            'content' => 'array',
            'settings' => 'array'
        ]);
        
        try {
            $campaign = $this->campaignRepository->findById($id);
            
            if (!$campaign) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Campaign not found'
                ], 404);
            }
            
            // Check if campaign can be edited
            if (in_array($campaign['status'], ['sent', 'sending'])) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Cannot edit campaign that has been sent'
                ], 400);
            }
            
            $updated = $this->campaignManager->updateCampaign($id, $request->all());
            
            return $this->json([
                'status' => 'success',
                'message' => 'Campaign updated successfully',
                'data' => $updated
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Delete campaign
     */
    public function delete(Request $request, int $id): Response
    {
        try {
            $campaign = $this->campaignRepository->findById($id);
            
            if (!$campaign) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Campaign not found'
                ], 404);
            }
            
            // Check if campaign can be deleted
            if (in_array($campaign['status'], ['sending'])) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Cannot delete campaign that is currently sending'
                ], 400);
            }
            
            $this->campaignManager->deleteCampaign($id);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Campaign deleted successfully'
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Send campaign
     */
    public function send(Request $request, int $id): Response
    {
        try {
            $campaign = $this->campaignRepository->findById($id);
            
            if (!$campaign) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Campaign not found'
                ], 404);
            }
            
            // Validate campaign is ready to send
            $validation = $this->campaignManager->validateCampaign($id);
            if (!$validation['valid']) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Campaign validation failed',
                    'errors' => $validation['errors']
                ], 400);
            }
            
            // Get recipients
            $recipients = $this->campaignManager->getCampaignRecipients($id);
            
            if (empty($recipients)) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'No recipients found for this campaign'
                ], 400);
            }
            
            // Send campaign
            $result = $this->campaignManager->sendCampaign($id, $recipients);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Campaign sent successfully',
                'data' => [
                    'campaign_id' => $id,
                    'recipients_count' => count($recipients),
                    'send_id' => $result['send_id']
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
     * Schedule campaign
     */
    public function schedule(Request $request, int $id): Response
    {
        $this->validate($request, [
            'scheduled_at' => 'required|date|after:now',
            'timezone' => 'string'
        ]);
        
        try {
            $campaign = $this->campaignRepository->findById($id);
            
            if (!$campaign) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Campaign not found'
                ], 404);
            }
            
            // Validate campaign
            $validation = $this->campaignManager->validateCampaign($id);
            if (!$validation['valid']) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Campaign validation failed',
                    'errors' => $validation['errors']
                ], 400);
            }
            
            $result = $this->campaignManager->scheduleCampaign(
                $id,
                $request->input('scheduled_at'),
                $request->input('timezone', 'UTC')
            );
            
            return $this->json([
                'status' => 'success',
                'message' => 'Campaign scheduled successfully',
                'data' => $result
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Send test email
     */
    public function sendTest(Request $request, int $id): Response
    {
        $this->validate($request, [
            'test_emails' => 'required|array|min:1|max:5',
            'test_emails.*' => 'email'
        ]);
        
        try {
            $campaign = $this->campaignRepository->findById($id);
            
            if (!$campaign) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Campaign not found'
                ], 404);
            }
            
            $testEmails = $request->input('test_emails');
            $result = $this->campaignManager->sendTestEmail($id, $testEmails);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Test emails sent successfully',
                'data' => [
                    'sent_to' => $testEmails,
                    'send_results' => $result
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
     * Duplicate campaign
     */
    public function duplicate(Request $request, int $id): Response
    {
        try {
            $campaign = $this->campaignRepository->findById($id);
            
            if (!$campaign) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Campaign not found'
                ], 404);
            }
            
            $duplicated = $this->campaignManager->duplicateCampaign($id);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Campaign duplicated successfully',
                'data' => $duplicated
            ], 201);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get campaign recipients preview
     */
    public function previewRecipients(Request $request, int $id): Response
    {
        try {
            $campaign = $this->campaignRepository->findById($id);
            
            if (!$campaign) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Campaign not found'
                ], 404);
            }
            
            $limit = (int)$request->query('limit', 10);
            $recipients = $this->campaignManager->getCampaignRecipients($id, $limit);
            $totalCount = $this->campaignManager->getCampaignRecipientsCount($id);
            
            return $this->json([
                'status' => 'success',
                'data' => [
                    'recipients' => $recipients,
                    'total_count' => $totalCount,
                    'showing' => min($limit, count($recipients))
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
     * Cancel scheduled campaign
     */
    public function cancelSchedule(Request $request, int $id): Response
    {
        try {
            $campaign = $this->campaignRepository->findById($id);
            
            if (!$campaign) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Campaign not found'
                ], 404);
            }
            
            if ($campaign['status'] !== 'scheduled') {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Campaign is not scheduled'
                ], 400);
            }
            
            $this->campaignManager->cancelScheduledCampaign($id);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Campaign schedule cancelled successfully'
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get campaign statistics
     */
    public function statistics(Request $request, int $id): Response
    {
        try {
            $campaign = $this->campaignRepository->findById($id);
            
            if (!$campaign) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Campaign not found'
                ], 404);
            }
            
            $stats = $this->analyticsService->getCampaignStatistics($id);
            
            return $this->json([
                'status' => 'success',
                'data' => $stats
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}