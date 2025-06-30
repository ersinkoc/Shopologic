<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Controllers;

use Shopologic\Core\Http\Controller;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use AdvancedEmailMarketing\Services\{
    AutomationEngine,
    SegmentationService,;
    TemplateManager,;
    AnalyticsService;
};
use AdvancedEmailMarketing\Repositories\{
    AutomationRepository,;
    SegmentRepository,;
    TemplateRepository;
};

class AutomationController extends Controller
{
    private AutomationEngine $automationEngine;
    private SegmentationService $segmentationService;
    private TemplateManager $templateManager;
    private AnalyticsService $analyticsService;
    private AutomationRepository $automationRepository;
    private SegmentRepository $segmentRepository;
    private TemplateRepository $templateRepository;

    public function __construct()
    {
        $this->automationEngine = app(AutomationEngine::class);
        $this->segmentationService = app(SegmentationService::class);
        $this->templateManager = app(TemplateManager::class);
        $this->analyticsService = app(AnalyticsService::class);
        $this->automationRepository = app(AutomationRepository::class);
        $this->segmentRepository = app(SegmentRepository::class);
        $this->templateRepository = app(TemplateRepository::class);
    }

    /**
     * List automations
     */
    public function index(Request $request): Response
    {
        $filters = [
            'status' => $request->query('status'),
            'trigger_type' => $request->query('trigger_type'),
            'search' => $request->query('search')
        ];
        
        $page = (int)$request->query('page', 1);
        $perPage = (int)$request->query('per_page', 20);
        
        $automations = $this->automationRepository->getWithPagination($filters, $page, $perPage);
        
        // Add statistics to each automation
        foreach ($automations['data'] as &$automation) {
            $automation['statistics'] = $this->automationEngine->getAutomationStatistics($automation['id']);
        }
        
        return $this->json([
            'status' => 'success',
            'data' => $automations['data'],
            'pagination' => $automations['pagination']
        ]);
    }

    /**
     * Get automation details
     */
    public function show(Request $request, int $id): Response
    {
        $automation = $this->automationRepository->findById($id);
        
        if (!$automation) {
            return $this->json([
                'status' => 'error',
                'message' => 'Automation not found'
            ], 404);
        }
        
        // Add workflow steps
        $automation['steps'] = $this->automationRepository->getWorkflowSteps($id);
        
        // Add statistics
        $automation['statistics'] = $this->automationEngine->getAutomationStatistics($id);
        
        // Add active subscribers count
        $automation['active_subscribers'] = $this->automationRepository->getActiveSubscribersCount($id);
        
        return $this->json([
            'status' => 'success',
            'data' => $automation
        ]);
    }

    /**
     * Create new automation
     */
    public function create(Request $request): Response
    {
        $this->validate($request, [
            'name' => 'required|string|max:255',
            'description' => 'string|max:500',
            'trigger_type' => 'required|string',
            'trigger_settings' => 'required|array',
            'workflow' => 'required|array',
            'segment_ids' => 'array',
            'settings' => 'array'
        ]);
        
        try {
            $data = $request->all();
            
            // Validate trigger type
            if (!$this->automationEngine->isValidTriggerType($data['trigger_type'])) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Invalid trigger type'
                ], 400);
            }
            
            // Validate workflow
            $workflowValidation = $this->automationEngine->validateWorkflow($data['workflow']);
            if (!$workflowValidation['valid']) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Invalid workflow',
                    'errors' => $workflowValidation['errors']
                ], 400);
            }
            
            // Validate segments
            if (!empty($data['segment_ids'])) {
                foreach ($data['segment_ids'] as $segmentId) {
                    if (!$this->segmentRepository->findById($segmentId)) {
                        return $this->json([
                            'status' => 'error',
                            'message' => "Segment {$segmentId} not found"
                        ], 400);
                    }
                }
            }
            
            $automation = $this->automationEngine->createAutomation($data);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Automation created successfully',
                'data' => $automation
            ], 201);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update automation
     */
    public function update(Request $request, int $id): Response
    {
        $this->validate($request, [
            'name' => 'string|max:255',
            'description' => 'string|max:500',
            'trigger_settings' => 'array',
            'workflow' => 'array',
            'segment_ids' => 'array',
            'settings' => 'array'
        ]);
        
        try {
            $automation = $this->automationRepository->findById($id);
            
            if (!$automation) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Automation not found'
                ], 404);
            }
            
            $data = $request->all();
            
            // Validate workflow if provided
            if (isset($data['workflow'])) {
                $workflowValidation = $this->automationEngine->validateWorkflow($data['workflow']);
                if (!$workflowValidation['valid']) {
                    return $this->json([
                        'status' => 'error',
                        'message' => 'Invalid workflow',
                        'errors' => $workflowValidation['errors']
                    ], 400);
                }
            }
            
            $updated = $this->automationEngine->updateAutomation($id, $data);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Automation updated successfully',
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
     * Delete automation
     */
    public function delete(Request $request, int $id): Response
    {
        try {
            $automation = $this->automationRepository->findById($id);
            
            if (!$automation) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Automation not found'
                ], 404);
            }
            
            // Check if automation has active subscribers
            $activeCount = $this->automationRepository->getActiveSubscribersCount($id);
            if ($activeCount > 0) {
                return $this->json([
                    'status' => 'error',
                    'message' => "Cannot delete automation with {$activeCount} active subscribers"
                ], 400);
            }
            
            $this->automationEngine->deleteAutomation($id);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Automation deleted successfully'
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Activate automation
     */
    public function activate(Request $request, int $id): Response
    {
        try {
            $automation = $this->automationRepository->findById($id);
            
            if (!$automation) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Automation not found'
                ], 404);
            }
            
            if ($automation['status'] === 'active') {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Automation is already active'
                ], 400);
            }
            
            // Validate automation is ready
            $validation = $this->automationEngine->validateAutomation($id);
            if (!$validation['valid']) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Automation validation failed',
                    'errors' => $validation['errors']
                ], 400);
            }
            
            $this->automationEngine->activateAutomation($id);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Automation activated successfully'
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Deactivate automation
     */
    public function deactivate(Request $request, int $id): Response
    {
        try {
            $automation = $this->automationRepository->findById($id);
            
            if (!$automation) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Automation not found'
                ], 404);
            }
            
            if ($automation['status'] === 'inactive') {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Automation is already inactive'
                ], 400);
            }
            
            $this->automationEngine->deactivateAutomation($id);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Automation deactivated successfully'
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get automation activity log
     */
    public function activityLog(Request $request, int $id): Response
    {
        $automation = $this->automationRepository->findById($id);
        
        if (!$automation) {
            return $this->json([
                'status' => 'error',
                'message' => 'Automation not found'
            ], 404);
        }
        
        $page = (int)$request->query('page', 1);
        $perPage = (int)$request->query('per_page', 50);
        $filters = [
            'action_type' => $request->query('action_type'),
            'from_date' => $request->query('from_date'),
            'to_date' => $request->query('to_date')
        ];
        
        $log = $this->automationRepository->getActivityLog($id, $filters, $page, $perPage);
        
        return $this->json([
            'status' => 'success',
            'data' => $log['data'],
            'pagination' => $log['pagination']
        ]);
    }

    /**
     * Get subscribers in automation
     */
    public function subscribers(Request $request, int $id): Response
    {
        $automation = $this->automationRepository->findById($id);
        
        if (!$automation) {
            return $this->json([
                'status' => 'error',
                'message' => 'Automation not found'
            ], 404);
        }
        
        $page = (int)$request->query('page', 1);
        $perPage = (int)$request->query('per_page', 20);
        $filters = [
            'status' => $request->query('status'),
            'current_step' => $request->query('current_step')
        ];
        
        $subscribers = $this->automationRepository->getSubscribers($id, $filters, $page, $perPage);
        
        return $this->json([
            'status' => 'success',
            'data' => $subscribers['data'],
            'pagination' => $subscribers['pagination']
        ]);
    }

    /**
     * Clone automation
     */
    public function clone(Request $request, int $id): Response
    {
        try {
            $automation = $this->automationRepository->findById($id);
            
            if (!$automation) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Automation not found'
                ], 404);
            }
            
            $cloned = $this->automationEngine->cloneAutomation($id);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Automation cloned successfully',
                'data' => $cloned
            ], 201);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Test automation workflow
     */
    public function test(Request $request, int $id): Response
    {
        $this->validate($request, [
            'test_email' => 'required|email'
        ]);
        
        try {
            $automation = $this->automationRepository->findById($id);
            
            if (!$automation) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Automation not found'
                ], 404);
            }
            
            $result = $this->automationEngine->testAutomation($id, $request->input('test_email'));
            
            return $this->json([
                'status' => 'success',
                'message' => 'Automation test initiated',
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
     * Get available trigger types
     */
    public function triggerTypes(Request $request): Response
    {
        $triggers = $this->automationEngine->getAvailableTriggers();
        
        return $this->json([
            'status' => 'success',
            'data' => $triggers
        ]);
    }

    /**
     * Get available action types
     */
    public function actionTypes(Request $request): Response
    {
        $actions = $this->automationEngine->getAvailableActions();
        
        return $this->json([
            'status' => 'success',
            'data' => $actions
        ]);
    }

    /**
     * Get workflow templates
     */
    public function templates(Request $request): Response
    {
        $templates = $this->automationEngine->getWorkflowTemplates();
        
        return $this->json([
            'status' => 'success',
            'data' => $templates
        ]);
    }

    /**
     * Export automation
     */
    public function export(Request $request, int $id): Response
    {
        try {
            $automation = $this->automationRepository->findById($id);
            
            if (!$automation) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Automation not found'
                ], 404);
            }
            
            $export = $this->automationEngine->exportAutomation($id);
            
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
     * Import automation
     */
    public function import(Request $request): Response
    {
        $this->validate($request, [
            'automation_data' => 'required|array'
        ]);
        
        try {
            $imported = $this->automationEngine->importAutomation($request->input('automation_data'));
            
            return $this->json([
                'status' => 'success',
                'message' => 'Automation imported successfully',
                'data' => $imported
            ], 201);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}