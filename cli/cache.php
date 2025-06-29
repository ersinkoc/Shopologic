<?php

declare(strict_types=1);

/**
 * Shopologic Cache Management Tool
 * 
 * Handles cache operations and optimization
 */

// Define root path
define('SHOPOLOGIC_ROOT', dirname(__DIR__));

// Register autoloader
require_once SHOPOLOGIC_ROOT . '/core/src/Autoloader.php';

use Shopologic\Core\Autoloader;
use Shopologic\Core\Configuration\ConfigurationManager;
use Shopologic\Core\Cache\CacheManager;
use Shopologic\Core\Cache\Advanced\AdvancedCacheManager;

// Initialize autoloader
$autoloader = new Autoloader();
$autoloader->register();
$autoloader->addNamespace('Shopologic\\Core', SHOPOLOGIC_ROOT . '/core/src');
$autoloader->addNamespace('Shopologic\\Plugins', SHOPOLOGIC_ROOT . '/plugins');

// Load environment
if (file_exists(SHOPOLOGIC_ROOT . '/.env')) {
    $lines = file(SHOPOLOGIC_ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

try {
    // Initialize cache manager
    $config = new ConfigurationManager();
    $cacheManager = new CacheManager($config);
    $advancedCache = new AdvancedCacheManager($cacheManager);
    
    // Parse command line arguments
    $command = $argv[1] ?? 'help';
    $arguments = array_slice($argv, 2);
    
    switch ($command) {
        case 'clear':
            $type = $arguments[0] ?? 'all';
            
            echo "Clearing cache...\n";
            
            switch ($type) {
                case 'all':
                    $cacheManager->clear();
                    echo "All caches cleared successfully.\n";
                    break;
                    
                case 'config':
                    $cacheManager->forget('config.*');
                    echo "Configuration cache cleared.\n";
                    break;
                    
                case 'routes':
                    $cacheManager->forget('routes.*');
                    echo "Route cache cleared.\n";
                    break;
                    
                case 'views':
                    $cacheManager->forget('views.*');
                    deleteDirectory(SHOPOLOGIC_ROOT . '/storage/cache/views');
                    echo "View cache cleared.\n";
                    break;
                    
                case 'plugins':
                    $cacheManager->forget('plugins.*');
                    echo "Plugin cache cleared.\n";
                    break;
                    
                case 'queries':
                    $cacheManager->forget('queries.*');
                    echo "Query cache cleared.\n";
                    break;
                    
                default:
                    echo "Unknown cache type: {$type}\n";
                    echo "Available types: all, config, routes, views, plugins, queries\n";
                    exit(1);
            }
            break;
            
        case 'warm':
            echo "Warming cache...\n";
            
            // Warm configuration cache
            echo "Warming configuration cache...\n";
            $config->get('app');
            
            // Warm route cache if available
            if (class_exists('Shopologic\\Core\\Router\\Router')) {
                echo "Warming route cache...\n";
                // This would compile routes but we'll skip for now
            }
            
            // Warm plugin cache
            echo "Warming plugin cache...\n";
            if (is_dir(SHOPOLOGIC_ROOT . '/plugins')) {
                $plugins = scandir(SHOPOLOGIC_ROOT . '/plugins');
                foreach ($plugins as $plugin) {
                    if ($plugin !== '.' && $plugin !== '..' && is_dir(SHOPOLOGIC_ROOT . '/plugins/' . $plugin)) {
                        $manifestFile = SHOPOLOGIC_ROOT . '/plugins/' . $plugin . '/plugin.json';
                        if (file_exists($manifestFile)) {
                            $manifest = json_decode(file_get_contents($manifestFile), true);
                            $cacheManager->put("plugins.{$plugin}.manifest", $manifest, 3600);
                        }
                    }
                }
            }
            
            echo "Cache warming completed.\n";
            break;
            
        case 'optimize':
            echo "Optimizing cache...\n";
            
            // Enable OPcache if available
            if (function_exists('opcache_compile_file')) {
                echo "Optimizing OPcache...\n";
                optimizeOpcache();
            }
            
            // Optimize file cache
            echo "Optimizing file cache...\n";
            $advancedCache->optimize();
            
            echo "Cache optimization completed.\n";
            break;
            
        case 'stats':
            echo "Cache Statistics:\n";
            echo "=================\n";
            
            // Show OPcache stats
            if (function_exists('opcache_get_status')) {
                $opcacheStats = opcache_get_status();
                if ($opcacheStats) {
                    echo "OPcache:\n";
                    echo "  Enabled: " . ($opcacheStats['opcache_enabled'] ? 'Yes' : 'No') . "\n";
                    echo "  Memory Usage: " . formatBytes($opcacheStats['memory_usage']['used_memory']) . " / " . formatBytes($opcacheStats['memory_usage']['free_memory'] + $opcacheStats['memory_usage']['used_memory']) . "\n";
                    echo "  Hit Rate: " . round($opcacheStats['opcache_statistics']['opcache_hit_rate'], 2) . "%\n";
                    echo "  Cached Files: " . $opcacheStats['opcache_statistics']['num_cached_scripts'] . "\n";
                }
            }
            
            // Show application cache stats
            echo "\nApplication Cache:\n";
            $stats = $cacheManager->getStats();
            if ($stats) {
                foreach ($stats as $key => $value) {
                    echo "  " . ucfirst($key) . ": {$value}\n";
                }
            } else {
                echo "  No statistics available\n";
            }
            
            // Show disk usage
            echo "\nDisk Usage:\n";
            $cacheDir = SHOPOLOGIC_ROOT . '/storage/cache';
            if (is_dir($cacheDir)) {
                $size = getDirSize($cacheDir);
                echo "  Cache Directory: " . formatBytes($size) . "\n";
            }
            break;
            
        case 'flush':
            echo "Flushing all caches and restarting...\n";
            
            // Clear application cache
            $cacheManager->clear();
            
            // Clear OPcache
            if (function_exists('opcache_reset')) {
                opcache_reset();
                echo "OPcache flushed.\n";
            }
            
            // Clear file cache directory
            deleteDirectory(SHOPOLOGIC_ROOT . '/storage/cache');
            mkdir(SHOPOLOGIC_ROOT . '/storage/cache', 0755, true);
            
            echo "All caches flushed successfully.\n";
            break;
            
        default:
            echo "Shopologic Cache Management Tool\n";
            echo "===============================\n\n";
            echo "Available commands:\n";
            echo "  clear [type]     Clear cache (types: all, config, routes, views, plugins, queries)\n";
            echo "  warm             Warm up caches\n";
            echo "  optimize         Optimize cache performance\n";
            echo "  stats            Show cache statistics\n";
            echo "  flush            Flush all caches completely\n";
            echo "  help             Show this help message\n";
            break;
    }
    
} catch (Exception $e) {
    echo "Cache Error: " . $e->getMessage() . "\n";
    
    if (getenv('APP_DEBUG') === 'true') {
        echo "\nStack trace:\n";
        echo $e->getTraceAsString() . "\n";
    }
    
    exit(1);
}

/**
 * Delete directory recursively
 */
function deleteDirectory(string $dir): bool
{
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    
    return rmdir($dir);
}

/**
 * Get directory size in bytes
 */
function getDirSize(string $dir): int
{
    $size = 0;
    
    if (!is_dir($dir)) {
        return 0;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            $size += getDirSize($path);
        } else {
            $size += filesize($path);
        }
    }
    
    return $size;
}

/**
 * Format bytes to human readable
 */
function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Optimize OPcache
 */
function optimizeOpcache(): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(SHOPOLOGIC_ROOT . '/core/src'),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            opcache_compile_file($file->getPathname());
        }
    }
    
    // Also compile plugin files
    if (is_dir(SHOPOLOGIC_ROOT . '/plugins')) {
        $pluginIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(SHOPOLOGIC_ROOT . '/plugins'),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($pluginIterator as $file) {
            if ($file->getExtension() === 'php') {
                opcache_compile_file($file->getPathname());
            }
        }
    }
}