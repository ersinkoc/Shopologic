<?php

declare(strict_types=1);

namespace Shopologic\Core\Marketing\Email;

use Shopologic\Core\Database\Model;
use Shopologic\Core\Events\EventDispatcherInterface;
use Shopologic\Core\Queue\QueueInterface;
use Shopologic\Core\Template\TemplateEngineInterface;

/**
 * Email campaign management system
 */
class EmailCampaignManager
{
    private EventDispatcherInterface $eventDispatcher;
    private QueueInterface $queue;
    private TemplateEngineInterface $templateEngine;
    private array $config;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        QueueInterface $queue,
        TemplateEngineInterface $templateEngine,
        array $config = []
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->queue = $queue;
        $this->templateEngine = $templateEngine;
        $this->config = array_merge([
            'batch_size' => 100,
            'send_rate' => 300, // emails per hour
            'bounce_threshold' => 5.0, // percentage
            'unsubscribe_url' => '/unsubscribe',
            'track_opens' => true,
            'track_clicks' => true
        ], $config);
    }

    /**
     * Create new campaign
     */
    public function createCampaign(array $data): Campaign
    {
        $campaign = new Campaign();
        $campaign->fill([
            'name' => $data['name'],
            'subject' => $data['subject'],
            'from_name' => $data['from_name'],
            'from_email' => $data['from_email'],
            'reply_to' => $data['reply_to'] ?? $data['from_email'],
            'template' => $data['template'],
            'content' => $data['content'] ?? [],
            'segment_id' => $data['segment_id'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'status' => 'draft'
        ]);
        
        $campaign->save();
        
        $this->eventDispatcher->dispatch('marketing.campaign_created', $campaign);
        
        return $campaign;
    }

    /**
     * Schedule campaign for sending
     */
    public function scheduleCampaign(Campaign $campaign, ?\DateTime $scheduledAt = null): void
    {
        if ($campaign->status !== 'draft') {
            throw new \Exception('Only draft campaigns can be scheduled');
        }
        
        $campaign->scheduled_at = $scheduledAt;
        $campaign->status = 'scheduled';
        $campaign->save();
        
        // Queue campaign for processing
        $this->queue->push('process_campaign', [
            'campaign_id' => $campaign->id,
            'scheduled_at' => $scheduledAt?->getTimestamp()
        ]);
        
        $this->eventDispatcher->dispatch('marketing.campaign_scheduled', $campaign);
    }

    /**
     * Send campaign immediately
     */
    public function sendCampaign(Campaign $campaign): void
    {
        if (!in_array($campaign->status, ['draft', 'scheduled'])) {
            throw new \Exception('Campaign cannot be sent in current status');
        }
        
        $campaign->status = 'sending';
        $campaign->started_at = new \DateTime();
        $campaign->save();
        
        // Get recipients
        $recipients = $this->getRecipients($campaign);
        $totalRecipients = count($recipients);
        
        // Process in batches
        $batches = array_chunk($recipients, $this->config['batch_size']);
        
        foreach ($batches as $index => $batch) {
            $this->queue->push('send_campaign_batch', [
                'campaign_id' => $campaign->id,
                'batch' => $batch,
                'batch_index' => $index,
                'total_batches' => count($batches)
            ]);
        }
        
        $campaign->total_recipients = $totalRecipients;
        $campaign->save();
        
        $this->eventDispatcher->dispatch('marketing.campaign_started', $campaign);
    }

    /**
     * Process campaign batch
     */
    public function processBatch(int $campaignId, array $recipients): void
    {
        $campaign = Campaign::find($campaignId);
        if (!$campaign) {
            return;
        }
        
        foreach ($recipients as $recipient) {
            try {
                $this->sendEmail($campaign, $recipient);
                
                // Track send
                $this->trackSend($campaign, $recipient);
                
                // Rate limiting
                $this->applyRateLimit();
                
            } catch (\Exception $e) {
                $this->trackFailure($campaign, $recipient, $e->getMessage());
            }
        }
        
        // Update campaign stats
        $this->updateCampaignStats($campaign);
    }

    /**
     * Track email open
     */
    public function trackOpen(string $trackingId): void
    {
        $tracking = EmailTracking::where('tracking_id', $trackingId)->first();
        
        if ($tracking && !$tracking->opened_at) {
            $tracking->opened_at = new \DateTime();
            $tracking->open_count++;
            $tracking->save();
            
            // Update campaign stats
            $campaign = Campaign::find($tracking->campaign_id);
            if ($campaign) {
                $campaign->opens++;
                $campaign->unique_opens++;
                $campaign->save();
            }
            
            $this->eventDispatcher->dispatch('marketing.email_opened', $tracking);
        }
    }

    /**
     * Track link click
     */
    public function trackClick(string $trackingId, string $linkId): void
    {
        $tracking = EmailTracking::where('tracking_id', $trackingId)->first();
        
        if ($tracking) {
            // Record click
            $click = new EmailClick();
            $click->fill([
                'tracking_id' => $tracking->id,
                'link_id' => $linkId,
                'clicked_at' => new \DateTime(),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            $click->save();
            
            // Update tracking
            if (!$tracking->clicked_at) {
                $tracking->clicked_at = new \DateTime();
            }
            $tracking->click_count++;
            $tracking->save();
            
            // Update campaign stats
            $campaign = Campaign::find($tracking->campaign_id);
            if ($campaign) {
                $campaign->clicks++;
                if ($tracking->click_count === 1) {
                    $campaign->unique_clicks++;
                }
                $campaign->save();
            }
            
            $this->eventDispatcher->dispatch('marketing.email_clicked', $click);
        }
    }

    /**
     * Handle unsubscribe
     */
    public function unsubscribe(string $email, ?int $campaignId = null): void
    {
        $subscriber = EmailSubscriber::where('email', $email)->first();
        
        if ($subscriber) {
            $subscriber->status = 'unsubscribed';
            $subscriber->unsubscribed_at = new \DateTime();
            $subscriber->save();
            
            if ($campaignId) {
                $campaign = Campaign::find($campaignId);
                if ($campaign) {
                    $campaign->unsubscribes++;
                    $campaign->save();
                }
            }
            
            $this->eventDispatcher->dispatch('marketing.unsubscribed', $subscriber);
        }
    }

    /**
     * Get campaign statistics
     */
    public function getStatistics(Campaign $campaign): array
    {
        $stats = [
            'sent' => $campaign->sent,
            'delivered' => $campaign->delivered,
            'bounced' => $campaign->bounced,
            'opens' => $campaign->opens,
            'unique_opens' => $campaign->unique_opens,
            'clicks' => $campaign->clicks,
            'unique_clicks' => $campaign->unique_clicks,
            'unsubscribes' => $campaign->unsubscribes,
            'complaints' => $campaign->complaints
        ];
        
        // Calculate rates
        if ($campaign->sent > 0) {
            $stats['delivery_rate'] = ($campaign->delivered / $campaign->sent) * 100;
            $stats['bounce_rate'] = ($campaign->bounced / $campaign->sent) * 100;
            $stats['open_rate'] = ($campaign->unique_opens / $campaign->delivered) * 100;
            $stats['click_rate'] = ($campaign->unique_clicks / $campaign->delivered) * 100;
            $stats['unsubscribe_rate'] = ($campaign->unsubscribes / $campaign->delivered) * 100;
        }
        
        return $stats;
    }

    /**
     * Get campaign performance over time
     */
    public function getPerformanceTimeline(Campaign $campaign, string $interval = 'hour'): array
    {
        $timeline = [];
        
        // Get tracking data grouped by interval
        $opens = EmailTracking::where('campaign_id', $campaign->id)
            ->whereNotNull('opened_at')
            ->selectRaw("DATE_TRUNC('{$interval}', opened_at) as time, COUNT(*) as count")
            ->groupBy('time')
            ->orderBy('time')
            ->get();
        
        foreach ($opens as $data) {
            $time = $data->time;
            if (!isset($timeline[$time])) {
                $timeline[$time] = ['opens' => 0, 'clicks' => 0];
            }
            $timeline[$time]['opens'] = $data->count;
        }
        
        // Get click data
        $clicks = EmailClick::join('email_trackings', 'email_clicks.tracking_id', '=', 'email_trackings.id')
            ->where('email_trackings.campaign_id', $campaign->id)
            ->selectRaw("DATE_TRUNC('{$interval}', clicked_at) as time, COUNT(*) as count")
            ->groupBy('time')
            ->orderBy('time')
            ->get();
        
        foreach ($clicks as $data) {
            $time = $data->time;
            if (!isset($timeline[$time])) {
                $timeline[$time] = ['opens' => 0, 'clicks' => 0];
            }
            $timeline[$time]['clicks'] = $data->count;
        }
        
        return $timeline;
    }

    // Private methods

    private function getRecipients(Campaign $campaign): array
    {
        if ($campaign->segment_id) {
            // Get recipients from segment
            $segment = EmailSegment::find($campaign->segment_id);
            return $segment ? $segment->getSubscribers() : [];
        }
        
        // Get all active subscribers
        return EmailSubscriber::where('status', 'active')
            ->pluck('email')
            ->toArray();
    }

    private function sendEmail(Campaign $campaign, string $recipient): void
    {
        // Generate tracking ID
        $trackingId = $this->generateTrackingId();
        
        // Prepare email content
        $content = $this->prepareContent($campaign, $recipient, $trackingId);
        
        // Send via email service
        $this->emailService->send([
            'to' => $recipient,
            'from' => $campaign->from_email,
            'from_name' => $campaign->from_name,
            'reply_to' => $campaign->reply_to,
            'subject' => $this->personalizeText($campaign->subject, $recipient),
            'html' => $content['html'],
            'text' => $content['text'],
            'headers' => [
                'List-Unsubscribe' => $this->getUnsubscribeUrl($recipient, $campaign->id),
                'X-Campaign-ID' => $campaign->id,
                'X-Tracking-ID' => $trackingId
            ]
        ]);
    }

    private function prepareContent(Campaign $campaign, string $recipient, string $trackingId): array
    {
        // Render template
        $html = $this->templateEngine->render($campaign->template, array_merge(
            $campaign->content,
            ['recipient' => $recipient]
        ));
        
        // Add tracking pixel
        if ($this->config['track_opens']) {
            $pixelUrl = $this->getTrackingPixelUrl($trackingId);
            $html .= '<img src="' . $pixelUrl . '" width="1" height="1" style="display:none">';
        }
        
        // Track links
        if ($this->config['track_clicks']) {
            $html = $this->trackLinks($html, $trackingId);
        }
        
        // Generate text version
        $text = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));
        
        return ['html' => $html, 'text' => $text];
    }

    private function trackLinks(string $html, string $trackingId): string
    {
        return preg_replace_callback(
            '/<a\s+href=["\']([^"\']+)["\']/',
            function ($matches) use ($trackingId) {
                $originalUrl = $matches[1];
                $linkId = md5($originalUrl);
                $trackedUrl = $this->getTrackedLinkUrl($trackingId, $linkId, $originalUrl);
                return '<a href="' . $trackedUrl . '"';
            },
            $html
        );
    }

    private function generateTrackingId(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function applyRateLimit(): void
    {
        $delay = (3600 / $this->config['send_rate']) * 1000000; // microseconds
        usleep((int)$delay);
    }
}

/**
 * Campaign model
 */
class Campaign extends Model
{
    protected $table = 'email_campaigns';
    
    protected $fillable = [
        'name', 'subject', 'from_name', 'from_email', 'reply_to',
        'template', 'content', 'segment_id', 'status',
        'scheduled_at', 'started_at', 'completed_at',
        'total_recipients', 'sent', 'delivered', 'bounced',
        'opens', 'unique_opens', 'clicks', 'unique_clicks',
        'unsubscribes', 'complaints'
    ];
    
    protected $casts = [
        'content' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];
}

/**
 * Email tracking model
 */
class EmailTracking extends Model
{
    protected $table = 'email_trackings';
    
    protected $fillable = [
        'campaign_id', 'tracking_id', 'recipient', 'sent_at',
        'delivered_at', 'opened_at', 'clicked_at', 'bounced_at',
        'open_count', 'click_count'
    ];
    
    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'bounced_at' => 'datetime'
    ];
}

/**
 * Email click model
 */
class EmailClick extends Model
{
    protected $table = 'email_clicks';
    
    protected $fillable = [
        'tracking_id', 'link_id', 'clicked_at',
        'ip_address', 'user_agent'
    ];
    
    protected $casts = [
        'clicked_at' => 'datetime'
    ];
}

/**
 * Email subscriber model
 */
class EmailSubscriber extends Model
{
    protected $table = 'email_subscribers';
    
    protected $fillable = [
        'email', 'name', 'status', 'subscribed_at',
        'unsubscribed_at', 'confirmed_at', 'tags', 'custom_fields'
    ];
    
    protected $casts = [
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'tags' => 'array',
        'custom_fields' => 'array'
    ];
}

/**
 * Email segment model
 */
class EmailSegment extends Model
{
    protected $table = 'email_segments';
    
    protected $fillable = [
        'name', 'description', 'criteria', 'subscriber_count'
    ];
    
    protected $casts = [
        'criteria' => 'array'
    ];
    
    public function getSubscribers(): array
    {
        // Implement segment criteria logic
        return [];
    }
}