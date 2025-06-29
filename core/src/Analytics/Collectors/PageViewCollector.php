<?php

declare(strict_types=1);

namespace Shopologic\Core\Analytics\Collectors;

use Shopologic\Core\Analytics\DataCollectorInterface;
use Shopologic\Core\Database\DB;

/**
 * Collects page view analytics data
 */
class PageViewCollector implements DataCollectorInterface
{
    private DB $db;
    private array $pageMetrics = [];

    public function __construct(DB $db)
    {
        $this->db = $db;
    }

    public function collect(array $event): void
    {
        if ($event['event'] !== 'page_view') {
            return;
        }
        
        $properties = $event['properties'];
        $page = $properties['page'] ?? '/';
        
        // Increment page view counter
        if (!isset($this->pageMetrics[$page])) {
            $this->pageMetrics[$page] = [
                'views' => 0,
                'unique_views' => [],
                'total_time' => 0,
                'bounce_count' => 0,
                'exit_count' => 0,
                'entrance_count' => 0
            ];
        }
        
        $this->pageMetrics[$page]['views']++;
        
        // Track unique views
        if (isset($event['user_id'])) {
            $this->pageMetrics[$page]['unique_views'][$event['user_id']] = true;
        }
        
        // Track time on page
        if (isset($properties['time_on_page'])) {
            $this->pageMetrics[$page]['total_time'] += $properties['time_on_page'];
        }
        
        // Track entrance
        if (isset($properties['is_entrance']) && $properties['is_entrance']) {
            $this->pageMetrics[$page]['entrance_count']++;
        }
        
        // Track exit
        if (isset($properties['is_exit']) && $properties['is_exit']) {
            $this->pageMetrics[$page]['exit_count']++;
        }
        
        // Track bounce
        if (isset($properties['is_bounce']) && $properties['is_bounce']) {
            $this->pageMetrics[$page]['bounce_count']++;
        }
        
        // Store page metrics in database
        $this->storePageMetrics($page, $event);
    }

    public function getName(): string
    {
        return 'page_view';
    }

    public function getMetrics(): array
    {
        $metrics = [];
        
        foreach ($this->pageMetrics as $page => $data) {
            $uniqueViews = count($data['unique_views']);
            $avgTimeOnPage = $data['views'] > 0 ? $data['total_time'] / $data['views'] : 0;
            $bounceRate = $data['entrance_count'] > 0 
                ? ($data['bounce_count'] / $data['entrance_count']) * 100 
                : 0;
            $exitRate = $data['views'] > 0 
                ? ($data['exit_count'] / $data['views']) * 100 
                : 0;
            
            $metrics[] = [
                'page' => $page,
                'pageviews' => $data['views'],
                'unique_pageviews' => $uniqueViews,
                'avg_time_on_page' => $avgTimeOnPage,
                'entrance_count' => $data['entrance_count'],
                'bounce_rate' => $bounceRate,
                'exit_rate' => $exitRate
            ];
        }
        
        // Sort by pageviews
        usort($metrics, function ($a, $b) {
            return $b['pageviews'] - $a['pageviews'];
        });
        
        return $metrics;
    }

    /**
     * Get page performance metrics
     */
    public function getPagePerformance(string $page, \DateTime $startDate, \DateTime $endDate): array
    {
        $metrics = $this->db->table('page_metrics')
            ->where('page', $page)
            ->whereBetween('date', [
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            ])
            ->selectRaw('
                SUM(pageviews) as total_pageviews,
                SUM(unique_pageviews) as total_unique_pageviews,
                AVG(avg_time_on_page) as avg_time_on_page,
                AVG(bounce_rate) as avg_bounce_rate,
                AVG(exit_rate) as avg_exit_rate
            ')
            ->first();
        
        // Get trend data
        $trend = $this->db->table('page_metrics')
            ->select('date', 'pageviews', 'unique_pageviews')
            ->where('page', $page)
            ->whereBetween('date', [
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            ])
            ->orderBy('date')
            ->get();
        
        return [
            'summary' => $metrics,
            'trend' => $trend->toArray()
        ];
    }

    /**
     * Get content groups performance
     */
    public function getContentGroups(\DateTime $startDate, \DateTime $endDate): array
    {
        $pages = $this->db->table('page_metrics')
            ->whereBetween('date', [
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            ])
            ->selectRaw('
                page,
                SUM(pageviews) as pageviews,
                SUM(unique_pageviews) as unique_pageviews
            ')
            ->groupBy('page')
            ->get();
        
        $groups = [];
        
        foreach ($pages as $page) {
            $group = $this->determineContentGroup($page->page);
            
            if (!isset($groups[$group])) {
                $groups[$group] = [
                    'pageviews' => 0,
                    'unique_pageviews' => 0,
                    'pages' => 0
                ];
            }
            
            $groups[$group]['pageviews'] += $page->pageviews;
            $groups[$group]['unique_pageviews'] += $page->unique_pageviews;
            $groups[$group]['pages']++;
        }
        
        return $groups;
    }

    // Private methods

    private function storePageMetrics(string $page, array $event): void
    {
        $date = date('Y-m-d', strtotime($event['timestamp']));
        $hour = (int)date('H', strtotime($event['timestamp']));
        
        // Update or insert page metrics
        $this->db->table('page_metrics')->updateOrInsert(
            [
                'page' => $page,
                'date' => $date,
                'hour' => $hour
            ],
            [
                'pageviews' => $this->db->raw('pageviews + 1'),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        );
    }

    private function determineContentGroup(string $page): string
    {
        // Determine content group based on URL patterns
        if (strpos($page, '/products/') !== false) {
            return 'Products';
        } elseif (strpos($page, '/category/') !== false) {
            return 'Categories';
        } elseif (strpos($page, '/blog/') !== false) {
            return 'Blog';
        } elseif (strpos($page, '/checkout') !== false) {
            return 'Checkout';
        } elseif (strpos($page, '/account') !== false) {
            return 'Account';
        } elseif ($page === '/') {
            return 'Homepage';
        } else {
            return 'Other';
        }
    }
}