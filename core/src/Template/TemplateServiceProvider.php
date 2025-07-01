<?php

declare(strict_types=1);

namespace Shopologic\Core\Template;

use Shopologic\Core\Container\ServiceProvider;

class TemplateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register template engine as singleton
        $this->container->singleton(TemplateEngine::class, function($container) {
            $basePath = SHOPOLOGIC_ROOT; // Use the constant
            
            // Create template engine with debug mode
            $engine = new TemplateEngine(true);
            
            // Add default template path
            $engine->addPath($basePath . '/themes/default/templates');
            
            // Add container for access in templates
            $engine->addGlobal('container', $container);
            
            // Register additional template paths
            if (is_dir($basePath . '/core/templates')) {
                $engine->addPath($basePath . '/core/templates', 'core');
            }
            if (is_dir($basePath . '/admin/templates')) {
                $engine->addPath($basePath . '/admin/templates', 'admin');
            }
            
            return $engine;
        });
        
        // Register view facade
        $this->container->alias('view', TemplateEngine::class);
    }
    
    public function boot(): void
    {
        // Register global view helper
        if (!function_exists('view')) {
            function view($template, $data = []) {
                $engine = app(\Shopologic\Core\Template\TemplateEngine::class);
                return $engine->render($template, $data);
            }
        }
    }
}