<?php

declare(strict_types=1);

namespace Shopologic\Core\Router;

class RouteCompiler
{
    public function compile(Route $route): CompiledRoute
    {
        $pattern = $this->compilePattern($route->getPath(), $route->getWhere());
        $variables = $this->extractVariables($route->getPath());
        
        return new CompiledRoute(
            $pattern,
            $variables,
            $route->getMethods(),
            $route->getDomain()
        );
    }

    private function compilePattern(string $path, array $requirements = []): string
    {
        $pattern = $path;
        
        // Replace route parameters with regex patterns
        $pattern = preg_replace_callback('/\{([^}]+)\}/', function($matches) use ($requirements) {
            $param = $matches[1];
            $optional = str_ends_with($param, '?');
            
            if ($optional) {
                $param = substr($param, 0, -1);
            }
            
            $regex = $requirements[$param] ?? '[^/]+';
            
            if ($optional) {
                return "(?P<{$param}>{$regex})?";
            }
            
            return "(?P<{$param}>{$regex})";
        }, $pattern);
        
        // Escape special regex characters
        $pattern = str_replace('/', '\/', $pattern);
        
        return '/^' . $pattern . '$/';
    }

    private function extractVariables(string $path): array
    {
        preg_match_all('/\{([^}]+)\}/', $path, $matches);
        
        $variables = [];
        foreach ($matches[1] as $variable) {
            $optional = str_ends_with($variable, '?');
            if ($optional) {
                $variable = substr($variable, 0, -1);
            }
            $variables[$variable] = !$optional;
        }
        
        return $variables;
    }
}