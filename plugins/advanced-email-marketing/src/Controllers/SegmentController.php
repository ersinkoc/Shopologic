<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Controllers;

use Shopologic\Core\Http\Controller;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use AdvancedEmailMarketing\Services\{;
    SegmentationService,;
    AnalyticsService;
};
use AdvancedEmailMarketing\Repositories\{;
    SegmentRepository,;
    SubscriberRepository;
};

class SegmentController extends Controller
{
    private SegmentationService $segmentationService;
    private AnalyticsService $analyticsService;
    private SegmentRepository $segmentRepository;
    private SubscriberRepository $subscriberRepository;

    public function __construct()
    {
        $this->segmentationService = app(SegmentationService::class);
        $this->analyticsService = app(AnalyticsService::class);
        $this->segmentRepository = app(SegmentRepository::class);
        $this->subscriberRepository = app(SubscriberRepository::class);
    }

    /**
     * List segments
     */
    public function index(Request $request): Response
    {
        $filters = [
            'type' => $request->query('type'),
            'status' => $request->query('status'),
            'search' => $request->query('search')
        ];
        
        $page = (int)$request->query('page', 1);
        $perPage = (int)$request->query('per_page', 20);
        
        $segments = $this->segmentRepository->getWithPagination($filters, $page, $perPage);
        
        // Add member counts to each segment
        foreach ($segments['data'] as &$segment) {
            $segment['member_count'] = $this->segmentRepository->getMemberCount($segment['id']);
            $segment['growth'] = $this->segmentRepository->getGrowthMetrics($segment['id'], 30);
        }
        
        return $this->json([
            'status' => 'success',
            'data' => $segments['data'],
            'pagination' => $segments['pagination']
        ]);
    }

    /**
     * Get segment details
     */
    public function show(Request $request, int $id): Response
    {
        $segment = $this->segmentRepository->findById($id);
        
        if (!$segment) {
            return $this->json([
                'status' => 'error',
                'message' => 'Segment not found'
            ], 404);
        }
        
        // Add additional data
        $segment['member_count'] = $this->segmentRepository->getMemberCount($id);
        $segment['conditions'] = json_decode($segment['conditions'], true);
        $segment['statistics'] = $this->segmentationService->getSegmentStatistics($id);
        $segment['growth'] = $this->segmentRepository->getGrowthMetrics($id, 30);
        
        return $this->json([
            'status' => 'success',
            'data' => $segment
        ]);
    }

    /**
     * Create new segment
     */
    public function create(Request $request): Response
    {
        $this->validate($request, [
            'name' => 'required|string|max:255',
            'description' => 'string|max:500',
            'type' => 'required|in:static,dynamic',
            'conditions' => 'required_if:type,dynamic|array',
            'members' => 'required_if:type,static|array',
            'tags' => 'array'
        ]);
        
        try {
            $data = $request->all();
            
            // Validate conditions for dynamic segments
            if ($data['type'] === 'dynamic' && isset($data['conditions'])) {
                $validation = $this->segmentationService->validateConditions($data['conditions']);
                if (!$validation['valid']) {
                    return $this->json([
                        'status' => 'error',
                        'message' => 'Invalid segment conditions',
                        'errors' => $validation['errors']
                    ], 400);
                }
            }
            
            // Validate member IDs for static segments
            if ($data['type'] === 'static' && isset($data['members'])) {
                foreach ($data['members'] as $memberId) {
                    if (!$this->subscriberRepository->findById($memberId)) {
                        return $this->json([
                            'status' => 'error',
                            'message' => "Subscriber {$memberId} not found"
                        ], 400);
                    }
                }
            }
            
            $segment = $this->segmentationService->createSegment($data);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Segment created successfully',
                'data' => $segment
            ], 201);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update segment
     */
    public function update(Request $request, int $id): Response
    {
        $this->validate($request, [
            'name' => 'string|max:255',
            'description' => 'string|max:500',
            'conditions' => 'array',
            'tags' => 'array'
        ]);
        
        try {
            $segment = $this->segmentRepository->findById($id);
            
            if (!$segment) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Segment not found'
                ], 404);
            }
            
            $data = $request->all();
            
            // Validate conditions if provided
            if (isset($data['conditions']) && $segment['type'] === 'dynamic') {
                $validation = $this->segmentationService->validateConditions($data['conditions']);
                if (!$validation['valid']) {
                    return $this->json([
                        'status' => 'error',
                        'message' => 'Invalid segment conditions',
                        'errors' => $validation['errors']
                    ], 400);
                }
            }
            
            $updated = $this->segmentationService->updateSegment($id, $data);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Segment updated successfully',
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
     * Delete segment
     */
    public function delete(Request $request, int $id): Response
    {
        try {
            $segment = $this->segmentRepository->findById($id);
            
            if (!$segment) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Segment not found'
                ], 404);
            }
            
            // Check if segment is in use
            $usage = $this->segmentRepository->getSegmentUsage($id);
            if (!empty($usage)) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Cannot delete segment that is in use',
                    'usage' => $usage
                ], 400);
            }
            
            $this->segmentationService->deleteSegment($id);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Segment deleted successfully'
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Calculate/refresh segment members
     */
    public function calculateSegment(Request $request, int $id): Response
    {
        try {
            $segment = $this->segmentRepository->findById($id);
            
            if (!$segment) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Segment not found'
                ], 404);
            }
            
            if ($segment['type'] !== 'dynamic') {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Only dynamic segments can be recalculated'
                ], 400);
            }
            
            $result = $this->segmentationService->calculateSegmentMembers($id);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Segment recalculated successfully',
                'data' => [
                    'segment_id' => $id,
                    'member_count' => $result['member_count'],
                    'added' => $result['added'],
                    'removed' => $result['removed'],
                    'calculation_time' => $result['calculation_time']
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
     * Get segment members
     */
    public function members(Request $request, int $id): Response
    {
        $segment = $this->segmentRepository->findById($id);
        
        if (!$segment) {
            return $this->json([
                'status' => 'error',
                'message' => 'Segment not found'
            ], 404);
        }
        
        $page = (int)$request->query('page', 1);
        $perPage = (int)$request->query('per_page', 20);
        $filters = [
            'status' => $request->query('status'),
            'search' => $request->query('search')
        ];
        
        $members = $this->segmentRepository->getMembers($id, $filters, $page, $perPage);
        
        return $this->json([
            'status' => 'success',
            'data' => $members['data'],
            'pagination' => $members['pagination']
        ]);
    }

    /**
     * Add members to static segment
     */
    public function addMembers(Request $request, int $id): Response
    {
        $this->validate($request, [
            'subscriber_ids' => 'required|array|min:1'
        ]);
        
        try {
            $segment = $this->segmentRepository->findById($id);
            
            if (!$segment) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Segment not found'
                ], 404);
            }
            
            if ($segment['type'] !== 'static') {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Can only add members to static segments'
                ], 400);
            }
            
            $subscriberIds = $request->input('subscriber_ids');
            $result = $this->segmentationService->addMembersToSegment($id, $subscriberIds);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Members added successfully',
                'data' => [
                    'added' => $result['added'],
                    'skipped' => $result['skipped'],
                    'total_members' => $result['total_members']
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
     * Remove members from static segment
     */
    public function removeMembers(Request $request, int $id): Response
    {
        $this->validate($request, [
            'subscriber_ids' => 'required|array|min:1'
        ]);
        
        try {
            $segment = $this->segmentRepository->findById($id);
            
            if (!$segment) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Segment not found'
                ], 404);
            }
            
            if ($segment['type'] !== 'static') {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Can only remove members from static segments'
                ], 400);
            }
            
            $subscriberIds = $request->input('subscriber_ids');
            $result = $this->segmentationService->removeMembersFromSegment($id, $subscriberIds);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Members removed successfully',
                'data' => [
                    'removed' => $result['removed'],
                    'total_members' => $result['total_members']
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
     * Preview segment members
     */
    public function preview(Request $request): Response
    {
        $this->validate($request, [
            'conditions' => 'required|array'
        ]);
        
        try {
            $conditions = $request->input('conditions');
            
            // Validate conditions
            $validation = $this->segmentationService->validateConditions($conditions);
            if (!$validation['valid']) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Invalid segment conditions',
                    'errors' => $validation['errors']
                ], 400);
            }
            
            $limit = (int)$request->query('limit', 10);
            $preview = $this->segmentationService->previewSegment($conditions, $limit);
            
            return $this->json([
                'status' => 'success',
                'data' => [
                    'members' => $preview['members'],
                    'estimated_count' => $preview['estimated_count'],
                    'showing' => count($preview['members'])
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
     * Duplicate segment
     */
    public function duplicate(Request $request, int $id): Response
    {
        try {
            $segment = $this->segmentRepository->findById($id);
            
            if (!$segment) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Segment not found'
                ], 404);
            }
            
            $duplicated = $this->segmentationService->duplicateSegment($id);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Segment duplicated successfully',
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
     * Export segment members
     */
    public function export(Request $request, int $id): Response
    {
        try {
            $segment = $this->segmentRepository->findById($id);
            
            if (!$segment) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Segment not found'
                ], 404);
            }
            
            $format = $request->query('format', 'csv');
            $fields = $request->query('fields', ['email', 'first_name', 'last_name']);
            
            $export = $this->segmentationService->exportSegment($id, $format, $fields);
            
            if ($format === 'csv') {
                return $this->csv($export, "segment_{$id}_export.csv");
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
     * Get available segment conditions
     */
    public function conditions(Request $request): Response
    {
        $conditions = $this->segmentationService->getAvailableConditions();
        
        return $this->json([
            'status' => 'success',
            'data' => $conditions
        ]);
    }

    /**
     * Get segment statistics
     */
    public function statistics(Request $request, int $id): Response
    {
        $segment = $this->segmentRepository->findById($id);
        
        if (!$segment) {
            return $this->json([
                'status' => 'error',
                'message' => 'Segment not found'
            ], 404);
        }
        
        $stats = $this->segmentationService->getSegmentStatistics($id);
        
        return $this->json([
            'status' => 'success',
            'data' => $stats
        ]);
    }
}