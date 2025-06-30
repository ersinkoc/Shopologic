<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedAnalyticsReporting\Services;

use AdvancedAnalytics\Repositories\EventRepository;
use AdvancedAnalytics\Repositories\SessionRepository;

class EventTracker\n{
    private EventRepository $eventRepository;
    private SessionRepository $sessionRepository;
    private array $config;

    public function __construct(
        EventRepository $eventRepository,
        SessionRepository $sessionRepository,
        array $config = []
    ) {
        $this->eventRepository = $eventRepository;
        $this->sessionRepository = $sessionRepository;
        $this->config = $config;
    }

    /**
     * Track an analytics event
     */
    public function track(string $eventType, array $eventData = [], array $context = []): void
    {
        // Skip tracking if disabled
        if (!$this->isTrackingEnabled($eventType)) {
            return;
        }

        // Get or create session
        $sessionId = $this->getOrCreateSession($context);
        
        // Prepare event data
        $event = [
            'event_type' => $eventType,
            'customer_id' => $context['customer_id'] ?? null,
            'session_id' => $sessionId,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'ip_address' => $this->getClientIpAddress(),
            'country' => $context['country'] ?? $this->detectCountry(),
            'region' => $context['region'] ?? null,
            'city' => $context['city'] ?? null,
            'device_type' => $this->detectDeviceType(),
            'browser' => $this->detectBrowser(),
            'operating_system' => $this->detectOperatingSystem(),
            'referrer_url' => $_SERVER['HTTP_REFERER'] ?? null,
            'page_url' => $context['page_url'] ?? $this->getCurrentUrl(),
            'utm_source' => $context['utm_source'] ?? $request->input('utm_source'] ?? null,
            'utm_medium' => $context['utm_medium'] ?? $request->input('utm_medium'] ?? null,
            'utm_campaign' => $context['utm_campaign'] ?? $request->input('utm_campaign'] ?? null,
            'utm_term' => $context['utm_term'] ?? $request->input('utm_term'] ?? null,
            'utm_content' => $context['utm_content'] ?? $request->input('utm_content'] ?? null,
            'event_data' => json_encode($this->sanitizeEventData($eventData)),
            'event_value' => $eventData['value'] ?? $eventData['amount'] ?? null,
            'currency' => $eventData['currency'] ?? $this->getDefaultCurrency(),
            'event_timestamp' => date('Y-m-d H:i:s')
        ];

        // Store the event
        $this->eventRepository->create($event);

        // Update session statistics
        $this->updateSessionStats($sessionId, $eventType);

        // Process real-time events if enabled
        if ($this->config['realtime_processing'] ?? false) {
            $this->processRealtimeEvent($eventType, $event);
        }
    }

    /**
     * Track page view
     */
    public function trackPageView(string $url, array $context = []): void
    {
        $this->track('page_viewed', [
            'url' => $url,
            'title' => $context['title'] ?? '',
            'category' => $context['category'] ?? null
        ], $context);
    }

    /**
     * Track custom event
     */
    public function trackCustomEvent(string $eventName, array $properties = [], array $context = []): void
    {
        $this->track("custom_{$eventName}", $properties, $context);
    }

    /**
     * Track e-commerce event
     */
    public function trackEcommerceEvent(string $action, array $data, array $context = []): void
    {
        $eventType = "ecommerce_{$action}";
        $this->track($eventType, $data, $context);
    }

    /**
     * Track conversion event
     */
    public function trackConversion(string $goalName, float $value = 0, array $context = []): void
    {
        $this->track('conversion', [
            'goal_name' => $goalName,
            'goal_value' => $value
        ], $context);
    }

    /**
     * Track user interaction
     */
    public function trackInteraction(string $element, string $action, array $context = []): void
    {
        $this->track('user_interaction', [
            'element' => $element,
            'action' => $action,
            'element_id' => $context['element_id'] ?? null,
            'element_class' => $context['element_class'] ?? null,
            'element_text' => $context['element_text'] ?? null
        ], $context);
    }

    /**
     * Track error event
     */
    public function trackError(string $errorType, string $message, array $context = []): void
    {
        $this->track('error', [
            'error_type' => $errorType,
            'error_message' => $message,
            'stack_trace' => $context['stack_trace'] ?? null,
            'file' => $context['file'] ?? null,
            'line' => $context['line'] ?? null
        ], $context);
    }

    /**
     * Track performance metric
     */
    public function trackPerformance(string $metric, float $value, array $context = []): void
    {
        $this->track('performance', [
            'metric_name' => $metric,
            'metric_value' => $value,
            'measurement_unit' => $context['unit'] ?? 'ms'
        ], $context);
    }

    /**
     * Start session tracking
     */
    public function startSession(?int $customerId = null, array $context = []): string
    {
        $sessionId = $this->generateSessionId();
        
        $sessionData = [
            'session_id' => $sessionId,
            'customer_id' => $customerId,
            'session_start' => date('Y-m-d H:i:s'),
            'page_views' => 0,
            'events_count' => 0,
            'landing_page' => $context['landing_page'] ?? $this->getCurrentUrl(),
            'traffic_source' => $this->detectTrafficSource(),
            'campaign' => $request->input('utm_campaign'] ?? null,
            'device_type' => $this->detectDeviceType(),
            'browser' => $this->detectBrowser(),
            'operating_system' => $this->detectOperatingSystem(),
            'country' => $context['country'] ?? $this->detectCountry(),
            'region' => $context['region'] ?? null,
            'city' => $context['city'] ?? null,
            'session_data' => json_encode($context)
        ];

        $this->sessionRepository->create($sessionData);
        
        // Store session ID in session/cookie
        $this->storeSessionId($sessionId);
        
        return $sessionId;
    }

    /**
     * End session tracking
     */
    public function endSession(string $sessionId): void
    {
        $session = $this->sessionRepository->findBySessionId($sessionId);
        if (!$session) {
            return;
        }

        $endTime = date('Y-m-d H:i:s');
        $duration = strtotime($endTime) - strtotime($session['session_start']);
        
        $updateData = [
            'session_end' => $endTime,
            'duration_seconds' => $duration,
            'exit_page' => $this->getCurrentUrl(),
            'is_bounce' => $session['page_views'] <= 1
        ];

        $this->sessionRepository->update($session['id'], $updateData);
    }

    /**
     * Get or create session
     */
    private function getOrCreateSession(array $context = []): string
    {
        $sessionId = $this->getStoredSessionId();
        
        if ($sessionId && $this->isSessionValid($sessionId)) {
            return $sessionId;
        }
        
        return $this->startSession($context['customer_id'] ?? null, $context);
    }

    /**
     * Check if tracking is enabled for event type
     */
    private function isTrackingEnabled(string $eventType): bool
    {
        $settings = $this->config;
        
        // Global tracking check
        if (!($settings['enabled'] ?? true)) {
            return false;
        }
        
        // Event type specific checks
        $eventTypeConfig = [
            'page_viewed' => $settings['track_page_views'] ?? true,
            'user_interaction' => $settings['track_user_interactions'] ?? true,
        ];
        
        if (str_starts_with($eventType, 'ecommerce_') || str_starts_with($eventType, 'order_')) {
            return $settings['track_ecommerce_events'] ?? true;
        }
        
        return $eventTypeConfig[$eventType] ?? true;
    }

    /**
     * Get client IP address
     */
    private function getClientIpAddress(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Detect device type
     */
    private function detectDeviceType(): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (preg_match('/tablet|ipad/i', $userAgent)) {
            return 'tablet';
        } elseif (preg_match('/mobile|android|iphone/i', $userAgent)) {
            return 'mobile';
        } else {
            return 'desktop';
        }
    }

    /**
     * Detect browser
     */
    private function detectBrowser(): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (strpos($userAgent, 'Chrome') !== false) {
            return 'Chrome';
        } elseif (strpos($userAgent, 'Firefox') !== false) {
            return 'Firefox';
        } elseif (strpos($userAgent, 'Safari') !== false) {
            return 'Safari';
        } elseif (strpos($userAgent, 'Edge') !== false) {
            return 'Edge';
        } elseif (strpos($userAgent, 'Opera') !== false) {
            return 'Opera';
        } else {
            return 'Unknown';
        }
    }

    /**
     * Detect operating system
     */
    private function detectOperatingSystem(): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (strpos($userAgent, 'Windows') !== false) {
            return 'Windows';
        } elseif (strpos($userAgent, 'Mac') !== false) {
            return 'macOS';
        } elseif (strpos($userAgent, 'Linux') !== false) {
            return 'Linux';
        } elseif (strpos($userAgent, 'Android') !== false) {
            return 'Android';
        } elseif (strpos($userAgent, 'iOS') !== false) {
            return 'iOS';
        } else {
            return 'Unknown';
        }
    }

    /**
     * Detect country from IP
     */
    private function detectCountry(): ?string
    {
        // In a real implementation, this would use a GeoIP service
        // For now, return null
        return null;
    }

    /**
     * Detect traffic source
     */
    private function detectTrafficSource(): string
    {
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        
        if (empty($referrer)) {
            return 'direct';
        }
        
        $domain = parse_url($referrer, PHP_URL_HOST);
        
        if (strpos($domain, 'google') !== false) {
            return 'google';
        } elseif (strpos($domain, 'facebook') !== false) {
            return 'facebook';
        } elseif (strpos($domain, 'twitter') !== false) {
            return 'twitter';
        } elseif (strpos($domain, 'linkedin') !== false) {
            return 'linkedin';
        } else {
            return 'referral';
        }
    }

    /**
     * Get current URL
     */
    private function getCurrentUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        return "{$protocol}://{$host}{$uri}";
    }

    /**
     * Get default currency
     */
    private function getDefaultCurrency(): string
    {
        return $this->config['default_currency'] ?? 'USD';
    }

    /**
     * Sanitize event data
     */
    private function sanitizeEventData(array $data): array
    {
        // Remove sensitive information
        $sensitiveKeys = ['password', 'credit_card', 'ssn', 'api_key', 'token'];
        
        foreach ($sensitiveKeys as $key) {
            unset($data[$key]);
        }
        
        // Limit data size
        $maxDataSize = $this->config['max_event_data_size'] ?? 1024;
        $jsonData = json_encode($data);
        
        if (strlen($jsonData) > $maxDataSize) {
            $data = ['_truncated' => true, '_original_size' => strlen($jsonData)];
        }
        
        return $data;
    }

    /**
     * Update session statistics
     */
    private function updateSessionStats(string $sessionId, string $eventType): void
    {
        $updates = ['events_count' => 'events_count + 1'];
        
        if ($eventType === 'page_viewed') {
            $updates['page_views'] = 'page_views + 1';
        }
        
        $this->sessionRepository->incrementCounters($sessionId, $updates);
    }

    /**
     * Process real-time event
     */
    private function processRealtimeEvent(string $eventType, array $event): void
    {
        // This would broadcast real-time events via WebSocket or similar
        // For now, just log high-priority events
        $highPriorityEvents = ['order_completed', 'payment_failed', 'error'];
        
        if (in_array($eventType, $highPriorityEvents)) {
            error_log("Real-time event: {$eventType} - " . json_encode($event));
        }
    }

    /**
     * Generate session ID
     */
    private function generateSessionId(): string
    {
        return 'sess_' . bin2hex(random_bytes(16));
    }

    /**
     * Store session ID
     */
    private function storeSessionId(string $sessionId): void
    {
        // Store in session
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['analytics_session_id'] = $sessionId;
        }
        
        // Store in cookie
        $expiry = time() + ($this->config['session_timeout'] ?? 1800); // 30 minutes default
        setcookie('analytics_session_id', $sessionId, $expiry, '/', '', false, true);
    }

    /**
     * Get stored session ID
     */
    private function getStoredSessionId(): ?string
    {
        // Try session first
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['analytics_session_id'])) {
            return $_SESSION['analytics_session_id'];
        }
        
        // Try cookie
        return $_COOKIE['analytics_session_id'] ?? null;
    }

    /**
     * Check if session is valid
     */
    private function isSessionValid(string $sessionId): bool
    {
        $session = $this->sessionRepository->findBySessionId($sessionId);
        
        if (!$session) {
            return false;
        }
        
        $timeout = $this->config['session_timeout'] ?? 1800;
        $lastActivity = strtotime($session['updated_at']);
        
        return (time() - $lastActivity) < $timeout;
    }
}