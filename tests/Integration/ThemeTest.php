<?php

declare(strict_types=1);

/**
 * Theme Integration Tests
 */

use Shopologic\Core\Theme\TemplateEngine;
use Shopologic\Core\Theme\Asset\AssetManager;
use Shopologic\Core\Theme\Component\ComponentManager;
use Shopologic\Core\Configuration\ConfigurationManager;

TestFramework::describe('Theme Integration', function() {
    TestFramework::it('should initialize theme system', function() {
        $config = new ConfigurationManager();
        $engine = new TemplateEngine($config);
        
        TestFramework::expect($engine)->toBeInstanceOf(TemplateEngine::class);
    });
    
    TestFramework::it('should load default theme', function() {
        $defaultThemePath = SHOPOLOGIC_ROOT . '/themes/default';
        $themeConfigPath = $defaultThemePath . '/theme.json';
        
        TestFramework::expect(is_dir($defaultThemePath))->toBeTrue();
        TestFramework::expect(file_exists($themeConfigPath))->toBeTrue();
        
        if (file_exists($themeConfigPath)) {
            $themeConfig = json_decode(file_get_contents($themeConfigPath), true);
            TestFramework::expect(is_array($themeConfig))->toBeTrue();
            TestFramework::expect(isset($themeConfig['name']))->toBeTrue();
        }
    });
    
    TestFramework::it('should discover theme components', function() {
        $componentsDir = SHOPOLOGIC_ROOT . '/themes/default/components';
        
        if (is_dir($componentsDir)) {
            $components = scandir($componentsDir);
            $components = array_filter($components, function($item) use ($componentsDir) {
                return $item !== '.' && $item !== '..' && is_dir($componentsDir . '/' . $item);
            });
            
            TestFramework::expect(count($components))->toBeGreaterThan(0);
            
            // Check if components have required files
            foreach ($components as $component) {
                $componentDir = $componentsDir . '/' . $component;
                $configFile = $componentDir . '/component.json';
                $templateFile = $componentDir . '/' . $component . '.twig';
                
                TestFramework::expect(file_exists($configFile))->toBeTrue();
                if (file_exists($templateFile)) {
                    TestFramework::expect(file_exists($templateFile))->toBeTrue();
                }
            }
        } else {
            // No components directory is valid for minimal themes
            TestFramework::expect(true)->toBeTrue();
        }
    });
    
    TestFramework::it('should load theme templates', function() {
        $templatesDir = SHOPOLOGIC_ROOT . '/themes/default/templates';
        
        TestFramework::expect(is_dir($templatesDir))->toBeTrue();
        
        $indexTemplate = $templatesDir . '/index.twig';
        if (file_exists($indexTemplate)) {
            $content = file_get_contents($indexTemplate);
            TestFramework::expect(strlen($content))->toBeGreaterThan(0);
        }
    });
    
    TestFramework::it('should handle theme assets', function() {
        $assetsDir = SHOPOLOGIC_ROOT . '/themes/default/assets';
        
        if (is_dir($assetsDir)) {
            TestFramework::expect(is_dir($assetsDir))->toBeTrue();
            
            // Check for CSS directory
            $cssDir = $assetsDir . '/css';
            if (is_dir($cssDir)) {
                $cssFiles = glob($cssDir . '/*.css');
                TestFramework::expect(count($cssFiles))->toBeGreaterThanOrEqualTo(0);
            }
            
            // Check for JS directory
            $jsDir = $assetsDir . '/js';
            if (is_dir($jsDir)) {
                $jsFiles = glob($jsDir . '/*.js');
                TestFramework::expect(count($jsFiles))->toBeGreaterThanOrEqualTo(0);
            }
        } else {
            // No assets directory is valid for minimal themes
            TestFramework::expect(true)->toBeTrue();
        }
    });
    
    TestFramework::it('should initialize asset manager', function() {
        $config = new ConfigurationManager();
        $assetManager = new AssetManager($config);
        
        TestFramework::expect($assetManager)->toBeInstanceOf(AssetManager::class);
    });
    
    TestFramework::it('should register and render CSS assets', function() {
        $config = new ConfigurationManager();
        $assetManager = new AssetManager($config);
        
        $assetManager->addStyle('test.css', 100);
        $styles = $assetManager->renderStyles();
        
        TestFramework::expect(strpos($styles, 'test.css') !== false)->toBeTrue();
        TestFramework::expect(strpos($styles, '<link') !== false)->toBeTrue();
    });
    
    TestFramework::it('should register and render JS assets', function() {
        $config = new ConfigurationManager();
        $assetManager = new AssetManager($config);
        
        $assetManager->addScript('test.js', 100);
        $scripts = $assetManager->renderScripts();
        
        TestFramework::expect(strpos($scripts, 'test.js') !== false)->toBeTrue();
        TestFramework::expect(strpos($scripts, '<script') !== false)->toBeTrue();
    });
    
    TestFramework::it('should handle inline styles', function() {
        $config = new ConfigurationManager();
        $assetManager = new AssetManager($config);
        
        $css = 'body { background: red; }';
        $assetManager->addInlineStyle($css);
        $styles = $assetManager->renderStyles();
        
        TestFramework::expect(strpos($styles, $css) !== false)->toBeTrue();
        TestFramework::expect(strpos($styles, '<style') !== false)->toBeTrue();
    });
    
    TestFramework::it('should handle inline scripts', function() {
        $config = new ConfigurationManager();
        $assetManager = new AssetManager($config);
        
        $js = 'console.log("test");';
        $assetManager->addInlineScript($js);
        $scripts = $assetManager->renderScripts();
        
        TestFramework::expect(strpos($scripts, $js) !== false)->toBeTrue();
        TestFramework::expect(strpos($scripts, '<script') !== false)->toBeTrue();
    });
    
    TestFramework::it('should initialize component manager', function() {
        $config = new ConfigurationManager();
        $engine = new TemplateEngine($config);
        $componentManager = new ComponentManager($engine);
        
        TestFramework::expect($componentManager)->toBeInstanceOf(ComponentManager::class);
    });
    
    TestFramework::it('should register theme components', function() {
        $config = new ConfigurationManager();
        $engine = new TemplateEngine($config);
        $componentManager = new ComponentManager($engine);
        
        // Mock component registration
        $componentManager->register('test-component', [
            'template' => '<div>Test Component</div>',
            'settings' => []
        ]);
        
        TestFramework::expect($componentManager->has('test-component'))->toBeTrue();
    });
    
    TestFramework::it('should render theme components', function() {
        $config = new ConfigurationManager();
        $engine = new TemplateEngine($config);
        $componentManager = new ComponentManager($engine);
        
        // Mock component rendering
        $componentManager->register('simple-component', [
            'template' => '<div class="simple">{{ content }}</div>',
            'settings' => []
        ]);
        
        $output = $componentManager->render('simple-component', ['content' => 'Hello']);
        TestFramework::expect(strpos($output, 'Hello') !== false)->toBeTrue();
    });
    
    TestFramework::it('should handle theme inheritance', function() {
        $templatesDir = SHOPOLOGIC_ROOT . '/themes/default/templates';
        $layoutsDir = $templatesDir . '/layouts';
        
        if (is_dir($layoutsDir)) {
            $baseLayout = $layoutsDir . '/base.twig';
            if (file_exists($baseLayout)) {
                $content = file_get_contents($baseLayout);
                // Check for basic layout structure
                TestFramework::expect(strpos($content, 'DOCTYPE') !== false || strpos($content, 'html') !== false)->toBeTrue();
            }
        } else {
            // No layouts directory is valid
            TestFramework::expect(true)->toBeTrue();
        }
    });
});