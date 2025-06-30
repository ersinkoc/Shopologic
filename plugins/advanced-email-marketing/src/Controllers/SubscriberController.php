<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Controllers;

use Shopologic\Core\Http\Controller;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use AdvancedEmailMarketing\Services\{
    SubscriberManager,;
    SegmentationService,;
    EmailSender;
};
use AdvancedEmailMarketing\Repositories\{;
    SubscriberRepository,;
    SegmentRepository;
};

class SubscriberController extends Controller
{
    private SubscriberManager $subscriberManager;
    private SegmentationService $segmentationService;
    private EmailSender $emailSender;
    private SubscriberRepository $subscriberRepository;
    private SegmentRepository $segmentRepository;

    public function __construct()
    {
        $this->subscriberManager = app(SubscriberManager::class);
        $this->segmentationService = app(SegmentationService::class);
        $this->emailSender = app(EmailSender::class);
        $this->subscriberRepository = app(SubscriberRepository::class);
        $this->segmentRepository = app(SegmentRepository::class);
    }

    /**
     * List subscribers
     */
    public function index(Request $request): Response
    {
        $filters = [
            'status' => $request->query('status'),
            'segment_id' => $request->query('segment_id'),
            'tag' => $request->query('tag'),
            'search' => $request->query('search'),
            'engagement_level' => $request->query('engagement_level')
        ];
        
        $page = (int)$request->query('page', 1);
        $perPage = (int)$request->query('per_page', 20);
        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = $request->query('sort_order', 'desc');
        
        $subscribers = $this->subscriberRepository->getWithPagination($filters, $page, $perPage, $sortBy, $sortOrder);
        
        // Add statistics to each subscriber
        foreach ($subscribers['data'] as &$subscriber) {
            $subscriber['statistics'] = $this->subscriberRepository->getEmailStats($subscriber['id']);
        }
        
        return $this->json([
            'status' => 'success',
            'data' => $subscribers['data'],
            'pagination' => $subscribers['pagination']
        ]);
    }

    /**
     * Get subscriber details
     */
    public function show(Request $request, int $id): Response
    {
        $subscriber = $this->subscriberRepository->findById($id);
        
        if (!$subscriber) {
            return $this->json([
                'status' => 'error',
                'message' => 'Subscriber not found'
            ], 404);
        }
        
        // Add additional data
        $subscriber['segments'] = $this->segmentRepository->getBySubscriberId($id);
        $subscriber['statistics'] = $this->subscriberRepository->getEmailStats($id);
        $subscriber['activity'] = $this->subscriberManager->getSubscriberActivity($id, 10);
        $subscriber['timeline'] = $this->subscriberManager->getSubscriberTimeline($id);
        
        return $this->json([
            'status' => 'success',
            'data' => $subscriber
        ]);
    }

    /**
     * Create new subscriber
     */
    public function create(Request $request): Response
    {
        $this->validate($request, [
            'email' => 'required|email',
            'first_name' => 'string|max:100',
            'last_name' => 'string|max:100',
            'status' => 'in:subscribed,unsubscribed,pending,bounced,complained',
            'source' => 'string',
            'tags' => 'array',
            'custom_fields' => 'array',
            'double_opt_in' => 'boolean',
            'send_welcome' => 'boolean'
        ]);
        
        try {
            $data = $request->all();
            
            // Check if subscriber already exists
            $existing = $this->subscriberRepository->findByEmail($data['email']);
            if ($existing) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Subscriber already exists'
                ], 400);
            }
            
            // Create subscriber
            $subscriber = $this->subscriberManager->createSubscriber($data);
            
            // Send welcome email if requested
            if ($request->input('send_welcome', false) && $subscriber['status'] === 'subscribed') {
                $this->emailSender->sendWelcomeEmail($subscriber);
            }
            
            return $this->json([
                'status' => 'success',
                'message' => 'Subscriber created successfully',
                'data' => $subscriber
            ], 201);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update subscriber
     */
    public function update(Request $request, int $id): Response
    {
        $this->validate($request, [
            'email' => 'email',
            'first_name' => 'string|max:100',
            'last_name' => 'string|max:100',
            'status' => 'in:subscribed,unsubscribed,pending,bounced,complained',
            'tags' => 'array',
            'custom_fields' => 'array',
            'preferences' => 'array'
        ]);
        
        try {
            $subscriber = $this->subscriberRepository->findById($id);
            
            if (!$subscriber) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Subscriber not found'
                ], 404);
            }
            
            $updated = $this->subscriberManager->updateSubscriber($id, $request->all());
            
            return $this->json([
                'status' => 'success',
                'message' => 'Subscriber updated successfully',
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
     * Unsubscribe subscriber
     */
    public function unsubscribe(Request $request, int $id): Response
    {
        $this->validate($request, [
            'reason' => 'string|max:500',
            'feedback' => 'string'
        ]);
        
        try {
            $subscriber = $this->subscriberRepository->findById($id);
            
            if (!$subscriber) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Subscriber not found'
                ], 404);
            }
            
            $reason = $request->input('reason');
            $feedback = $request->input('feedback');
            
            $this->subscriberManager->unsubscribe($id, $reason, $feedback);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Subscriber unsubscribed successfully'
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Bulk import subscribers
     */
    public function import(Request $request): Response
    {
        $this->validate($request, [
            'subscribers' => 'required|array',
            'update_existing' => 'boolean',
            'send_welcome' => 'boolean',
            'segment_ids' => 'array'
        ]);
        
        try {
            $subscribers = $request->input('subscribers');
            $updateExisting = (bool)$request->input('update_existing', false);
            $sendWelcome = (bool)$request->input('send_welcome', false);
            $segmentIds = $request->input('segment_ids', []);
            
            $result = $this->subscriberManager->bulkImport($subscribers, $updateExisting, $sendWelcome, $segmentIds);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Import completed',
                'data' => [
                    'imported' => $result['imported'],
                    'updated' => $result['updated'],
                    'skipped' => $result['skipped'],
                    'errors' => $result['errors']
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
     * Export subscribers
     */
    public function export(Request $request): Response
    {
        $filters = [
            'status' => $request->query('status'),
            'segment_id' => $request->query('segment_id'),
            'tag' => $request->query('tag'),
            'engagement_level' => $request->query('engagement_level')
        ];
        
        $format = $request->query('format', 'csv');
        $fields = $request->query('fields', ['email', 'first_name', 'last_name', 'status']);
        
        try {
            $export = $this->subscriberManager->exportSubscribers($filters, $format, $fields);
            
            if ($format === 'csv') {
                return $this->csv($export, 'subscribers_export.csv');
            }
            
            return $this->json([
                'status' => 'success',
                'data' => $export
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update subscriber tags
     */
    public function updateTags(Request $request, int $id): Response
    {
        $this->validate($request, [
            'action' => 'required|in:add,remove,replace',
            'tags' => 'required|array'
        ]);
        
        try {
            $subscriber = $this->subscriberRepository->findById($id);
            
            if (!$subscriber) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Subscriber not found'
                ], 404);
            }
            
            $action = $request->input('action');
            $tags = $request->input('tags');
            
            $updated = $this->subscriberManager->updateTags($id, $tags, $action);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Tags updated successfully',
                'data' => ['tags' => $updated['tags']]
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update subscriber preferences
     */
    public function updatePreferences(Request $request, int $id): Response
    {
        $this->validate($request, [
            'preferences' => 'required|array'
        ]);
        
        try {
            $subscriber = $this->subscriberRepository->findById($id);
            
            if (!$subscriber) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Subscriber not found'
                ], 404);
            }
            
            $preferences = $request->input('preferences');
            $updated = $this->subscriberManager->updatePreferences($id, $preferences);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Preferences updated successfully',
                'data' => ['preferences' => $updated['preferences']]
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get subscriber activity
     */
    public function activity(Request $request, int $id): Response
    {
        $subscriber = $this->subscriberRepository->findById($id);
        
        if (!$subscriber) {
            return $this->json([
                'status' => 'error',
                'message' => 'Subscriber not found'
            ], 404);
        }
        
        $page = (int)$request->query('page', 1);
        $perPage = (int)$request->query('per_page', 50);
        $type = $request->query('type'); // email_sent, email_opened, email_clicked, etc.
        
        $activity = $this->subscriberManager->getSubscriberActivity($id, $perPage, $page, $type);
        
        return $this->json([
            'status' => 'success',
            'data' => $activity['data'],
            'pagination' => $activity['pagination']
        ]);
    }

    /**
     * Resubscribe subscriber
     */
    public function resubscribe(Request $request, int $id): Response
    {
        try {
            $subscriber = $this->subscriberRepository->findById($id);
            
            if (!$subscriber) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Subscriber not found'
                ], 404);
            }
            
            if ($subscriber['status'] !== 'unsubscribed') {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Subscriber is not unsubscribed'
                ], 400);
            }
            
            $this->subscriberManager->resubscribe($id);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Subscriber resubscribed successfully'
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Merge duplicate subscribers
     */
    public function merge(Request $request): Response
    {
        $this->validate($request, [
            'primary_id' => 'required|integer',
            'duplicate_ids' => 'required|array|min:1'
        ]);
        
        try {
            $primaryId = $request->input('primary_id');
            $duplicateIds = $request->input('duplicate_ids');
            
            $result = $this->subscriberManager->mergeSubscribers($primaryId, $duplicateIds);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Subscribers merged successfully',
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
     * Bulk update subscribers
     */
    public function bulkUpdate(Request $request): Response
    {
        $this->validate($request, [
            'subscriber_ids' => 'required|array|min:1',
            'updates' => 'required|array'
        ]);
        
        try {
            $subscriberIds = $request->input('subscriber_ids');
            $updates = $request->input('updates');
            
            $result = $this->subscriberManager->bulkUpdate($subscriberIds, $updates);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Bulk update completed',
                'data' => [
                    'updated' => $result['updated'],
                    'failed' => $result['failed']
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
     * Get subscriber growth statistics
     */
    public function growth(Request $request): Response
    {
        $period = $request->query('period', 'last_30_days');
        $groupBy = $request->query('group_by', 'day');
        
        $growth = $this->subscriberManager->getGrowthStatistics($period, $groupBy);
        
        return $this->json([
            'status' => 'success',
            'data' => $growth
        ]);
    }

    /**
     * Validate email address
     */
    public function validateEmail(Request $request): Response
    {
        $this->validate($request, [
            'email' => 'required|email'
        ]);
        
        $email = $request->input('email');
        $validation = $this->subscriberManager->validateEmail($email);
        
        return $this->json([
            'status' => 'success',
            'data' => $validation
        ]);
    }
}