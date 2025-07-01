<?php

declare(strict_types=1);

namespace Shopologic\Core\Order;

use Shopologic\Core\Plugin\HookSystem;
use Shopologic\Core\Cart\CartService;

class OrderService
{
    private array $orders = [];
    
    public function __construct()
    {
        $this->loadFromSession();
    }
    
    /**
     * Create order from cart
     */
    public function createOrder(CartService $cart, array $customerData, array $shippingData, array $paymentData): ?array
    {
        try {
            if ($cart->isEmpty()) {
                throw new \Exception('Cannot create order from empty cart');
            }
            
            // Generate order ID
            $orderId = $this->generateOrderId();
            
            // Get cart items and totals
            $items = $cart->getItems();
            $totals = $cart->getTotals();
            
            // Apply filters to allow plugins to modify order data
            $customerData = HookSystem::applyFilters('order.customer_data', $customerData);
            $shippingData = HookSystem::applyFilters('order.shipping_data', $shippingData);
            $paymentData = HookSystem::applyFilters('order.payment_data', $paymentData);
            
            // Create order array
            $order = [
                'id' => $orderId,
                'order_number' => $this->generateOrderNumber(),
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                
                // Customer information
                'customer' => [
                    'email' => $customerData['email'] ?? '',
                    'first_name' => $customerData['first_name'] ?? '',
                    'last_name' => $customerData['last_name'] ?? '',
                    'phone' => $customerData['phone'] ?? '',
                ],
                
                // Billing address
                'billing_address' => [
                    'first_name' => $customerData['billing_first_name'] ?? $customerData['first_name'] ?? '',
                    'last_name' => $customerData['billing_last_name'] ?? $customerData['last_name'] ?? '',
                    'company' => $customerData['billing_company'] ?? '',
                    'address_1' => $customerData['billing_address_1'] ?? '',
                    'address_2' => $customerData['billing_address_2'] ?? '',
                    'city' => $customerData['billing_city'] ?? '',
                    'state' => $customerData['billing_state'] ?? '',
                    'postcode' => $customerData['billing_postcode'] ?? '',
                    'country' => $customerData['billing_country'] ?? 'US',
                ],
                
                // Shipping address
                'shipping_address' => [
                    'first_name' => $shippingData['shipping_first_name'] ?? $customerData['first_name'] ?? '',
                    'last_name' => $shippingData['shipping_last_name'] ?? $customerData['last_name'] ?? '',
                    'company' => $shippingData['shipping_company'] ?? '',
                    'address_1' => $shippingData['shipping_address_1'] ?? '',
                    'address_2' => $shippingData['shipping_address_2'] ?? '',
                    'city' => $shippingData['shipping_city'] ?? '',
                    'state' => $shippingData['shipping_state'] ?? '',
                    'postcode' => $shippingData['shipping_postcode'] ?? '',
                    'country' => $shippingData['shipping_country'] ?? 'US',
                ],
                
                // Order items
                'items' => array_map(function($item) {
                    return [
                        'product_id' => $item['product_id'],
                        'name' => $item['name'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'total' => $item['price'] * $item['quantity'],
                        'options' => $item['options'] ?? [],
                    ];
                }, $items),
                
                // Order totals
                'totals' => $totals,
                
                // Payment information
                'payment' => [
                    'method' => $paymentData['payment_method'] ?? 'manual',
                    'status' => 'pending',
                    'transaction_id' => null,
                    'gateway_response' => null,
                ],
                
                // Shipping information
                'shipping' => [
                    'method' => $shippingData['shipping_method'] ?? 'standard',
                    'cost' => $totals['shipping'] ?? 0,
                    'tracking_number' => null,
                ],
                
                // Notes and metadata
                'notes' => $customerData['order_notes'] ?? '',
                'metadata' => []
            ];
            
            // Allow plugins to modify the order before creation
            $order = HookSystem::applyFilters('order.before_create', $order);
            
            // Store the order
            $this->orders[$orderId] = $order;
            $this->saveToSession();
            
            // Fire order created action
            HookSystem::doAction('order.created', $order);
            
            return $order;
            
        } catch (\Exception $e) {
            error_log('Order creation error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get order by ID
     */
    public function getOrder(string $orderId): ?array
    {
        return $this->orders[$orderId] ?? null;
    }
    
    /**
     * Update order status
     */
    public function updateOrderStatus(string $orderId, string $status): bool
    {
        if (!isset($this->orders[$orderId])) {
            return false;
        }
        
        $oldStatus = $this->orders[$orderId]['status'];
        $this->orders[$orderId]['status'] = $status;
        $this->orders[$orderId]['updated_at'] = date('Y-m-d H:i:s');
        
        $this->saveToSession();
        
        // Fire status change action
        HookSystem::doAction('order.status_changed', $this->orders[$orderId], $oldStatus, $status);
        
        return true;
    }
    
    /**
     * Update payment status
     */
    public function updatePaymentStatus(string $orderId, string $status, ?string $transactionId = null, ?array $gatewayResponse = null): bool
    {
        if (!isset($this->orders[$orderId])) {
            return false;
        }
        
        $this->orders[$orderId]['payment']['status'] = $status;
        $this->orders[$orderId]['payment']['transaction_id'] = $transactionId;
        $this->orders[$orderId]['payment']['gateway_response'] = $gatewayResponse;
        $this->orders[$orderId]['updated_at'] = date('Y-m-d H:i:s');
        
        $this->saveToSession();
        
        // Fire payment status change action
        HookSystem::doAction('order.payment_status_changed', $this->orders[$orderId], $status);
        
        return true;
    }
    
    /**
     * Process payment
     */
    public function processPayment(string $orderId, array $paymentData): array
    {
        $order = $this->getOrder($orderId);
        if (!$order) {
            return ['success' => false, 'message' => 'Order not found'];
        }
        
        // Allow plugins to process payment
        $result = HookSystem::applyFilters('order.process_payment', [
            'success' => false,
            'message' => 'No payment processor available'
        ], $order, $paymentData);
        
        // If no plugin handled payment, simulate basic processing
        if (!$result['success']) {
            $paymentMethod = $paymentData['payment_method'] ?? 'manual';
            
            switch ($paymentMethod) {
                case 'card':
                    $result = $this->processCardPayment($order, $paymentData);
                    break;
                case 'paypal':
                    $result = $this->processPayPalPayment($order, $paymentData);
                    break;
                case 'bank_transfer':
                    $result = $this->processBankTransferPayment($order, $paymentData);
                    break;
                case 'cash_on_delivery':
                    $result = $this->processCashOnDeliveryPayment($order, $paymentData);
                    break;
                default:
                    $result = ['success' => false, 'message' => 'Unsupported payment method'];
            }
        }
        
        // Update payment status based on result
        if ($result['success']) {
            $this->updatePaymentStatus(
                $orderId, 
                'completed', 
                $result['transaction_id'] ?? null,
                $result['gateway_response'] ?? null
            );
            $this->updateOrderStatus($orderId, 'processing');
        } else {
            $this->updatePaymentStatus($orderId, 'failed');
        }
        
        return $result;
    }
    
    /**
     * Get all orders
     */
    public function getAllOrders(): array
    {
        return $this->orders;
    }
    
    /**
     * Generate unique order ID
     */
    private function generateOrderId(): string
    {
        return uniqid('order_', true);
    }
    
    /**
     * Generate human-readable order number
     */
    private function generateOrderNumber(): string
    {
        return 'ORD-' . date('Y') . '-' . str_pad((string)rand(1000, 9999), 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Simulate card payment processing
     */
    private function processCardPayment(array $order, array $paymentData): array
    {
        // Simulate payment processing delay
        usleep(500000); // 0.5 seconds
        
        // Basic validation
        $cardNumber = $paymentData['card_number'] ?? '';
        $expiryMonth = $paymentData['expiry_month'] ?? '';
        $expiryYear = $paymentData['expiry_year'] ?? '';
        $cvv = $paymentData['cvv'] ?? '';
        
        if (empty($cardNumber) || empty($expiryMonth) || empty($expiryYear) || empty($cvv)) {
            return ['success' => false, 'message' => 'Missing required card information'];
        }
        
        // Remove spaces and validate card number format
        $cardNumber = str_replace(' ', '', $cardNumber);
        if (!preg_match('/^\d{13,19}$/', $cardNumber)) {
            return ['success' => false, 'message' => 'Invalid card number format'];
        }
        
        // Simulate different card responses
        $lastDigit = (int)substr($cardNumber, -1);
        if ($lastDigit % 2 === 0) {
            // Even last digit = success
            return [
                'success' => true,
                'message' => 'Payment processed successfully',
                'transaction_id' => 'txn_' . uniqid(),
                'gateway_response' => [
                    'status' => 'approved',
                    'authorization_code' => 'AUTH' . rand(100000, 999999),
                    'last_four' => substr($cardNumber, -4)
                ]
            ];
        } else {
            // Odd last digit = failure
            return ['success' => false, 'message' => 'Payment declined by bank'];
        }
    }
    
    /**
     * Simulate PayPal payment processing
     */
    private function processPayPalPayment(array $order, array $paymentData): array
    {
        return [
            'success' => true,
            'message' => 'PayPal payment initiated',
            'transaction_id' => 'pp_' . uniqid(),
            'gateway_response' => [
                'status' => 'pending',
                'paypal_order_id' => 'PAYPAL' . rand(100000, 999999),
                'redirect_url' => 'https://www.paypal.com/checkoutnow?token=' . uniqid()
            ]
        ];
    }
    
    /**
     * Simulate bank transfer payment
     */
    private function processBankTransferPayment(array $order, array $paymentData): array
    {
        return [
            'success' => true,
            'message' => 'Bank transfer details sent. Payment pending confirmation.',
            'transaction_id' => 'bt_' . uniqid(),
            'gateway_response' => [
                'status' => 'pending',
                'bank_details' => [
                    'account_name' => 'Shopologic Ltd',
                    'account_number' => '1234567890',
                    'routing_number' => '021000021',
                    'reference' => $order['order_number']
                ]
            ]
        ];
    }
    
    /**
     * Process cash on delivery
     */
    private function processCashOnDeliveryPayment(array $order, array $paymentData): array
    {
        return [
            'success' => true,
            'message' => 'Cash on delivery order placed successfully',
            'transaction_id' => 'cod_' . uniqid(),
            'gateway_response' => [
                'status' => 'pending',
                'payment_due' => $order['totals']['total'],
                'collection_method' => 'cash_on_delivery'
            ]
        ];
    }
    
    /**
     * Load orders from session
     */
    private function loadFromSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $this->orders = $_SESSION['orders'] ?? [];
    }
    
    /**
     * Save orders to session
     */
    private function saveToSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['orders'] = $this->orders;
    }
}