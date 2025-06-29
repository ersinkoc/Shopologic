<?php

declare(strict_types=1);

namespace Shopologic\Core\MultiStore\Middleware;

use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use Shopologic\Core\Http\Middleware\MiddlewareInterface;
use Shopologic\Core\MultiStore\StoreManager;
use Shopologic\Core\Auth\AuthManager;

/**
 * Middleware to check user access to current store
 */
class StoreAccessMiddleware implements MiddlewareInterface
{
    private StoreManager $storeManager;
    private AuthManager $authManager;
    private array $options;

    public function __construct(
        StoreManager $storeManager,
        AuthManager $authManager,
        array $options = []
    ) {
        $this->storeManager = $storeManager;
        $this->authManager = $authManager;
        $this->options = array_merge([
            'require_auth' => true,
            'require_role' => null,
            'allow_guest' => false
        ], $options);
    }

    public function handle(Request $request, callable $next): Response
    {
        $store = $this->storeManager->getCurrentStore();
        
        if (!$store) {
            return new Response(
                json_encode(['error' => 'Store not found']),
                404,
                ['Content-Type' => 'application/json']
            );
        }
        
        // Check if authentication is required
        if ($this->options['require_auth']) {
            $user = $this->authManager->user();
            
            if (!$user && !$this->options['allow_guest']) {
                return new Response(
                    json_encode(['error' => 'Authentication required']),
                    401,
                    ['Content-Type' => 'application/json']
                );
            }
            
            if ($user) {
                // Check store access
                if (!$this->storeManager->hasAccess($store, $user)) {
                    return new Response(
                        json_encode(['error' => 'Access denied to this store']),
                        403,
                        ['Content-Type' => 'application/json']
                    );
                }
                
                // Check required role
                if ($this->options['require_role']) {
                    $userRole = $this->storeManager->getUserRole($store, $user);
                    
                    if (!$this->hasRequiredRole($userRole, $this->options['require_role'])) {
                        return new Response(
                            json_encode(['error' => 'Insufficient permissions']),
                            403,
                            ['Content-Type' => 'application/json']
                        );
                    }
                }
            }
        }
        
        return $next($request);
    }

    private function hasRequiredRole(?string $userRole, $requiredRole): bool
    {
        if (!$userRole) {
            return false;
        }
        
        // Role hierarchy
        $roleHierarchy = [
            'viewer' => 1,
            'editor' => 2,
            'manager' => 3,
            'admin' => 4,
            'owner' => 5
        ];
        
        $userLevel = $roleHierarchy[$userRole] ?? 0;
        
        if (is_array($requiredRole)) {
            foreach ($requiredRole as $role) {
                $requiredLevel = $roleHierarchy[$role] ?? 0;
                if ($userLevel >= $requiredLevel) {
                    return true;
                }
            }
            return false;
        }
        
        $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;
        return $userLevel >= $requiredLevel;
    }
}