<?php

declare(strict_types=1);

namespace Shopologic\Core\Http\Controllers;

use Shopologic\Core\Template\TemplateEngine;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use Shopologic\Core\Http\Stream;
use Shopologic\Core\Search\SearchService;
use Psr\Http\Message\RequestInterface;

/**
 * Product controller for handling product catalog and details
 */
class ProductController
{
    private TemplateEngine $template;
    private SearchService $searchService;
    
    public function __construct(TemplateEngine $template)
    {
        $this->template = $template;
        $this->searchService = new SearchService();
    }
    
    /**
     * Display product catalog with filtering and pagination
     */
    public function index(Request $request): Response
    {
        try {
            $page = max(1, (int) ($request->getQueryParams()['page'] ?? 1));
            $perPage = 12;
            $category = $request->getQueryParams()['category'] ?? null;
            $sort = $request->getQueryParams()['sort'] ?? 'newest';
            $search = $request->getQueryParams()['q'] ?? '';
            
            // Get demo products
            $allProducts = $this->getDemoProducts();
            
            // Filter by category
            if ($category) {
                $allProducts = array_filter($allProducts, function($product) use ($category) {
                    return $product->category_slug === $category;
                });
            }
            
            // Filter by search
            if ($search) {
                $allProducts = array_filter($allProducts, function($product) use ($search) {
                    return stripos($product->name, $search) !== false || 
                           stripos($product->description, $search) !== false;
                });
            }
            
            // Sort products
            switch ($sort) {
                case 'price_low':
                    usort($allProducts, fn($a, $b) => $a->price <=> $b->price);
                    break;
                case 'price_high':
                    usort($allProducts, fn($a, $b) => $b->price <=> $a->price);
                    break;
                case 'name_asc':
                    usort($allProducts, fn($a, $b) => strcmp($a->name, $b->name));
                    break;
                case 'name_desc':
                    usort($allProducts, fn($a, $b) => strcmp($b->name, $a->name));
                    break;
                case 'rating':
                    usort($allProducts, fn($a, $b) => $b->rating <=> $a->rating);
                    break;
                case 'newest':
                default:
                    usort($allProducts, fn($a, $b) => $b->id <=> $a->id);
                    break;
            }
            
            // Paginate
            $totalProducts = count($allProducts);
            $totalPages = (int) ceil($totalProducts / $perPage);
            $offset = ($page - 1) * $perPage;
            $products = array_slice($allProducts, $offset, $perPage);
            
            $data = [
                'title' => $search ? "Search results for: {$search}" : 'Shop All Products',
                'description' => 'Browse our complete product catalog',
                'products' => $products,
                'categories' => $this->getDemoCategories(),
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_products' => $totalProducts,
                'current_category' => $category,
                'current_sort' => $sort,
                'current_search' => $search,
                'pagination' => $this->generatePagination($page, $totalPages, $request->getUri()->getPath(), $request->getQueryParams())
            ];
            
            $content = $this->template->render('products/index', $data);
            
            $stream = new Stream('php://temp', 'w+');
            $stream->write($content);
            
            return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $stream);
            
        } catch (\Exception $e) {
            error_log('Product catalog error: ' . $e->getMessage());
            
            $errorContent = $this->template->render('error/500', [
                'title' => 'Error',
                'message' => 'An error occurred while loading products.'
            ]);
            
            $errorStream = new Stream('php://temp', 'w+');
            $errorStream->write($errorContent);
            
            return new Response(500, ['Content-Type' => 'text/html; charset=UTF-8'], $errorStream);
        }
    }
    
    /**
     * Display single product details
     */
    public function show(Request $request, string $slug): Response
    {
        try {
            // Find product by slug
            $products = $this->getDemoProducts();
            $product = null;
            
            foreach ($products as $p) {
                if ($p->slug === $slug) {
                    $product = $p;
                    break;
                }
            }
            
            if (!$product) {
                $content = $this->template->render('error/404', [
                    'title' => 'Product Not Found',
                    'message' => 'The product you are looking for could not be found.'
                ]);
                
                $stream = new Stream('php://temp', 'w+');
                $stream->write($content);
                
                return new Response(404, ['Content-Type' => 'text/html; charset=UTF-8'], $stream);
            }
            
            // Get related products (same category)
            $relatedProducts = array_filter($products, function($p) use ($product) {
                return $p->category_slug === $product->category_slug && $p->id !== $product->id;
            });
            $relatedProducts = array_slice($relatedProducts, 0, 4);
            
            $data = [
                'title' => $product->name . ' - Shopologic',
                'description' => $product->short_description,
                'product' => $product,
                'related_products' => $relatedProducts,
                'categories' => $this->getDemoCategories()
            ];
            
            $content = $this->template->render('products/show', $data);
            
            $stream = new Stream('php://temp', 'w+');
            $stream->write($content);
            
            return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $stream);
            
        } catch (\Exception $e) {
            error_log('Product detail error: ' . $e->getMessage());
            
            $errorContent = $this->template->render('error/500', [
                'title' => 'Error',
                'message' => 'An error occurred while loading the product.'
            ]);
            
            $errorStream = new Stream('php://temp', 'w+');
            $errorStream->write($errorContent);
            
            return new Response(500, ['Content-Type' => 'text/html; charset=UTF-8'], $errorStream);
        }
    }
    
    /**
     * Generate pagination links
     */
    private function generatePagination(int $currentPage, int $totalPages, string $path, array $queryParams): array
    {
        $pagination = [];
        
        // Remove page from query params for base URL
        unset($queryParams['page']);
        $baseUrl = $path . (!empty($queryParams) ? '?' . http_build_query($queryParams) : '');
        $separator = !empty($queryParams) ? '&' : '?';
        
        // Previous link
        if ($currentPage > 1) {
            $pagination['prev'] = $baseUrl . $separator . 'page=' . ($currentPage - 1);
        }
        
        // Page numbers
        $pagination['pages'] = [];
        $start = max(1, $currentPage - 2);
        $end = min($totalPages, $currentPage + 2);
        
        for ($i = $start; $i <= $end; $i++) {
            $pagination['pages'][] = [
                'number' => $i,
                'url' => $baseUrl . $separator . 'page=' . $i,
                'current' => $i === $currentPage
            ];
        }
        
        // Next link
        if ($currentPage < $totalPages) {
            $pagination['next'] = $baseUrl . $separator . 'page=' . ($currentPage + 1);
        }
        
        return $pagination;
    }
    
    /**
     * Get demo products for testing
     */
    private function getDemoProducts(): array
    {
        $categories = $this->getDemoCategories();
        $products = [];
        
        $productNames = [
            'Wireless Bluetooth Headphones', 'Smart LED TV 55 inch', 'Gaming Mechanical Keyboard',
            'Cotton T-Shirt', 'Denim Jeans', 'Running Sneakers',
            'Coffee Maker', 'Indoor Plant Pot', 'Garden Tools Set',
            'Football', 'Basketball', 'Tennis Racket',
            'Programming Book', 'Science Fiction Novel', 'History Encyclopedia',
            'Educational Toy', 'Board Game', 'Action Figure'
        ];
        
        for ($i = 1; $i <= 48; $i++) {
            $categoryIndex = ($i - 1) % count($categories);
            $category = $categories[$categoryIndex];
            $nameIndex = ($i - 1) % count($productNames);
            $name = $productNames[$nameIndex] . " #{$i}";
            
            $product = new \stdClass();
            $product->id = $i;
            $product->name = $name;
            $product->slug = strtolower(str_replace([' ', '#'], ['-', ''], $name));
            $product->price = rand(20, 500);
            $product->sale_price = $i % 4 === 0 ? (int)($product->price * 0.8) : null;
            $product->short_description = "High-quality {$name} with excellent features and modern design.";
            $product->description = "This {$name} offers exceptional value and performance. Built with premium materials and designed for long-lasting use. Perfect for both casual and professional use. Features modern technology and user-friendly design.";
            $product->category_name = $category->name;
            $product->category_slug = $category->slug;
            $product->in_stock = rand(0, 10) > 1; // 90% in stock
            $product->stock_quantity = rand(5, 50);
            $product->is_new = $i <= 8;
            $product->is_featured = $i % 6 === 0;
            $product->rating = rand(3, 5);
            $product->review_count = rand(5, 100);
            
            // Create sample images
            $product->images = [];
            for ($j = 1; $j <= 3; $j++) {
                $image = new \stdClass();
                $image->url = "https://via.placeholder.com/400x400?text=Product+{$i}+Image+{$j}";
                $image->alt = "{$name} Image {$j}";
                $product->images[] = $image;
            }
            
            $products[] = $product;
        }
        
        return $products;
    }
    
    /**
     * Get demo categories
     */
    private function getDemoCategories(): array
    {
        return [
            (object)['id' => 1, 'name' => 'Electronics', 'slug' => 'electronics', 'icon' => '💻', 'product_count' => 8],
            (object)['id' => 2, 'name' => 'Fashion', 'slug' => 'fashion', 'icon' => '👕', 'product_count' => 8],
            (object)['id' => 3, 'name' => 'Home & Garden', 'slug' => 'home-garden', 'icon' => '🏠', 'product_count' => 8],
            (object)['id' => 4, 'name' => 'Sports', 'slug' => 'sports', 'icon' => '⚽', 'product_count' => 8],
            (object)['id' => 5, 'name' => 'Books', 'slug' => 'books', 'icon' => '📚', 'product_count' => 8],
            (object)['id' => 6, 'name' => 'Toys', 'slug' => 'toys', 'icon' => '🧸', 'product_count' => 8]
        ];
    }
    
    /**
     * Search products with filters and pagination
     */
    public function search(RequestInterface $request): Response
    {
        try {
            // Get search parameters from query string
            $queryParams = $request->getUri()->getQuery();
            parse_str($queryParams, $params);
            
            // Perform search
            $searchResults = $this->searchService->searchProducts($params);
            $availableFilters = $this->searchService->getAvailableFilters($params);
            $categories = $this->searchService->getCategories();
            
            // Prepare template data
            $data = [
                'title' => !empty($params['q']) ? 'Search Results for "' . htmlspecialchars($params['q']) . '"' : 'Search Products',
                'query' => $params['q'] ?? '',
                'products' => $searchResults['products'],
                'pagination' => $searchResults['pagination'],
                'filters' => $searchResults['filters'],
                'aggregations' => $searchResults['aggregations'],
                'available_filters' => $availableFilters,
                'categories' => $categories,
                'sort_options' => [
                    'relevance' => 'Best Match',
                    'price_low' => 'Price: Low to High',
                    'price_high' => 'Price: High to Low',
                    'rating' => 'Customer Rating',
                    'reviews' => 'Most Reviews',
                    'newest' => 'Newest First',
                    'name_asc' => 'Name A-Z',
                    'name_desc' => 'Name Z-A'
                ],
                'current_sort' => $params['sort'] ?? 'relevance'
            ];
            
            $content = $this->template->render('search/results', $data);
            
            $body = new Stream('php://memory', 'w+');
            $body->write($content);
            
            return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
            
        } catch (\Exception $e) {
            return $this->errorResponse('Search error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get search suggestions for autocomplete (AJAX endpoint)
     */
    public function suggestions(RequestInterface $request): Response
    {
        try {
            $queryParams = $request->getUri()->getQuery();
            parse_str($queryParams, $params);
            
            $query = $params['q'] ?? '';
            $limit = isset($params['limit']) ? (int)$params['limit'] : 10;
            
            $suggestions = $this->searchService->getSearchSuggestions($query, $limit);
            
            $body = new Stream('php://memory', 'w+');
            $body->write(json_encode([
                'success' => true,
                'query' => $query,
                'suggestions' => $suggestions
            ]));
            
            return new Response(200, ['Content-Type' => 'application/json'], $body);
            
        } catch (\Exception $e) {
            $body = new Stream('php://memory', 'w+');
            $body->write(json_encode([
                'success' => false,
                'message' => 'Error getting suggestions: ' . $e->getMessage()
            ]));
            return new Response(500, ['Content-Type' => 'application/json'], $body);
        }
    }
    
    private function errorResponse(string $message): Response
    {
        $body = new Stream('php://memory', 'w+');
        $body->write('<h1>Error</h1><p>' . htmlspecialchars($message) . '</p>');
        return new Response(500, ['Content-Type' => 'text/html'], $body);
    }
}