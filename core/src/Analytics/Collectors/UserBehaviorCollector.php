<?php

declare(strict_types=1);

namespace Shopologic\Core\Analytics\Collectors;

use Shopologic\Core\Analytics\DataCollectorInterface;
use Shopologic\Core\Database\DB;

/**
 * Collects user behavior analytics data
 */
class UserBehaviorCollector implements DataCollectorInterface
{
    private DB $db;
    private array $sessions = [];
    private array $users = [];

    public function __construct(DB $db)
    {
        $this->db = $db;
    }

    public function collect(array $event): void
    {
        $sessionId = $event['session_id'];
        $userId = $event['user_id'] ?? null;
        $timestamp = strtotime($event['timestamp']);
        
        // Initialize session tracking
        if (!isset($this->sessions[$sessionId])) {
            $this->sessions[$sessionId] = [
                'user_id' => $userId,
                'started_at' => $timestamp,
                'last_activity' => $timestamp,
                'page_count' => 0,
                'event_count' => 0,
                'pages' => [],
                'events' => [],
                'source' => null,
                'medium' => null,
                'device' => null
            ];
            
            $this->detectSessionAttributes($event);
        }
        
        // Update session data
        $this->sessions[$sessionId]['last_activity'] = $timestamp;
        $this->sessions[$sessionId]['event_count']++;
        $this->sessions[$sessionId]['events'][] = $event['event'];
        
        // Track page views
        if ($event['event'] === 'page_view') {
            $page = $event['properties']['page'] ?? '/';
            $this->sessions[$sessionId]['page_count']++;
            $this->sessions[$sessionId]['pages'][] = $page;
        }
        
        // Track user data
        if ($userId) {
            if (!isset($this->users[$userId])) {
                $this->users[$userId] = [
                    'first_seen' => $timestamp,
                    'last_seen' => $timestamp,
                    'session_count' => 0,
                    'total_events' => 0,
                    'total_pageviews' => 0
                ];
            }
            
            $this->users[$userId]['last_seen'] = $timestamp;
            $this->users[$userId]['total_events']++;
            
            if ($event['event'] === 'page_view') {
                $this->users[$userId]['total_pageviews']++;
            }
        }
        
        // Store event
        $this->storeEvent($event);
        
        // Process completed sessions periodically
        $this->processCompletedSessions();
    }

    public function getName(): string
    {
        return 'user_behavior';
    }

    public function getMetrics(): array
    {
        return [
            'active_sessions' => count($this->sessions),
            'tracked_users' => count($this->users),
            'avg_session_duration' => $this->calculateAvgSessionDuration(),
            'avg_pages_per_session' => $this->calculateAvgPagesPerSession()
        ];
    }

    /**
     * Get user journey for specific user
     */
    public function getUserJourney(string $userId, \DateTime $startDate, \DateTime $endDate): array
    {
        $sessions = $this->db->table('analytics_sessions')
            ->where('user_id', $userId)
            ->whereBetween('started_at', [
                $startDate->format('Y-m-d H:i:s'),
                $endDate->format('Y-m-d H:i:s')
            ])
            ->orderBy('started_at')
            ->get();
        
        $journey = [];
        
        foreach ($sessions as $session) {
            $events = $this->db->table('analytics_events')
                ->where('session_id', $session->id)
                ->orderBy('timestamp')
                ->get();
            
            $journey[] = [
                'session' => $session,
                'events' => $events->toArray()
            ];
        }
        
        return $journey;
    }

    /**
     * Get user segments based on behavior
     */
    public function getUserSegments(\DateTime $startDate, \DateTime $endDate): array
    {
        // Power users (high engagement)
        $powerUsers = $this->db->table('analytics_users')
            ->selectRaw('COUNT(*) as count')
            ->whereRaw('
                (SELECT COUNT(*) FROM analytics_sessions WHERE user_id = analytics_users.id 
                AND started_at BETWEEN ? AND ?) >= 10
            ', [
                $startDate->format('Y-m-d H:i:s'),
                $endDate->format('Y-m-d H:i:s')
            ])
            ->first()->count;
        
        // Regular users (moderate engagement)
        $regularUsers = $this->db->table('analytics_users')
            ->selectRaw('COUNT(*) as count')
            ->whereRaw('
                (SELECT COUNT(*) FROM analytics_sessions WHERE user_id = analytics_users.id 
                AND started_at BETWEEN ? AND ?) BETWEEN 2 AND 9
            ', [
                $startDate->format('Y-m-d H:i:s'),
                $endDate->format('Y-m-d H:i:s')
            ])
            ->first()->count;
        
        // One-time users
        $oneTimeUsers = $this->db->table('analytics_users')
            ->selectRaw('COUNT(*) as count')
            ->whereRaw('
                (SELECT COUNT(*) FROM analytics_sessions WHERE user_id = analytics_users.id 
                AND started_at BETWEEN ? AND ?) = 1
            ', [
                $startDate->format('Y-m-d H:i:s'),
                $endDate->format('Y-m-d H:i:s')
            ])
            ->first()->count;
        
        return [
            'power_users' => $powerUsers,
            'regular_users' => $regularUsers,
            'one_time_users' => $oneTimeUsers,
            'segments' => [
                [
                    'name' => 'Power Users',
                    'count' => $powerUsers,
                    'criteria' => '10+ sessions'
                ],
                [
                    'name' => 'Regular Users',
                    'count' => $regularUsers,
                    'criteria' => '2-9 sessions'
                ],
                [
                    'name' => 'One-time Users',
                    'count' => $oneTimeUsers,
                    'criteria' => '1 session'
                ]
            ]
        ];
    }

    /**
     * Get user flow analysis
     */
    public function getUserFlow(\DateTime $startDate, \DateTime $endDate, int $maxSteps = 5): array
    {
        $flows = [];
        
        // Get sample of sessions
        $sessions = $this->db->table('analytics_sessions')
            ->whereBetween('started_at', [
                $startDate->format('Y-m-d H:i:s'),
                $endDate->format('Y-m-d H:i:s')
            ])
            ->limit(1000)
            ->get();
        
        foreach ($sessions as $session) {
            $pages = json_decode($session->pages, true);
            
            if (empty($pages)) {
                continue;
            }
            
            // Build flow path
            $path = array_slice($pages, 0, $maxSteps);
            $pathKey = implode(' > ', $path);
            
            if (!isset($flows[$pathKey])) {
                $flows[$pathKey] = [
                    'path' => $path,
                    'count' => 0,
                    'completions' => 0,
                    'dropoffs' => []
                ];
            }
            
            $flows[$pathKey]['count']++;
            
            if (count($pages) >= count($path)) {
                $flows[$pathKey]['completions']++;
            }
            
            // Track where users dropped off
            if (count($pages) < $maxSteps && count($pages) < count($path)) {
                $dropoffStep = count($pages);
                if (!isset($flows[$pathKey]['dropoffs'][$dropoffStep])) {
                    $flows[$pathKey]['dropoffs'][$dropoffStep] = 0;
                }
                $flows[$pathKey]['dropoffs'][$dropoffStep]++;
            }
        }
        
        // Sort by frequency
        uasort($flows, function ($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        return array_slice($flows, 0, 20);
    }

    // Private methods

    private function detectSessionAttributes(array $event): void
    {
        $sessionId = $event['session_id'];
        $properties = $event['properties'];
        
        // Detect traffic source
        if (isset($properties['referrer'])) {
            $source = $this->parseTrafficSource($properties['referrer']);
            $this->sessions[$sessionId]['source'] = $source['source'];
            $this->sessions[$sessionId]['medium'] = $source['medium'];
        }
        
        // Detect device type
        if (isset($properties['user_agent'])) {
            $this->sessions[$sessionId]['device'] = $this->detectDevice($properties['user_agent']);
        }
        
        // Detect location
        if (isset($properties['ip_address'])) {
            // Location detection would go here
        }
    }

    private function parseTrafficSource(string $referrer): array
    {
        if (empty($referrer)) {
            return ['source' => 'direct', 'medium' => 'none'];
        }
        
        $domain = parse_url($referrer, PHP_URL_HOST);
        
        // Search engines
        $searchEngines = [
            'google' => 'google',
            'bing' => 'bing',
            'yahoo' => 'yahoo',
            'duckduckgo' => 'duckduckgo'
        ];
        
        foreach ($searchEngines as $pattern => $engine) {
            if (stripos($domain, $pattern) !== false) {
                return ['source' => $engine, 'medium' => 'organic'];
            }
        }
        
        // Social networks
        $socialNetworks = [
            'facebook' => 'facebook',
            'twitter' => 'twitter',
            'linkedin' => 'linkedin',
            'instagram' => 'instagram'
        ];
        
        foreach ($socialNetworks as $pattern => $network) {
            if (stripos($domain, $pattern) !== false) {
                return ['source' => $network, 'medium' => 'social'];
            }
        }
        
        return ['source' => $domain, 'medium' => 'referral'];
    }

    private function detectDevice(string $userAgent): string
    {
        if (preg_match('/Mobile|Android|iPhone|iPad/i', $userAgent)) {
            if (preg_match('/iPad|Tablet/i', $userAgent)) {
                return 'tablet';
            }
            return 'mobile';
        }
        
        return 'desktop';
    }

    private function processCompletedSessions(): void
    {
        $currentTime = time();
        $sessionTimeout = 1800; // 30 minutes
        
        foreach ($this->sessions as $sessionId => $session) {
            if ($currentTime - $session['last_activity'] > $sessionTimeout) {
                $this->storeSession($sessionId, $session);
                unset($this->sessions[$sessionId]);
            }
        }
    }

    private function storeEvent(array $event): void
    {
        $this->db->table('analytics_events')->insert([
            'event' => $event['event'],
            'properties' => json_encode($event['properties']),
            'user_id' => $event['user_id'] ?? null,
            'session_id' => $event['session_id'],
            'timestamp' => $event['timestamp'],
            'date' => date('Y-m-d', strtotime($event['timestamp'])),
            'hour' => (int)date('H', strtotime($event['timestamp']))
        ]);
    }

    private function storeSession(string $sessionId, array $session): void
    {
        $duration = $session['last_activity'] - $session['started_at'];
        
        $this->db->table('analytics_sessions')->insert([
            'id' => $sessionId,
            'user_id' => $session['user_id'],
            'started_at' => date('Y-m-d H:i:s', $session['started_at']),
            'ended_at' => date('Y-m-d H:i:s', $session['last_activity']),
            'duration' => $duration,
            'page_count' => $session['page_count'],
            'event_count' => $session['event_count'],
            'pages' => json_encode($session['pages']),
            'source' => $session['source'],
            'medium' => $session['medium'],
            'device_type' => $session['device'],
            'is_bounce' => $session['page_count'] <= 1
        ]);
        
        // Update user record
        if ($session['user_id']) {
            $this->db->table('analytics_users')->updateOrInsert(
                ['id' => $session['user_id']],
                [
                    'last_seen' => date('Y-m-d H:i:s', $session['last_activity']),
                    'session_count' => $this->db->raw('session_count + 1'),
                    'total_duration' => $this->db->raw("total_duration + {$duration}"),
                    'acquisition_channel' => $session['source'] // First touch attribution
                ]
            );
        }
    }

    private function calculateAvgSessionDuration(): float
    {
        $totalDuration = 0;
        $count = 0;
        
        foreach ($this->sessions as $session) {
            $duration = $session['last_activity'] - $session['started_at'];
            $totalDuration += $duration;
            $count++;
        }
        
        return $count > 0 ? $totalDuration / $count : 0;
    }

    private function calculateAvgPagesPerSession(): float
    {
        $totalPages = 0;
        $count = 0;
        
        foreach ($this->sessions as $session) {
            $totalPages += $session['page_count'];
            $count++;
        }
        
        return $count > 0 ? $totalPages / $count : 0;
    }
}