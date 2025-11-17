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
            
            // Create order items and reserve inventory with row-level locking
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

                // SECURITY FIX: Reserve inventory with row-level locking to prevent race conditions
                // Use SELECT FOR UPDATE to lock the row and prevent concurrent modifications
                if ($cartItem->variant) {
                    $connection = DB::connection();

                    // Lock and fetch current reserved quantity
                    $query = "SELECT id, reserved_quantity, quantity, track_quantity, allow_backorder
                              FROM product_variants WHERE id = ? FOR UPDATE";
                    $stmt = $connection->prepare($query);
                    $stmt->execute([$cartItem->variant->id]);
                    $variantData = $stmt->fetch(\PDO::FETCH_ASSOC);

                    if (!$variantData) {
                        throw new \Exception('Variant not found: ' . $cartItem->variant->id);
                    }

                    $currentReserved = (int)$variantData['reserved_quantity'];
                    $currentQuantity = (int)$variantData['quantity'];
                    $trackQuantity = (bool)$variantData['track_quantity'];
                    $allowBackorder = (bool)$variantData['allow_backorder'];

                    // Validate stock is still available
                    if ($trackQuantity && !$allowBackorder) {
                        $availableQty = $currentQuantity - $currentReserved;
                        if ($availableQty < $cartItem->quantity) {
                            throw new \Exception(
                                "Insufficient stock for {$cartItem->product->name}. " .
                                "Only {$availableQty} available."
                            );
                        }
                    }

                    // Atomically update reserved quantity
                    $newReserved = $currentReserved + $cartItem->quantity;
                    $updateQuery = "UPDATE product_variants SET reserved_quantity = ? WHERE id = ?";
                    $updateStmt = $connection->prepare($updateQuery);
                    $updateStmt->execute([$newReserved, $cartItem->variant->id]);

                } else {
                    $connection = DB::connection();

                    // Lock and fetch current reserved quantity for product
                    $query = "SELECT id, reserved_quantity, quantity, track_quantity, allow_backorder
                              FROM products WHERE id = ? FOR UPDATE";
                    $stmt = $connection->prepare($query);
                    $stmt->execute([$cartItem->product->id]);
                    $productData = $stmt->fetch(\PDO::FETCH_ASSOC);

                    if (!$productData) {
                        throw new \Exception('Product not found: ' . $cartItem->product->id);
                    }

                    $currentReserved = (int)$productData['reserved_quantity'];
                    $currentQuantity = (int)$productData['quantity'];
                    $trackQuantity = (bool)$productData['track_quantity'];
                    $allowBackorder = (bool)$productData['allow_backorder'];

                    // Validate stock is still available
                    if ($trackQuantity && !$allowBackorder) {
                        $availableQty = $currentQuantity - $currentReserved;
                        if ($availableQty < $cartItem->quantity) {
                            throw new \Exception(
                                "Insufficient stock for {$cartItem->product->name}. " .
                                "Only {$availableQty} available."
                            );
                        }
                    }

                    // Atomically update reserved quantity
                    $newReserved = $currentReserved + $cartItem->quantity;
                    $updateQuery = "UPDATE products SET reserved_quantity = ? WHERE id = ?";
                    $updateStmt = $connection->prepare($updateQuery);
                    $updateStmt->execute([$newReserved, $cartItem->product->id]);
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
     * Generate unique order number with race condition protection
     * BUG-QUALITY-007 FIX: Improved randomness and added unique constraint handling
     *
     * Note: This method still has a theoretical TOCTOU race condition. For production,
     * ensure the orders.order_number column has a UNIQUE constraint in the database,
     * and handle duplicate key exceptions when creating the order.
     */
    protected function generateOrderNumber(): string
    {
        $maxAttempts = 10;
        $attempt = 0;

        do {
            // Use cryptographically secure random instead of md5(uniqid())
            $randomPart = strtoupper(bin2hex(random_bytes(4)));
            $number = 'ORD-' . date('Ymd') . '-' . $randomPart;
            $exists = Order::where('order_number', $number)->exists();
            $attempt++;
        } while ($exists && $attempt < $maxAttempts);

        if ($attempt >= $maxAttempts) {
            // Fallback to UUID-style to ensure uniqueness
            $number = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(8)));
        }

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