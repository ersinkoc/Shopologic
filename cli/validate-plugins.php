#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Shopologic\Core\Plugin\PluginValidator;

// ANSI color codes
const COLOR_RED = "\033[31m";
const COLOR_GREEN = "\033[32m";
const COLOR_YELLOW = "\033[33m";
const COLOR_BLUE = "\033[34m";
const COLOR_RESET = "\033[0m";

function printColored(string $text, string $color): void {
    echo $color . $text . COLOR_RESET;
}

function printHeader(string $text): void {
    echo "\n";
    printColored("=== {$text} ===\n", COLOR_BLUE);
}

function validatePlugins(): void {
    $pluginPath = __DIR__ . '/../plugins';
    $validator = new PluginValidator();
    
    printHeader("Shopologic Plugin Validation");
    
    if (!is_dir($pluginPath)) {
        printColored("Error: Plugin directory not found at {$pluginPath}\n", COLOR_RED);
        exit(1);
    }
    
    $directories = scandir($pluginPath);
    $totalPlugins = 0;
    $validPlugins = 0;
    $pluginsWithWarnings = 0;
    $invalidPlugins = 0;
    
    $results = [];
    
    foreach ($directories as $directory) {
        if ($directory === '.' || $directory === '..') {
            continue;
        }
        
        $pluginDir = $pluginPath . '/' . $directory;
        
        if (!is_dir($pluginDir)) {
            continue;
        }
        
        $totalPlugins++;
        
        // Check for plugin.json
        $manifestFile = $pluginDir . '/plugin.json';
        
        if (!file_exists($manifestFile)) {
            $results[] = [
                'name' => $directory,
                'valid' => false,
                'errors' => ["No plugin.json file found"],
                'warnings' => []
            ];
            $invalidPlugins++;
            continue;
        }
        
        // Load and validate manifest
        $manifestContent = file_get_contents($manifestFile);
        $manifest = json_decode($manifestContent, true);
        
        if (!$manifest) {
            $results[] = [
                'name' => $directory,
                'valid' => false,
                'errors' => ["Invalid JSON in plugin.json: " . json_last_error_msg()],
                'warnings' => []
            ];
            $invalidPlugins++;
            continue;
        }
        
        // Validate plugin
        $validationResult = $validator->validate($pluginDir, $manifest);
        
        $results[] = [
            'name' => $directory,
            'valid' => $validationResult->isValid(),
            'errors' => $validationResult->getErrors(),
            'warnings' => $validationResult->getWarnings()
        ];
        
        if ($validationResult->isValid()) {
            $validPlugins++;
            if ($validationResult->hasWarnings()) {
                $pluginsWithWarnings++;
            }
        } else {
            $invalidPlugins++;
        }
    }
    
    // Print summary
    printHeader("Validation Summary");
    
    echo "Total plugins: {$totalPlugins}\n";
    printColored("✓ Valid plugins: {$validPlugins}\n", COLOR_GREEN);
    
    if ($pluginsWithWarnings > 0) {
        printColored("⚠ Plugins with warnings: {$pluginsWithWarnings}\n", COLOR_YELLOW);
    }
    
    if ($invalidPlugins > 0) {
        printColored("✗ Invalid plugins: {$invalidPlugins}\n", COLOR_RED);
    }
    
    // Print detailed results
    if ($invalidPlugins > 0) {
        printHeader("Invalid Plugins");
        foreach ($results as $result) {
            if (!$result['valid']) {
                printColored("\n✗ {$result['name']}\n", COLOR_RED);
                foreach ($result['errors'] as $error) {
                    echo "  Error: {$error}\n";
                }
            }
        }
    }
    
    // Print warnings
    $hasWarnings = false;
    foreach ($results as $result) {
        if (!empty($result['warnings'])) {
            if (!$hasWarnings) {
                printHeader("Warnings");
                $hasWarnings = true;
            }
            printColored("\n⚠ {$result['name']}\n", COLOR_YELLOW);
            foreach ($result['warnings'] as $warning) {
                echo "  Warning: {$warning}\n";
            }
        }
    }
    
    // Print valid plugins
    if ($validPlugins > 0) {
        printHeader("Valid Plugins");
        $count = 0;
        foreach ($results as $result) {
            if ($result['valid']) {
                if ($count % 3 == 0) {
                    echo "\n";
                }
                $status = empty($result['warnings']) ? '✓' : '⚠';
                $color = empty($result['warnings']) ? COLOR_GREEN : COLOR_YELLOW;
                printColored(sprintf("%-25s ", $status . ' ' . $result['name']), $color);
                $count++;
            }
        }
        echo "\n";
    }
    
    echo "\n";
    
    // Exit code based on validation results
    if ($invalidPlugins > 0) {
        printColored("Validation failed! Fix the errors above and run again.\n", COLOR_RED);
        exit(1);
    } else {
        printColored("All plugins are valid!\n", COLOR_GREEN);
        exit(0);
    }
}

// Run validation
try {
    validatePlugins();
} catch (Exception $e) {
    printColored("Error: " . $e->getMessage() . "\n", COLOR_RED);
    exit(1);
}