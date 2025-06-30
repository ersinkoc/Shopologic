<?php

declare(strict_types=1);

namespace Shopologic\Core\Plugin;

use Shopologic\Core\Plugin\Exception\ValidationException;

class PluginValidator
{
    protected array $requiredManifestFields = [
        'name',
        'version',
        'description',
        'author'
    ];
    
    protected array $optionalManifestFields = [
        'main',
        'class',
        'file',
        'main_class',
        'namespace',
        'autoload',
        'permissions',
        'dependencies',
        'requirements',
        'config',
        'hooks',
        'api_endpoints',
        'cron_jobs',
        'widgets',
        'features',
        'settings'
    ];
    
    protected array $errors = [];
    protected array $warnings = [];
    
    /**
     * Validate a plugin
     */
    public function validate(string $pluginPath, array $manifest): ValidationResult
    {
        $this->errors = [];
        $this->warnings = [];
        
        $pluginName = basename($pluginPath);
        
        // Validate manifest structure
        $this->validateManifest($manifest, $pluginName);
        
        // Validate plugin class
        $this->validatePluginClass($pluginPath, $manifest, $pluginName);
        
        // Validate directory structure
        $this->validateDirectoryStructure($pluginPath, $pluginName);
        
        // Validate dependencies
        if (isset($manifest['dependencies'])) {
            $this->validateDependencies($manifest['dependencies'], $pluginName);
        }
        
        // Validate permissions
        if (isset($manifest['permissions'])) {
            $this->validatePermissions($manifest['permissions'], $pluginName);
        }
        
        // Validate version format
        if (isset($manifest['version'])) {
            $this->validateVersion($manifest['version'], $pluginName);
        }
        
        return new ValidationResult($this->errors, $this->warnings);
    }
    
    /**
     * Validate manifest structure
     */
    protected function validateManifest(array $manifest, string $pluginName): void
    {
        // Check required fields
        foreach ($this->requiredManifestFields as $field) {
            if (!isset($manifest[$field])) {
                $this->errors[] = "Plugin {$pluginName}: Missing required field '{$field}' in plugin.json";
            }
        }
        
        // Check for class definition
        $hasClassDefinition = isset($manifest['class']) || 
                             isset($manifest['main']) || 
                             isset($manifest['main_class']) || 
                             (isset($manifest['config']['main_class']));
        
        if (!$hasClassDefinition) {
            $this->errors[] = "Plugin {$pluginName}: No class definition found (class, main, or main_class field required)";
        }
        
        // Check for unknown fields
        $knownFields = array_merge($this->requiredManifestFields, $this->optionalManifestFields);
        foreach (array_keys($manifest) as $field) {
            if (!in_array($field, $knownFields)) {
                $this->warnings[] = "Plugin {$pluginName}: Unknown field '{$field}' in plugin.json";
            }
        }
        
        // Recommend using 'main' field
        if (!isset($manifest['main']) && (isset($manifest['class']) || isset($manifest['main_class']))) {
            $this->warnings[] = "Plugin {$pluginName}: Consider using the standardized 'main' field instead of 'class' or 'main_class'";
        }
    }
    
    /**
     * Validate plugin class
     */
    protected function validatePluginClass(string $pluginPath, array $manifest, string $pluginName): void
    {
        try {
            // Try to resolve plugin class using PluginManager logic
            $pluginInfo = $this->resolvePluginClass($pluginPath, $manifest);
            $classFile = $pluginInfo['file'];
            $className = $pluginInfo['class'];
            
            if (!file_exists($classFile)) {
                $this->errors[] = "Plugin {$pluginName}: Main class file not found at {$classFile}";
                return;
            }
            
            // Check if file is readable
            if (!is_readable($classFile)) {
                $this->errors[] = "Plugin {$pluginName}: Main class file is not readable";
                return;
            }
            
            // Validate PHP syntax
            $output = shell_exec("php -l {$classFile} 2>&1");
            if (!str_contains($output, 'No syntax errors detected')) {
                $this->errors[] = "Plugin {$pluginName}: PHP syntax error in main class file";
            }
            
            // Check class structure
            $content = file_get_contents($classFile);
            
            // Check namespace
            if (!preg_match('/namespace\s+([^;]+);/', $content)) {
                $this->warnings[] = "Plugin {$pluginName}: No namespace declaration found";
            }
            
            // Check class extends AbstractPlugin or implements PluginInterface
            if (!preg_match('/class\s+\w+\s+extends\s+(?:AbstractPlugin|Plugin)/', $content) &&
                !preg_match('/class\s+\w+\s+implements\s+PluginInterface/', $content)) {
                $this->errors[] = "Plugin {$pluginName}: Main class must extend AbstractPlugin or implement PluginInterface";
            }
            
        } catch (\Exception $e) {
            $this->errors[] = "Plugin {$pluginName}: Failed to validate plugin class - " . $e->getMessage();
        }
    }
    
    /**
     * Validate directory structure
     */
    protected function validateDirectoryStructure(string $pluginPath, string $pluginName): void
    {
        // Check if plugin directory exists
        if (!is_dir($pluginPath)) {
            $this->errors[] = "Plugin {$pluginName}: Plugin directory does not exist";
            return;
        }
        
        // Recommend standard directories
        $recommendedDirs = ['src', 'migrations', 'templates', 'assets'];
        $existingDirs = [];
        
        foreach ($recommendedDirs as $dir) {
            if (is_dir($pluginPath . '/' . $dir)) {
                $existingDirs[] = $dir;
            }
        }
        
        if (empty($existingDirs)) {
            $this->warnings[] = "Plugin {$pluginName}: Consider organizing code in standard directories (src/, migrations/, templates/, assets/)";
        }
        
        // Check for README
        if (!file_exists($pluginPath . '/README.md') && !file_exists($pluginPath . '/readme.md')) {
            $this->warnings[] = "Plugin {$pluginName}: No README.md file found";
        }
    }
    
    /**
     * Validate dependencies
     */
    protected function validateDependencies(array $dependencies, string $pluginName): void
    {
        foreach ($dependencies as $dependency => $version) {
            // Validate version constraint format
            if (!$this->isValidVersionConstraint($version)) {
                $this->errors[] = "Plugin {$pluginName}: Invalid version constraint '{$version}' for dependency '{$dependency}'";
            }
        }
    }
    
    /**
     * Validate permissions
     */
    protected function validatePermissions(array $permissions, string $pluginName): void
    {
        foreach ($permissions as $permission) {
            if (!is_string($permission)) {
                $this->errors[] = "Plugin {$pluginName}: Invalid permission format - permissions must be strings";
                continue;
            }
            
            // Check permission naming convention
            if (!preg_match('/^[a-z_]+\.[a-z_]+$/', $permission)) {
                $this->warnings[] = "Plugin {$pluginName}: Permission '{$permission}' doesn't follow naming convention (resource.action)";
            }
        }
    }
    
    /**
     * Validate version format
     */
    protected function validateVersion(string $version, string $pluginName): void
    {
        // Check semantic versioning
        if (!preg_match('/^\d+\.\d+\.\d+(?:-[a-zA-Z0-9]+)?$/', $version)) {
            $this->warnings[] = "Plugin {$pluginName}: Version '{$version}' doesn't follow semantic versioning (X.Y.Z)";
        }
    }
    
    /**
     * Check if version constraint is valid
     */
    protected function isValidVersionConstraint(string $constraint): bool
    {
        // Simple validation for common constraint patterns
        $patterns = [
            '/^\*$/',                          // Any version
            '/^\d+\.\d+\.\d+$/',              // Exact version
            '/^[><=]+\d+\.\d+\.\d+$/',        // Comparison operators
            '/^\^\d+\.\d+\.\d+$/',            // Caret constraint
            '/^~\d+\.\d+\.\d+$/',             // Tilde constraint
            '/^\d+\.\d+\.\*$/',               // Wildcard
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $constraint)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Resolve plugin class (similar to PluginManager logic)
     */
    protected function resolvePluginClass(string $pluginPath, array $manifest): array
    {
        // Priority 1: New standardized 'main' field
        if (isset($manifest['main'])) {
            $classFile = $pluginPath . '/' . $manifest['main'];
            $className = $this->extractClassNameFromFile($classFile);
            return ['file' => $classFile, 'class' => $className];
        }
        
        // Priority 2: Legacy 'class' + 'file' combination
        if (isset($manifest['class']) && isset($manifest['file'])) {
            return [
                'file' => $pluginPath . '/' . $manifest['file'],
                'class' => $manifest['class']
            ];
        }
        
        // Priority 3: Only 'class' field
        if (isset($manifest['class'])) {
            $className = $manifest['class'];
            $parts = explode('\\', $className);
            $simpleClassName = end($parts);
            
            // Common locations
            $locations = [
                $simpleClassName . '.php',
                'src/' . $simpleClassName . '.php',
            ];
            
            foreach ($locations as $location) {
                $file = $pluginPath . '/' . $location;
                if (file_exists($file)) {
                    return ['file' => $file, 'class' => $className];
                }
            }
        }
        
        throw new \Exception("Could not resolve plugin class");
    }
    
    /**
     * Extract class name from file
     */
    protected function extractClassNameFromFile(string $file): ?string
    {
        if (!file_exists($file)) {
            return null;
        }
        
        $content = file_get_contents($file);
        
        // Extract namespace
        $namespace = null;
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = $matches[1];
        }
        
        // Extract class name
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            $className = $matches[1];
            return $namespace ? $namespace . '\\' . $className : $className;
        }
        
        return null;
    }
}

class ValidationResult
{
    protected array $errors;
    protected array $warnings;
    
    public function __construct(array $errors, array $warnings)
    {
        $this->errors = $errors;
        $this->warnings = $warnings;
    }
    
    public function isValid(): bool
    {
        return empty($this->errors);
    }
    
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    public function getWarnings(): array
    {
        return $this->warnings;
    }
    
    public function getReport(): string
    {
        $report = '';
        
        if (!empty($this->errors)) {
            $report .= "Errors:\n";
            foreach ($this->errors as $error) {
                $report .= "  - {$error}\n";
            }
            $report .= "\n";
        }
        
        if (!empty($this->warnings)) {
            $report .= "Warnings:\n";
            foreach ($this->warnings as $warning) {
                $report .= "  - {$warning}\n";
            }
        }
        
        return $report;
    }
}