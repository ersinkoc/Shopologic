<?php

declare(strict_types=1);

namespace Shopologic\Core\Ecommerce\Cart;

use Shopologic\Core\Container\Container;
use Shopologic\Core\Session\SessionManager;
use Shopologic\Core\Events\EventDispatcher;
use Shopologic\Core\Ecommerce\Models\Product;
use Shopologic\Core\Ecommerce\Models\ProductVariant;
use Shopologic\Core\Ecommerce\Models\Order;
use Shopologic\Core\Ecommerce\Models\OrderItem;
use Shopologic\Core\Ecommerce\Customer\Customer;
use Shopologic\Core\Database\DB;

class CartService
{
    protected Container $container;
    protected Cart $cart;
    
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->cart = new Cart(
            $container->get(SessionManager::class),
            $container->get(EventDispatcher::class)
        );
    }
    
    /**
     * Get the current cart instance
     */
    public function getCart(): Cart
    {
        return $this->cart;
    }
    
    /**
     * Add product to cart
     */
    public function addProduct(int $productId, int $quantity = 1, ?int $variantId = null): CartItem
    {
        $product = Product::findOrFail($productId);
        $variant = $variantId ? ProductVariant::findOrFail($variantId) : null;
        
        // Validate product is purchasable
        if (!$product->is_active) {
            throw new \Exception('Product is not available');
        }
        
        // Validate stock
        $availableQty = $variant ? $variant->getAvailableQuantity() : $product->getAvailableQuantity();
        if ($availableQty < $quantity) {
            throw new \Exception("Only {$availableQty} items available");
        }
        
        return $this->cart->add($product, $quantity, $variant);
    }
    
    /**
     * Update cart item quantity
     */
    public function updateQuantity(string $cartKey, int $quantity): ?CartItem
    {
        if ($quantity <= 0) {
            return $this->cart->remove($cartKey);
        }
        
        // Validate stock for the new quantity
        $item = $this->cart->get($cartKey);
        if ($item) {
            $availableQty = $item->variant ? 
                $item->variant->getAvailableQuantity() : 
                $item->product->getAvailableQuantity();
                
            if ($availableQty < $quantity) {
                throw new \Exception("Only {$availableQty} items available");
            }
        }
        
        return $this->cart->update($cartKey, $quantity);
    }
    
    /**
     * Remove item from cart
     */
    public function removeItem(string $cartKey): ?CartItem
    {
        return $this->cart->remove($cartKey);
    }
    
    /**
     * Clear the cart
     */
    public function clear(): void
    {
        $this->cart->clear();
    }
    
    /**
     * Apply coupon code
     */
    public function applyCoupon(string $code): bool
    {
        // TODO: Implement coupon validation
        return $this->cart->applyCoupon($code);
    }
    
    /**
     * Remove coupon
     */
    public function removeCoupon(): void
    {
        $this->cart->removeCoupon();
    }
    
    /**
     * Convert cart to order
     */
    public function convertToOrder(Customer $customer, array $shippingAddress, array $billingAddress): Order
    {
        // Validate cart has items
        if ($this->cart->isEmpty()) {
            throw new \Exception('Cart is empty');
        }
        
        // Validate stock
        $stockErrors = $this->cart->validateStock();
        if (!empty($stockErrors)) {
            throw new \Exception('Some items are out of stock');
        }
        
        DB::beginTransaction();
        
        try {
            // Create order
            $order = new Order();
            $order->customer_id = $customer->id;
            $order->order_number = $this->generateOrderNumber();
            $order->status = 'pending';
            $order->currency = 'USD'; // TODO: Make configurable
            $order->subtotal = $this->cart->getSubtotal();
            $order->discount_amount = $this->cart->getDiscount();
            $order->tax_amount = $this->cart->getTax();
            $order->shipping_amount = $this->cart->getShipping();
            $order->total = $this->cart->getTotal();
            $order->coupon_code = $this->cart->getCouponCode();
            
            // Set addresses
            $order->shipping_name = $shippingAddress['name'];
            $order->shipping_address = $shippingAddress['address'];
            $order->shipping_city = $shippingAddress['city'];
            $order->shipping_state = $shippingAddress['state'];
            $order->shipping_zip = $shippingAddress['zip'];
            $order->shipping_country = $shippingAddress['country'];
            $order->shipping_phone = $shippingAddress['phone'] ?? null;
            
            $order->billing_name = $billingAddress['name'];
            $order->billing_address = $billingAddress['address'];
            $order->billing_city = $billingAddress['city'];
            $order->billing_state = $billingAddress['state'];
            $order->billing_zip = $billingAddress['zip'];
            $order->billing_country = $billingAddress['country'];
            $order->billing_phone = $billingAddress['phone'] ?? null;
            
            $order->save();
            
            // Create order items
            foreach ($this->cart->items() as $cartItem) {
                $orderItem = new OrderItem();
                $orderItem->order_id = $order->id;
                $orderItem->product_id = $cartItem->product->id;
                $orderItem->product_variant_id = $cartItem->variant?->id;
                $orderItem->product_name = $cartItem->product->name;
                $orderItem->product_sku = $cartItem->variant ? 
                    $cartItem->variant->sku : 
                    $cartItem->product->sku;
                $orderItem->price = $cartItem->getPrice();
                $orderItem->quantity = $cartItem->quantity;
                $orderItem->subtotal = $cartItem->getTotal();
                
                // Store variant options
                if ($cartItem->variant) {
                    $orderItem->variant_options = json_encode($cartItem->variant->getOptionsArray());
                }
                
                $orderItem->save();
                
                // Reserve inventory
                if ($cartItem->variant) {
                    $cartItem->variant->reserved_quantity += $cartItem->quantity;
                    $cartItem->variant->save();
                } else {
                    $cartItem->product->reserved_quantity += $cartItem->quantity;
                    $cartItem->product->save();
                }
            }
            
            // Clear cart after successful order creation
            $this->cart->clear();
            
            DB::commit();
            
            // Dispatch order created event
            $this->container->get(EventDispatcher::class)->dispatch(
                new \Shopologic\Core\Ecommerce\Order\Events\OrderCreated($order)
            );
            
            return $order;
            
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }
    
    /**
     * Generate unique order number
     */
    protected function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
            $exists = Order::where('order_number', $number)->exists();
        } while ($exists);
        
        return $number;
    }
    
    /**
     * Get cart data for API response
     */
    public function toArray(): array
    {
        $items = [];
        
        foreach ($this->cart->items() as $key => $item) {
            $items[] = [
                'key' => $key,
                'product' => [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'slug' => $item->product->slug,
                    'image' => $item->product->getMainImage()?->url,
                ],
                'variant' => $item->variant ? [
                    'id' => $item->variant->id,
                    'sku' => $item->variant->sku,
                    'options' => $item->variant->getOptionsArray(),
                ] : null,
                'price' => $item->getPrice(),
                'quantity' => $item->quantity,
                'total' => $item->getTotal(),
            ];
        }
        
        return [
            'items' => $items,
            'summary' => $this->cart->getSummary(),
            'coupon_code' => $this->cart->getCouponCode(),
        ];
    }
}