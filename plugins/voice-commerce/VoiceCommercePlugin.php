<?php

declare(strict_types=1);
namespace Shopologic\Plugins\VoiceCommerce;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\Hook;

/**
 * Voice Commerce Plugin
 * 
 * Voice-powered shopping using browser Speech Recognition APIs
 */
class VoiceCommercePlugin extends AbstractPlugin
{
    private $speechProcessor;
    private $commandRegistry;

    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
        $this->registerVoiceCommands();
    }

    private function registerServices(): void
    {
        $this->speechProcessor = new Services\SpeechProcessor($this->api);
        $this->commandRegistry = new Services\CommandRegistry($this->api);
    }

    private function registerHooks(): void
    {
        // Add voice interface to frontend
        Hook::addAction('frontend.head', [$this, 'addVoiceInterface'], 10);
        Hook::addFilter('search.form', [$this, 'addVoiceSearchButton'], 10, 1);
        Hook::addFilter('product.actions', [$this, 'addVoiceProductActions'], 10, 2);
        Hook::addFilter('cart.controls', [$this, 'addVoiceCartControls'], 10, 1);
        
        // Process voice interactions
        Hook::addAction('voice.command_received', [$this, 'processVoiceCommand'], 10, 2);
        Hook::addAction('voice.search_requested', [$this, 'handleVoiceSearch'], 10, 1);
    }

    public function addVoiceInterface(): void
    {
        if (!$this->getConfig('enable_voice_search', true)) {
            return;
        }

        echo $this->api->view('voice/interface', [
            'config' => [
                'language' => $this->getConfig('language', 'en-US'),
                'speed' => $this->getConfig('voice_speed', 1.0),
                'confidence_threshold' => $this->getConfig('confidence_threshold', 0.7),
                'feedback_enabled' => $this->getConfig('voice_feedback', true)
            ],
            'commands' => $this->commandRegistry->getPublicCommands()
        ]);
    }

    public function addVoiceSearchButton($searchForm): string
    {
        $voiceButton = $this->api->view('voice/search-button', [
            'enabled' => $this->getConfig('enable_voice_search', true)
        ]);
        
        return str_replace('</form>', $voiceButton . '</form>', $searchForm);
    }

    public function addVoiceProductActions($actions, $product): string
    {
        if (!$this->getConfig('enable_product_description_speech', true)) {
            return $actions;
        }

        return $actions . $this->api->view('voice/product-actions', [
            'product_id' => $product->id,
            'has_description' => !empty($product->description)
        ]);
    }

    public function addVoiceCartControls($controls): string
    {
        if (!$this->getConfig('enable_voice_navigation', true)) {
            return $controls;
        }

        return $controls . $this->api->view('voice/cart-controls', [
            'voice_commands' => [
                'checkout' => 'Say "checkout" to proceed',
                'clear cart' => 'Say "clear cart" to empty cart',
                'continue shopping' => 'Say "continue shopping" to go back'
            ]
        ]);
    }

    public function processVoiceCommand($command, $context): void
    {
        $this->logVoiceInteraction($command, $context);
        
        $normalizedCommand = $this->speechProcessor->normalizeCommand($command);
        $intent = $this->speechProcessor->extractIntent($normalizedCommand);
        
        switch ($intent['action']) {
            case 'search':
                $this->executeVoiceSearch($intent['query']);
                break;
                
            case 'add_to_cart':
                $this->executeAddToCart($intent['product']);
                break;
                
            case 'navigate':
                $this->executeNavigation($intent['destination']);
                break;
                
            case 'read_product':
                $this->executeProductReading($intent['product_id']);
                break;
                
            case 'checkout':
                $this->executeCheckout();
                break;
                
            default:
                $this->sendVoiceFeedback("Sorry, I didn't understand that command. Try saying 'help' for available commands.");
        }
    }

    private function executeVoiceSearch($query): void
    {
        $searchResults = $this->api->service('ProductSearch')->search($query, [
            'limit' => 5,
            'include_descriptions' => true
        ]);
        
        if (empty($searchResults)) {
            $this->sendVoiceFeedback("No products found for '{$query}'. Try a different search term.");
            return;
        }
        
        $resultCount = count($searchResults);
        $feedback = "Found {$resultCount} products for '{$query}'. ";
        
        foreach (array_slice($searchResults, 0, 3) as $index => $product) {
            $feedback .= ($index + 1) . ". {$product->name} for ${$product->price}. ";
        }
        
        if ($resultCount > 3) {
            $feedback .= "And " . ($resultCount - 3) . " more results.";
        }
        
        $this->sendVoiceFeedback($feedback);
        $this->redirectToSearch($query);
    }

    private function executeAddToCart($productIdentifier): void
    {
        $product = $this->findProductByVoice($productIdentifier);
        
        if (!$product) {
            $this->sendVoiceFeedback("Product not found. Please be more specific.");
            return;
        }
        
        $cart = $this->api->service('CartService');
        $cart->addItem($product->id, 1);
        
        $this->sendVoiceFeedback("{$product->name} has been added to your cart for ${$product->price}.");
    }

    private function executeNavigation($destination): void
    {
        $routes = [
            'home' => '/',
            'cart' => '/cart',
            'checkout' => '/checkout',
            'account' => '/account',
            'categories' => '/categories',
            'deals' => '/deals',
            'new arrivals' => '/products/new'
        ];
        
        $destination = strtolower($destination);
        
        if (isset($routes[$destination])) {
            $this->sendVoiceFeedback("Navigating to {$destination}.");
            $this->redirectTo($routes[$destination]);
        } else {
            $this->sendVoiceFeedback("Unknown destination. Available pages: " . implode(', ', array_keys($routes)));
        }
    }

    private function executeProductReading($productId): void
    {
        $product = $this->api->service('ProductRepository')->find($productId);
        
        if (!$product) {
            $this->sendVoiceFeedback("Product not found.");
            return;
        }
        
        $description = strip_tags($product->description);
        $speech = "{$product->name}. Price: ${$product->price}. ";
        
        if ($product->stock_quantity > 0) {
            $speech .= "In stock. ";
        } else {
            $speech .= "Out of stock. ";
        }
        
        if (!empty($description)) {
            $speech .= "Description: " . substr($description, 0, 200);
            if (strlen($description) > 200) {
                $speech .= "... Say 'read more' for full description.";
            }
        }
        
        $this->sendVoiceFeedback($speech, ['type' => 'product_reading', 'product_id' => $productId]);
    }

    private function executeCheckout(): void
    {
        $cart = $this->api->service('CartService');
        
        if ($cart->isEmpty()) {
            $this->sendVoiceFeedback("Your cart is empty. Add some products first.");
            return;
        }
        
        $itemCount = $cart->getItemCount();
        $total = $cart->getTotal();
        
        $this->sendVoiceFeedback("Proceeding to checkout with {$itemCount} items. Total: ${$total}.");
        $this->redirectTo('/checkout');
    }

    private function registerVoiceCommands(): void
    {
        $commands = [
            // Search commands
            'search for {query}' => ['action' => 'search'],
            'find {query}' => ['action' => 'search'],
            'look for {query}' => ['action' => 'search'],
            
            // Cart commands
            'add {product} to cart' => ['action' => 'add_to_cart'],
            'buy {product}' => ['action' => 'add_to_cart'],
            
            // Navigation commands
            'go to {page}' => ['action' => 'navigate'],
            'show me {page}' => ['action' => 'navigate'],
            'checkout' => ['action' => 'checkout'],
            
            // Product commands
            'read product description' => ['action' => 'read_product'],
            'tell me about this product' => ['action' => 'read_product'],
            
            // Help command
            'help' => ['action' => 'help'],
            'what can I say' => ['action' => 'help']
        ];
        
        foreach ($commands as $pattern => $config) {
            $this->commandRegistry->register($pattern, $config);
        }
    }

    private function findProductByVoice($identifier): ?object
    {
        // Try to find product by name similarity
        $products = $this->api->service('ProductRepository')->searchByName($identifier);
        
        if (empty($products)) {
            return null;
        }
        
        // Return the most similar match
        return $products[0];
    }

    private function sendVoiceFeedback($message, $metadata = []): void
    {
        if (!$this->getConfig('voice_feedback', true)) {
            return;
        }
        
        $this->api->response()->json([
            'type' => 'voice_feedback',
            'message' => $message,
            'speech_config' => [
                'rate' => $this->getConfig('voice_speed', 1.0),
                'lang' => $this->getConfig('language', 'en-US')
            ],
            'metadata' => $metadata
        ]);
    }

    private function redirectTo($url): void
    {
        $this->api->response()->json([
            'type' => 'redirect',
            'url' => $url
        ]);
    }

    private function redirectToSearch($query): void
    {
        $this->api->response()->json([
            'type' => 'redirect',
            'url' => '/search?q=' . urlencode($query)
        ]);
    }

    private function logVoiceInteraction($command, $context): void
    {
        $this->api->database()->table('voice_interactions')->insert([
            'command' => $command,
            'context' => json_encode($context),
            'user_id' => $this->api->auth()->user()?->id,
            'session_id' => session_id(),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function registerRoutes(): void
    {
        $this->api->router()->post('/voice/process-command', 'Controllers\VoiceController@processCommand');
        $this->api->router()->get('/voice/commands', 'Controllers\VoiceController@getCommands');
        $this->api->router()->post('/voice/feedback', 'Controllers\VoiceController@recordFeedback');
        $this->api->router()->get('/voice/product-speech/{id}', 'Controllers\VoiceController@getProductSpeech');
    }

    public function install(): void
    {
        $this->runMigrations();
        $this->createDefaultCommands();
    }

    private function createDefaultCommands(): void
    {
        $defaultCommands = [
            ['pattern' => 'search for *', 'action' => 'search', 'description' => 'Search for products'],
            ['pattern' => 'add * to cart', 'action' => 'add_to_cart', 'description' => 'Add product to cart'],
            ['pattern' => 'go to checkout', 'action' => 'checkout', 'description' => 'Proceed to checkout'],
            ['pattern' => 'help', 'action' => 'help', 'description' => 'Show available commands']
        ];

        foreach ($defaultCommands as $command) {
            $this->api->database()->table('voice_commands')->insert($command);
        }
    }

    /**
     * Register Services
     */
    protected function registerServices(): void
    {
        // TODO: Implement registerServices
    }

    /**
     * Register EventListeners
     */
    protected function registerEventListeners(): void
    {
        // TODO: Implement registerEventListeners
    }

    /**
     * Register Hooks
     */
    protected function registerHooks(): void
    {
        // TODO: Implement registerHooks
    }

    /**
     * Register Routes
     */
    protected function registerRoutes(): void
    {
        // TODO: Implement registerRoutes
    }

    /**
     * Register Permissions
     */
    protected function registerPermissions(): void
    {
        // TODO: Implement registerPermissions
    }

    /**
     * Register ScheduledJobs
     */
    protected function registerScheduledJobs(): void
    {
        // TODO: Implement registerScheduledJobs
    }
}