<?php
/**
 * FedEx Tracking Information Template
 * 
 * Available variables:
 * @var Order $order
 * @var array $trackingInfo
 */
?>

<div class="fedex-tracking-info">
    <h3>FedEx Tracking Information</h3>
    
    <div class="tracking-summary">
        <div class="tracking-number">
            <label>Tracking Number:</label>
            <a href="https://www.fedex.com/fedextrack/?trknbr=<?php echo urlencode($trackingInfo['tracking_number']); ?>" 
               target="_blank" rel="noopener">
                <?php echo htmlspecialchars($trackingInfo['tracking_number']); ?>
            </a>
        </div>
        
        <div class="tracking-status">
            <label>Status:</label>
            <span class="status-<?php echo strtolower(str_replace(' ', '-', $trackingInfo['status'] ?? '')); ?>">
                <?php echo htmlspecialchars($trackingInfo['status_description'] ?? 'Unknown'); ?>
            </span>
        </div>
        
        <?php if (!empty($trackingInfo['current_location'])): ?>
        <div class="current-location">
            <label>Current Location:</label>
            <span><?php echo htmlspecialchars($trackingInfo['current_location']); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($trackingInfo['estimated_delivery'])): ?>
        <div class="estimated-delivery">
            <label>Estimated Delivery:</label>
            <span><?php echo date('F j, Y', strtotime($trackingInfo['estimated_delivery'])); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($trackingInfo['actual_delivery'])): ?>
        <div class="actual-delivery">
            <label>Delivered On:</label>
            <span><?php echo date('F j, Y g:i A', strtotime($trackingInfo['actual_delivery'])); ?></span>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($trackingInfo['events'])): ?>
    <div class="tracking-timeline">
        <h4>Tracking History</h4>
        <div class="timeline-events">
            <?php foreach ($trackingInfo['events'] as $event): ?>
            <div class="timeline-event">
                <div class="event-date">
                    <?php echo date('M j, Y g:i A', strtotime($event['timestamp'])); ?>
                </div>
                <div class="event-details">
                    <div class="event-description">
                        <?php echo htmlspecialchars($event['description']); ?>
                    </div>
                    <?php if (!empty($event['location'])): ?>
                    <div class="event-location">
                        <i class="icon-location"></i>
                        <?php echo htmlspecialchars($event['location']); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.fedex-tracking-info {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.tracking-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.tracking-summary > div {
    display: flex;
    flex-direction: column;
}

.tracking-summary label {
    font-size: 12px;
    color: #666;
    margin-bottom: 5px;
}

.tracking-summary a {
    color: #4D148C;
    text-decoration: none;
}

.tracking-summary a:hover {
    text-decoration: underline;
}

.status-delivered {
    color: #008000;
    font-weight: bold;
}

.status-in-transit {
    color: #FF6200;
}

.status-out-for-delivery {
    color: #4D148C;
}

.tracking-timeline {
    margin-top: 30px;
}

.timeline-events {
    position: relative;
    padding-left: 30px;
}

.timeline-events::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e0e0e0;
}

.timeline-event {
    position: relative;
    padding-bottom: 20px;
}

.timeline-event::before {
    content: '';
    position: absolute;
    left: -24px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #4D148C;
    border: 2px solid #fff;
}

.event-date {
    font-size: 12px;
    color: #666;
    margin-bottom: 5px;
}

.event-description {
    font-weight: 500;
    margin-bottom: 3px;
}

.event-location {
    font-size: 14px;
    color: #666;
}
</style>