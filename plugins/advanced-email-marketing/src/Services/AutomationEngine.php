<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Services;

use AdvancedEmailMarketing\Repositories\{
    AutomationRepository,;
    SubscriberRepository,;
    EmailSendRepository;
};
use Shopologic\Core\Queue\QueueInterface;
use Shopologic\Core\Events\EventDispatcher;

class AutomationEngine\n{
    private AutomationRepository $automationRepository;
    private SubscriberRepository $subscriberRepository;
    private EmailSendRepository $emailSendRepository;
    private QueueInterface $queue;
    private array $config;

    public function __construct(
        AutomationRepository $automationRepository,
        SubscriberRepository $subscriberRepository,
        EmailSendRepository $emailSendRepository,
        array $config = []
    ) {
        $this->automationRepository = $automationRepository;
        $this->subscriberRepository = $subscriberRepository;
        $this->emailSendRepository = $emailSendRepository;
        $this->queue = app(QueueInterface::class);
        $this->config = $config;
    }

    /**
     * Create new automation
     */
    public function createAutomation(array $automationData): array
    {
        $automation = $this->automationRepository->create($automationData);
        
        // Setup triggers
        $this->setupAutomationTriggers($automation['id'], $automationData['triggers'] ?? []);
        
        // Setup workflow steps
        $this->setupWorkflowSteps($automation['id'], $automationData['workflow_steps'] ?? []);
        
        return $automation;
    }

    /**
     * Trigger automation for subscriber
     */
    public function triggerAutomation(string $automationType, array $subscriber, array $context = []): bool
    {
        $automations = $this->automationRepository->getActiveAutomationsByType($automationType);
        
        foreach ($automations as $automation) {
            if ($this->checkTriggerConditions($automation, $subscriber, $context)) {
                $this->startAutomationFlow($automation['id'], $subscriber['id'], $context);
            }
        }
        
        return true;
    }

    /**
     * Process automation queue
     */
    public function processQueue(): void
    {
        $pendingActions = $this->automationRepository->getPendingActions();
        
        foreach ($pendingActions as $action) {
            $this->processAutomationAction($action);
        }
    }

    /**
     * Check behavioral triggers
     */
    public function checkBehavioralTriggers(array $subscriber, string $event, array $data): void
    {
        $behavioralAutomations = $this->automationRepository->getBehavioralAutomations($event);
        
        foreach ($behavioralAutomations as $automation) {
            if ($this->evaluateBehavioralConditions($automation, $subscriber, $data)) {
                $this->startAutomationFlow($automation['id'], $subscriber['id'], $data);
            }
        }
    }

    /**
     * Get running automations
     */
    public function getRunningAutomations(): array
    {
        return $this->automationRepository->getRunningAutomations();
    }

    /**
     * Get automation analytics
     */
    public function getAutomationAnalytics(int $automationId): array
    {
        $automation = $this->automationRepository->findById($automationId);
        if (!$automation) {
            return [];
        }

        return [
            'total_flows' => $this->automationRepository->getTotalFlows($automationId),
            'active_flows' => $this->automationRepository->getActiveFlows($automationId),
            'completed_flows' => $this->automationRepository->getCompletedFlows($automationId),
            'emails_sent' => $this->automationRepository->getEmailsSent($automationId),
            'conversion_rate' => $this->calculateConversionRate($automationId),
            'revenue_generated' => $this->automationRepository->getRevenueGenerated($automationId),
            'step_performance' => $this->getStepPerformance($automationId)
        ];
    }

    /**
     * Activate automation
     */
    public function activateAutomation(int $automationId): bool
    {
        return $this->automationRepository->updateStatus($automationId, 'active');
    }

    /**
     * Deactivate automation
     */
    public function deactivateAutomation(int $automationId): bool
    {
        return $this->automationRepository->updateStatus($automationId, 'inactive');
    }

    /**
     * Stop subscriber flow
     */
    public function stopSubscriberFlow(int $automationId, int $subscriberId): bool
    {
        return $this->automationRepository->stopFlow($automationId, $subscriberId);
    }

    /**
     * Setup automation triggers
     */
    private function setupAutomationTriggers(int $automationId, array $triggers): void
    {
        foreach ($triggers as $trigger) {
            $this->automationRepository->createTrigger([
                'automation_id' => $automationId,
                'event_type' => $trigger['event_type'],
                'conditions' => $trigger['conditions'] ?? [],
                'delay_minutes' => $trigger['delay_minutes'] ?? 0,
                'is_active' => true
            ]);
        }
    }

    /**
     * Setup workflow steps
     */
    private function setupWorkflowSteps(int $automationId, array $steps): void
    {
        foreach ($steps as $index => $step) {
            $this->automationRepository->createAction([
                'automation_id' => $automationId,
                'step_order' => $index + 1,
                'action_type' => $step['action_type'],
                'action_data' => $step['action_data'],
                'delay_minutes' => $step['delay_minutes'] ?? 0,
                'conditions' => $step['conditions'] ?? [],
                'is_active' => true
            ]);
        }
    }

    /**
     * Check trigger conditions
     */
    private function checkTriggerConditions(array $automation, array $subscriber, array $context): bool
    {
        $conditions = json_decode($automation['trigger_conditions'], true);
        
        // Check if subscriber already in this automation
        if ($this->automationRepository->isSubscriberInAutomation($automation['id'], $subscriber['id'])) {
            return false;
        }
        
        // Evaluate conditions
        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $subscriber, $context)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Start automation flow for subscriber
     */
    private function startAutomationFlow(int $automationId, int $subscriberId, array $context): void
    {
        $this->automationRepository->createFlow([
            'automation_id' => $automationId,
            'subscriber_id' => $subscriberId,
            'current_step' => 0,
            'status' => 'active',
            'started_at' => date('Y-m-d H:i:s'),
            'context_data' => json_encode($context)
        ]);
        
        // Queue first action
        $this->queueNextAction($automationId, $subscriberId);
    }

    /**
     * Process automation action
     */
    private function processAutomationAction(array $action): void
    {
        switch ($action['action_type']) {
            case 'send_email':
                $this->processSendEmailAction($action);
                break;
            case 'wait':
                $this->processWaitAction($action);
                break;
            case 'condition':
                $this->processConditionAction($action);
                break;
            case 'tag':
                $this->processTagAction($action);
                break;
            case 'segment':
                $this->processSegmentAction($action);
                break;
            case 'webhook':
                $this->processWebhookAction($action);
                break;
        }
    }

    /**
     * Process send email action
     */
    private function processSendEmailAction(array $action): void
    {
        $actionData = json_decode($action['action_data'], true);
        $subscriber = $this->subscriberRepository->findById($action['subscriber_id']);
        
        if (!$subscriber) {
            return;
        }
        
        // Send email
        $emailSender = app(EmailSender::class);
        $result = $emailSender->sendAutomationEmail(
            $subscriber,
            $actionData['template_id'],
            $actionData['context'] ?? []
        );
        
        if ($result) {
            // Move to next step
            $this->moveToNextStep($action['automation_id'], $action['subscriber_id']);
        }
    }

    /**
     * Process wait action
     */
    private function processWaitAction(array $action): void
    {
        $actionData = json_decode($action['action_data'], true);
        $waitMinutes = $actionData['wait_minutes'] ?? 60;
        
        // Schedule next action
        $this->automationRepository->updateFlowNextAction(
            $action['automation_id'],
            $action['subscriber_id'],
            date('Y-m-d H:i:s', time() + ($waitMinutes * 60))
        );
        
        // Move to next step
        $this->moveToNextStep($action['automation_id'], $action['subscriber_id']);
    }

    /**
     * Move to next step
     */
    private function moveToNextStep(int $automationId, int $subscriberId): void
    {
        $this->automationRepository->incrementFlowStep($automationId, $subscriberId);
        $this->queueNextAction($automationId, $subscriberId);
    }

    /**
     * Queue next action
     */
    private function queueNextAction(int $automationId, int $subscriberId): void
    {
        $flow = $this->automationRepository->getFlow($automationId, $subscriberId);
        if (!$flow) {
            return;
        }
        
        $nextAction = $this->automationRepository->getActionByStep($automationId, $flow['current_step'] + 1);
        if (!$nextAction) {
            // No more actions, complete flow
            $this->automationRepository->updateFlowStatus($automationId, $subscriberId, 'completed');
            return;
        }
        
        // Calculate when to execute
        $executeAt = time() + ($nextAction['delay_minutes'] * 60);
        
        $this->queue->push('automation.process_action', [
            'automation_id' => $automationId,
            'subscriber_id' => $subscriberId,
            'action_id' => $nextAction['id']
        ], $executeAt);
    }

    /**
     * Evaluate condition
     */
    private function evaluateCondition(array $condition, array $subscriber, array $context): bool
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];
        
        $fieldValue = $this->getFieldValue($field, $subscriber, $context);
        
        switch ($operator) {
            case 'equals':
                return $fieldValue == $value;
            case 'not_equals':
                return $fieldValue != $value;
            case 'contains':
                return strpos($fieldValue, $value) !== false;
            case 'greater_than':
                return $fieldValue > $value;
            case 'less_than':
                return $fieldValue < $value;
            default:
                return false;
        }
    }

    /**
     * Get field value from subscriber or context
     */
    private function getFieldValue(string $field, array $subscriber, array $context)
    {
        if (isset($subscriber[$field])) {
            return $subscriber[$field];
        }
        
        if (isset($context[$field])) {
            return $context[$field];
        }
        
        return null;
    }

    /**
     * Calculate conversion rate
     */
    private function calculateConversionRate(int $automationId): float
    {
        $totalFlows = $this->automationRepository->getTotalFlows($automationId);
        $conversions = $this->automationRepository->getConversions($automationId);
        
        if ($totalFlows === 0) {
            return 0.0;
        }
        
        return ($conversions / $totalFlows) * 100;
    }

    /**
     * Get step performance
     */
    private function getStepPerformance(int $automationId): array
    {
        return $this->automationRepository->getStepPerformance($automationId);
    }

    /**
     * Evaluate behavioral conditions
     */
    private function evaluateBehavioralConditions(array $automation, array $subscriber, array $data): bool
    {
        $conditions = json_decode($automation['trigger_conditions'], true);
        
        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $subscriber, $data)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Process condition action
     */
    private function processConditionAction(array $action): void
    {
        // Implementation for conditional logic
        $this->moveToNextStep($action['automation_id'], $action['subscriber_id']);
    }

    /**
     * Process tag action
     */
    private function processTagAction(array $action): void
    {
        // Implementation for tagging subscribers
        $this->moveToNextStep($action['automation_id'], $action['subscriber_id']);
    }

    /**
     * Process segment action
     */
    private function processSegmentAction(array $action): void
    {
        // Implementation for adding/removing from segments
        $this->moveToNextStep($action['automation_id'], $action['subscriber_id']);
    }

    /**
     * Process webhook action
     */
    private function processWebhookAction(array $action): void
    {
        // Implementation for webhook calls
        $this->moveToNextStep($action['automation_id'], $action['subscriber_id']);
    }
}