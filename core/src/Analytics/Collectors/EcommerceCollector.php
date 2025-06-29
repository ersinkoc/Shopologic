<?php

declare(strict_types=1);

namespace Shopologic\Core\Analytics\Collectors;

use Shopologic\Core\Analytics\DataCollectorInterface;
use Shopologic\Core\Database\DB;

/**
 * Collects e-commerce analytics data
 */
class EcommerceCollector implements DataCollectorInterface
{
    private DB $db;
    private array $metrics = [
        'transactions' => 0,
        'revenue' => 0,
        'products_sold' => 0,
        'average_order_value' => 0,
        'cart_additions' => 0,
        'cart_removals' => 0,
        'checkouts' => 0
    ];

    public function __construct(DB $db)
    {
        $this->db = $db;
    }

    public function collect(array $event): void
    {
        switch ($event['event']) {
            case 'purchase':
                $this->collectPurchase($event);
                break;
                
            case 'add_to_cart':
                $this->collectAddToCart($event);
                break;
                
            case 'remove_from_cart':
                $this->collectRemoveFromCart($event);
                break;
                
            case 'begin_checkout':
                $this->collectBeginCheckout($event);
                break;
                
            case 'view_item':
                $this->collectProductView($event);
                break;
                
            case 'view_item_list':
                $this->collectProductListView($event);
                break;
        }
    }

    public function getName(): string
    {
        return 'ecommerce';
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * Get product performance metrics
     */
    public function getProductPerformance(\DateTime $startDate, \DateTime $endDate, int $limit = 50): array
    {
        return $this->db->table('product_analytics')
            ->selectRaw('
                product_id,
                product_name,
                product_sku,
                SUM(views) as views,
                SUM(add_to_carts) as add_to_carts,
                SUM(purchases) as purchases,
                SUM(revenue) as revenue,
                SUM(quantity_sold) as quantity_sold,
                AVG(CASE WHEN views > 0 THEN (add_to_carts * 100.0 / views) ELSE 0 END) as cart_rate,
                AVG(CASE WHEN add_to_carts > 0 THEN (purchases * 100.0 / add_to_carts) ELSE 0 END) as purchase_rate
            ')
            ->whereBetween('date', [
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            ])
            ->groupBy('product_id', 'product_name', 'product_sku')
            ->orderBy('revenue', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get shopping behavior funnel
     */
    public function getShoppingBehavior(\DateTime $startDate, \DateTime $endDate): array
    {
        $sessions = $this->db->table('analytics_sessions')
            ->whereBetween('started_at', [
                $startDate->format('Y-m-d H:i:s'),
                $endDate->format('Y-m-d H:i:s')
            ])
            ->count();
        
        $productViews = $this->db->table('analytics_events')
            ->where('event', 'view_item')
            ->whereBetween('timestamp', [
                $startDate->format('Y-m-d H:i:s'),
                $endDate->format('Y-m-d H:i:s')
            ])
            ->distinct('session_id')
            ->count('session_id');
        
        $addToCarts = $this->db->table('analytics_events')
            ->where('event', 'add_to_cart')
            ->whereBetween('timestamp', [
                $startDate->format('Y-m-d H:i:s'),
                $endDate->format('Y-m-d H:i:s')
            ])
            ->distinct('session_id')
            ->count('session_id');
        
        $checkouts = $this->db->table('analytics_events')
            ->where('event', 'begin_checkout')
            ->whereBetween('timestamp', [
                $startDate->format('Y-m-d H:i:s'),
                $endDate->format('Y-m-d H:i:s')
            ])
            ->distinct('session_id')
            ->count('session_id');
        
        $purchases = $this->db->table('analytics_events')
            ->where('event', 'purchase')
            ->whereBetween('timestamp', [
                $startDate->format('Y-m-d H:i:s'),
                $endDate->format('Y-m-d H:i:s')
            ])
            ->distinct('session_id')
            ->count('session_id');
        
        return [
            [
                'step' => 'All Sessions',
                'sessions' => $sessions,
                'percentage' => 100
            ],
            [
                'step' => 'Sessions with Product Views',
                'sessions' => $productViews,
                'percentage' => $sessions > 0 ? ($productViews / $sessions) * 100 : 0,
                'abandonment_rate' => $sessions > 0 ? (($sessions - $productViews) / $sessions) * 100 : 0
            ],
            [
                'step' => 'Sessions with Add to Cart',
                'sessions' => $addToCarts,
                'percentage' => $sessions > 0 ? ($addToCarts / $sessions) * 100 : 0,
                'abandonment_rate' => $productViews > 0 ? (($productViews - $addToCarts) / $productViews) * 100 : 0
            ],
            [
                'step' => 'Sessions with Checkout',
                'sessions' => $checkouts,
                'percentage' => $sessions > 0 ? ($checkouts / $sessions) * 100 : 0,
                'abandonment_rate' => $addToCarts > 0 ? (($addToCarts - $checkouts) / $addToCarts) * 100 : 0
            ],
            [
                'step' => 'Sessions with Transactions',
                'sessions' => $purchases,
                'percentage' => $sessions > 0 ? ($purchases / $sessions) * 100 : 0,
                'abandonment_rate' => $checkouts > 0 ? (($checkouts - $purchases) / $checkouts) * 100 : 0
            ]
        ];
    }

    /**
     * Get revenue metrics
     */
    public function getRevenueMetrics(\DateTime $startDate, \DateTime $endDate): array
    {
        $metrics = $this->db->table('ecommerce_analytics')
            ->selectRaw('
                SUM(revenue) as total_revenue,
                COUNT(DISTINCT transaction_id) as transactions,
                SUM(quantity) as products_sold,
                AVG(revenue) as avg_order_value
            ')
            ->whereBetween('date', [
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            ])
            ->first();
        
        // Get revenue by source
        $revenueBySource = $this->db->table('ecommerce_analytics')
            ->selectRaw('
                source,
                SUM(revenue) as revenue,
                COUNT(DISTINCT transaction_id) as transactions
            ')
            ->whereBetween('date', [
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            ])
            ->groupBy('source')
            ->orderBy('revenue', 'desc')
            ->get();
        
        // Get revenue trend
        $revenueTrend = $this->db->table('ecommerce_analytics')
            ->selectRaw('
                date,
                SUM(revenue) as revenue,
                COUNT(DISTINCT transaction_id) as transactions
            ')
            ->whereBetween('date', [
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            ])
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        return [
            'summary' => $metrics,
            'by_source' => $revenueBySource->toArray(),
            'trend' => $revenueTrend->toArray()
        ];
    }

    // Private collection methods

    private function collectPurchase(array $event): void
    {
        $properties = $event['properties'];
        
        $this->metrics['transactions']++;
        $this->metrics['revenue'] += $properties['value'] ?? 0;
        
        // Calculate products sold
        if (isset($properties['items'])) {
            foreach ($properties['items'] as $item) {
                $this->metrics['products_sold'] += $item['quantity'] ?? 1;
                $this->storeProductPurchase($item, $event);
            }
        }
        
        // Update average order value
        if ($this->metrics['transactions'] > 0) {
            $this->metrics['average_order_value'] = $this->metrics['revenue'] / $this->metrics['transactions'];
        }
        
        // Store transaction
        $this->storeTransaction($event);
    }

    private function collectAddToCart(array $event): void
    {
        $this->metrics['cart_additions']++;
        
        $properties = $event['properties'];
        if (isset($properties['items'])) {
            foreach ($properties['items'] as $item) {
                $this->storeProductAddToCart($item, $event);
            }
        }
    }

    private function collectRemoveFromCart(array $event): void
    {
        $this->metrics['cart_removals']++;
    }

    private function collectBeginCheckout(array $event): void
    {
        $this->metrics['checkouts']++;
    }

    private function collectProductView(array $event): void
    {
        $properties = $event['properties'];
        if (isset($properties['items'])) {
            foreach ($properties['items'] as $item) {
                $this->storeProductView($item, $event);
            }
        }
    }

    private function collectProductListView(array $event): void
    {
        $properties = $event['properties'];
        if (isset($properties['items'])) {
            foreach ($properties['items'] as $item) {
                $this->storeProductImpression($item, $event);
            }
        }
    }

    // Storage methods

    private function storeTransaction(array $event): void
    {
        $properties = $event['properties'];
        $date = date('Y-m-d', strtotime($event['timestamp']));
        
        $this->db->table('ecommerce_analytics')->insert([
            'transaction_id' => $properties['transaction_id'] ?? uniqid(),
            'user_id' => $event['user_id'] ?? null,
            'session_id' => $event['session_id'],
            'date' => $date,
            'revenue' => $properties['value'] ?? 0,
            'tax' => $properties['tax'] ?? 0,
            'shipping' => $properties['shipping'] ?? 0,
            'quantity' => count($properties['items'] ?? []),
            'currency' => $properties['currency'] ?? 'USD',
            'source' => $properties['source'] ?? 'direct',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function storeProductView(array $item, array $event): void
    {
        $date = date('Y-m-d', strtotime($event['timestamp']));
        
        $this->db->table('product_analytics')->updateOrInsert(
            [
                'product_id' => $item['item_id'],
                'date' => $date
            ],
            [
                'product_name' => $item['item_name'] ?? '',
                'product_sku' => $item['item_sku'] ?? '',
                'views' => $this->db->raw('views + 1'),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        );
    }

    private function storeProductAddToCart(array $item, array $event): void
    {
        $date = date('Y-m-d', strtotime($event['timestamp']));
        
        $this->db->table('product_analytics')->updateOrInsert(
            [
                'product_id' => $item['item_id'],
                'date' => $date
            ],
            [
                'product_name' => $item['item_name'] ?? '',
                'product_sku' => $item['item_sku'] ?? '',
                'add_to_carts' => $this->db->raw('add_to_carts + 1'),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        );
    }

    private function storeProductPurchase(array $item, array $event): void
    {
        $date = date('Y-m-d', strtotime($event['timestamp']));
        $quantity = $item['quantity'] ?? 1;
        $price = $item['price'] ?? 0;
        $revenue = $quantity * $price;
        
        $this->db->table('product_analytics')->updateOrInsert(
            [
                'product_id' => $item['item_id'],
                'date' => $date
            ],
            [
                'product_name' => $item['item_name'] ?? '',
                'product_sku' => $item['item_sku'] ?? '',
                'purchases' => $this->db->raw('purchases + 1'),
                'quantity_sold' => $this->db->raw("quantity_sold + {$quantity}"),
                'revenue' => $this->db->raw("revenue + {$revenue}"),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        );
    }

    private function storeProductImpression(array $item, array $event): void
    {
        $date = date('Y-m-d', strtotime($event['timestamp']));
        
        $this->db->table('product_analytics')->updateOrInsert(
            [
                'product_id' => $item['item_id'],
                'date' => $date
            ],
            [
                'product_name' => $item['item_name'] ?? '',
                'product_sku' => $item['item_sku'] ?? '',
                'impressions' => $this->db->raw('impressions + 1'),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        );
    }
}