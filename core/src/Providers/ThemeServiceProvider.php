<?php

declare(strict_types=1);

namespace Shopologic\Core\Providers;

use Shopologic\Core\Container\ServiceProvider;
use Shopologic\Core\Theme\TemplateEngine;
use Shopologic\Core\Theme\Component\ComponentManager;
use Shopologic\Core\Theme\Asset\AssetManager;
use Shopologic\Core\Theme\LiveEditor\ThemeEditor;
use Shopologic\Core\Theme\Extension\CoreExtension;
use Shopologic\Core\Theme\Extension\HtmlExtension;
use Shopologic\Core\Theme\Extension\AssetExtension;
use Shopologic\Core\Theme\Extension\HookExtension;
use Shopologic\Core\Theme\Extension\ComponentExtension;

class ThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register template loader
        $this->container->singleton(\Shopologic\Core\Theme\Loader\TemplateLoader::class, function ($container) {
            return new \Shopologic\Core\Theme\Loader\TemplateLoader();
        });
        
        // Register template compiler
        $this->container->singleton(\Shopologic\Core\Theme\Compiler\TemplateCompiler::class, function ($container) {
            return new \Shopologic\Core\Theme\Compiler\TemplateCompiler();
        });
        
        // Register template engine
        $this->container->singleton(TemplateEngine::class, function ($container) {
            $config = $container->get('config')['theme'] ?? [];
            $debug = $container->get('config')['app']['debug'] ?? false;
            
            $engine = new TemplateEngine(
                $container->get(\Shopologic\Core\Theme\Loader\TemplateLoader::class),
                $container->get(\Shopologic\Core\Theme\Compiler\TemplateCompiler::class),
                $container->get('cache'),
                $debug
            );
            
            // Register core extensions
            $engine->addExtension(new CoreExtension());
            $engine->addExtension(new HtmlExtension());
            $engine->addExtension(new HookExtension());
            
            return $engine;
        });
        
        // Register asset manager
        $this->container->singleton(AssetManager::class, function ($container) {
            $config = $container->get('config')['assets'] ?? [];
            
            return new AssetManager(
                $container->get('cache'),
                $container->get('events'),
                array_merge([
                    'public_path' => '/assets',
                    'themes_path' => dirname(__DIR__, 3) . '/themes',
                    'cache_path' => dirname(__DIR__, 3) . '/storage/cache/assets',
                    'debug' => $container->get('config')['app']['debug'] ?? false
                ], $config)
            );
        });
        
        // Register component manager
        $this->container->singleton(ComponentManager::class, function ($container) {
            $engine = $container->get(TemplateEngine::class);
            
            $manager = new ComponentManager(
                $engine,
                $container->get('cache'),
                $container->get('events'),
                $container->get('config')['app']['debug'] ?? false
            );
            
            // Register component paths
            $themePath = dirname(__DIR__, 3) . '/themes/default/components';
            if (is_dir($themePath)) {
                $manager->addPath($themePath, 'default');
            }
            
            return $manager;
        });
        
        // Register theme editor
        $this->container->singleton(ThemeEditor::class, function ($container) {
            return new ThemeEditor(
                $container->get(TemplateEngine::class),
                $container->get(ComponentManager::class),
                $container->get(AssetManager::class),
                $container->get('events'),
                $container->get('config')['theme_editor'] ?? []
            );
        });
        
        // Register extensions that depend on other services
        $this->container->extend(TemplateEngine::class, function ($engine, $container) {
            $engine->addExtension(new AssetExtension($container->get(AssetManager::class)));
            $engine->addExtension(new ComponentExtension($container->get(ComponentManager::class)));
            
            // Set component manager on engine
            $engine->setComponentManager($container->get(ComponentManager::class));
            
            return $engine;
        });
        
        // Register template helper
        $this->container->bind('template', function ($container) {
            return $container->get(TemplateEngine::class);
        });
        
        // Register asset helper
        $this->container->bind('assets', function ($container) {
            return $container->get(AssetManager::class);
        });
        
        // Register component helper
        $this->container->bind('components', function ($container) {
            return $container->get(ComponentManager::class);
        });
    }
    
    public function boot(): void
    {
        // Add default template paths
        $engine = $this->container->get(TemplateEngine::class);
        
        // Add core template path
        $corePath = dirname(__DIR__, 2) . '/templates';
        if (is_dir($corePath)) {
            $engine->addPath($corePath, 'core');
        }
        
        // Add active theme path
        $activeTheme = $this->container->get('config')['theme']['active'] ?? 'default';
        $themePath = dirname(__DIR__, 3) . '/themes/' . $activeTheme . '/templates';
        if (is_dir($themePath)) {
            $engine->addPath($themePath, 'theme');
        }
        
        // Register theme routes
        $router = $this->container->get('router');
        
        // Theme editor routes
        $router->group(['prefix' => '/admin/theme-editor', 'middleware' => ['auth', 'admin']], function ($router) {
            $router->get('/', 'Admin\ThemeEditorController@index');
            $router->post('/preview', 'Admin\ThemeEditorController@preview');
            $router->post('/component/add', 'Admin\ThemeEditorController@addComponent');
            $router->post('/component/update', 'Admin\ThemeEditorController@updateComponent');
            $router->post('/component/remove', 'Admin\ThemeEditorController@removeComponent');
            $router->post('/component/move', 'Admin\ThemeEditorController@moveComponent');
            $router->post('/styles/update', 'Admin\ThemeEditorController@updateStyles');
            $router->post('/save', 'Admin\ThemeEditorController@save');
            $router->post('/export', 'Admin\ThemeEditorController@export');
            $router->post('/import', 'Admin\ThemeEditorController@import');
        });
    }
}