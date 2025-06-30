<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Controllers;

use Shopologic\Core\Http\Controller;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use AdvancedEmailMarketing\Services\{
    SubscriberManager,
    DeliverabilityManager,;
    AnalyticsService,;
    EmailSender;
};
use AdvancedEmailMarketing\Repositories\{;
    EmailSendRepository,;
    SubscriberRepository;
};

class WebhookController extends Controller
{
    private SubscriberManager $subscriberManager;
    private DeliverabilityManager $deliverabilityManager;
    private AnalyticsService $analyticsService;
    private EmailSender $emailSender;
    private EmailSendRepository $emailSendRepository;
    private SubscriberRepository $subscriberRepository;

    public function __construct()
    {
        $this->subscriberManager = app(SubscriberManager::class);
        $this->deliverabilityManager = app(DeliverabilityManager::class);
        $this->analyticsService = app(AnalyticsService::class);
        $this->emailSender = app(EmailSender::class);
        $this->emailSendRepository = app(EmailSendRepository::class);
        $this->subscriberRepository = app(SubscriberRepository::class);
    }

    /**
     * Handle bounce webhook
     */
    public function handleBounce(Request $request): Response
    {
        $this->logWebhook('bounce', $request->all());
        
        try {
            // Verify webhook authenticity
            if (!$this->verifyWebhook($request)) {
                return $this->json(['status' => 'error', 'message' => 'Invalid webhook signature'], 401);
            }
            
            $provider = $this->detectProvider($request);
            $bounceData = $this->parseBounceData($request, $provider);
            
            if (!$bounceData) {
                return $this->json(['status' => 'error', 'message' => 'Invalid bounce data'], 400);
            }
            
            // Process bounce
            $this->deliverabilityManager->processBounce($bounceData);
            
            // Update subscriber status if hard bounce
            if ($bounceData['bounce_type'] === 'hard') {
                $subscriber = $this->subscriberRepository->findByEmail($bounceData['email']);
                if ($subscriber) {
                    $this->subscriberManager->markAsBounced($subscriber['id']);
                }
            }
            
            // Track analytics
            $this->analyticsService->trackBounce($bounceData);
            
            return $this->json(['status' => 'success', 'message' => 'Bounce processed']);
        } catch (\RuntimeException $e) {
            $this->logError('bounce_webhook', $e->getMessage(), $request->all());
            return $this->json(['status' => 'error', 'message' => 'Failed to process bounce'], 500);
        }
    }

    /**
     * Handle complaint webhook
     */
    public function handleComplaint(Request $request): Response
    {
        $this->logWebhook('complaint', $request->all());
        
        try {
            // Verify webhook authenticity
            if (!$this->verifyWebhook($request)) {
                return $this->json(['status' => 'error', 'message' => 'Invalid webhook signature'], 401);
            }
            
            $provider = $this->detectProvider($request);
            $complaintData = $this->parseComplaintData($request, $provider);
            
            if (!$complaintData) {
                return $this->json(['status' => 'error', 'message' => 'Invalid complaint data'], 400);
            }
            
            // Process complaint
            $this->deliverabilityManager->processComplaint($complaintData);
            
            // Update subscriber status
            $subscriber = $this->subscriberRepository->findByEmail($complaintData['email']);
            if ($subscriber) {
                $this->subscriberManager->markAsComplained($subscriber['id']);
                
                // Automatically unsubscribe on complaint
                $this->subscriberManager->unsubscribe(
                    $subscriber['id'], 
                    'Marked as spam',
                    'Automatic unsubscribe due to complaint'
                );
            }
            
            // Track analytics
            $this->analyticsService->trackComplaint($complaintData);
            
            return $this->json(['status' => 'success', 'message' => 'Complaint processed']);
        } catch (\RuntimeException $e) {
            $this->logError('complaint_webhook', $e->getMessage(), $request->all());
            return $this->json(['status' => 'error', 'message' => 'Failed to process complaint'], 500);
        }
    }

    /**
     * Handle delivery webhook
     */
    public function handleDelivery(Request $request): Response
    {
        $this->logWebhook('delivery', $request->all());
        
        try {
            // Verify webhook authenticity
            if (!$this->verifyWebhook($request)) {
                return $this->json(['status' => 'error', 'message' => 'Invalid webhook signature'], 401);
            }
            
            $provider = $this->detectProvider($request);
            $deliveryData = $this->parseDeliveryData($request, $provider);
            
            if (!$deliveryData) {
                return $this->json(['status' => 'error', 'message' => 'Invalid delivery data'], 400);
            }
            
            // Update email send record
            if (isset($deliveryData['message_id'])) {
                $this->emailSendRepository->updateDeliveryStatus(
                    $deliveryData['message_id'],
                    'delivered',
                    $deliveryData
                );
            }
            
            // Track analytics
            $this->analyticsService->trackDelivery($deliveryData);
            
            return $this->json(['status' => 'success', 'message' => 'Delivery processed']);
        } catch (\RuntimeException $e) {
            $this->logError('delivery_webhook', $e->getMessage(), $request->all());
            return $this->json(['status' => 'error', 'message' => 'Failed to process delivery'], 500);
        }
    }

    /**
     * Handle open webhook
     */
    public function handleOpen(Request $request): Response
    {
        $this->logWebhook('open', $request->all());
        
        try {
            // Verify webhook authenticity
            if (!$this->verifyWebhook($request)) {
                return $this->json(['status' => 'error', 'message' => 'Invalid webhook signature'], 401);
            }
            
            $provider = $this->detectProvider($request);
            $openData = $this->parseOpenData($request, $provider);
            
            if (!$openData) {
                return $this->json(['status' => 'error', 'message' => 'Invalid open data'], 400);
            }
            
            // Track open
            $this->emailSendRepository->trackOpen($openData);
            
            // Update subscriber engagement
            $subscriber = $this->subscriberRepository->findByEmail($openData['email']);
            if ($subscriber) {
                $this->subscriberManager->updateEngagementScore($subscriber['id']);
                $this->subscriberManager->trackActivity($subscriber['id'], 'email_opened', $openData);
            }
            
            // Track analytics
            $this->analyticsService->trackOpen($openData);
            
            return $this->json(['status' => 'success', 'message' => 'Open tracked']);
        } catch (\RuntimeException $e) {
            $this->logError('open_webhook', $e->getMessage(), $request->all());
            return $this->json(['status' => 'error', 'message' => 'Failed to track open'], 500);
        }
    }

    /**
     * Handle click webhook
     */
    public function handleClick(Request $request): Response
    {
        $this->logWebhook('click', $request->all());
        
        try {
            // Verify webhook authenticity
            if (!$this->verifyWebhook($request)) {
                return $this->json(['status' => 'error', 'message' => 'Invalid webhook signature'], 401);
            }
            
            $provider = $this->detectProvider($request);
            $clickData = $this->parseClickData($request, $provider);
            
            if (!$clickData) {
                return $this->json(['status' => 'error', 'message' => 'Invalid click data'], 400);
            }
            
            // Track click
            $this->emailSendRepository->trackClick($clickData);
            
            // Update subscriber engagement
            $subscriber = $this->subscriberRepository->findByEmail($clickData['email']);
            if ($subscriber) {
                $this->subscriberManager->updateEngagementScore($subscriber['id']);
                $this->subscriberManager->trackActivity($subscriber['id'], 'email_clicked', $clickData);
            }
            
            // Track analytics
            $this->analyticsService->trackClick($clickData);
            
            return $this->json(['status' => 'success', 'message' => 'Click tracked']);
        } catch (\RuntimeException $e) {
            $this->logError('click_webhook', $e->getMessage(), $request->all());
            return $this->json(['status' => 'error', 'message' => 'Failed to track click'], 500);
        }
    }

    /**
     * Handle unsubscribe webhook
     */
    public function handleUnsubscribe(Request $request): Response
    {
        $this->logWebhook('unsubscribe', $request->all());
        
        try {
            // Verify webhook authenticity
            if (!$this->verifyWebhook($request)) {
                return $this->json(['status' => 'error', 'message' => 'Invalid webhook signature'], 401);
            }
            
            $provider = $this->detectProvider($request);
            $unsubscribeData = $this->parseUnsubscribeData($request, $provider);
            
            if (!$unsubscribeData) {
                return $this->json(['status' => 'error', 'message' => 'Invalid unsubscribe data'], 400);
            }
            
            // Process unsubscribe
            $subscriber = $this->subscriberRepository->findByEmail($unsubscribeData['email']);
            if ($subscriber) {
                $this->subscriberManager->unsubscribe(
                    $subscriber['id'],
                    $unsubscribeData['reason'] ?? 'Webhook unsubscribe',
                    $unsubscribeData['feedback'] ?? null
                );
            }
            
            // Track analytics
            $this->analyticsService->trackUnsubscribe($unsubscribeData);
            
            return $this->json(['status' => 'success', 'message' => 'Unsubscribe processed']);
        } catch (\RuntimeException $e) {
            $this->logError('unsubscribe_webhook', $e->getMessage(), $request->all());
            return $this->json(['status' => 'error', 'message' => 'Failed to process unsubscribe'], 500);
        }
    }

    /**
     * Verify webhook signature
     */
    private function verifyWebhook(Request $request): bool
    {
        $provider = $this->detectProvider($request);
        
        switch ($provider) {
            case 'sendgrid':
                return $this->verifySendGridWebhook($request);
            case 'mailgun':
                return $this->verifyMailgunWebhook($request);
            case 'aws_ses':
                return $this->verifyAWSSESWebhook($request);
            default:
                // Allow webhooks without signature verification in development
                return app()->environment('local', 'development');
        }
    }

    /**
     * Detect email provider from webhook
     */
    private function detectProvider(Request $request): string
    {
        // Check headers for provider identification
        if ($request->header('X-Twilio-Signature')) {
            return 'sendgrid';
        }
        
        if ($request->header('X-Mailgun-Signature')) {
            return 'mailgun';
        }
        
        if ($request->header('X-Amz-SNS-Message-Type')) {
            return 'aws_ses';
        }
        
        // Check request body structure
        $data = $request->all();
        if (isset($data['sg_event_id'])) {
            return 'sendgrid';
        }
        
        if (isset($data['event-data'])) {
            return 'mailgun';
        }
        
        return 'unknown';
    }

    /**
     * Parse bounce data based on provider
     */
    private function parseBounceData(Request $request, string $provider): ?array
    {
        switch ($provider) {
            case 'sendgrid':
                return $this->parseSendGridBounce($request->all());
            case 'mailgun':
                return $this->parseMailgunBounce($request->all());
            case 'aws_ses':
                return $this->parseAWSSESBounce($request->all());
            default:
                return null;
        }
    }

    /**
     * Parse complaint data based on provider
     */
    private function parseComplaintData(Request $request, string $provider): ?array
    {
        switch ($provider) {
            case 'sendgrid':
                return $this->parseSendGridComplaint($request->all());
            case 'mailgun':
                return $this->parseMailgunComplaint($request->all());
            case 'aws_ses':
                return $this->parseAWSSESComplaint($request->all());
            default:
                return null;
        }
    }

    /**
     * Parse delivery data based on provider
     */
    private function parseDeliveryData(Request $request, string $provider): ?array
    {
        switch ($provider) {
            case 'sendgrid':
                return $this->parseSendGridDelivery($request->all());
            case 'mailgun':
                return $this->parseMailgunDelivery($request->all());
            case 'aws_ses':
                return $this->parseAWSSESDelivery($request->all());
            default:
                return null;
        }
    }

    /**
     * Parse open data based on provider
     */
    private function parseOpenData(Request $request, string $provider): ?array
    {
        switch ($provider) {
            case 'sendgrid':
                return $this->parseSendGridOpen($request->all());
            case 'mailgun':
                return $this->parseMailgunOpen($request->all());
            default:
                return null;
        }
    }

    /**
     * Parse click data based on provider
     */
    private function parseClickData(Request $request, string $provider): ?array
    {
        switch ($provider) {
            case 'sendgrid':
                return $this->parseSendGridClick($request->all());
            case 'mailgun':
                return $this->parseMailgunClick($request->all());
            default:
                return null;
        }
    }

    /**
     * Parse unsubscribe data based on provider
     */
    private function parseUnsubscribeData(Request $request, string $provider): ?array
    {
        switch ($provider) {
            case 'sendgrid':
                return $this->parseSendGridUnsubscribe($request->all());
            case 'mailgun':
                return $this->parseMailgunUnsubscribe($request->all());
            default:
                return null;
        }
    }

    /**
     * Verify SendGrid webhook
     */
    private function verifySendGridWebhook(Request $request): bool
    {
        $publicKey = config('email.providers.sendgrid.webhook_verification_key');
        if (!$publicKey) {
            return false;
        }
        
        $signature = $request->header('X-Twilio-Email-Event-Webhook-Signature');
        $timestamp = $request->header('X-Twilio-Email-Event-Webhook-Timestamp');
        
        if (!$signature || !$timestamp) {
            return false;
        }
        
        $payload = $timestamp . $request->getContent();
        $expectedSignature = base64_encode(hash_hmac('sha256', $payload, $publicKey, true));
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Verify Mailgun webhook
     */
    private function verifyMailgunWebhook(Request $request): bool
    {
        $apiKey = config('email.providers.mailgun.webhook_signing_key');
        if (!$apiKey) {
            return false;
        }
        
        $token = $request->input('signature.token');
        $timestamp = $request->input('signature.timestamp');
        $signature = $request->input('signature.signature');
        
        if (!$token || !$timestamp || !$signature) {
            return false;
        }
        
        $expectedSignature = hash_hmac('sha256', $timestamp . $token, $apiKey);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Verify AWS SES webhook
     */
    private function verifyAWSSESWebhook(Request $request): bool
    {
        // AWS SES uses SNS for webhooks, which requires certificate verification
        // This is a simplified version - implement full SNS verification in production
        $messageType = $request->header('X-Amz-SNS-Message-Type');
        
        return in_array($messageType, ['Notification', 'SubscriptionConfirmation']);
    }

    /**
     * Parse SendGrid bounce data
     */
    private function parseSendGridBounce(array $data): ?array
    {
        if (!isset($data['email']) || !isset($data['event']) || $data['event'] !== 'bounce') {
            return null;
        }
        
        return [
            'email' => $data['email'],
            'bounce_type' => $data['type'] ?? 'hard',
            'reason' => $data['reason'] ?? 'Unknown',
            'status' => $data['status'] ?? null,
            'message_id' => $data['sg_message_id'] ?? null,
            'timestamp' => isset($data['timestamp']) ? date('Y-m-d H:i:s', $data['timestamp']) : now(),
            'provider' => 'sendgrid',
            'raw_data' => $data
        ];
    }

    /**
     * Parse Mailgun bounce data
     */
    private function parseMailgunBounce(array $data): ?array
    {
        $eventData = $data['event-data'] ?? [];
        
        if (!isset($eventData['recipient']) || !isset($eventData['event']) || $eventData['event'] !== 'failed') {
            return null;
        }
        
        $severity = $eventData['severity'] ?? 'permanent';
        
        return [
            'email' => $eventData['recipient'],
            'bounce_type' => $severity === 'permanent' ? 'hard' : 'soft',
            'reason' => $eventData['reason'] ?? 'Unknown',
            'status' => $eventData['delivery-status']['code'] ?? null,
            'message_id' => $eventData['message']['headers']['message-id'] ?? null,
            'timestamp' => isset($eventData['timestamp']) ? date('Y-m-d H:i:s', $eventData['timestamp']) : now(),
            'provider' => 'mailgun',
            'raw_data' => $data
        ];
    }

    /**
     * Parse AWS SES bounce data
     */
    private function parseAWSSESBounce(array $data): ?array
    {
        $message = json_decode($data['Message'] ?? '{}', true);
        
        if (!isset($message['bounce'])) {
            return null;
        }
        
        $bounce = $message['bounce'];
        $recipients = $bounce['bouncedRecipients'] ?? [];
        
        if (empty($recipients)) {
            return null;
        }
        
        // Process first recipient (handle multiple recipients separately if needed)
        $recipient = $recipients[0];
        
        return [
            'email' => $recipient['emailAddress'],
            'bounce_type' => $bounce['bounceType'] === 'Permanent' ? 'hard' : 'soft',
            'reason' => $recipient['diagnosticCode'] ?? 'Unknown',
            'status' => $recipient['status'] ?? null,
            'message_id' => $message['mail']['messageId'] ?? null,
            'timestamp' => $bounce['timestamp'] ?? now(),
            'provider' => 'aws_ses',
            'raw_data' => $data
        ];
    }

    /**
     * Log webhook data
     */
    private function logWebhook(string $type, array $data): void
    {
        // Log webhook for debugging and audit trail
        app('log')->info("Email webhook received: {$type}", [
            'type' => $type,
            'data' => $data,
            'timestamp' => now()
        ]);
    }

    /**
     * Log webhook error
     */
    private function logError(string $type, string $error, array $data): void
    {
        app('log')->error("Email webhook error: {$type}", [
            'type' => $type,
            'error' => $error,
            'data' => $data,
            'timestamp' => now()
        ]);
    }
}