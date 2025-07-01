<?php

declare(strict_types=1);

namespace Shopologic\Core\Search;

use Shopologic\Core\Plugin\HookSystem;

class SearchService
{
    private array $products = [];
    private array $categories = [];
    
    public function __construct()
    {
        $this->initializeData();
    }
    
    private function initializeData(): void
    {
        // Categories
        $this->categories = [
            'electronics' => ['name' => 'Electronics', 'slug' => 'electronics'],
            'clothing' => ['name' => 'Clothing', 'slug' => 'clothing'],
            'books' => ['name' => 'Books', 'slug' => 'books'],
            'home' => ['name' => 'Home & Garden', 'slug' => 'home'],
            'sports' => ['name' => 'Sports & Outdoors', 'slug' => 'sports'],
            'toys' => ['name' => 'Toys & Games', 'slug' => 'toys'],
            'beauty' => ['name' => 'Beauty & Personal Care', 'slug' => 'beauty'],
            'automotive' => ['name' => 'Automotive', 'slug' => 'automotive']
        ];
        
        // Sample products with searchable content
        $this->products = [
            [
                'id' => 1,
                'name' => 'MacBook Pro 16-inch',
                'slug' => 'macbook-pro-16-inch',
                'description' => 'Powerful laptop with M2 chip, 16GB RAM, and stunning Retina display. Perfect for professionals and creative work.',
                'short_description' => 'Powerful laptop with M2 chip and Retina display',
                'price' => 2499.00,
                'sale_price' => 2299.00,
                'category' => 'electronics',
                'brand' => 'Apple',
                'tags' => ['laptop', 'computer', 'apple', 'macbook', 'professional', 'm2', 'retina'],
                'sku' => 'APPLE-MBP-16',
                'stock' => 15,
                'featured' => true,
                'rating' => 4.8,
                'reviews_count' => 127,
                'attributes' => [
                    'Screen Size' => '16 inches',
                    'Processor' => 'Apple M2',
                    'RAM' => '16GB',
                    'Storage' => '512GB SSD',
                    'Color' => 'Space Gray'
                ]
            ],
            [
                'id' => 2,
                'name' => 'iPhone 15 Pro',
                'slug' => 'iphone-15-pro',
                'description' => 'Latest iPhone with titanium design, A17 Pro chip, and advanced camera system. Experience the future of mobile technology.',
                'short_description' => 'Latest iPhone with titanium design and A17 Pro chip',
                'price' => 999.00,
                'sale_price' => null,
                'category' => 'electronics',
                'brand' => 'Apple',
                'tags' => ['smartphone', 'phone', 'apple', 'iphone', 'mobile', 'titanium', 'a17'],
                'sku' => 'APPLE-IP15-PRO',
                'stock' => 32,
                'featured' => true,
                'rating' => 4.9,
                'reviews_count' => 89,
                'attributes' => [
                    'Screen Size' => '6.1 inches',
                    'Processor' => 'A17 Pro',
                    'Storage' => '128GB',
                    'Camera' => '48MP',
                    'Color' => 'Natural Titanium'
                ]
            ],
            [
                'id' => 3,
                'name' => 'Samsung Galaxy S24 Ultra',
                'slug' => 'samsung-galaxy-s24-ultra',
                'description' => 'Premium Android smartphone with S Pen, 200MP camera, and AI-powered features for ultimate productivity.',
                'short_description' => 'Premium Android smartphone with S Pen and 200MP camera',
                'price' => 1199.00,
                'sale_price' => 1099.00,
                'category' => 'electronics',
                'brand' => 'Samsung',
                'tags' => ['smartphone', 'android', 'samsung', 'galaxy', 's-pen', 'camera', 'ai'],
                'sku' => 'SAMSUNG-S24-ULTRA',
                'stock' => 28,
                'featured' => true,
                'rating' => 4.7,
                'reviews_count' => 156,
                'attributes' => [
                    'Screen Size' => '6.8 inches',
                    'Processor' => 'Snapdragon 8 Gen 3',
                    'RAM' => '12GB',
                    'Storage' => '256GB',
                    'Camera' => '200MP'
                ]
            ],
            [
                'id' => 4,
                'name' => 'Nike Air Jordan 1 Retro High',
                'slug' => 'nike-air-jordan-1-retro-high',
                'description' => 'Iconic basketball shoes with classic design, premium leather, and Air cushioning. A timeless sneaker for style and performance.',
                'short_description' => 'Iconic basketball shoes with classic design and Air cushioning',
                'price' => 170.00,
                'sale_price' => 149.00,
                'category' => 'clothing',
                'brand' => 'Nike',
                'tags' => ['shoes', 'sneakers', 'nike', 'jordan', 'basketball', 'retro', 'air'],
                'sku' => 'NIKE-AJ1-RETRO',
                'stock' => 45,
                'featured' => false,
                'rating' => 4.6,
                'reviews_count' => 234,
                'attributes' => [
                    'Brand' => 'Nike',
                    'Style' => 'Basketball',
                    'Material' => 'Leather',
                    'Size Range' => '7-13',
                    'Color' => 'Chicago'
                ]
            ],
            [
                'id' => 5,
                'name' => 'Adidas Ultraboost 22',
                'slug' => 'adidas-ultraboost-22',
                'description' => 'Premium running shoes with Boost midsole technology, Primeknit upper, and superior energy return for all-day comfort.',
                'short_description' => 'Premium running shoes with Boost technology',
                'price' => 180.00,
                'sale_price' => null,
                'category' => 'clothing',
                'brand' => 'Adidas',
                'tags' => ['shoes', 'running', 'adidas', 'ultraboost', 'boost', 'primeknit', 'comfort'],
                'sku' => 'ADIDAS-UB22',
                'stock' => 38,
                'featured' => false,
                'rating' => 4.5,
                'reviews_count' => 178,
                'attributes' => [
                    'Brand' => 'Adidas',
                    'Style' => 'Running',
                    'Technology' => 'Boost',
                    'Upper' => 'Primeknit',
                    'Size Range' => '6-14'
                ]
            ],
            [
                'id' => 6,
                'name' => 'The Psychology of Money',
                'slug' => 'psychology-of-money-book',
                'description' => 'Timeless lessons on wealth, greed, and happiness by Morgan Housel. Learn how psychology affects financial decisions.',
                'short_description' => 'Timeless lessons on wealth and financial psychology',
                'price' => 18.99,
                'sale_price' => 14.99,
                'category' => 'books',
                'brand' => 'Harriman House',
                'tags' => ['book', 'finance', 'psychology', 'money', 'investing', 'wealth', 'economics'],
                'sku' => 'BOOK-POM-001',
                'stock' => 67,
                'featured' => true,
                'rating' => 4.8,
                'reviews_count' => 892,
                'attributes' => [
                    'Author' => 'Morgan Housel',
                    'Pages' => '256',
                    'Publisher' => 'Harriman House',
                    'Language' => 'English',
                    'Format' => 'Paperback'
                ]
            ],
            [
                'id' => 7,
                'name' => 'Dyson V15 Detect Absolute',
                'slug' => 'dyson-v15-detect-absolute',
                'description' => 'Powerful cordless vacuum with laser dust detection, LCD screen, and advanced filtration system for deep cleaning.',
                'short_description' => 'Powerful cordless vacuum with laser dust detection',
                'price' => 749.00,
                'sale_price' => 699.00,
                'category' => 'home',
                'brand' => 'Dyson',
                'tags' => ['vacuum', 'cordless', 'dyson', 'cleaning', 'laser', 'hepa', 'home'],
                'sku' => 'DYSON-V15-ABS',
                'stock' => 23,
                'featured' => false,
                'rating' => 4.7,
                'reviews_count' => 445,
                'attributes' => [
                    'Type' => 'Cordless Stick',
                    'Battery Life' => 'Up to 60 minutes',
                    'Filtration' => 'HEPA',
                    'Weight' => '6.8 lbs',
                    'Bin Capacity' => '0.2 gallons'
                ]
            ],
            [
                'id' => 8,
                'name' => 'YETI Rambler 20oz Tumbler',
                'slug' => 'yeti-rambler-20oz-tumbler',
                'description' => 'Insulated stainless steel tumbler that keeps drinks hot or cold for hours. Perfect for coffee, tea, or cold beverages.',
                'short_description' => 'Insulated stainless steel tumbler for hot and cold drinks',
                'price' => 35.00,
                'sale_price' => null,
                'category' => 'home',
                'brand' => 'YETI',
                'tags' => ['tumbler', 'insulated', 'yeti', 'coffee', 'travel', 'stainless', 'drinkware'],
                'sku' => 'YETI-RAM-20OZ',
                'stock' => 89,
                'featured' => false,
                'rating' => 4.9,
                'reviews_count' => 567,
                'attributes' => [
                    'Capacity' => '20 oz',
                    'Material' => 'Stainless Steel',
                    'Insulation' => 'Double Wall',
                    'Lid Type' => 'MagSlider',
                    'Dishwasher Safe' => 'Yes'
                ]
            ]
        ];
    }
    
    /**
     * Search products with various filters and sorting options
     */
    public function searchProducts(array $params = []): array
    {
        $query = $params['q'] ?? '';
        $category = $params['category'] ?? '';
        $brand = $params['brand'] ?? '';
        $minPrice = isset($params['min_price']) ? (float)$params['min_price'] : null;
        $maxPrice = isset($params['max_price']) ? (float)$params['max_price'] : null;
        $sortBy = $params['sort'] ?? 'relevance';
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $perPage = isset($params['per_page']) ? (int)$params['per_page'] : 12;
        $featuredOnly = isset($params['featured']) ? (bool)$params['featured'] : false;
        $inStock = isset($params['in_stock']) ? (bool)$params['in_stock'] : false;
        
        $results = $this->products;
        
        // Apply text search
        if (!empty($query)) {
            $results = $this->filterByQuery($results, $query);
        }
        
        // Apply category filter
        if (!empty($category)) {
            $results = array_filter($results, function($product) use ($category) {
                return $product['category'] === $category;
            });
        }
        
        // Apply brand filter
        if (!empty($brand)) {
            $results = array_filter($results, function($product) use ($brand) {
                return strtolower($product['brand']) === strtolower($brand);
            });
        }
        
        // Apply price filters
        if ($minPrice !== null || $maxPrice !== null) {
            $results = array_filter($results, function($product) use ($minPrice, $maxPrice) {
                $price = $product['sale_price'] ?? $product['price'];
                if ($minPrice !== null && $price < $minPrice) return false;
                if ($maxPrice !== null && $price > $maxPrice) return false;
                return true;
            });
        }
        
        // Apply featured filter
        if ($featuredOnly) {
            $results = array_filter($results, function($product) {
                return $product['featured'] === true;
            });
        }
        
        // Apply stock filter
        if ($inStock) {
            $results = array_filter($results, function($product) {
                return $product['stock'] > 0;
            });
        }
        
        // Apply sorting
        $results = $this->sortResults($results, $sortBy, $query);
        
        // Apply filters hook for plugins
        $results = HookSystem::applyFilters('search.results.filtered', $results, $params);
        
        // Calculate pagination
        $total = count($results);
        $totalPages = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $paginatedResults = array_slice($results, $offset, $perPage);
        
        // Add additional product data
        $paginatedResults = array_map([$this, 'enrichProductData'], $paginatedResults);
        
        $searchResults = [
            'products' => $paginatedResults,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $total)
            ],
            'filters' => [
                'query' => $query,
                'category' => $category,
                'brand' => $brand,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'sort' => $sortBy,
                'featured' => $featuredOnly,
                'in_stock' => $inStock
            ],
            'aggregations' => $this->getAggregations($this->products, $params)
        ];
        
        return HookSystem::applyFilters('search.results.final', $searchResults, $params);
    }
    
    /**
     * Get search suggestions for autocomplete
     */
    public function getSearchSuggestions(string $query, int $limit = 10): array
    {
        if (strlen($query) < 2) {
            return [];
        }
        
        $suggestions = [];
        $query = strtolower(trim($query));
        
        // Search in product names
        foreach ($this->products as $product) {
            if (stripos($product['name'], $query) !== false) {
                $suggestions[] = [
                    'type' => 'product',
                    'text' => $product['name'],
                    'url' => '/product/' . $product['slug'],
                    'price' => $product['sale_price'] ?? $product['price'],
                    'category' => $this->categories[$product['category']]['name'] ?? $product['category']
                ];
            }
        }
        
        // Search in brands
        $brands = array_unique(array_column($this->products, 'brand'));
        foreach ($brands as $brand) {
            if (stripos($brand, $query) !== false) {
                $suggestions[] = [
                    'type' => 'brand',
                    'text' => $brand,
                    'url' => '/search?brand=' . urlencode($brand),
                    'label' => 'Brand'
                ];
            }
        }
        
        // Search in categories
        foreach ($this->categories as $slug => $category) {
            if (stripos($category['name'], $query) !== false) {
                $suggestions[] = [
                    'type' => 'category',
                    'text' => $category['name'],
                    'url' => '/search?category=' . $slug,
                    'label' => 'Category'
                ];
            }
        }
        
        // Search in tags
        foreach ($this->products as $product) {
            foreach ($product['tags'] as $tag) {
                if (stripos($tag, $query) !== false) {
                    $suggestions[] = [
                        'type' => 'tag',
                        'text' => ucfirst($tag),
                        'url' => '/search?q=' . urlencode($tag),
                        'label' => 'Related'
                    ];
                }
            }
        }
        
        // Remove duplicates and limit results
        $suggestions = array_unique($suggestions, SORT_REGULAR);
        $suggestions = array_slice($suggestions, 0, $limit);
        
        return HookSystem::applyFilters('search.suggestions', $suggestions, $query, $limit);
    }
    
    /**
     * Get available filters based on current search results
     */
    public function getAvailableFilters(array $params = []): array
    {
        $results = $this->searchProducts($params);
        $products = $this->products; // Use all products for filter options
        
        $filters = [
            'categories' => [],
            'brands' => [],
            'price_ranges' => [
                ['label' => 'Under $25', 'min' => 0, 'max' => 25],
                ['label' => '$25 - $50', 'min' => 25, 'max' => 50],
                ['label' => '$50 - $100', 'min' => 50, 'max' => 100],
                ['label' => '$100 - $500', 'min' => 100, 'max' => 500],
                ['label' => '$500 - $1000', 'min' => 500, 'max' => 1000],
                ['label' => 'Over $1000', 'min' => 1000, 'max' => null]
            ],
            'ratings' => [
                ['label' => '4 stars & up', 'min' => 4],
                ['label' => '3 stars & up', 'min' => 3],
                ['label' => '2 stars & up', 'min' => 2],
                ['label' => '1 star & up', 'min' => 1]
            ]
        ];
        
        // Get unique categories
        $categoryMap = [];
        foreach ($products as $product) {
            $categorySlug = $product['category'];
            if (!isset($categoryMap[$categorySlug])) {
                $categoryMap[$categorySlug] = [
                    'slug' => $categorySlug,
                    'name' => $this->categories[$categorySlug]['name'] ?? ucfirst($categorySlug),
                    'count' => 0
                ];
            }
            $categoryMap[$categorySlug]['count']++;
        }
        $filters['categories'] = array_values($categoryMap);
        
        // Get unique brands
        $brandMap = [];
        foreach ($products as $product) {
            $brand = $product['brand'];
            if (!isset($brandMap[$brand])) {
                $brandMap[$brand] = [
                    'name' => $brand,
                    'count' => 0
                ];
            }
            $brandMap[$brand]['count']++;
        }
        $filters['brands'] = array_values($brandMap);
        
        return HookSystem::applyFilters('search.available_filters', $filters, $params);
    }
    
    private function filterByQuery(array $products, string $query): array
    {
        $query = strtolower(trim($query));
        $words = explode(' ', $query);
        
        return array_filter($products, function($product) use ($query, $words) {
            $searchText = strtolower(implode(' ', [
                $product['name'],
                $product['description'],
                $product['short_description'],
                $product['brand'],
                $product['sku'],
                implode(' ', $product['tags']),
                implode(' ', array_values($product['attributes'] ?? []))
            ]));
            
            // Check for exact phrase match (higher relevance)
            if (stripos($searchText, $query) !== false) {
                return true;
            }
            
            // Check for all words present
            foreach ($words as $word) {
                if (strlen($word) > 2 && stripos($searchText, $word) === false) {
                    return false;
                }
            }
            
            return true;
        });
    }
    
    private function sortResults(array $results, string $sortBy, string $query = ''): array
    {
        switch ($sortBy) {
            case 'price_low':
                usort($results, function($a, $b) {
                    $priceA = $a['sale_price'] ?? $a['price'];
                    $priceB = $b['sale_price'] ?? $b['price'];
                    return $priceA <=> $priceB;
                });
                break;
                
            case 'price_high':
                usort($results, function($a, $b) {
                    $priceA = $a['sale_price'] ?? $a['price'];
                    $priceB = $b['sale_price'] ?? $b['price'];
                    return $priceB <=> $priceA;
                });
                break;
                
            case 'rating':
                usort($results, function($a, $b) {
                    return $b['rating'] <=> $a['rating'];
                });
                break;
                
            case 'reviews':
                usort($results, function($a, $b) {
                    return $b['reviews_count'] <=> $a['reviews_count'];
                });
                break;
                
            case 'name_asc':
                usort($results, function($a, $b) {
                    return strcmp($a['name'], $b['name']);
                });
                break;
                
            case 'name_desc':
                usort($results, function($a, $b) {
                    return strcmp($b['name'], $a['name']);
                });
                break;
                
            case 'newest':
                usort($results, function($a, $b) {
                    return $b['id'] <=> $a['id']; // Assuming higher ID = newer
                });
                break;
                
            case 'relevance':
            default:
                if (!empty($query)) {
                    $results = $this->sortByRelevance($results, $query);
                } else {
                    // Default sort: featured first, then by rating
                    usort($results, function($a, $b) {
                        if ($a['featured'] !== $b['featured']) {
                            return $b['featured'] <=> $a['featured'];
                        }
                        return $b['rating'] <=> $a['rating'];
                    });
                }
                break;
        }
        
        return $results;
    }
    
    private function sortByRelevance(array $results, string $query): array
    {
        $query = strtolower($query);
        
        // Calculate relevance scores
        foreach ($results as &$product) {
            $score = 0;
            
            // Name matches (highest priority)
            if (stripos($product['name'], $query) !== false) {
                $score += 100;
                if (stripos($product['name'], $query) === 0) {
                    $score += 50; // Starts with query
                }
            }
            
            // Brand matches
            if (stripos($product['brand'], $query) !== false) {
                $score += 80;
            }
            
            // Tag matches
            foreach ($product['tags'] as $tag) {
                if (stripos($tag, $query) !== false) {
                    $score += 30;
                }
            }
            
            // Description matches
            if (stripos($product['description'], $query) !== false) {
                $score += 20;
            }
            
            // SKU matches
            if (stripos($product['sku'], $query) !== false) {
                $score += 40;
            }
            
            // Boost for featured products
            if ($product['featured']) {
                $score += 10;
            }
            
            // Boost for higher ratings
            $score += $product['rating'] * 5;
            
            $product['_relevance_score'] = $score;
        }
        
        // Sort by relevance score
        usort($results, function($a, $b) {
            return $b['_relevance_score'] <=> $a['_relevance_score'];
        });
        
        // Remove relevance scores
        foreach ($results as &$product) {
            unset($product['_relevance_score']);
        }
        
        return $results;
    }
    
    private function enrichProductData(array $product): array
    {
        // Add computed fields
        $product['category_name'] = $this->categories[$product['category']]['name'] ?? ucfirst($product['category']);
        $product['has_sale'] = $product['sale_price'] !== null;
        $product['discount_percentage'] = $product['has_sale'] 
            ? round((($product['price'] - $product['sale_price']) / $product['price']) * 100)
            : 0;
        $product['current_price'] = $product['sale_price'] ?? $product['price'];
        $product['is_in_stock'] = $product['stock'] > 0;
        $product['stock_status'] = $product['stock'] > 10 ? 'in_stock' : ($product['stock'] > 0 ? 'low_stock' : 'out_of_stock');
        $product['url'] = '/product/' . $product['slug'];
        $product['add_to_cart_url'] = '/cart/add';
        
        return HookSystem::applyFilters('search.product_data', $product);
    }
    
    private function getAggregations(array $allProducts, array $currentParams): array
    {
        return [
            'total_products' => count($allProducts),
            'price_range' => [
                'min' => min(array_column($allProducts, 'price')),
                'max' => max(array_column($allProducts, 'price'))
            ],
            'category_counts' => array_count_values(array_column($allProducts, 'category')),
            'brand_counts' => array_count_values(array_column($allProducts, 'brand')),
            'in_stock_count' => count(array_filter($allProducts, fn($p) => $p['stock'] > 0)),
            'featured_count' => count(array_filter($allProducts, fn($p) => $p['featured'])),
        ];
    }
    
    /**
     * Get popular/trending search terms
     */
    public function getPopularSearches(int $limit = 10): array
    {
        // In a real implementation, this would come from search analytics
        return [
            'iPhone', 'MacBook', 'Nike shoes', 'Samsung Galaxy',
            'Laptop', 'Headphones', 'Watch', 'Camera',
            'Books', 'Coffee maker'
        ];
    }
    
    /**
     * Get categories for navigation
     */
    public function getCategories(): array
    {
        return $this->categories;
    }
}