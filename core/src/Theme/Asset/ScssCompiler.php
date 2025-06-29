<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Asset;

/**
 * Simple SCSS compiler with basic features
 */
class ScssCompiler
{
    private array $variables = [];
    private array $mixins = [];
    private array $imports = [];
    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'import_paths' => [],
            'precision' => 5
        ], $options);
    }

    /**
     * Compile SCSS to CSS
     */
    public function compile(string $scss): string
    {
        // Reset state
        $this->variables = [];
        $this->mixins = [];
        $this->imports = [];
        
        // Process imports
        $scss = $this->processImports($scss);
        
        // Process variables
        $scss = $this->processVariables($scss);
        
        // Process mixins
        $scss = $this->processMixins($scss);
        
        // Process nesting
        $scss = $this->processNesting($scss);
        
        // Process color functions
        $scss = $this->processColorFunctions($scss);
        
        // Process math expressions
        $scss = $this->processMath($scss);
        
        // Process extends
        $scss = $this->processExtends($scss);
        
        // Clean up
        $scss = $this->cleanCss($scss);
        
        return $scss;
    }

    // Process imports
    private function processImports(string $scss): string
    {
        return preg_replace_callback(
            '/@import\s+["\']([^"\']+)["\']\s*;/i',
            function ($matches) {
                $file = $matches[1];
                
                // Add .scss extension if not present
                if (!preg_match('/\.s?css$/', $file)) {
                    $file .= '.scss';
                }
                
                // Try to find file in import paths
                foreach ($this->options['import_paths'] as $path) {
                    $fullPath = $path . '/' . $file;
                    if (file_exists($fullPath)) {
                        $content = file_get_contents($fullPath);
                        return $this->compile($content);
                    }
                }
                
                return '';
            },
            $scss
        );
    }

    // Process variables
    private function processVariables(string $scss): string
    {
        // Extract variables
        preg_match_all('/\$([a-zA-Z_][\w-]*)\s*:\s*([^;]+);/', $scss, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $this->variables[$match[1]] = trim($match[2]);
        }
        
        // Replace variable references
        foreach ($this->variables as $name => $value) {
            $scss = preg_replace('/\$' . preg_quote($name, '/') . '\b/', $value, $scss);
        }
        
        // Remove variable declarations
        $scss = preg_replace('/\$[a-zA-Z_][\w-]*\s*:\s*[^;]+;\s*/', '', $scss);
        
        return $scss;
    }

    // Process mixins
    private function processMixins(string $scss): string
    {
        // Extract mixin definitions
        preg_match_all('/@mixin\s+([a-zA-Z_][\w-]*)\s*(\([^)]*\))?\s*{([^}]+)}/s', $scss, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $name = $match[1];
            $params = $match[2] ?? '';
            $body = $match[3];
            
            $this->mixins[$name] = [
                'params' => $this->parseMixinParams($params),
                'body' => $body
            ];
        }
        
        // Remove mixin definitions
        $scss = preg_replace('/@mixin\s+[a-zA-Z_][\w-]*\s*(\([^)]*\))?\s*{[^}]+}/s', '', $scss);
        
        // Process mixin includes
        $scss = preg_replace_callback(
            '/@include\s+([a-zA-Z_][\w-]*)\s*(\([^)]*\))?\s*;/s',
            function ($matches) {
                $name = $matches[1];
                $args = $matches[2] ?? '';
                
                if (!isset($this->mixins[$name])) {
                    return '';
                }
                
                $mixin = $this->mixins[$name];
                $body = $mixin['body'];
                
                // Replace parameters
                if ($args && $mixin['params']) {
                    $argValues = $this->parseMixinArgs($args);
                    foreach ($mixin['params'] as $i => $param) {
                        if (isset($argValues[$i])) {
                            $body = str_replace('$' . $param, $argValues[$i], $body);
                        }
                    }
                }
                
                return $body;
            },
            $scss
        );
        
        return $scss;
    }

    // Process nesting
    private function processNesting(string $scss): string
    {
        $output = '';
        $lines = explode("\n", $scss);
        $selectors = [];
        $currentBlock = '';
        $braceCount = 0;
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            if (empty($trimmed)) {
                continue;
            }
            
            // Count braces
            $openBraces = substr_count($line, '{');
            $closeBraces = substr_count($line, '}');
            
            if ($openBraces > 0 && $braceCount === 0) {
                // New selector
                $selector = trim(str_replace('{', '', $line));
                $selectors[] = $selector;
                $braceCount += $openBraces - $closeBraces;
            } elseif ($closeBraces > 0) {
                $braceCount -= $closeBraces;
                
                if ($braceCount === 0) {
                    // End of block
                    $fullSelector = $this->buildNestedSelector($selectors);
                    $output .= $fullSelector . " {\n" . $currentBlock . "}\n";
                    array_pop($selectors);
                    $currentBlock = '';
                } else {
                    $currentBlock .= $line . "\n";
                }
            } else {
                if ($braceCount > 0) {
                    $currentBlock .= $line . "\n";
                } else {
                    $output .= $line . "\n";
                }
                $braceCount += $openBraces - $closeBraces;
            }
        }
        
        return $output;
    }

    // Process color functions
    private function processColorFunctions(string $scss): string
    {
        // lighten()
        $scss = preg_replace_callback(
            '/lighten\s*\(\s*([^,]+),\s*([^)]+)\s*\)/',
            function ($matches) {
                $color = trim($matches[1]);
                $amount = trim($matches[2]);
                return $this->adjustColor($color, $amount, 'lighten');
            },
            $scss
        );
        
        // darken()
        $scss = preg_replace_callback(
            '/darken\s*\(\s*([^,]+),\s*([^)]+)\s*\)/',
            function ($matches) {
                $color = trim($matches[1]);
                $amount = trim($matches[2]);
                return $this->adjustColor($color, $amount, 'darken');
            },
            $scss
        );
        
        // rgba()
        $scss = preg_replace_callback(
            '/rgba\s*\(\s*([^,]+),\s*([^)]+)\s*\)/',
            function ($matches) {
                $color = trim($matches[1]);
                $alpha = trim($matches[2]);
                return $this->colorToRgba($color, $alpha);
            },
            $scss
        );
        
        return $scss;
    }

    // Process math expressions
    private function processMath(string $scss): string
    {
        // Simple math operations in calc()
        $scss = preg_replace_callback(
            '/calc\s*\(\s*([^)]+)\s*\)/',
            function ($matches) {
                $expression = $matches[1];
                // Keep calc() as is - browsers handle it
                return 'calc(' . $expression . ')';
            },
            $scss
        );
        
        // Process percentage calculations
        $scss = preg_replace_callback(
            '/(\d+)\s*\/\s*(\d+)\s*\*\s*100%/',
            function ($matches) {
                $numerator = floatval($matches[1]);
                $denominator = floatval($matches[2]);
                $result = ($numerator / $denominator) * 100;
                return round($result, $this->options['precision']) . '%';
            },
            $scss
        );
        
        return $scss;
    }

    // Process extends
    private function processExtends(string $scss): string
    {
        $extends = [];
        
        // Extract extends
        preg_match_all('/([^{]+){([^}]*@extend\s+([^;]+);[^}]*)}/s', $scss, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $selector = trim($match[1]);
            $extendedSelector = trim($match[3]);
            
            if (!isset($extends[$extendedSelector])) {
                $extends[$extendedSelector] = [];
            }
            $extends[$extendedSelector][] = $selector;
        }
        
        // Apply extends
        foreach ($extends as $baseSelector => $extendingSelectors) {
            $scss = preg_replace_callback(
                '/' . preg_quote($baseSelector, '/') . '\s*{([^}]+)}/s',
                function ($matches) use ($baseSelector, $extendingSelectors) {
                    $rules = $matches[1];
                    $selectors = array_merge([$baseSelector], $extendingSelectors);
                    return implode(', ', $selectors) . ' {' . $rules . '}';
                },
                $scss
            );
        }
        
        // Remove @extend statements
        $scss = preg_replace('/@extend\s+[^;]+;/', '', $scss);
        
        return $scss;
    }

    // Helper methods

    private function parseMixinParams(string $params): array
    {
        if (empty($params)) {
            return [];
        }
        
        $params = trim($params, '()');
        $parts = explode(',', $params);
        
        return array_map(function ($part) {
            return trim(str_replace('$', '', $part));
        }, $parts);
    }

    private function parseMixinArgs(string $args): array
    {
        if (empty($args)) {
            return [];
        }
        
        $args = trim($args, '()');
        $parts = explode(',', $args);
        
        return array_map('trim', $parts);
    }

    private function buildNestedSelector(array $selectors): string
    {
        $result = '';
        
        foreach ($selectors as $i => $selector) {
            if ($i === 0) {
                $result = $selector;
            } else {
                if (strpos($selector, '&') !== false) {
                    $result = str_replace('&', $result, $selector);
                } else {
                    $result .= ' ' . $selector;
                }
            }
        }
        
        return $result;
    }

    private function adjustColor(string $color, string $amount, string $operation): string
    {
        // Simple hex color adjustment
        if (preg_match('/^#([0-9a-f]{6})$/i', $color, $matches)) {
            $hex = $matches[1];
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            
            $percent = floatval(str_replace('%', '', $amount)) / 100;
            
            if ($operation === 'lighten') {
                $r = min(255, $r + (255 - $r) * $percent);
                $g = min(255, $g + (255 - $g) * $percent);
                $b = min(255, $b + (255 - $b) * $percent);
            } else {
                $r = max(0, $r - $r * $percent);
                $g = max(0, $g - $g * $percent);
                $b = max(0, $b - $b * $percent);
            }
            
            return sprintf('#%02x%02x%02x', $r, $g, $b);
        }
        
        return $color;
    }

    private function colorToRgba(string $color, string $alpha): string
    {
        if (preg_match('/^#([0-9a-f]{6})$/i', $color, $matches)) {
            $hex = $matches[1];
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            
            return sprintf('rgba(%d, %d, %d, %s)', $r, $g, $b, $alpha);
        }
        
        return $color;
    }

    private function cleanCss(string $css): string
    {
        // Remove empty rules
        $css = preg_replace('/[^{}]+{\s*}/', '', $css);
        
        // Remove multiple whitespace
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Remove whitespace around braces
        $css = preg_replace('/\s*{\s*/', ' { ', $css);
        $css = preg_replace('/\s*}\s*/', ' } ', $css);
        
        // Remove whitespace around semicolons
        $css = preg_replace('/\s*;\s*/', '; ', $css);
        
        return trim($css);
    }
}