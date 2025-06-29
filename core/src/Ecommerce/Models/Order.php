<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Models;

use Shopologic\Core\Database\Model;
use Shopologic\Core\Auth\Models\User;
use Shopologic\Core\MultiStore\Traits\BelongsToStore;

class Order extends Model
{
    use BelongsToStore;
    protected string $table = 'orders';
    
    protected array $fillable = [
        'order_number',
        'user_id',
        'customer_email',
        'customer_name',
        'customer_phone',
        'billing_address',
        'shipping_address',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'shipping_amount',
        'total_amount',
        'currency',
        'status',
        'payment_status',
        'payment_method',
        'payment_id',
        'shipping_status',
        'shipping_method',
        'tracking_number',
        'notes',
        'coupon_code',
        'ip_address',
        'user_agent',
        'ordered_at',
        'paid_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
    ];
    
    protected array $casts = [
        'user_id' => 'integer',
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'ordered_at' => 'datetime',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Order statuses
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_FAILED = 'failed';

    // Payment statuses
    const PAYMENT_PENDING = 'pending';
    const PAYMENT_PAID = 'paid';
    const PAYMENT_FAILED = 'failed';
    const PAYMENT_REFUNDED = 'refunded';
    const PAYMENT_PARTIALLY_REFUNDED = 'partially_refunded';

    // Shipping statuses
    const SHIPPING_PENDING = 'pending';
    const SHIPPING_PROCESSING = 'processing';
    const SHIPPING_SHIPPED = 'shipped';
    const SHIPPING_DELIVERED = 'delivered';
    const SHIPPING_RETURNED = 'returned';

    /**
     * Boot method
     */
    protected static function boot(): void
    {
        parent::boot();
        
        static::creating(function ($order) {
            if (!$order->order_number) {
                $order->order_number = $order->generateOrderNumber();
            }
            if (!$order->ordered_at) {
                $order->ordered_at = new \DateTime();
            }
        });
    }

    /**
     * Get the user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get order items
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get order status history
     */
    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get order transactions
     */
    public function transactions()
    {
        return $this->hasMany(OrderTransaction::class)->orderBy('created_at', 'desc');
    }

    /**
     * Generate unique order number
     */
    public function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $timestamp = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        
        return "{$prefix}-{$timestamp}-{$random}";
    }

    /**
     * Update order status
     */
    public function updateStatus(string $status, string $comment = ''): bool
    {
        $oldStatus = $this->status;
        $this->status = $status;
        
        if ($this->save()) {
            $this->statusHistory()->create([
                'from_status' => $oldStatus,
                'to_status' => $status,
                'comment' => $comment,
            ]);
            
            return true;
        }
        
        return false;
    }

    /**
     * Mark as paid
     */
    public function markAsPaid(string $paymentId = null): bool
    {
        $this->payment_status = self::PAYMENT_PAID;
        $this->paid_at = new \DateTime();
        
        if ($paymentId) {
            $this->payment_id = $paymentId;
        }
        
        return $this->save() && $this->updateStatus(self::STATUS_PROCESSING, 'Payment received');
    }

    /**
     * Mark as shipped
     */
    public function markAsShipped(string $trackingNumber = null): bool
    {
        $this->shipping_status = self::SHIPPING_SHIPPED;
        $this->shipped_at = new \DateTime();
        
        if ($trackingNumber) {
            $this->tracking_number = $trackingNumber;
        }
        
        return $this->save();
    }

    /**
     * Mark as delivered
     */
    public function markAsDelivered(): bool
    {
        $this->shipping_status = self::SHIPPING_DELIVERED;
        $this->delivered_at = new \DateTime();
        
        return $this->save() && $this->updateStatus(self::STATUS_COMPLETED, 'Order delivered');
    }

    /**
     * Cancel order
     */
    public function cancel(string $reason = ''): bool
    {
        if (!$this->canBeCancelled()) {
            return false;
        }
        
        $this->cancelled_at = new \DateTime();
        
        // Restore inventory
        foreach ($this->items as $item) {
            if ($item->variant) {
                $item->variant->increaseStock($item->quantity);
            } else {
                $item->product->increaseStock($item->quantity);
            }
        }
        
        return $this->updateStatus(self::STATUS_CANCELLED, $reason);
    }

    /**
     * Check if order can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING
        ]) && $this->shipping_status !== self::SHIPPING_SHIPPED;
    }

    /**
     * Process refund
     */
    public function refund(float $amount, string $reason = ''): bool
    {
        if ($amount > $this->total_amount) {
            return false;
        }
        
        $this->transactions()->create([
            'type' => 'refund',
            'amount' => $amount,
            'status' => 'completed',
            'notes' => $reason,
        ]);
        
        if ($amount >= $this->total_amount) {
            $this->payment_status = self::PAYMENT_REFUNDED;
            $this->updateStatus(self::STATUS_REFUNDED, $reason);
        } else {
            $this->payment_status = self::PAYMENT_PARTIALLY_REFUNDED;
        }
        
        return $this->save();
    }

    /**
     * Get refunded amount
     */
    public function getRefundedAmount(): float
    {
        return $this->transactions()
            ->where('type', 'refund')
            ->where('status', 'completed')
            ->sum('amount');
    }

    /**
     * Get remaining refundable amount
     */
    public function getRefundableAmount(): float
    {
        return $this->total_amount - $this->getRefundedAmount();
    }

    /**
     * Calculate totals
     */
    public function calculateTotals(): void
    {
        $subtotal = 0;
        
        foreach ($this->items as $item) {
            $subtotal += $item->total;
        }
        
        $this->subtotal = $subtotal;
        $this->total_amount = $subtotal - $this->discount_amount + $this->tax_amount + $this->shipping_amount;
    }

    /**
     * Create from cart
     */
    public static function createFromCart(Cart $cart, array $data): self
    {
        $order = new self($data);
        
        // Set amounts from cart
        $order->subtotal = $cart->getSubtotal();
        $order->discount_amount = $cart->getDiscount();
        $order->tax_amount = $cart->getTax();
        $order->shipping_amount = $cart->getShipping();
        $order->total_amount = $cart->getTotal();
        $order->coupon_code = $cart->getCouponCode();
        
        $order->save();
        
        // Create order items
        foreach ($cart->items() as $cartItem) {
            $order->items()->create([
                'product_id' => $cartItem->product->id,
                'variant_id' => $cartItem->variant?->id,
                'name' => $cartItem->getName(),
                'sku' => $cartItem->getSku(),
                'price' => $cartItem->getPrice(),
                'quantity' => $cartItem->quantity,
                'total' => $cartItem->getTotal(),
            ]);
            
            // Decrease stock
            if ($cartItem->variant) {
                $cartItem->variant->decreaseStock($cartItem->quantity);
            } else {
                $cartItem->product->decreaseStock($cartItem->quantity);
            }
        }
        
        return $order;
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_PROCESSING => 'info',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_CANCELLED => 'danger',
            self::STATUS_REFUNDED => 'secondary',
            self::STATUS_FAILED => 'danger',
            default => 'secondary'
        };
    }

    /**
     * Get payment status badge class
     */
    public function getPaymentStatusBadgeClass(): string
    {
        return match($this->payment_status) {
            self::PAYMENT_PENDING => 'warning',
            self::PAYMENT_PAID => 'success',
            self::PAYMENT_FAILED => 'danger',
            self::PAYMENT_REFUNDED => 'secondary',
            self::PAYMENT_PARTIALLY_REFUNDED => 'info',
            default => 'secondary'
        };
    }
}