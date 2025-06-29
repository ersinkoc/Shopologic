<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Models;

use Shopologic\Core\Database\Model;
use Shopologic\Core\Auth\Models\User;

class ProductReview extends Model
{
    protected string $table = 'product_reviews';
    
    protected array $fillable = [
        'product_id',
        'user_id',
        'rating',
        'title',
        'comment',
        'is_verified_purchase',
        'is_approved',
        'helpful_count',
        'not_helpful_count',
    ];
    
    protected array $casts = [
        'product_id' => 'integer',
        'user_id' => 'integer',
        'rating' => 'integer',
        'is_verified_purchase' => 'boolean',
        'is_approved' => 'boolean',
        'helpful_count' => 'integer',
        'not_helpful_count' => 'integer',
    ];

    /**
     * Get the product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark as helpful
     */
    public function markAsHelpful(): void
    {
        $this->helpful_count++;
        $this->save();
    }

    /**
     * Mark as not helpful
     */
    public function markAsNotHelpful(): void
    {
        $this->not_helpful_count++;
        $this->save();
    }

    /**
     * Get helpfulness percentage
     */
    public function getHelpfulnessPercentage(): int
    {
        $total = $this->helpful_count + $this->not_helpful_count;
        
        if ($total === 0) {
            return 0;
        }
        
        return (int) round(($this->helpful_count / $total) * 100);
    }
}