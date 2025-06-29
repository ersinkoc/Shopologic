<?php

declare(strict_types=1);

namespace Shopologic\Core\Marketing\Automation;

use Shopologic\Core\Database\Model;
use Shopologic\Core\Events\EventDispatcherInterface;
use Shopologic\Core\Queue\QueueInterface;

/**
 * Marketing automation workflow engine
 */
class MarketingAutomation
{
    private EventDispatcherInterface $eventDispatcher;
    private QueueInterface $queue;
    private array $triggers = [];
    private array $actions = [];
    private array $conditions = [];

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        QueueInterface $queue
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->queue = $queue;
        
        $this->registerDefaultComponents();
    }

    /**
     * Register trigger type
     */
    public function registerTrigger(string $type, TriggerInterface $trigger): void
    {
        $this->triggers[$type] = $trigger;
    }

    /**
     * Register action type
     */
    public function registerAction(string $type, ActionInterface $action): void
    {
        $this->actions[$type] = $action;
    }

    /**
     * Register condition type
     */
    public function registerCondition(string $type, ConditionInterface $condition): void
    {
        $this->conditions[$type] = $condition;
    }

    /**
     * Create automation workflow
     */
    public function createWorkflow(array $data): Workflow
    {
        $workflow = new Workflow();
        $workflow->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'trigger_type' => $data['trigger_type'],
            'trigger_config' => $data['trigger_config'] ?? [],
            'actions' => $data['actions'] ?? [],
            'conditions' => $data['conditions'] ?? [],
            'status' => 'active',
            'priority' => $data['priority'] ?? 0
        ]);
        
        $workflow->save();
        
        // Register trigger listener
        $this->setupTriggerListener($workflow);
        
        $this->eventDispatcher->dispatch('automation.workflow_created', $workflow);
        
        return $workflow;
    }

    /**
     * Execute workflow for contact
     */
    public function executeWorkflow(Workflow $workflow, Contact $contact, array $context = []): void
    {
        // Check if workflow is active
        if ($workflow->status !== 'active') {
            return;
        }
        
        // Check conditions
        if (!$this->evaluateConditions($workflow->conditions, $contact, $context)) {
            return;
        }
        
        // Create execution record
        $execution = new WorkflowExecution();
        $execution->fill([
            'workflow_id' => $workflow->id,
            'contact_id' => $contact->id,
            'context' => $context,
            'status' => 'running',
            'started_at' => new \DateTime()
        ]);
        $execution->save();
        
        // Queue workflow execution
        $this->queue->push('execute_workflow_actions', [
            'execution_id' => $execution->id,
            'workflow_id' => $workflow->id,
            'contact_id' => $contact->id,
            'context' => $context
        ]);
        
        $this->eventDispatcher->dispatch('automation.workflow_started', [
            'workflow' => $workflow,
            'contact' => $contact,
            'execution' => $execution
        ]);
    }

    /**
     * Process workflow actions
     */
    public function processActions(int $executionId): void
    {
        $execution = WorkflowExecution::find($executionId);
        if (!$execution) {
            return;
        }
        
        $workflow = Workflow::find($execution->workflow_id);
        $contact = Contact::find($execution->contact_id);
        
        if (!$workflow || !$contact) {
            $execution->status = 'failed';
            $execution->completed_at = new \DateTime();
            $execution->save();
            return;
        }
        
        $currentStep = 0;
        
        foreach ($workflow->actions as $actionConfig) {
            try {
                // Execute action
                $this->executeAction($actionConfig, $contact, $execution->context);
                
                // Record step completion
                $this->recordStepCompletion($execution, $currentStep, $actionConfig);
                
                // Handle delays
                if (isset($actionConfig['delay'])) {
                    $this->scheduleNextAction($execution, $currentStep + 1, $actionConfig['delay']);
                    return;
                }
                
                $currentStep++;
                
            } catch (\Exception $e) {
                $this->handleActionError($execution, $currentStep, $e);
                return;
            }
        }
        
        // Mark execution as completed
        $execution->status = 'completed';
        $execution->completed_at = new \DateTime();
        $execution->save();
        
        $this->eventDispatcher->dispatch('automation.workflow_completed', [
            'workflow' => $workflow,
            'contact' => $contact,
            'execution' => $execution
        ]);
    }

    /**
     * Handle trigger event
     */
    public function handleTrigger(string $triggerType, array $data): void
    {
        // Find workflows with this trigger
        $workflows = Workflow::where('trigger_type', $triggerType)
            ->where('status', 'active')
            ->orderBy('priority', 'desc')
            ->get();
        
        foreach ($workflows as $workflow) {
            // Check if trigger matches
            if (!$this->triggers[$triggerType]->matches($workflow->trigger_config, $data)) {
                continue;
            }
            
            // Get or create contact
            $contact = $this->resolveContact($data);
            
            if ($contact) {
                $this->executeWorkflow($workflow, $contact, $data);
            }
        }
    }

    /**
     * Get workflow statistics
     */
    public function getStatistics(Workflow $workflow, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = WorkflowExecution::where('workflow_id', $workflow->id);
        
        if ($startDate) {
            $query->where('started_at', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->where('started_at', '<=', $endDate);
        }
        
        $stats = [
            'total_executions' => $query->count(),
            'completed' => $query->where('status', 'completed')->count(),
            'running' => $query->where('status', 'running')->count(),
            'failed' => $query->where('status', 'failed')->count()
        ];
        
        // Calculate conversion rates for each action
        $actionStats = [];
        foreach ($workflow->actions as $index => $action) {
            $completions = WorkflowStep::where('workflow_execution_id', 'IN', 
                $query->pluck('id')->toArray()
            )
            ->where('step_index', $index)
            ->where('status', 'completed')
            ->count();
            
            $actionStats[] = [
                'action' => $action['type'],
                'completions' => $completions,
                'conversion_rate' => $stats['total_executions'] > 0 
                    ? ($completions / $stats['total_executions']) * 100 
                    : 0
            ];
        }
        
        $stats['action_stats'] = $actionStats;
        
        return $stats;
    }

    // Private methods

    private function registerDefaultComponents(): void
    {
        // Register triggers
        $this->registerTrigger('form_submitted', new FormSubmittedTrigger());
        $this->registerTrigger('order_placed', new OrderPlacedTrigger());
        $this->registerTrigger('cart_abandoned', new CartAbandonedTrigger());
        $this->registerTrigger('user_registered', new UserRegisteredTrigger());
        $this->registerTrigger('tag_added', new TagAddedTrigger());
        
        // Register actions
        $this->registerAction('send_email', new SendEmailAction());
        $this->registerAction('add_tag', new AddTagAction());
        $this->registerAction('remove_tag', new RemoveTagAction());
        $this->registerAction('update_field', new UpdateFieldAction());
        $this->registerAction('webhook', new WebhookAction());
        $this->registerAction('create_task', new CreateTaskAction());
        
        // Register conditions
        $this->registerCondition('field_equals', new FieldEqualsCondition());
        $this->registerCondition('has_tag', new HasTagCondition());
        $this->registerCondition('date_range', new DateRangeCondition());
        $this->registerCondition('purchase_history', new PurchaseHistoryCondition());
    }

    private function setupTriggerListener(Workflow $workflow): void
    {
        $triggerType = $workflow->trigger_type;
        
        if (isset($this->triggers[$triggerType])) {
            $this->triggers[$triggerType]->setup($workflow->trigger_config);
        }
    }

    private function evaluateConditions(array $conditions, Contact $contact, array $context): bool
    {
        foreach ($conditions as $conditionConfig) {
            $type = $conditionConfig['type'];
            
            if (!isset($this->conditions[$type])) {
                continue;
            }
            
            if (!$this->conditions[$type]->evaluate($conditionConfig, $contact, $context)) {
                return false;
            }
        }
        
        return true;
    }

    private function executeAction(array $actionConfig, Contact $contact, array $context): void
    {
        $type = $actionConfig['type'];
        
        if (!isset($this->actions[$type])) {
            throw new \Exception("Unknown action type: {$type}");
        }
        
        $this->actions[$type]->execute($actionConfig, $contact, $context);
    }

    private function resolveContact(array $data): ?Contact
    {
        // Try to find contact by email
        if (isset($data['email'])) {
            $contact = Contact::where('email', $data['email'])->first();
            
            if (!$contact) {
                // Create new contact
                $contact = new Contact();
                $contact->fill([
                    'email' => $data['email'],
                    'name' => $data['name'] ?? '',
                    'phone' => $data['phone'] ?? null,
                    'custom_fields' => $data['custom_fields'] ?? []
                ]);
                $contact->save();
            }
            
            return $contact;
        }
        
        // Try to find by user ID
        if (isset($data['user_id'])) {
            return Contact::where('user_id', $data['user_id'])->first();
        }
        
        return null;
    }

    private function recordStepCompletion(WorkflowExecution $execution, int $stepIndex, array $actionConfig): void
    {
        $step = new WorkflowStep();
        $step->fill([
            'workflow_execution_id' => $execution->id,
            'step_index' => $stepIndex,
            'action_type' => $actionConfig['type'],
            'action_config' => $actionConfig,
            'status' => 'completed',
            'executed_at' => new \DateTime()
        ]);
        $step->save();
    }

    private function scheduleNextAction(WorkflowExecution $execution, int $nextStep, array $delay): void
    {
        $delaySeconds = $this->calculateDelaySeconds($delay);
        
        $this->queue->push('execute_workflow_actions', [
            'execution_id' => $execution->id,
            'start_from_step' => $nextStep
        ], $delaySeconds);
    }

    private function handleActionError(WorkflowExecution $execution, int $stepIndex, \Exception $error): void
    {
        $step = new WorkflowStep();
        $step->fill([
            'workflow_execution_id' => $execution->id,
            'step_index' => $stepIndex,
            'status' => 'failed',
            'error' => $error->getMessage(),
            'executed_at' => new \DateTime()
        ]);
        $step->save();
        
        $execution->status = 'failed';
        $execution->completed_at = new \DateTime();
        $execution->error = $error->getMessage();
        $execution->save();
        
        $this->eventDispatcher->dispatch('automation.workflow_failed', [
            'execution' => $execution,
            'error' => $error
        ]);
    }

    private function calculateDelaySeconds(array $delay): int
    {
        $value = $delay['value'] ?? 0;
        $unit = $delay['unit'] ?? 'minutes';
        
        switch ($unit) {
            case 'seconds':
                return $value;
            case 'minutes':
                return $value * 60;
            case 'hours':
                return $value * 3600;
            case 'days':
                return $value * 86400;
            default:
                return 0;
        }
    }
}

/**
 * Workflow model
 */
class Workflow extends Model
{
    protected $table = 'automation_workflows';
    
    protected $fillable = [
        'name', 'description', 'trigger_type', 'trigger_config',
        'actions', 'conditions', 'status', 'priority'
    ];
    
    protected $casts = [
        'trigger_config' => 'array',
        'actions' => 'array',
        'conditions' => 'array'
    ];
}

/**
 * Workflow execution model
 */
class WorkflowExecution extends Model
{
    protected $table = 'workflow_executions';
    
    protected $fillable = [
        'workflow_id', 'contact_id', 'context', 'status',
        'started_at', 'completed_at', 'error'
    ];
    
    protected $casts = [
        'context' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];
}

/**
 * Workflow step model
 */
class WorkflowStep extends Model
{
    protected $table = 'workflow_steps';
    
    protected $fillable = [
        'workflow_execution_id', 'step_index', 'action_type',
        'action_config', 'status', 'executed_at', 'error'
    ];
    
    protected $casts = [
        'action_config' => 'array',
        'executed_at' => 'datetime'
    ];
}

/**
 * Contact model
 */
class Contact extends Model
{
    protected $table = 'contacts';
    
    protected $fillable = [
        'email', 'name', 'phone', 'user_id',
        'tags', 'custom_fields', 'score'
    ];
    
    protected $casts = [
        'tags' => 'array',
        'custom_fields' => 'array'
    ];
}

// Interfaces

interface TriggerInterface
{
    public function setup(array $config): void;
    public function matches(array $config, array $data): bool;
}

interface ActionInterface
{
    public function execute(array $config, Contact $contact, array $context): void;
}

interface ConditionInterface
{
    public function evaluate(array $config, Contact $contact, array $context): bool;
}

// Default trigger implementations

class FormSubmittedTrigger implements TriggerInterface
{
    public function setup(array $config): void
    {
        // Register form submission listener
    }
    
    public function matches(array $config, array $data): bool
    {
        return isset($config['form_id']) && 
               isset($data['form_id']) && 
               $config['form_id'] === $data['form_id'];
    }
}

class OrderPlacedTrigger implements TriggerInterface
{
    public function setup(array $config): void
    {
        // Register order placed listener
    }
    
    public function matches(array $config, array $data): bool
    {
        if (!isset($data['order'])) {
            return false;
        }
        
        // Check order total
        if (isset($config['min_total']) && $data['order']['total'] < $config['min_total']) {
            return false;
        }
        
        // Check product categories
        if (isset($config['categories'])) {
            // Check if order contains products from specified categories
        }
        
        return true;
    }
}

// Default action implementations

class SendEmailAction implements ActionInterface
{
    public function execute(array $config, Contact $contact, array $context): void
    {
        // Send email using email service
        $emailData = [
            'to' => $contact->email,
            'subject' => $this->parseTemplate($config['subject'], $contact, $context),
            'body' => $this->parseTemplate($config['body'], $contact, $context),
            'from' => $config['from'] ?? null
        ];
        
        // Queue email for sending
    }
    
    private function parseTemplate(string $template, Contact $contact, array $context): string
    {
        // Replace placeholders with actual values
        $replacements = [
            '{{contact.name}}' => $contact->name,
            '{{contact.email}}' => $contact->email
        ];
        
        foreach ($context as $key => $value) {
            if (is_scalar($value)) {
                $replacements['{{' . $key . '}}'] = $value;
            }
        }
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
}

class AddTagAction implements ActionInterface
{
    public function execute(array $config, Contact $contact, array $context): void
    {
        $tags = $contact->tags ?? [];
        $newTag = $config['tag'];
        
        if (!in_array($newTag, $tags)) {
            $tags[] = $newTag;
            $contact->tags = $tags;
            $contact->save();
        }
    }
}