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
 * Category controller for handling product categories and filtering
 */
class CategoryController
{
    private TemplateEngine $template;
    private SearchService $searchService;
    
    public function __construct(TemplateEngine $template)
    {
        $this->template = $template;
        $this->searchService = new SearchService();
    }
    
    /**
     * Display all categories
     */
    public function index(RequestInterface $request): Response
    {
        try {
            $categories = $this->searchService->getCategories();
            $categoriesWithCounts = [];
            
            foreach ($categories as $slug => $category) {
                $categoryProducts = $this->searchService->searchProducts(['category' => $slug]);
                $categoriesWithCounts[] = [
                    'slug' => $slug,
                    'name' => $category['name'],
                    'product_count' => $categoryProducts['pagination']['total'],
                    'description' => $this->getCategoryDescription($slug),
                    'icon' => $this->getCategoryIcon($slug),
                    'featured_products' => array_slice($categoryProducts['products'], 0, 3)
                ];
            }
            
            // Sort by product count (most popular first)
            usort($categoriesWithCounts, function($a, $b) {
                return $b['product_count'] <=> $a['product_count'];
            });
            
            $data = [
                'title' => 'Shop by Category - Shopologic',
                'description' => 'Browse our product categories to find exactly what you\'re looking for',
                'categories' => $categoriesWithCounts,
                'total_categories' => count($categoriesWithCounts),
                'total_products' => array_sum(array_column($categoriesWithCounts, 'product_count'))
            ];
            
            $content = $this->template->render('categories/index', $data);
            
            $body = new Stream('php://memory', 'w+');
            $body->write($content);
            
            return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
            
        } catch (\Exception $e) {
            return $this->errorResponse('Error loading categories: ' . $e->getMessage());
        }
    }
    
    /**
     * Display products in a specific category
     */
    public function show(RequestInterface $request, string $categorySlug): Response
    {
        try {
            $categories = $this->searchService->getCategories();
            
            if (!isset($categories[$categorySlug])) {
                return $this->notFoundResponse('Category not found');
            }
            
            $category = $categories[$categorySlug];
            
            // Get query parameters
            $queryParams = $request->getUri()->getQuery();
            parse_str($queryParams, $params);
            
            // Force category filter
            $params['category'] = $categorySlug;
            
            // Get products in this category
            $searchResults = $this->searchService->searchProducts($params);
            $availableFilters = $this->searchService->getAvailableFilters($params);
            
            // Get subcategories or related categories
            $relatedCategories = $this->getRelatedCategories($categorySlug);
            
            $data = [
                'title' => $category['name'] . ' - Shopologic',
                'description' => $this->getCategoryDescription($categorySlug),
                'category' => [
                    'slug' => $categorySlug,
                    'name' => $category['name'],
                    'description' => $this->getCategoryDescription($categorySlug),
                    'icon' => $this->getCategoryIcon($categorySlug)
                ],
                'products' => $searchResults['products'],
                'pagination' => $searchResults['pagination'],
                'filters' => $searchResults['filters'],
                'available_filters' => $availableFilters,
                'related_categories' => $relatedCategories,
                'breadcrumbs' => [
                    ['name' => 'Home', 'url' => '/'],
                    ['name' => 'Categories', 'url' => '/categories'],
                    ['name' => $category['name'], 'url' => '/category/' . $categorySlug]
                ],
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
            
            $content = $this->template->render('categories/show', $data);
            
            $body = new Stream('php://memory', 'w+');
            $body->write($content);
            
            return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
            
        } catch (\Exception $e) {
            return $this->errorResponse('Error loading category: ' . $e->getMessage());
        }
    }
    
    private function getCategoryDescription(string $slug): string
    {
        $descriptions = [
            'electronics' => 'Discover the latest electronics including laptops, smartphones, tablets, and smart home devices from top brands.',
            'clothing' => 'Shop stylish clothing, shoes, and accessories for men, women, and children from your favorite brands.',
            'books' => 'Explore our vast collection of books including fiction, non-fiction, educational, and children\'s books.',
            'home' => 'Transform your home with our collection of furniture, décor, kitchen appliances, and garden tools.',
            'sports' => 'Find everything you need for your active lifestyle including sports equipment, fitness gear, and outdoor activities.',
            'toys' => 'Discover fun and educational toys, games, and activities for children of all ages.',
            'beauty' => 'Shop premium beauty and personal care products including skincare, makeup, and grooming essentials.',
            'automotive' => 'Keep your vehicle running smoothly with our selection of automotive parts, accessories, and maintenance products.'
        ];
        
        return $descriptions[$slug] ?? 'Browse our collection of high-quality products in this category.';
    }
    
    private function getCategoryIcon(string $slug): string
    {
        $icons = [
            'electronics' => '💻',
            'clothing' => '👕',
            'books' => '📚',
            'home' => '🏠',
            'sports' => '⚽',
            'toys' => '🧸',
            'beauty' => '💄',
            'automotive' => '🚗'
        ];
        
        return $icons[$slug] ?? '📦';
    }
    
    private function getRelatedCategories(string $currentSlug): array
    {
        $categories = $this->searchService->getCategories();
        $related = [];
        
        foreach ($categories as $slug => $category) {
            if ($slug !== $currentSlug) {
                $categoryProducts = $this->searchService->searchProducts(['category' => $slug]);
                $related[] = [
                    'slug' => $slug,
                    'name' => $category['name'],
                    'icon' => $this->getCategoryIcon($slug),
                    'product_count' => $categoryProducts['pagination']['total']
                ];
            }
        }
        
        // Return random 4 categories
        shuffle($related);
        return array_slice($related, 0, 4);
    }
    
    private function errorResponse(string $message): Response
    {
        $body = new Stream('php://memory', 'w+');
        $body->write('<h1>Error</h1><p>' . htmlspecialchars($message) . '</p>');
        return new Response(500, ['Content-Type' => 'text/html'], $body);
    }
    
    private function notFoundResponse(string $message): Response
    {
        $body = new Stream('php://memory', 'w+');
        $body->write('<h1>Not Found</h1><p>' . htmlspecialchars($message) . '</p>');
        return new Response(404, ['Content-Type' => 'text/html'], $body);
    }
}