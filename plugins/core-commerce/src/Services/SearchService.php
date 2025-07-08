<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce\Services;

use Shopologic\Core\Database\DatabaseManager;

class SearchService
{
    private DatabaseManager $db;
    
    public function __construct(DatabaseManager $db)
    {
        $this->db = $db;
    }
    
    public function search(string $query, array $filters = []): array
    {
        $builder = $this->db->table('products')
            ->where('status', 'active');
            
        if (!empty($query)) {
            $builder->where(function($q) use ($query) {
                $q->where('name', 'like', '%' . $query . '%')
                  ->orWhere('description', 'like', '%' . $query . '%');
            });
        }
        
        return $builder->get();
    }
}