<?php

declare(strict_types=1);

namespace Shopologic\Core\Http\Controllers;

use Shopologic\Core\Template\TemplateEngine;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use Shopologic\Core\Http\Stream;

/**
 * Home page controller
 */
class HomeController
{
    private TemplateEngine $template;
    
    public function __construct(TemplateEngine $template)
    {
        $this->template = $template;
    }
    
    /**
     * Display the home page
     */
    public function index(Request $request): Response
    {
        try {
            // Demo data for the home page
            $data = [
                'title' => 'Welcome to Shopologic - Your Modern E-commerce Platform',
                'description' => 'Shop the latest products at great prices with fast shipping and excellent customer service.',
                
                // Demo categories
                'categories' => $this->getDemoCategories(),
                
                // Demo featured products
                'featured_products' => $this->getSampleProducts(),
                
                // Demo new arrivals
                'new_arrivals' => $this->getSampleProducts(),
                
                // Demo best sellers
                'best_sellers' => $this->getSampleProducts(),
                
                // Demo special offers
                'special_offers' => array_slice($this->getSampleProducts(), 0, 4)
            ];
            
            // Data is already populated with sample products
            
            // Render the complex template
            $content = $this->template->render('home', $data);
            
            
            $stream = new Stream('php://temp', 'w+');
            $stream->write($content);
            
            return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $stream);
            
        } catch (\Exception $e) {
            // Log the error
            error_log('Home page error: ' . $e->getMessage());
            
            // Return error page
            $errorContent = $this->template->render('error/500', [
                'title' => 'Error',
                'message' => 'An error occurred while loading the page.'
            ]);
            
            $errorStream = new Stream('php://temp', 'w+');
            $errorStream->write($errorContent);
            
            return new Response(500, ['Content-Type' => 'text/html; charset=UTF-8'], $errorStream);
        }
    }
    
    /**
     * Get sample products for demonstration
     */
    private function getSampleProducts(): array
    {
        $sampleProducts = [];
        
        for ($i = 1; $i <= 8; $i++) {
            $product = new \stdClass();
            $product->id = $i;
            $product->name = "Sample Product {$i}";
            $product->slug = "sample-product-{$i}";
            $product->price = rand(20, 200);
            $product->sale_price = $i % 3 === 0 ? (int)($product->price * 0.8) : null;
            $product->short_description = "This is a sample product description for product {$i}.";
            $product->in_stock = true;
            $product->is_new = $i <= 3;
            $product->rating = rand(3, 5);
            $product->review_count = rand(5, 50);
            
            // Create sample images
            $product->images = [];
            for ($j = 1; $j <= 2; $j++) {
                $image = new \stdClass();
                $image->url = "https://via.placeholder.com/300x300?text=Product+{$i}+Image+{$j}";
                $image->alt = "Product {$i} Image {$j}";
                $product->images[] = $image;
            }
            
            $sampleProducts[] = $product;
        }
        
        return $sampleProducts;
    }
    
    /**
     * Display the shop page
     */
    public function shop(Request $request): Response
    {
        $page = (int) $request->get('page', 1);
        $perPage = 12;
        $category = $request->get('category');
        $sort = $request->get('sort', 'newest');
        
        // Build query
        $query = Product::where('status', 'active')
            ->with(['images', 'category']);
        
        // Filter by category
        if ($category) {
            $query->whereHas('category', function($q) use ($category) {
                $q->where('slug', $category);
            });
        }
        
        // Apply sorting
        switch ($sort) {
            case 'price_low':
                $query->orderBy('price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('price', 'desc');
                break;
            case 'popular':
                $query->orderBy('sales_count', 'desc');
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }
        
        // Paginate results
        $products = $query->paginate($perPage, ['*'], 'page', $page);
        
        $data = [
            'title' => 'Shop All Products',
            'products' => $products,
            'categories' => Category::where('status', 'active')->get(),
            'current_category' => $category,
            'current_sort' => $sort
        ];
        
        return new Response(
            $this->template->render('shop', $data),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }
    
    /**
     * Display a single product
     */
    public function product(Request $request, string $slug): Response
    {
        $product = Product::where('slug', $slug)
            ->where('status', 'active')
            ->with(['images', 'category', 'reviews'])
            ->firstOrFail();
        
        // Get related products
        $relatedProducts = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('status', 'active')
            ->with(['images'])
            ->limit(4)
            ->get();
        
        $data = [
            'title' => $product->name . ' - Shopologic',
            'description' => $product->short_description,
            'product' => $product,
            'related_products' => $relatedProducts
        ];
        
        return new Response(
            $this->template->render('product/show', $data),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }
    
    /**
     * Get demo categories for demonstration
     */
    private function getDemoCategories(): array
    {
        return [
            (object)['id' => 1, 'name' => 'Electronics', 'slug' => 'electronics', 'icon' => '💻', 'product_count' => 45],
            (object)['id' => 2, 'name' => 'Fashion', 'slug' => 'fashion', 'icon' => '👕', 'product_count' => 128],
            (object)['id' => 3, 'name' => 'Home & Garden', 'slug' => 'home-garden', 'icon' => '🏠', 'product_count' => 67],
            (object)['id' => 4, 'name' => 'Sports', 'slug' => 'sports', 'icon' => '⚽', 'product_count' => 34],
            (object)['id' => 5, 'name' => 'Books', 'slug' => 'books', 'icon' => '📚', 'product_count' => 89],
            (object)['id' => 6, 'name' => 'Toys', 'slug' => 'toys', 'icon' => '🧸', 'product_count' => 56]
        ];
    }
}