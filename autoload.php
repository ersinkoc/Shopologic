<?php

declare(strict_types=1);

// Custom autoloader for Shopologic
spl_autoload_register(function ($class) {
    // PSR-4 namespace prefixes
    $prefixes = [
        'Shopologic\\Core\\' => __DIR__ . '/core/src/',
        'Shopologic\\Plugins\\' => __DIR__ . '/plugins/',
        'Shopologic\\PSR\\' => __DIR__ . '/core/src/PSR/',
        'Shopologic\\Tests\\' => __DIR__ . '/tests/',
    ];
    
    // Try each prefix
    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        
        // Get the relative class name
        $relativeClass = substr($class, $len);
        
        // For plugins, handle the special directory structure
        if (strpos($class, 'Shopologic\\Plugins\\') === 0) {
            // Extract plugin name and rest of path
            $parts = explode('\\', $relativeClass);
            if (count($parts) >= 2) {
                $pluginName = $parts[0];
                $restOfPath = implode('/', array_slice($parts, 1));
                
                // Convert CamelCase to kebab-case for plugin directory
                $pluginDir = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $pluginName));
                
                // Try both naming conventions
                $files = [
                    $baseDir . $pluginDir . '/src/' . $restOfPath . '.php',
                    $baseDir . $pluginName . '/src/' . $restOfPath . '.php',
                ];
                
                foreach ($files as $file) {
                    if (file_exists($file)) {
                        require $file;
                        return;
                    }
                }
            }
        }
        
        // Standard PSR-4 autoloading
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

// Load helper files
$helperFiles = [
    __DIR__ . '/core/src/helpers.php',
    __DIR__ . '/core/src/Plugin/hooks.php',
];

foreach ($helperFiles as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}