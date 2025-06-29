<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\LiveEditor;

use Shopologic\Core\Theme\TemplateEngine;
use Shopologic\Core\Theme\Component\ComponentManager;
use Shopologic\Core\Theme\Asset\AssetManager;
use Shopologic\Core\Events\EventDispatcherInterface;

/**
 * Live theme editor with drag-drop functionality
 */
class ThemeEditor
{
    private TemplateEngine $templateEngine;
    private ComponentManager $componentManager;
    private AssetManager $assetManager;
    private EventDispatcherInterface $eventDispatcher;
    private array $config;
    private ?string $currentTheme = null;
    private array $unsavedChanges = [];

    public function __construct(
        TemplateEngine $templateEngine,
        ComponentManager $componentManager,
        AssetManager $assetManager,
        EventDispatcherInterface $eventDispatcher,
        array $config = []
    ) {
        $this->templateEngine = $templateEngine;
        $this->componentManager = $componentManager;
        $this->assetManager = $assetManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->config = $config;
    }

    /**
     * Initialize editor for a theme
     */
    public function initialize(string $theme): array
    {
        $this->currentTheme = $theme;
        
        // Load theme configuration
        $themeConfig = $this->loadThemeConfig($theme);
        
        // Get available components
        $components = $this->componentManager->getAvailableComponents();
        
        // Get theme layouts
        $layouts = $this->getThemeLayouts($theme);
        
        // Get editable regions
        $regions = $this->getEditableRegions($theme);
        
        return [
            'theme' => $theme,
            'config' => $themeConfig,
            'components' => $components,
            'layouts' => $layouts,
            'regions' => $regions,
            'assets' => [
                'css' => $this->assetManager->getThemeStyles($theme),
                'js' => $this->assetManager->getThemeScripts($theme)
            ]
        ];
    }

    /**
     * Render preview
     */
    public function renderPreview(array $data): string
    {
        try {
            // Apply unsaved changes
            $this->applyUnsavedChanges($data);
            
            // Set preview mode
            $this->templateEngine->addGlobal('_preview_mode', true);
            $this->templateEngine->addGlobal('_editor_data', $data);
            
            // Render template
            $template = $data['template'] ?? 'index';
            $context = $data['context'] ?? [];
            
            $output = $this->templateEngine->render($template, $context);
            
            // Inject editor assets
            $output = $this->injectEditorAssets($output);
            
            // Wrap in iframe-safe container
            $output = $this->wrapInPreviewContainer($output);
            
            return $output;

        } catch (\Exception $e) {
            return $this->renderErrorPreview($e);
        }
    }

    /**
     * Add component to page
     */
    public function addComponent(array $data): array
    {
        $componentName = $data['component'] ?? '';
        $targetRegion = $data['region'] ?? '';
        $position = $data['position'] ?? 'append';
        $props = $data['props'] ?? [];
        
        // Validate component
        if (!$this->componentManager->exists($componentName)) {
            throw new EditorException('Component not found: ' . $componentName);
        }
        
        // Generate unique ID
        $componentId = $this->generateComponentId($componentName);
        
        // Create component instance
        $component = [
            'id' => $componentId,
            'name' => $componentName,
            'region' => $targetRegion,
            'position' => $position,
            'props' => $props,
            'styles' => []
        ];
        
        // Add to unsaved changes
        $this->unsavedChanges['components'][$componentId] = $component;
        
        // Trigger event
        $this->eventDispatcher->dispatch('theme.component.added', [
            'component' => $component,
            'theme' => $this->currentTheme
        ]);
        
        return [
            'success' => true,
            'component' => $component,
            'html' => $this->componentManager->render($componentName, $props)
        ];
    }

    /**
     * Update component
     */
    public function updateComponent(string $componentId, array $updates): array
    {
        if (!isset($this->unsavedChanges['components'][$componentId])) {
            throw new EditorException('Component not found: ' . $componentId);
        }
        
        $component = &$this->unsavedChanges['components'][$componentId];
        
        // Update props
        if (isset($updates['props'])) {
            $component['props'] = array_merge($component['props'], $updates['props']);
        }
        
        // Update styles
        if (isset($updates['styles'])) {
            $component['styles'] = array_merge($component['styles'], $updates['styles']);
        }
        
        // Update position
        if (isset($updates['position'])) {
            $component['position'] = $updates['position'];
        }
        
        return [
            'success' => true,
            'component' => $component,
            'html' => $this->componentManager->render($component['name'], $component['props'])
        ];
    }

    /**
     * Remove component
     */
    public function removeComponent(string $componentId): array
    {
        unset($this->unsavedChanges['components'][$componentId]);
        
        return [
            'success' => true,
            'componentId' => $componentId
        ];
    }

    /**
     * Move component
     */
    public function moveComponent(string $componentId, string $targetRegion, int $position): array
    {
        if (!isset($this->unsavedChanges['components'][$componentId])) {
            throw new EditorException('Component not found: ' . $componentId);
        }
        
        $component = &$this->unsavedChanges['components'][$componentId];
        $component['region'] = $targetRegion;
        $component['position'] = $position;
        
        return [
            'success' => true,
            'component' => $component
        ];
    }

    /**
     * Update theme styles
     */
    public function updateStyles(array $styles): array
    {
        $this->unsavedChanges['styles'] = array_merge(
            $this->unsavedChanges['styles'] ?? [],
            $styles
        );
        
        // Generate CSS
        $css = $this->generateCssFromStyles($styles);
        
        return [
            'success' => true,
            'css' => $css
        ];
    }

    /**
     * Save changes
     */
    public function saveChanges(): array
    {
        if (empty($this->unsavedChanges)) {
            return ['success' => true, 'message' => 'No changes to save'];
        }
        
        try {
            // Save components
            if (isset($this->unsavedChanges['components'])) {
                $this->saveComponents($this->unsavedChanges['components']);
            }
            
            // Save styles
            if (isset($this->unsavedChanges['styles'])) {
                $this->saveStyles($this->unsavedChanges['styles']);
            }
            
            // Save settings
            if (isset($this->unsavedChanges['settings'])) {
                $this->saveSettings($this->unsavedChanges['settings']);
            }
            
            // Clear unsaved changes
            $this->unsavedChanges = [];
            
            // Clear template cache
            $this->templateEngine->clearCache();
            
            // Trigger event
            $this->eventDispatcher->dispatch('theme.saved', [
                'theme' => $this->currentTheme
            ]);
            
            return [
                'success' => true,
                'message' => 'Changes saved successfully'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Export theme
     */
    public function exportTheme(): array
    {
        $themePath = $this->getThemePath($this->currentTheme);
        $exportPath = sys_get_temp_dir() . '/' . $this->currentTheme . '_' . date('Y-m-d_H-i-s') . '.zip';
        
        $zip = new \ZipArchive();
        if ($zip->open($exportPath, \ZipArchive::CREATE) !== true) {
            throw new EditorException('Failed to create export archive');
        }
        
        // Add theme files
        $this->addDirectoryToZip($zip, $themePath, $this->currentTheme);
        
        $zip->close();
        
        return [
            'success' => true,
            'path' => $exportPath,
            'size' => filesize($exportPath)
        ];
    }

    /**
     * Import theme
     */
    public function importTheme(string $archivePath, string $themeName): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new EditorException('Failed to open theme archive');
        }
        
        $themePath = dirname($this->getThemePath(''), 4) . '/themes/' . $themeName;
        
        // Extract theme
        $zip->extractTo($themePath);
        $zip->close();
        
        // Validate theme
        $this->validateTheme($themeName);
        
        return [
            'success' => true,
            'theme' => $themeName
        ];
    }

    /**
     * Get responsive preview sizes
     */
    public function getPreviewSizes(): array
    {
        return [
            'mobile' => ['width' => 375, 'height' => 667, 'label' => 'Mobile'],
            'tablet' => ['width' => 768, 'height' => 1024, 'label' => 'Tablet'],
            'desktop' => ['width' => 1920, 'height' => 1080, 'label' => 'Desktop']
        ];
    }

    // Private methods

    private function loadThemeConfig(string $theme): array
    {
        $configPath = $this->getThemePath($theme) . '/theme.json';
        
        if (!file_exists($configPath)) {
            return [];
        }
        
        $config = json_decode(file_get_contents($configPath), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new EditorException('Invalid theme configuration');
        }
        
        return $config;
    }

    private function getThemeLayouts(string $theme): array
    {
        $layoutsPath = $this->getThemePath($theme) . '/templates/layouts';
        
        if (!is_dir($layoutsPath)) {
            return [];
        }
        
        $layouts = [];
        foreach (glob($layoutsPath . '/*.twig') as $file) {
            $name = basename($file, '.twig');
            $layouts[] = [
                'name' => $name,
                'file' => $file,
                'label' => ucfirst(str_replace('-', ' ', $name))
            ];
        }
        
        return $layouts;
    }

    private function getEditableRegions(string $theme): array
    {
        $config = $this->loadThemeConfig($theme);
        
        return $config['regions'] ?? [
            ['id' => 'header', 'label' => 'Header', 'accepts' => ['navigation', 'banner']],
            ['id' => 'content', 'label' => 'Content', 'accepts' => ['*']],
            ['id' => 'sidebar', 'label' => 'Sidebar', 'accepts' => ['widget', 'navigation']],
            ['id' => 'footer', 'label' => 'Footer', 'accepts' => ['navigation', 'text']]
        ];
    }

    private function applyUnsavedChanges(array &$data): void
    {
        if (isset($this->unsavedChanges['components'])) {
            $data['components'] = $this->unsavedChanges['components'];
        }
        
        if (isset($this->unsavedChanges['styles'])) {
            $data['styles'] = $this->unsavedChanges['styles'];
        }
    }

    private function injectEditorAssets(string $html): string
    {
        $editorCss = '<link rel="stylesheet" href="/assets/theme-editor/editor.css">';
        $editorJs = '<script src="/assets/theme-editor/editor.js"></script>';
        
        // Inject before </head>
        $html = str_replace('</head>', $editorCss . '</head>', $html);
        
        // Inject before </body>
        $html = str_replace('</body>', $editorJs . '</body>', $html);
        
        return $html;
    }

    private function wrapInPreviewContainer(string $html): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base target="_parent">
    <style>
        body { margin: 0; }
        .editor-overlay { display: none; }
        [data-editable-region] { min-height: 50px; position: relative; }
        [data-editable-region]:empty::after { 
            content: "Drop components here"; 
            display: block;
            padding: 20px;
            text-align: center;
            color: #999;
            border: 2px dashed #ddd;
        }
    </style>
</head>
<body>
    {$html}
</body>
</html>
HTML;
    }

    private function renderErrorPreview(\Exception $e): string
    {
        $message = htmlspecialchars($e->getMessage());
        
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .error {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 500px;
        }
        h1 { color: #d32f2f; margin-top: 0; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="error">
        <h1>Preview Error</h1>
        <p>{$message}</p>
    </div>
</body>
</html>
HTML;
    }

    private function generateComponentId(string $componentName): string
    {
        return $componentName . '_' . uniqid();
    }

    private function generateCssFromStyles(array $styles): string
    {
        $css = '';
        
        foreach ($styles as $selector => $properties) {
            $css .= $selector . ' {' . PHP_EOL;
            foreach ($properties as $property => $value) {
                $css .= '  ' . $property . ': ' . $value . ';' . PHP_EOL;
            }
            $css .= '}' . PHP_EOL;
        }
        
        return $css;
    }

    private function saveComponents(array $components): void
    {
        $componentsFile = $this->getThemePath($this->currentTheme) . '/components.json';
        file_put_contents($componentsFile, json_encode($components, JSON_PRETTY_PRINT));
    }

    private function saveStyles(array $styles): void
    {
        $css = $this->generateCssFromStyles($styles);
        $stylesFile = $this->getThemePath($this->currentTheme) . '/assets/css/custom.css';
        file_put_contents($stylesFile, $css);
    }

    private function saveSettings(array $settings): void
    {
        $config = $this->loadThemeConfig($this->currentTheme);
        $config['settings'] = array_merge($config['settings'] ?? [], $settings);
        
        $configFile = $this->getThemePath($this->currentTheme) . '/theme.json';
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
    }

    private function getThemePath(string $theme): string
    {
        return dirname(__DIR__, 4) . '/themes/' . $theme;
    }

    private function addDirectoryToZip(\ZipArchive $zip, string $dir, string $base): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = $base . '/' . substr($filePath, strlen($dir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
    }

    private function validateTheme(string $theme): void
    {
        $requiredFiles = [
            '/theme.json',
            '/templates/index.twig',
            '/templates/layouts/base.twig'
        ];
        
        $themePath = $this->getThemePath($theme);
        
        foreach ($requiredFiles as $file) {
            if (!file_exists($themePath . $file)) {
                throw new EditorException('Missing required file: ' . $file);
            }
        }
    }
}

class EditorException extends \Exception {}