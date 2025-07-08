<?php

declare(strict_types=1);

namespace Shopologic\Plugins\CoreCommerce\Services;

class RecommendationService
{
    public function getProductRecommendations(int $productId, int $limit = 4): array
    {
        // Stub implementation - would use ML/AI in production
        return [
            ['product_id' => 1, 'score' => 0.95],
            ['product_id' => 2, 'score' => 0.87],
            ['product_id' => 3, 'score' => 0.82],
            ['product_id' => 4, 'score' => 0.75]
        ];
    }
}