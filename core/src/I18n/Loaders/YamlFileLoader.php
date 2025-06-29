<?php

declare(strict_types=1);

namespace Shopologic\Core\I18n\Loaders;

use Shopologic\Core\I18n\TranslationLoaderInterface;

/**
 * Loads translations from YAML files
 */
class YamlFileLoader implements TranslationLoaderInterface
{
    public function load(string $file): array
    {
        if (!file_exists($file)) {
            return [];
        }
        
        $content = file_get_contents($file);
        
        // Simple YAML parser for basic key-value pairs
        $translations = [];
        $lines = explode("\n", $content);
        $currentKey = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines and comments
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            // Check indentation level
            $indent = strlen($line) - strlen(ltrim($line));
            $line = ltrim($line);
            
            // Parse key-value pairs
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if (preg_match('/^["\'](.+)["\']$/', $value, $matches)) {
                    $value = $matches[1];
                }
                
                if ($indent === 0) {
                    $currentKey = $key;
                    if (!empty($value)) {
                        $translations[$key] = $value;
                    } else {
                        $translations[$key] = [];
                    }
                } else {
                    if (is_array($translations[$currentKey])) {
                        $translations[$currentKey][$key] = $value;
                    }
                }
            }
        }
        
        return $translations;
    }
}