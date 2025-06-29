<?php

declare(strict_types=1);

namespace Shopologic\Core\GraphQL\Resolvers;

use Shopologic\Core\Database\DB;
use Shopologic\Core\Cache\CacheInterface;
use Shopologic\Core\Search\SearchEngine;

/**
 * Product GraphQL resolver
 */
class ProductResolver
{
    private DB $db;
    private CacheInterface $cache;
    private SearchEngine $search;
    
    public function __construct(DB $db, CacheInterface $cache, SearchEngine $search)
    {
        $this->db = $db;
        $this->cache = $cache;
        $this->search = $search;
    }
    
    /**
     * Resolve products query with pagination and filtering
     */
    public function resolveProducts(array $args): array
    {
        $first = $args['first'] ?? 20;
        $after = $args['after'] ?? null;
        $filter = $args['filter'] ?? [];
        $sort = $args['sort'] ?? 'CREATED_DESC';
        
        // Build query
        $query = $this->db->table('products')->where('status', 'active');
        
        // Apply filters
        $this->applyFilters($query, $filter);
        
        // Apply sorting
        $this->applySorting($query, $sort);
        
        // Calculate offset from cursor
        $offset = 0;
        if ($after) {
            $offset = (int)base64_decode($after);
        }
        
        // Get total count
        $totalCount = (clone $query)->count();
        
        // Get products
        $products = $query
            ->offset($offset)
            ->limit($first + 1) // Get one extra to check if there's a next page
            ->get();
        
        // Check if there's a next page
        $hasNextPage = $products->count() > $first;
        if ($hasNextPage) {
            $products = $products->slice(0, $first);
        }
        
        // Build edges
        $edges = [];
        foreach ($products as $index => $product) {
            $edges[] = [
                'node' => $product,
                'cursor' => base64_encode((string)($offset + $index))
            ];
        }
        
        // Build page info
        $pageInfo = [
            'hasNextPage' => $hasNextPage,
            'hasPreviousPage' => $offset > 0,
            'startCursor' => !empty($edges) ? $edges[0]['cursor'] : null,
            'endCursor' => !empty($edges) ? $edges[count($edges) - 1]['cursor'] : null
        ];
        
        return [
            'edges' => $edges,
            'pageInfo' => $pageInfo,
            'totalCount' => $totalCount
        ];
    }
    
    /**
     * Resolve single product
     */
    public function resolveProduct(array $args): ?object
    {
        $cacheKey = 'product:' . json_encode($args);
        
        return $this->cache->remember($cacheKey, 300, function () use ($args) {
            $query = $this->db->table('products');
            
            if (isset($args['id'])) {
                return $query->find($args['id']);
            } elseif (isset($args['slug'])) {
                return $query->where('slug', $args['slug'])->first();
            } elseif (isset($args['sku'])) {
                return $query->where('sku', $args['sku'])->first();
            }
            
            return null;
        });
    }
    
    /**
     * Search products
     */
    public function searchProducts(string $query, array $options = []): array
    {
        $results = $this->search->search($query, array_merge([
            'index' => 'product',
            'facets' => [
                'category' => 'terms',
                'price' => [
                    'type' => 'range',
                    'ranges' => [
                        ['to' => 50],
                        ['from' => 50, 'to' => 100],
                        ['from' => 100, 'to' => 200],
                        ['from' => 200]
                    ]
                ],
                'brand' => 'terms',
                'rating' => 'terms'
            ]
        ], $options));
        
        return [
            'products' => array_map(function ($hit) {
                return $this->db->table('products')->find($hit['id']);
            }, $results->getHits()),
            'facets' => $results->getFacets(),
            'totalCount' => $results->getTotal(),
            'suggestions' => $results->getSuggestions()
        ];
    }
    
    /**
     * Get related products
     */
    public function getRelatedProducts($product, int $limit = 4): array
    {
        $cacheKey = "related_products:{$product->id}:{$limit}";
        
        return $this->cache->remember($cacheKey, 3600, function () use ($product, $limit) {
            // Get products from same category
            $relatedByCategory = $this->db->table('products')
                ->where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
                ->where('status', 'active')
                ->inRandomOrder()
                ->limit($limit)
                ->get();
            
            if ($relatedByCategory->count() >= $limit) {
                return $relatedByCategory->toArray();
            }
            
            // If not enough, get more by tags
            $tags = explode(',', $product->tags ?? '');
            if (!empty($tags)) {
                $relatedByTags = $this->db->table('products')
                    ->where('id', '!=', $product->id)
                    ->where('status', 'active')
                    ->where(function ($query) use ($tags) {
                        foreach ($tags as $tag) {
                            $query->orWhere('tags', 'LIKE', '%' . trim($tag) . '%');
                        }
                    })
                    ->whereNotIn('id', $relatedByCategory->pluck('id'))
                    ->inRandomOrder()
                    ->limit($limit - $relatedByCategory->count())
                    ->get();
                
                return array_merge(
                    $relatedByCategory->toArray(),
                    $relatedByTags->toArray()
                );
            }
            
            return $relatedByCategory->toArray();
        });
    }
    
    /**
     * Get product recommendations
     */
    public function getRecommendations($customer, int $limit = 8): array
    {
        if (!$customer) {
            // For guests, return popular products
            return $this->getPopularProducts($limit);
        }
        
        $cacheKey = "recommendations:{$customer->id}:{$limit}";
        
        return $this->cache->remember($cacheKey, 1800, function () use ($customer, $limit) {
            // Get customer's purchase history
            $purchasedProducts = $this->db->table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.user_id', $customer->id)
                ->where('orders.status', '!=', 'cancelled')
                ->pluck('order_items.product_id')
                ->unique()
                ->toArray();
            
            if (empty($purchasedProducts)) {
                return $this->getPopularProducts($limit);
            }
            
            // Get categories from purchased products
            $categories = $this->db->table('products')
                ->whereIn('id', $purchasedProducts)
                ->pluck('category_id')
                ->unique()
                ->toArray();
            
            // Get recommended products
            return $this->db->table('products')
                ->whereIn('category_id', $categories)
                ->whereNotIn('id', $purchasedProducts)
                ->where('status', 'active')
                ->where('featured', true)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }
    
    /**
     * Get popular products
     */
    public function getPopularProducts(int $limit = 8): array
    {
        $cacheKey = "popular_products:{$limit}";
        
        return $this->cache->remember($cacheKey, 3600, function () use ($limit) {
            return $this->db->table('products')
                ->leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
                ->select('products.*', $this->db->raw('COUNT(order_items.id) as order_count'))
                ->where('products.status', 'active')
                ->groupBy('products.id')
                ->orderBy('order_count', 'desc')
                ->orderBy('products.created_at', 'desc')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }
    
    /**
     * Get trending products
     */
    public function getTrendingProducts(int $days = 7, int $limit = 8): array
    {
        $cacheKey = "trending_products:{$days}:{$limit}";
        
        return $this->cache->remember($cacheKey, 3600, function () use ($days, $limit) {
            $since = date('Y-m-d', strtotime("-{$days} days"));
            
            return $this->db->table('products')
                ->leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
                ->leftJoin('orders', 'order_items.order_id', '=', 'orders.id')
                ->select('products.*', $this->db->raw('COUNT(order_items.id) as recent_orders'))
                ->where('products.status', 'active')
                ->where('orders.created_at', '>=', $since)
                ->groupBy('products.id')
                ->orderBy('recent_orders', 'desc')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }
    
    /**
     * Apply filters to query
     */
    private function applyFilters($query, array $filter): void
    {
        if (isset($filter['categoryId'])) {
            $query->where('category_id', $filter['categoryId']);
        }
        
        if (isset($filter['categorySlug'])) {
            $category = $this->db->table('categories')
                ->where('slug', $filter['categorySlug'])
                ->first();
            
            if ($category) {
                $query->where('category_id', $category->id);
            }
        }
        
        if (isset($filter['minPrice'])) {
            $query->where('price', '>=', $filter['minPrice']);
        }
        
        if (isset($filter['maxPrice'])) {
            $query->where('price', '<=', $filter['maxPrice']);
        }
        
        if (isset($filter['inStock']) && $filter['inStock']) {
            $query->where('stock', '>', 0);
        }
        
        if (isset($filter['featured']) && $filter['featured']) {
            $query->where('featured', true);
        }
        
        if (isset($filter['search'])) {
            $search = '%' . $filter['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', $search)
                    ->orWhere('description', 'LIKE', $search)
                    ->orWhere('sku', 'LIKE', $search);
            });
        }
        
        if (isset($filter['tags']) && is_array($filter['tags'])) {
            foreach ($filter['tags'] as $tag) {
                $query->where('tags', 'LIKE', '%' . $tag . '%');
            }
        }
        
        if (isset($filter['attributes']) && is_array($filter['attributes'])) {
            foreach ($filter['attributes'] as $attribute => $value) {
                $query->whereExists(function ($q) use ($attribute, $value) {
                    $q->select($this->db->raw(1))
                        ->from('product_attributes')
                        ->whereColumn('product_attributes.product_id', 'products.id')
                        ->where('product_attributes.name', $attribute)
                        ->where('product_attributes.value', $value);
                });
            }
        }
    }
    
    /**
     * Apply sorting to query
     */
    private function applySorting($query, string $sort): void
    {
        switch ($sort) {
            case 'NAME_ASC':
                $query->orderBy('name', 'asc');
                break;
            case 'NAME_DESC':
                $query->orderBy('name', 'desc');
                break;
            case 'PRICE_ASC':
                $query->orderBy('price', 'asc');
                break;
            case 'PRICE_DESC':
                $query->orderBy('price', 'desc');
                break;
            case 'CREATED_ASC':
                $query->orderBy('created_at', 'asc');
                break;
            case 'CREATED_DESC':
                $query->orderBy('created_at', 'desc');
                break;
            case 'POPULARITY':
                $query->leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
                    ->select('products.*', $this->db->raw('COUNT(order_items.id) as order_count'))
                    ->groupBy('products.id')
                    ->orderBy('order_count', 'desc');
                break;
            case 'RATING':
                $query->leftJoin('reviews', 'products.id', '=', 'reviews.product_id')
                    ->select('products.*', $this->db->raw('AVG(reviews.rating) as avg_rating'))
                    ->groupBy('products.id')
                    ->orderBy('avg_rating', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }
    }
}