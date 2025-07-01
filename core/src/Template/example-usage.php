<?php

/**
 * Example usage of the Template Engine
 */

use Shopologic\Core\Template\TemplateEngine;
use Shopologic\Core\Ecommerce\Models\Product;
use Shopologic\Core\Ecommerce\Models\Category;

// Create template engine instance
$template = new TemplateEngine(debug: true);

// Add template paths
$template->addPath('/path/to/themes/default/templates', 'default');
$template->addPath('/path/to/themes/admin/templates', 'admin');

// Add global variables available in all templates
$template->addGlobal('site_name', 'Shopologic');
$template->addGlobal('site_tagline', 'Your Modern E-commerce Platform');
$template->addGlobal('current_year', date('Y'));

// Add custom template functions
$template->addFunction('format_price', function($price, $decimals = 2) {
    return '$' . number_format($price, $decimals);
});

// Example: Render home page
$homeData = [
    'title' => 'Welcome to Shopologic',
    'description' => 'Shop the latest products at great prices',
    'categories' => Category::where('status', 'active')
        ->where('parent_id', null)
        ->orderBy('sort_order')
        ->limit(6)
        ->get(),
    'featured_products' => Product::where('is_featured', true)
        ->where('status', 'active')
        ->with(['images', 'category'])
        ->limit(8)
        ->get(),
    'new_arrivals' => Product::where('status', 'active')
        ->orderBy('created_at', 'desc')
        ->with(['images', 'category'])
        ->limit(8)
        ->get(),
    'best_sellers' => Product::where('status', 'active')
        ->orderBy('sales_count', 'desc')
        ->with(['images', 'category'])
        ->limit(8)
        ->get(),
    'special_offers' => Product::where('status', 'active')
        ->whereNotNull('sale_price')
        ->where('sale_price', '<', 'price')
        ->with(['images', 'category'])
        ->limit(4)
        ->get()
];

// Render the template
$html = $template->render('home', $homeData);
echo $html;

// Example: Render a product page with layout
$productData = [
    'title' => 'Product Name - Shopologic',
    'product' => Product::with(['images', 'category', 'reviews'])->find(1),
    'related_products' => Product::where('category_id', $product->category_id)
        ->where('id', '!=', $product->id)
        ->limit(4)
        ->get()
];

$html = $template->render('product/show', $productData);

// Example: Using partials within a template
// In your template file:
/*
<?php $this->partial('partials/breadcrumb', [
    'items' => [
        ['label' => 'Home', 'url' => $this->url()],
        ['label' => $category->name, 'url' => $this->url('category/' . $category->slug)],
        ['label' => $product->name]
    ]
]); ?>
*/

// Example: Using layouts and blocks
// In your template file:
/*
<?php $this->layout('layouts/main'); ?>

<?php $this->startBlock('content'); ?>
    <h1>Page Content</h1>
    <p>This content will be inserted into the layout's content block.</p>
<?php $this->endBlock(); ?>

<?php $this->startBlock('sidebar'); ?>
    <aside>
        <h3>Sidebar Content</h3>
    </aside>
<?php $this->endBlock(); ?>
*/

// Example: Using template in a controller
class HomeController
{
    private TemplateEngine $template;
    
    public function __construct(TemplateEngine $template)
    {
        $this->template = $template;
    }
    
    public function index()
    {
        $data = [
            'categories' => Category::getActive(),
            'featured_products' => Product::getFeatured(),
            // ... more data
        ];
        
        return $this->template->render('home', $data);
    }
}