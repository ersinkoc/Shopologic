<?php

declare(strict_types=1);

namespace Shopologic\Core\Marketing\ABTesting;

use Shopologic\Core\Database\Model;
use Shopologic\Core\Cache\CacheInterface;
use Shopologic\Core\Events\EventDispatcherInterface;

/**
 * A/B Testing framework for conversion optimization
 */
class ABTestingManager
{
    private CacheInterface $cache;
    private EventDispatcherInterface $eventDispatcher;
    private array $config;
    private array $activeTests = [];

    public function __construct(
        CacheInterface $cache,
        EventDispatcherInterface $eventDispatcher,
        array $config = []
    ) {
        $this->cache = $cache;
        $this->eventDispatcher = $eventDispatcher;
        $this->config = array_merge([
            'cookie_name' => 'ab_tests',
            'cookie_lifetime' => 30 * 24 * 60 * 60, // 30 days
            'minimum_sample_size' => 100,
            'confidence_level' => 0.95,
            'cache_ttl' => 300
        ], $config);
        
        $this->loadActiveTests();
    }

    /**
     * Create new A/B test
     */
    public function createTest(array $data): ABTest
    {
        $test = new ABTest();
        $test->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'type' => $data['type'], // 'split', 'multivariate', 'redirect'
            'variants' => $data['variants'],
            'traffic_allocation' => $data['traffic_allocation'] ?? null,
            'targeting_rules' => $data['targeting_rules'] ?? [],
            'goal_type' => $data['goal_type'], // 'conversion', 'revenue', 'engagement'
            'goal_config' => $data['goal_config'] ?? [],
            'status' => 'draft',
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null
        ]);
        
        $test->save();
        
        $this->eventDispatcher->dispatch('ab_testing.test_created', $test);
        
        return $test;
    }

    /**
     * Start A/B test
     */
    public function startTest(ABTest $test): void
    {
        if ($test->status !== 'draft') {
            throw new \Exception('Only draft tests can be started');
        }
        
        $test->status = 'running';
        $test->started_at = new \DateTime();
        $test->save();
        
        // Add to active tests
        $this->activeTests[$test->id] = $test;
        $this->cacheActiveTests();
        
        $this->eventDispatcher->dispatch('ab_testing.test_started', $test);
    }

    /**
     * Get variant for visitor
     */
    public function getVariant(string $testName, ?string $visitorId = null): ?string
    {
        $test = $this->findActiveTest($testName);
        
        if (!$test) {
            return null;
        }
        
        // Check targeting rules
        if (!$this->evaluateTargeting($test)) {
            return null;
        }
        
        // Get or assign variant
        $visitorId = $visitorId ?? $this->getVisitorId();
        $variant = $this->getAssignedVariant($test, $visitorId);
        
        if (!$variant) {
            $variant = $this->assignVariant($test, $visitorId);
        }
        
        return $variant;
    }

    /**
     * Track conversion
     */
    public function trackConversion(string $testName, ?float $value = null, ?string $visitorId = null): void
    {
        $test = $this->findActiveTest($testName);
        
        if (!$test) {
            return;
        }
        
        $visitorId = $visitorId ?? $this->getVisitorId();
        $variant = $this->getAssignedVariant($test, $visitorId);
        
        if (!$variant) {
            return;
        }
        
        // Record conversion
        $conversion = new ABTestConversion();
        $conversion->fill([
            'test_id' => $test->id,
            'variant' => $variant,
            'visitor_id' => $visitorId,
            'value' => $value,
            'converted_at' => new \DateTime()
        ]);
        $conversion->save();
        
        // Update test statistics
        $this->updateTestStatistics($test);
        
        $this->eventDispatcher->dispatch('ab_testing.conversion_tracked', [
            'test' => $test,
            'variant' => $variant,
            'value' => $value
        ]);
    }

    /**
     * Track event
     */
    public function trackEvent(string $testName, string $event, array $data = [], ?string $visitorId = null): void
    {
        $test = $this->findActiveTest($testName);
        
        if (!$test) {
            return;
        }
        
        $visitorId = $visitorId ?? $this->getVisitorId();
        $variant = $this->getAssignedVariant($test, $visitorId);
        
        if (!$variant) {
            return;
        }
        
        // Record event
        $testEvent = new ABTestEvent();
        $testEvent->fill([
            'test_id' => $test->id,
            'variant' => $variant,
            'visitor_id' => $visitorId,
            'event' => $event,
            'data' => $data,
            'occurred_at' => new \DateTime()
        ]);
        $testEvent->save();
        
        $this->eventDispatcher->dispatch('ab_testing.event_tracked', [
            'test' => $test,
            'variant' => $variant,
            'event' => $event
        ]);
    }

    /**
     * Get test results
     */
    public function getResults(ABTest $test): array
    {
        $results = [];
        
        foreach ($test->variants as $variant) {
            $variantName = $variant['name'];
            
            // Get participant count
            $participants = ABTestParticipant::where('test_id', $test->id)
                ->where('variant', $variantName)
                ->count();
            
            // Get conversion data
            $conversions = ABTestConversion::where('test_id', $test->id)
                ->where('variant', $variantName)
                ->get();
            
            $conversionCount = $conversions->count();
            $totalValue = $conversions->sum('value');
            
            $results[$variantName] = [
                'participants' => $participants,
                'conversions' => $conversionCount,
                'conversion_rate' => $participants > 0 ? ($conversionCount / $participants) * 100 : 0,
                'total_value' => $totalValue,
                'average_value' => $conversionCount > 0 ? $totalValue / $conversionCount : 0
            ];
        }
        
        // Calculate statistical significance
        if (count($results) === 2) {
            $variants = array_values($results);
            $significance = $this->calculateSignificance(
                $variants[0]['participants'],
                $variants[0]['conversions'],
                $variants[1]['participants'],
                $variants[1]['conversions']
            );
            
            $results['statistical_significance'] = $significance;
        }
        
        return $results;
    }

    /**
     * Declare winner
     */
    public function declareWinner(ABTest $test, string $variant): void
    {
        if ($test->status !== 'running') {
            throw new \Exception('Only running tests can have winners declared');
        }
        
        $test->status = 'completed';
        $test->completed_at = new \DateTime();
        $test->winner = $variant;
        $test->save();
        
        // Remove from active tests
        unset($this->activeTests[$test->id]);
        $this->cacheActiveTests();
        
        $this->eventDispatcher->dispatch('ab_testing.winner_declared', [
            'test' => $test,
            'winner' => $variant
        ]);
    }

    /**
     * Pause test
     */
    public function pauseTest(ABTest $test): void
    {
        if ($test->status !== 'running') {
            throw new \Exception('Only running tests can be paused');
        }
        
        $test->status = 'paused';
        $test->save();
        
        // Remove from active tests
        unset($this->activeTests[$test->id]);
        $this->cacheActiveTests();
        
        $this->eventDispatcher->dispatch('ab_testing.test_paused', $test);
    }

    /**
     * Resume test
     */
    public function resumeTest(ABTest $test): void
    {
        if ($test->status !== 'paused') {
            throw new \Exception('Only paused tests can be resumed');
        }
        
        $test->status = 'running';
        $test->save();
        
        // Add back to active tests
        $this->activeTests[$test->id] = $test;
        $this->cacheActiveTests();
        
        $this->eventDispatcher->dispatch('ab_testing.test_resumed', $test);
    }

    /**
     * Get active tests for visitor
     */
    public function getActiveTestsForVisitor(?string $visitorId = null): array
    {
        $visitorId = $visitorId ?? $this->getVisitorId();
        $activeTests = [];
        
        foreach ($this->activeTests as $test) {
            if ($this->evaluateTargeting($test)) {
                $variant = $this->getAssignedVariant($test, $visitorId);
                if ($variant) {
                    $activeTests[] = [
                        'test' => $test->name,
                        'variant' => $variant
                    ];
                }
            }
        }
        
        return $activeTests;
    }

    // Private methods

    private function loadActiveTests(): void
    {
        $this->activeTests = $this->cache->remember('ab_tests_active', $this->config['cache_ttl'], function () {
            return ABTest::where('status', 'running')
                ->get()
                ->keyBy('id')
                ->toArray();
        });
    }

    private function cacheActiveTests(): void
    {
        $this->cache->set('ab_tests_active', $this->activeTests, $this->config['cache_ttl']);
    }

    private function findActiveTest(string $name): ?ABTest
    {
        foreach ($this->activeTests as $test) {
            if ($test->name === $name) {
                return $test;
            }
        }
        
        return null;
    }

    private function getVisitorId(): string
    {
        if (isset($_COOKIE['visitor_id'])) {
            return $_COOKIE['visitor_id'];
        }
        
        $visitorId = bin2hex(random_bytes(16));
        setcookie('visitor_id', $visitorId, time() + (365 * 24 * 60 * 60), '/');
        
        return $visitorId;
    }

    private function getAssignedVariant(ABTest $test, string $visitorId): ?string
    {
        $participant = ABTestParticipant::where('test_id', $test->id)
            ->where('visitor_id', $visitorId)
            ->first();
        
        return $participant ? $participant->variant : null;
    }

    private function assignVariant(ABTest $test, string $visitorId): string
    {
        // Use traffic allocation or random assignment
        if ($test->traffic_allocation) {
            $variant = $this->allocateByTraffic($test->traffic_allocation, $visitorId);
        } else {
            $variants = array_column($test->variants, 'name');
            $variant = $variants[array_rand($variants)];
        }
        
        // Record participant
        $participant = new ABTestParticipant();
        $participant->fill([
            'test_id' => $test->id,
            'visitor_id' => $visitorId,
            'variant' => $variant,
            'assigned_at' => new \DateTime()
        ]);
        $participant->save();
        
        return $variant;
    }

    private function allocateByTraffic(array $allocation, string $visitorId): string
    {
        // Use consistent hashing for deterministic assignment
        $hash = crc32($visitorId);
        $bucket = $hash % 100;
        
        $cumulative = 0;
        foreach ($allocation as $variant => $percentage) {
            $cumulative += $percentage;
            if ($bucket < $cumulative) {
                return $variant;
            }
        }
        
        // Fallback to last variant
        return array_key_last($allocation);
    }

    private function evaluateTargeting(ABTest $test): bool
    {
        if (empty($test->targeting_rules)) {
            return true;
        }
        
        foreach ($test->targeting_rules as $rule) {
            if (!$this->evaluateRule($rule)) {
                return false;
            }
        }
        
        return true;
    }

    private function evaluateRule(array $rule): bool
    {
        switch ($rule['type']) {
            case 'url':
                return $this->matchesUrl($rule['value']);
            case 'user_agent':
                return $this->matchesUserAgent($rule['value']);
            case 'referrer':
                return $this->matchesReferrer($rule['value']);
            case 'cookie':
                return $this->matchesCookie($rule['key'], $rule['value']);
            default:
                return true;
        }
    }

    private function calculateSignificance(int $visitorsA, int $conversionsA, int $visitorsB, int $conversionsB): array
    {
        if ($visitorsA === 0 || $visitorsB === 0) {
            return ['significant' => false, 'p_value' => 1.0];
        }
        
        $rateA = $conversionsA / $visitorsA;
        $rateB = $conversionsB / $visitorsB;
        
        // Pooled probability
        $pooled = ($conversionsA + $conversionsB) / ($visitorsA + $visitorsB);
        
        // Standard error
        $se = sqrt($pooled * (1 - $pooled) * (1 / $visitorsA + 1 / $visitorsB));
        
        if ($se === 0) {
            return ['significant' => false, 'p_value' => 1.0];
        }
        
        // Z-score
        $z = abs($rateA - $rateB) / $se;
        
        // P-value (two-tailed)
        $pValue = 2 * (1 - $this->normalCDF($z));
        
        return [
            'significant' => $pValue < (1 - $this->config['confidence_level']),
            'p_value' => $pValue,
            'confidence' => (1 - $pValue) * 100
        ];
    }

    private function normalCDF(float $z): float
    {
        // Approximation of normal CDF
        $t = 1 / (1 + 0.2316419 * abs($z));
        $d = 0.3989423 * exp(-$z * $z / 2);
        $p = $d * $t * (0.3193815 + $t * (-0.3565638 + $t * (1.781478 + $t * (-1.821256 + $t * 1.330274))));
        
        return $z > 0 ? 1 - $p : $p;
    }

    private function updateTestStatistics(ABTest $test): void
    {
        // Update cached statistics
        $results = $this->getResults($test);
        
        $test->statistics = $results;
        $test->save();
    }

    private function matchesUrl(string $pattern): bool
    {
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
        return fnmatch($pattern, $currentUrl);
    }

    private function matchesUserAgent(string $pattern): bool
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return stripos($userAgent, $pattern) !== false;
    }

    private function matchesReferrer(string $pattern): bool
    {
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        return stripos($referrer, $pattern) !== false;
    }

    private function matchesCookie(string $key, string $value): bool
    {
        return isset($_COOKIE[$key]) && $_COOKIE[$key] === $value;
    }
}

/**
 * A/B Test model
 */
class ABTest extends Model
{
    protected $table = 'ab_tests';
    
    protected $fillable = [
        'name', 'description', 'type', 'variants', 'traffic_allocation',
        'targeting_rules', 'goal_type', 'goal_config', 'status',
        'start_date', 'end_date', 'started_at', 'completed_at',
        'winner', 'statistics'
    ];
    
    protected $casts = [
        'variants' => 'array',
        'traffic_allocation' => 'array',
        'targeting_rules' => 'array',
        'goal_config' => 'array',
        'statistics' => 'array',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];
}

/**
 * A/B Test participant model
 */
class ABTestParticipant extends Model
{
    protected $table = 'ab_test_participants';
    
    protected $fillable = [
        'test_id', 'visitor_id', 'variant', 'assigned_at'
    ];
    
    protected $casts = [
        'assigned_at' => 'datetime'
    ];
}

/**
 * A/B Test conversion model
 */
class ABTestConversion extends Model
{
    protected $table = 'ab_test_conversions';
    
    protected $fillable = [
        'test_id', 'variant', 'visitor_id', 'value', 'converted_at'
    ];
    
    protected $casts = [
        'converted_at' => 'datetime'
    ];
}

/**
 * A/B Test event model
 */
class ABTestEvent extends Model
{
    protected $table = 'ab_test_events';
    
    protected $fillable = [
        'test_id', 'variant', 'visitor_id', 'event', 'data', 'occurred_at'
    ];
    
    protected $casts = [
        'data' => 'array',
        'occurred_at' => 'datetime'
    ];
}