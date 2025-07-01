<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php $this->escape($title ?? 'Shopologic - Modern E-commerce Platform'); ?></title>
    <meta name="description" content="<?php $this->escape($description ?? 'Shop the latest products at great prices'); ?>">
    
    <!-- Theme CSS -->
    <link rel="stylesheet" href="<?php echo $this->theme_asset('css/theme.css'); ?>">
    
    <!-- Additional head content -->
    <?php $this->block('head'); ?>
    
    <!-- Hook for plugins to add head content -->
    <?php $this->do_action('template.head'); ?>
</head>
<body>
    <!-- Header -->
    <?php $this->partial('partials/header'); ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <?php $this->do_action('template.before_content'); ?>
        
        <?php $this->block('content'); ?>
        
        <?php $this->do_action('template.after_content'); ?>
    </main>
    
    <!-- Footer -->
    <?php $this->partial('partials/footer'); ?>
    
    <!-- Theme JavaScript -->
    <script src="<?php echo $this->theme_asset('js/theme.js'); ?>"></script>
    
    <!-- Additional scripts -->
    <?php $this->block('scripts'); ?>
    
    <!-- Hook for plugins to add scripts -->
    <?php $this->do_action('template.footer'); ?>
</body>
</html>