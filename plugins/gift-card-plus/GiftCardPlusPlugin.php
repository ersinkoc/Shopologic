<?php

declare(strict_types=1);
namespace Shopologic\Plugins\GiftCardPlus;

use Shopologic\Core\Plugin\AbstractPlugin;
use Shopologic\Core\Plugin\Hook;

/**
 * Gift Card Plus Plugin
 * 
 * Advanced gift card system with scheduling, personalization, and social gifting
 */
class GiftCardPlusPlugin extends AbstractPlugin
{
    private $giftCardManager;
    private $deliveryScheduler;
    private $templateEngine;
    private $balanceTracker;

    public function boot(): void
    {
        $this->registerServices();
        $this->registerHooks();
        $this->registerRoutes();
        $this->scheduleDeliveries();
    }

    private function registerServices(): void
    {
        $this->giftCardManager = new Services\GiftCardManager($this->api);
        $this->deliveryScheduler = new Services\DeliveryScheduler($this->api);
        $this->templateEngine = new Services\TemplateEngine($this->api);
        $this->balanceTracker = new Services\BalanceTracker($this->api);
    }

    private function registerHooks(): void
    {
        // Product integration
        Hook::addFilter('product.types', [$this, 'addGiftCardType'], 10, 1);
        Hook::addFilter('product.form', [$this, 'addGiftCardFields'], 10, 2);
        Hook::addFilter('product.display', [$this, 'customizeGiftCardDisplay'], 10, 2);
        
        // Cart and checkout
        Hook::addFilter('cart.item_added', [$this, 'processGiftCardAddition'], 10, 2);
        Hook::addFilter('checkout.validation', [$this, 'validateGiftCardPurchase'], 10, 1);
        Hook::addAction('order.completed', [$this, 'generateGiftCards'], 10, 1);
        
        // Payment integration
        Hook::addFilter('checkout.payment_methods', [$this, 'addGiftCardPayment'], 10, 1);
        Hook::addFilter('payment.process', [$this, 'processGiftCardPayment'], 10, 2);
        Hook::addAction('order.refunded', [$this, 'handleGiftCardRefund'], 10, 2);
        
        // Gift card management
        Hook::addAction('gift_card.redeemed', [$this, 'trackRedemption'], 10, 2);
        Hook::addAction('gift_card.expired', [$this, 'handleExpiration'], 10, 1);
        
        // Customer features
        Hook::addFilter('customer.dashboard', [$this, 'addGiftCardSection'], 10, 2);
        Hook::addFilter('customer.account_menu', [$this, 'addGiftCardMenu'], 10, 1);
        
        // Frontend customization
        Hook::addAction('frontend.gift_card_form', [$this, 'renderGiftCardForm'], 10, 1);
        Hook::addFilter('email.gift_card', [$this, 'customizeGiftCardEmail'], 10, 2);
    }

    public function addGiftCardType($types): array
    {
        $types['gift_card'] = [
            'name' => 'Gift Card',
            'icon' => 'gift',
            'features' => ['customizable_amount', 'scheduling', 'personalization'],
            'requires_shipping' => false
        ];
        
        return $types;
    }

    public function customizeGiftCardDisplay($display, $product): string
    {
        if ($product->type !== 'gift_card') {
            return $display;
        }
        
        $giftCardOptions = $this->api->view('gift-card/product-options', [
            'product' => $product,
            'preset_amounts' => $this->getPresetAmounts(),
            'custom_amount_allowed' => true,
            'min_amount' => 10,
            'max_amount' => $this->getConfig('max_gift_card_amount', 500),
            'templates' => $this->templateEngine->getAvailableTemplates(),
            'delivery_options' => ['immediate', 'scheduled', 'print_at_home'],
            'personalization_enabled' => $this->getConfig('enable_custom_designs', true)
        ]);
        
        return str_replace('<!-- gift-card-options -->', $giftCardOptions, $display);
    }

    public function processGiftCardAddition($cartItem, $product): array
    {
        if ($product->type !== 'gift_card') {
            return $cartItem;
        }
        
        // Validate gift card data
        $giftCardData = $cartItem['gift_card_data'] ?? [];
        
        $validated = $this->validateGiftCardData($giftCardData);
        if (!$validated['valid']) {
            throw new \Exception($validated['error']);
        }
        
        // Process custom amount
        if ($giftCardData['amount_type'] === 'custom') {
            $cartItem['price'] = $giftCardData['custom_amount'];
        }
        
        // Store gift card metadata
        $cartItem['metadata']['gift_card'] = [
            'recipient_email' => $giftCardData['recipient_email'],
            'recipient_name' => $giftCardData['recipient_name'],
            'sender_name' => $giftCardData['sender_name'],
            'message' => $giftCardData['message'] ?? '',
            'delivery_date' => $giftCardData['delivery_date'] ?? 'immediate',
            'template_id' => $giftCardData['template_id'],
            'amount' => $cartItem['price']
        ];
        
        return $cartItem;
    }

    public function generateGiftCards($order): void
    {
        foreach ($order->items as $item) {
            if ($item->product_type === 'gift_card') {
                $giftCard = $this->createGiftCard($order, $item);
                
                if ($giftCard->delivery_date === 'immediate') {
                    $this->deliverGiftCard($giftCard);
                } else {
                    $this->scheduleGiftCardDelivery($giftCard);
                }
            }
        }
    }

    public function addGiftCardPayment($methods): array
    {
        $customer = $this->api->auth()->user();
        if (!$customer) {
            return $methods;
        }
        
        $giftCards = $this->giftCardManager->getCustomerGiftCards($customer->id);
        $totalBalance = $this->calculateTotalBalance($giftCards);
        
        if ($totalBalance > 0) {
            $methods['gift_card'] = [
                'id' => 'gift_card',
                'name' => 'Gift Card',
                'description' => "Available balance: $" . number_format($totalBalance, 2),
                'icon' => 'gift-card',
                'available_balance' => $totalBalance,
                'cards' => $this->formatGiftCardsForCheckout($giftCards)
            ];
        }
        
        return $methods;
    }

    public function processGiftCardPayment($payment, $order): array
    {
        if ($payment['method'] !== 'gift_card') {
            return $payment;
        }
        
        $cardsToUse = $payment['gift_cards'] ?? [];
        $amountToCharge = $order->total;
        $transactions = [];
        
        foreach ($cardsToUse as $cardCode => $useAmount) {
            $giftCard = $this->giftCardManager->getByCode($cardCode);
            
            if (!$giftCard || !$this->validateGiftCard($giftCard)) {
                throw new \Exception("Invalid gift card: {$cardCode}");
            }
            
            $chargeAmount = min($useAmount, $giftCard->balance, $amountToCharge);
            
            if ($chargeAmount > 0) {
                $transaction = $this->giftCardManager->debit($giftCard->id, $chargeAmount, [
                    'order_id' => $order->id,
                    'description' => "Order #{$order->id}"
                ]);
                
                $transactions[] = $transaction;
                $amountToCharge -= $chargeAmount;
                
                Hook::doAction('gift_card.redeemed', $giftCard, $transaction);
            }
            
            if ($amountToCharge <= 0) {
                break;
            }
        }
        
        $payment['gift_card_transactions'] = $transactions;
        $payment['amount_paid'] = $order->total - $amountToCharge;
        $payment['remaining_balance'] = $amountToCharge;
        
        return $payment;
    }

    public function addGiftCardSection($dashboard, $customer): string
    {
        $giftCards = $this->giftCardManager->getCustomerGiftCards($customer->id);
        $sentGiftCards = $this->giftCardManager->getSentGiftCards($customer->id);
        
        $section = $this->api->view('gift-card/dashboard-section', [
            'owned_cards' => $giftCards,
            'sent_cards' => $sentGiftCards,
            'total_balance' => $this->calculateTotalBalance($giftCards),
            'transaction_history' => $this->getRecentTransactions($customer->id),
            'can_check_balance' => true,
            'can_combine_cards' => true
        ]);
        
        return $dashboard . $section;
    }

    public function renderGiftCardForm($context): void
    {
        echo $this->api->view('gift-card/purchase-form', [
            'templates' => $this->templateEngine->getAvailableTemplates(),
            'occasions' => $this->getOccasions(),
            'delivery_options' => $this->getDeliveryOptions(),
            'personalization_options' => [
                'custom_message' => true,
                'custom_image' => $this->getConfig('enable_custom_designs', true),
                'video_message' => false,
                'font_choices' => ['elegant', 'playful', 'modern', 'classic']
            ],
            'social_sharing' => $this->getConfig('enable_social_sharing', true),
            'bulk_purchase' => true
        ]);
    }

    private function createGiftCard($order, $item): object
    {
        $metadata = $item->metadata['gift_card'];
        $code = $this->generateUniqueCode();
        
        $giftCard = $this->giftCardManager->create([
            'code' => $code,
            'initial_balance' => $metadata['amount'],
            'current_balance' => $metadata['amount'],
            'purchaser_id' => $order->customer_id,
            'purchaser_email' => $order->customer_email,
            'recipient_email' => $metadata['recipient_email'],
            'recipient_name' => $metadata['recipient_name'],
            'sender_name' => $metadata['sender_name'],
            'message' => $metadata['message'],
            'template_id' => $metadata['template_id'],
            'delivery_date' => $metadata['delivery_date'],
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'expires_at' => $this->calculateExpiryDate(),
            'status' => 'pending_delivery'
        ]);
        
        // Generate card design
        if ($metadata['custom_design'] ?? false) {
            $this->templateEngine->generateCustomDesign($giftCard, $metadata['design_data']);
        }
        
        return $giftCard;
    }

    private function deliverGiftCard($giftCard): void
    {
        // Generate gift card email/PDF
        $deliveryData = $this->prepareDeliveryData($giftCard);
        
        // Send based on delivery method
        if ($giftCard->delivery_method === 'email') {
            $this->sendGiftCardEmail($giftCard, $deliveryData);
        } elseif ($giftCard->delivery_method === 'print') {
            $this->generatePrintableCard($giftCard, $deliveryData);
        }
        
        // Update status
        $this->giftCardManager->updateStatus($giftCard->id, 'delivered');
        
        // Track delivery
        $this->trackDelivery($giftCard);
        
        // Enable social sharing if configured
        if ($this->getConfig('enable_social_sharing', true)) {
            $this->enableSocialSharing($giftCard);
        }
    }

    private function scheduleGiftCardDelivery($giftCard): void
    {
        $deliveryDate = $giftCard->delivery_date;
        
        $this->deliveryScheduler->schedule($deliveryDate, function() use ($giftCard) {
            $this->deliverGiftCard($giftCard);
            
            // Send reminder to purchaser
            $this->sendDeliveryConfirmation($giftCard);
        });
        
        // Send scheduling confirmation
        $this->sendSchedulingConfirmation($giftCard);
    }

    private function validateGiftCard($giftCard): bool
    {
        // Check expiration
        if ($giftCard->expires_at && strtotime($giftCard->expires_at) < time()) {
            return false;
        }
        
        // Check status
        if (!in_array($giftCard->status, ['active', 'delivered'])) {
            return false;
        }
        
        // Check balance
        if ($giftCard->current_balance <= 0) {
            return false;
        }
        
        return true;
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 4) . '-' .
                            substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 4) . '-' .
                            substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 4) . '-' .
                            substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 4));
        } while ($this->giftCardManager->codeExists($code));
        
        return $code;
    }

    private function calculateExpiryDate(): string
    {
        $expiryDays = $this->getConfig('expiry_days', 365);
        return date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));
    }

    private function scheduleDeliveries(): void
    {
        // Process scheduled deliveries
        $this->api->scheduler()->addJob('deliver_scheduled_cards', '0 9 * * *', function() {
            $this->deliveryScheduler->processScheduledDeliveries();
        });
        
        // Check expiring cards
        $this->api->scheduler()->addJob('check_expiring_cards', '0 10 * * *', function() {
            $this->checkExpiringCards();
        });
        
        // Clean up expired cards
        $this->api->scheduler()->addJob('cleanup_expired_cards', '0 2 * * *', function() {
            $this->cleanupExpiredCards();
        });
    }

    private function checkExpiringCards(): void
    {
        $expiringCards = $this->giftCardManager->getExpiringCards(30); // 30 days
        
        foreach ($expiringCards as $card) {
            if ($card->current_balance > 0) {
                $this->sendExpirationReminder($card);
            }
        }
    }

    private function registerRoutes(): void
    {
        $this->api->router()->post('/gift-cards/purchase', 'Controllers\GiftCardController@purchase');
        $this->api->router()->get('/gift-cards/balance/{code}', 'Controllers\GiftCardController@checkBalance');
        $this->api->router()->post('/gift-cards/redeem', 'Controllers\GiftCardController@redeem');
        $this->api->router()->post('/gift-cards/schedule', 'Controllers\GiftCardController@scheduleDelivery');
        $this->api->router()->get('/gift-cards/templates', 'Controllers\GiftCardController@getTemplates');
        $this->api->router()->post('/gift-cards/combine', 'Controllers\GiftCardController@combineCards');
        $this->api->router()->get('/gift-cards/transactions/{code}', 'Controllers\GiftCardController@getTransactions');
    }

    public function install(): void
    {
        $this->runMigrations();
        $this->createDefaultTemplates();
        $this->setupGiftCardProducts();
    }

    private function createDefaultTemplates(): void
    {
        $templates = [
            ['name' => 'Birthday', 'category' => 'occasion', 'design' => 'birthday-balloons'],
            ['name' => 'Holiday', 'category' => 'seasonal', 'design' => 'holiday-sparkle'],
            ['name' => 'Thank You', 'category' => 'gratitude', 'design' => 'elegant-thanks'],
            ['name' => 'Congratulations', 'category' => 'celebration', 'design' => 'confetti-burst']
        ];

        foreach ($templates as $template) {
            $this->api->database()->table('gift_card_templates')->insert($template);
        }
    }

    private function setupGiftCardProducts(): void
    {
        // Create default gift card products
        $amounts = [25, 50, 100, 250];
        
        foreach ($amounts as $amount) {
            $this->api->service('ProductService')->create([
                'name' => '$' . $amount . ' Gift Card',
                'type' => 'gift_card',
                'price' => $amount,
                'sku' => 'GC-' . $amount,
                'status' => 'active',
                'virtual' => true
            ]);
        }
    }

    /**
     * Register Services
     */
    protected function registerServices(): void
    {
        // TODO: Implement registerServices
    }

    /**
     * Register EventListeners
     */
    protected function registerEventListeners(): void
    {
        // TODO: Implement registerEventListeners
    }

    /**
     * Register Hooks
     */
    protected function registerHooks(): void
    {
        // TODO: Implement registerHooks
    }

    /**
     * Register Routes
     */
    protected function registerRoutes(): void
    {
        // TODO: Implement registerRoutes
    }

    /**
     * Register Permissions
     */
    protected function registerPermissions(): void
    {
        // TODO: Implement registerPermissions
    }

    /**
     * Register ScheduledJobs
     */
    protected function registerScheduledJobs(): void
    {
        // TODO: Implement registerScheduledJobs
    }
}