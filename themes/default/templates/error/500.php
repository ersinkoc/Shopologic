<?php $this->layout('layouts/main'); ?>

<?php $this->startBlock('content'); ?>
<div class="error-page">
    <div class="container">
        <div class="error-content">
            <h1 class="error-code">500</h1>
            <h2 class="error-title">Oops! Something went wrong</h2>
            <p class="error-message">
                <?php $this->escape($message ?? 'We encountered an unexpected error. Please try again later.'); ?>
            </p>
            <div class="error-actions">
                <a href="<?php echo $this->url(); ?>" class="btn btn-primary">Go to Homepage</a>
                <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
            </div>
        </div>
    </div>
</div>

<style>
.error-page {
    min-height: 400px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 60px 0;
}

.error-code {
    font-size: 120px;
    font-weight: bold;
    color: #e0e0e0;
    margin-bottom: 20px;
}

.error-title {
    font-size: 32px;
    margin-bottom: 20px;
}

.error-message {
    font-size: 18px;
    color: #666;
    margin-bottom: 40px;
}

.error-actions {
    display: flex;
    gap: 20px;
    justify-content: center;
}
</style>
<?php $this->endBlock(); ?>