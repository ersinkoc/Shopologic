<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce\Services;

use Shopologic\Core\Cache\CacheInterface;

class CacheService
{
    private CacheInterface $cache;
    
    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }
    
    public function warmCache(): void
    {
        // Pre-load frequently accessed data
        $this->cache->tags(['products', 'categories'])->flush();
    }
}