<?php

declare(strict_types=1);

namespace Shopologic\Core\MultiStore\Middleware;

use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use Shopologic\Core\Http\Middleware\MiddlewareInterface;
use Shopologic\Core\MultiStore\StoreManager;

/**
 * Middleware to detect and set current store from request
 */
class StoreDetectionMiddleware implements MiddlewareInterface
{
    private StoreManager $storeManager;

    public function __construct(StoreManager $storeManager)
    {
        $this->storeManager = $storeManager;
    }

    public function handle(Request $request, callable $next): Response
    {
        // Detect store from request
        $store = $this->storeManager->detectStore($request);
        
        if (!$store) {
            // No store found and no default available
            return new Response(
                'Store not found',
                404,
                ['Content-Type' => 'text/plain']
            );
        }
        
        // Add store to request attributes
        $request->setAttribute('store', $store);
        $request->setAttribute('store_id', $store->id);
        
        // Continue with request
        $response = $next($request);
        
        // Add store header to response
        $response->header('X-Store-ID', (string) $store->id);
        $response->header('X-Store-Code', $store->code);
        
        return $response;
    }
}