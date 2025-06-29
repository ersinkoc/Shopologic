<?php

declare(strict_types=1);

namespace Shopologic\Core\Marketing\Conversion;

use Shopologic\Core\Database\Model;
use Shopologic\Core\Events\EventDispatcherInterface;
use Shopologic\Core\Session\SessionManager;

/**
 * Conversion tracking and optimization
 */
class ConversionTracker
{
    private EventDispatcherInterface $eventDispatcher;
    private SessionManager $session;
    private array $config;
    private array $goals = [];

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        SessionManager $session,
        array $config = []
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->session = $session;
        $this->config = array_merge([
            'cookie_lifetime' => 30 * 24 * 60 * 60, // 30 days
            'attribution_window' => 7 * 24 * 60 * 60, // 7 days
            'track_micro_conversions' => true,
            'enable_funnel_tracking' => true
        ], $config);
    }

    /**
     * Define conversion goal
     */
    public function defineGoal(string $name, array $config): void
    {
        $this->goals[$name] = array_merge([
            'type' => 'event', // 'event', 'pageview', 'revenue'
            'value' => null,
            'conditions' => [],
            'funnel_steps' => []
        ], $config);
    }

    /**
     * Track conversion
     */
    public function trackConversion(string $goalName, array $data = []): void
    {
        if (!isset($this->goals[$goalName])) {
            throw new \Exception("Goal {$goalName} not defined");
        }
        
        $goal = $this->goals[$goalName];
        $visitorId = $this->getVisitorId();
        
        // Check if goal conditions are met
        if (!$this->evaluateConditions($goal['conditions'], $data)) {
            return;
        }
        
        // Create conversion record
        $conversion = new Conversion();
        $conversion->fill([
            'goal_name' => $goalName,
            'visitor_id' => $visitorId,
            'session_id' => $this->session->getId(),
            'value' => $data['value'] ?? $goal['value'] ?? 0,
            'revenue' => $data['revenue'] ?? 0,
            'attribution' => $this->getAttribution(),
            'metadata' => $data,
            'converted_at' => new \DateTime()
        ]);
        
        $conversion->save();
        
        // Track funnel completion if applicable
        if ($this->config['enable_funnel_tracking'] && !empty($goal['funnel_steps'])) {
            $this->trackFunnelCompletion($goalName, $visitorId);
        }
        
        $this->eventDispatcher->dispatch('conversion.tracked', [
            'goal' => $goalName,
            'conversion' => $conversion
        ]);
    }

    /**
     * Track micro conversion
     */
    public function trackMicroConversion(string $action, array $data = []): void
    {
        if (!$this->config['track_micro_conversions']) {
            return;
        }
        
        $microConversion = new MicroConversion();
        $microConversion->fill([
            'action' => $action,
            'visitor_id' => $this->getVisitorId(),
            'session_id' => $this->session->getId(),
            'data' => $data,
            'occurred_at' => new \DateTime()
        ]);
        
        $microConversion->save();
        
        $this->eventDispatcher->dispatch('conversion.micro_tracked', [
            'action' => $action,
            'data' => $data
        ]);
    }

    /**
     * Track funnel step
     */
    public function trackFunnelStep(string $funnelName, string $step, array $data = []): void
    {
        if (!$this->config['enable_funnel_tracking']) {
            return;
        }
        
        $visitorId = $this->getVisitorId();
        
        $funnelStep = new FunnelStep();
        $funnelStep->fill([
            'funnel_name' => $funnelName,
            'step_name' => $step,
            'visitor_id' => $visitorId,
            'session_id' => $this->session->getId(),
            'data' => $data,
            'entered_at' => new \DateTime()
        ]);
        
        $funnelStep->save();
        
        $this->eventDispatcher->dispatch('conversion.funnel_step', [
            'funnel' => $funnelName,
            'step' => $step
        ]);
    }

    /**
     * Get conversion rate for goal
     */
    public function getConversionRate(string $goalName, ?\DateTime $startDate = null, ?\DateTime $endDate = null): float
    {
        $query = Conversion::where('goal_name', $goalName);
        
        if ($startDate) {
            $query->where('converted_at', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->where('converted_at', '<=', $endDate);
        }
        
        $conversions = $query->count();
        $visitors = $this->getUniqueVisitorCount($startDate, $endDate);
        
        return $visitors > 0 ? ($conversions / $visitors) * 100 : 0;
    }

    /**
     * Get funnel analysis
     */
    public function getFunnelAnalysis(string $funnelName, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $goal = null;
        foreach ($this->goals as $name => $g) {
            if (isset($g['funnel_steps']) && $g['funnel_name'] === $funnelName) {
                $goal = $g;
                break;
            }
        }
        
        if (!$goal || empty($goal['funnel_steps'])) {
            return [];
        }
        
        $analysis = [];
        $previousStepVisitors = null;
        
        foreach ($goal['funnel_steps'] as $step) {
            $query = FunnelStep::where('funnel_name', $funnelName)
                ->where('step_name', $step);
            
            if ($startDate) {
                $query->where('entered_at', '>=', $startDate);
            }
            
            if ($endDate) {
                $query->where('entered_at', '<=', $endDate);
            }
            
            $visitors = $query->distinct('visitor_id')->count('visitor_id');
            
            $stepData = [
                'step' => $step,
                'visitors' => $visitors
            ];
            
            if ($previousStepVisitors !== null) {
                $stepData['drop_off_rate'] = $previousStepVisitors > 0 
                    ? (($previousStepVisitors - $visitors) / $previousStepVisitors) * 100 
                    : 0;
            }
            
            $analysis[] = $stepData;
            $previousStepVisitors = $visitors;
        }
        
        // Calculate overall conversion rate
        if (count($analysis) > 0) {
            $firstStep = $analysis[0]['visitors'];
            $lastStep = $analysis[count($analysis) - 1]['visitors'];
            
            $overallConversionRate = $firstStep > 0 
                ? ($lastStep / $firstStep) * 100 
                : 0;
            
            return [
                'steps' => $analysis,
                'overall_conversion_rate' => $overallConversionRate
            ];
        }
        
        return ['steps' => $analysis];
    }

    /**
     * Get conversion attribution
     */
    public function getAttribution(): array
    {
        $attribution = [];
        
        // Get UTM parameters
        $utmParams = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        foreach ($utmParams as $param) {
            if (isset($_GET[$param])) {
                $attribution[$param] = $_GET[$param];
            }
        }
        
        // Get referrer
        if (isset($_SERVER['HTTP_REFERER'])) {
            $attribution['referrer'] = $_SERVER['HTTP_REFERER'];
        }
        
        // Get landing page
        $attribution['landing_page'] = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Get device info
        $attribution['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $attribution['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // Store attribution in session for multi-touch
        $sessionAttribution = $this->session->get('attribution', []);
        if (empty($sessionAttribution) && !empty($attribution)) {
            $this->session->set('attribution', $attribution);
        }
        
        return $attribution;
    }

    /**
     * Calculate conversion value
     */
    public function calculateConversionValue(string $goalName, array $data = []): float
    {
        if (!isset($this->goals[$goalName])) {
            return 0;
        }
        
        $goal = $this->goals[$goalName];
        
        // Use provided value
        if (isset($data['value'])) {
            return (float)$data['value'];
        }
        
        // Use goal default value
        if (isset($goal['value'])) {
            return (float)$goal['value'];
        }
        
        // Calculate based on goal type
        switch ($goal['type']) {
            case 'revenue':
                return (float)($data['revenue'] ?? 0);
            
            case 'engagement':
                // Calculate based on engagement metrics
                $engagementScore = 0;
                if (isset($data['time_on_site'])) {
                    $engagementScore += $data['time_on_site'] / 60; // minutes
                }
                if (isset($data['pages_viewed'])) {
                    $engagementScore += $data['pages_viewed'] * 0.5;
                }
                return $engagementScore;
            
            default:
                return 1.0; // Default value for event-based goals
        }
    }

    /**
     * Get conversion insights
     */
    public function getInsights(string $goalName, int $days = 30): array
    {
        $endDate = new \DateTime();
        $startDate = clone $endDate;
        $startDate->modify("-{$days} days");
        
        // Get conversion trend
        $trend = [];
        $currentDate = clone $startDate;
        
        while ($currentDate <= $endDate) {
            $dayStart = clone $currentDate;
            $dayEnd = clone $currentDate;
            $dayEnd->setTime(23, 59, 59);
            
            $conversions = Conversion::where('goal_name', $goalName)
                ->whereBetween('converted_at', [$dayStart, $dayEnd])
                ->count();
            
            $trend[] = [
                'date' => $currentDate->format('Y-m-d'),
                'conversions' => $conversions
            ];
            
            $currentDate->modify('+1 day');
        }
        
        // Get top converting sources
        $sources = Conversion::where('goal_name', $goalName)
            ->whereBetween('converted_at', [$startDate, $endDate])
            ->selectRaw("attribution->>'utm_source' as source, COUNT(*) as count")
            ->groupBy('source')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();
        
        // Get device breakdown
        $devices = Conversion::where('goal_name', $goalName)
            ->whereBetween('converted_at', [$startDate, $endDate])
            ->selectRaw("
                CASE
                    WHEN attribution->>'user_agent' LIKE '%Mobile%' THEN 'Mobile'
                    WHEN attribution->>'user_agent' LIKE '%Tablet%' THEN 'Tablet'
                    ELSE 'Desktop'
                END as device,
                COUNT(*) as count
            ")
            ->groupBy('device')
            ->get();
        
        return [
            'trend' => $trend,
            'top_sources' => $sources,
            'device_breakdown' => $devices,
            'total_conversions' => array_sum(array_column($trend, 'conversions')),
            'average_daily' => array_sum(array_column($trend, 'conversions')) / count($trend)
        ];
    }

    // Private methods

    private function getVisitorId(): string
    {
        if (isset($_COOKIE['visitor_id'])) {
            return $_COOKIE['visitor_id'];
        }
        
        $visitorId = bin2hex(random_bytes(16));
        setcookie('visitor_id', $visitorId, time() + $this->config['cookie_lifetime'], '/');
        
        return $visitorId;
    }

    private function evaluateConditions(array $conditions, array $data): bool
    {
        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $data)) {
                return false;
            }
        }
        
        return true;
    }

    private function evaluateCondition(array $condition, array $data): bool
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];
        
        $actualValue = $data[$field] ?? null;
        
        switch ($operator) {
            case 'equals':
                return $actualValue == $value;
            case 'not_equals':
                return $actualValue != $value;
            case 'contains':
                return stripos((string)$actualValue, (string)$value) !== false;
            case 'greater_than':
                return $actualValue > $value;
            case 'less_than':
                return $actualValue < $value;
            case 'exists':
                return $actualValue !== null;
            case 'not_exists':
                return $actualValue === null;
            default:
                return true;
        }
    }

    private function trackFunnelCompletion(string $goalName, string $visitorId): void
    {
        $goal = $this->goals[$goalName];
        $funnelSteps = $goal['funnel_steps'];
        
        if (empty($funnelSteps)) {
            return;
        }
        
        // Check if visitor completed all funnel steps
        $completedSteps = FunnelStep::where('funnel_name', $goal['funnel_name'] ?? $goalName)
            ->where('visitor_id', $visitorId)
            ->whereIn('step_name', $funnelSteps)
            ->distinct('step_name')
            ->count('step_name');
        
        if ($completedSteps === count($funnelSteps)) {
            $this->eventDispatcher->dispatch('conversion.funnel_completed', [
                'goal' => $goalName,
                'visitor_id' => $visitorId
            ]);
        }
    }

    private function getUniqueVisitorCount(?\DateTime $startDate, ?\DateTime $endDate): int
    {
        // This would typically query a visitor tracking table
        // For now, we'll estimate based on session data
        return 1000; // Placeholder
    }
}

/**
 * Conversion model
 */
class Conversion extends Model
{
    protected $table = 'conversions';
    
    protected $fillable = [
        'goal_name', 'visitor_id', 'session_id', 'value',
        'revenue', 'attribution', 'metadata', 'converted_at'
    ];
    
    protected $casts = [
        'attribution' => 'array',
        'metadata' => 'array',
        'converted_at' => 'datetime'
    ];
}

/**
 * Micro conversion model
 */
class MicroConversion extends Model
{
    protected $table = 'micro_conversions';
    
    protected $fillable = [
        'action', 'visitor_id', 'session_id', 'data', 'occurred_at'
    ];
    
    protected $casts = [
        'data' => 'array',
        'occurred_at' => 'datetime'
    ];
}

/**
 * Funnel step model
 */
class FunnelStep extends Model
{
    protected $table = 'funnel_steps';
    
    protected $fillable = [
        'funnel_name', 'step_name', 'visitor_id', 'session_id',
        'data', 'entered_at'
    ];
    
    protected $casts = [
        'data' => 'array',
        'entered_at' => 'datetime'
    ];
}