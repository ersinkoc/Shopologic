<?php

declare(strict_types=1);

namespace Shopologic\Core\Security;

/**
 * Dependency Security Scanner
 * 
 * Scans dependencies for known vulnerabilities
 */
class DependencyScanner
{
    private array $knownVulnerabilities = [];
    private string $advisoryDatabase = '';
    
    public function __construct()
    {
        $this->initializeKnownVulnerabilities();
        $this->advisoryDatabase = 'https://packagist.org/api/security-advisories/';
    }
    
    /**
     * Initialize known vulnerability database
     */
    private function initializeKnownVulnerabilities(): void
    {
        // Common PHP vulnerabilities to check for
        $this->knownVulnerabilities = [
            'symfony/symfony' => [
                '<2.8.52' => 'CVE-2019-10909: Possible SQL injection',
                '<3.4.26' => 'CVE-2019-10910: Information disclosure',
                '<4.2.12' => 'CVE-2019-18888: CSRF vulnerability'
            ],
            'laravel/framework' => [
                '<5.5.40' => 'CVE-2018-15133: SQL injection vulnerability',
                '<5.6.30' => 'CVE-2018-15133: SQL injection vulnerability',
                '<5.7.15' => 'CVE-2018-15133: SQL injection vulnerability'
            ],
            'doctrine/orm' => [
                '<2.5.13' => 'CVE-2015-5723: Mass assignment vulnerability',
                '<2.6.4' => 'CVE-2020-5259: SQL injection vulnerability'
            ],
            'twig/twig' => [
                '<1.38.0' => 'CVE-2019-9942: Sandbox escape vulnerability',
                '<2.6.2' => 'CVE-2019-9942: Sandbox escape vulnerability'
            ],
            'monolog/monolog' => [
                '<1.24.0' => 'CVE-2018-13982: Code injection vulnerability',
                '<2.0.0' => 'CVE-2018-13982: Code injection vulnerability'
            ]
        ];
    }
    
    /**
     * Scan dependencies for vulnerabilities
     */
    public function scan(): array
    {
        $violations = [];
        
        // Scan Composer dependencies
        $composerViolations = $this->scanComposerDependencies();
        $violations = array_merge($violations, $composerViolations);
        
        // Scan for outdated PHP version
        $phpViolations = $this->scanPhpVersion();
        $violations = array_merge($violations, $phpViolations);
        
        // Scan for insecure extensions
        $extensionViolations = $this->scanPhpExtensions();
        $violations = array_merge($violations, $extensionViolations);
        
        return $violations;
    }
    
    /**
     * Scan Composer dependencies
     */
    private function scanComposerDependencies(): array
    {
        $violations = [];
        $rootPath = defined('SHOPOLOGIC_ROOT') ? SHOPOLOGIC_ROOT : dirname(__DIR__, 3);
        $composerLock = $rootPath . '/composer.lock';
        
        if (!file_exists($composerLock)) {
            $violations[] = [
                'type' => 'missing_lock_file',
                'severity' => 'medium',
                'message' => 'composer.lock file not found',
                'recommendation' => 'Run composer install to generate lock file',
                'file' => $composerLock
            ];
            return $violations;
        }
        
        $lockData = json_decode(file_get_contents($composerLock), true);
        
        if (!$lockData || !isset($lockData['packages'])) {
            $violations[] = [
                'type' => 'invalid_lock_file',
                'severity' => 'medium',
                'message' => 'Invalid or corrupted composer.lock file',
                'file' => $composerLock
            ];
            return $violations;
        }
        
        foreach ($lockData['packages'] as $package) {
            $packageName = $package['name'];
            $version = $package['version'];
            
            // Check against known vulnerabilities
            if (isset($this->knownVulnerabilities[$packageName])) {
                foreach ($this->knownVulnerabilities[$packageName] as $vulnerableVersion => $description) {
                    if ($this->isVersionVulnerable($version, $vulnerableVersion)) {
                        $violations[] = [
                            'type' => 'vulnerable_dependency',
                            'severity' => 'high',
                            'message' => "Vulnerable package: {$packageName} {$version}",
                            'description' => $description,
                            'package' => $packageName,
                            'version' => $version,
                            'vulnerable_version' => $vulnerableVersion,
                            'recommendation' => "Update {$packageName} to a secure version"
                        ];
                    }
                }
            }
            
            // Check for development dependencies in production
            if (isset($package['dev']) && $package['dev'] === true) {
                $violations[] = [
                    'type' => 'dev_dependency_in_production',
                    'severity' => 'low',
                    'message' => "Development dependency in production: {$packageName}",
                    'package' => $packageName,
                    'recommendation' => 'Use --no-dev flag when installing for production'
                ];
            }
        }
        
        return $violations;
    }
    
    /**
     * Scan PHP version for security issues
     */
    private function scanPhpVersion(): array
    {
        $violations = [];
        $currentVersion = PHP_VERSION;
        
        // Check if PHP version is supported
        $supportedVersions = ['8.0', '8.1', '8.2', '8.3'];
        $majorMinor = substr($currentVersion, 0, 3);
        
        if (!in_array($majorMinor, $supportedVersions)) {
            $severity = version_compare($currentVersion, '7.4.0', '<') ? 'critical' : 'high';
            
            $violations[] = [
                'type' => 'unsupported_php_version',
                'severity' => $severity,
                'message' => "Unsupported PHP version: {$currentVersion}",
                'current_version' => $currentVersion,
                'recommendation' => 'Upgrade to a supported PHP version (8.0+)'
            ];
        }
        
        // Check for known vulnerable versions
        $vulnerableVersions = [
            '8.0.0' => 'Multiple security vulnerabilities',
            '8.0.1' => 'Multiple security vulnerabilities',
            '8.1.0' => 'Multiple security vulnerabilities',
            '7.4.0' => 'Multiple security vulnerabilities'
        ];
        
        if (isset($vulnerableVersions[$currentVersion])) {
            $violations[] = [
                'type' => 'vulnerable_php_version',
                'severity' => 'high',
                'message' => "Vulnerable PHP version: {$currentVersion}",
                'description' => $vulnerableVersions[$currentVersion],
                'current_version' => $currentVersion,
                'recommendation' => 'Update to the latest patch version'
            ];
        }
        
        return $violations;
    }
    
    /**
     * Scan PHP extensions for security issues
     */
    private function scanPhpExtensions(): array
    {
        $violations = [];
        
        // Check for dangerous extensions
        $dangerousExtensions = [
            'eval' => 'Eval extension allows code execution',
            'assert' => 'Assert extension can be dangerous if misused',
            'phpinfo' => 'PHPInfo extension can leak information'
        ];
        
        foreach ($dangerousExtensions as $extension => $risk) {
            if (extension_loaded($extension)) {
                $violations[] = [
                    'type' => 'dangerous_extension',
                    'severity' => 'medium',
                    'message' => "Dangerous extension loaded: {$extension}",
                    'description' => $risk,
                    'extension' => $extension,
                    'recommendation' => "Consider disabling {$extension} extension in production"
                ];
            }
        }
        
        // Check for missing security extensions
        $securityExtensions = [
            'openssl' => 'Required for encryption and secure communications',
            'hash' => 'Required for secure hashing',
            'random' => 'Required for secure random number generation'
        ];
        
        foreach ($securityExtensions as $extension => $purpose) {
            if (!extension_loaded($extension)) {
                $violations[] = [
                    'type' => 'missing_security_extension',
                    'severity' => 'high',
                    'message' => "Missing security extension: {$extension}",
                    'description' => $purpose,
                    'extension' => $extension,
                    'recommendation' => "Install and enable {$extension} extension"
                ];
            }
        }
        
        return $violations;
    }
    
    /**
     * Check if version is vulnerable based on constraint
     */
    private function isVersionVulnerable(string $version, string $constraint): bool
    {
        // Remove 'v' prefix if present
        $version = ltrim($version, 'v');
        $constraint = ltrim($constraint, 'v');
        
        // Handle version constraints
        if (strpos($constraint, '<') === 0) {
            $constraintVersion = ltrim($constraint, '<');
            return version_compare($version, $constraintVersion, '<');
        }
        
        if (strpos($constraint, '<=') === 0) {
            $constraintVersion = ltrim($constraint, '<=');
            return version_compare($version, $constraintVersion, '<=');
        }
        
        if (strpos($constraint, '>') === 0) {
            $constraintVersion = ltrim($constraint, '>');
            return version_compare($version, $constraintVersion, '>');
        }
        
        if (strpos($constraint, '>=') === 0) {
            $constraintVersion = ltrim($constraint, '>=');
            return version_compare($version, $constraintVersion, '>=');
        }
        
        // Exact version match
        return version_compare($version, $constraint, '=');
    }
    
    /**
     * Fetch security advisories from external source
     */
    public function fetchSecurityAdvisories(): array
    {
        $advisories = [];
        
        try {
            // This would typically fetch from a security advisory API
            // For now, we'll return the static known vulnerabilities
            $advisories = $this->knownVulnerabilities;
        } catch (Exception $e) {
            // Fallback to local database
            $advisories = $this->knownVulnerabilities;
        }
        
        return $advisories;
    }
    
    /**
     * Check for outdated dependencies
     */
    public function checkOutdatedDependencies(): array
    {
        $outdated = [];
        $rootPath = defined('SHOPOLOGIC_ROOT') ? SHOPOLOGIC_ROOT : dirname(__DIR__, 3);
        $composerJson = $rootPath . '/composer.json';
        $composerLock = $rootPath . '/composer.lock';
        
        if (!file_exists($composerJson) || !file_exists($composerLock)) {
            return $outdated;
        }
        
        $jsonData = json_decode(file_get_contents($composerJson), true);
        $lockData = json_decode(file_get_contents($composerLock), true);
        
        if (!$jsonData || !$lockData) {
            return $outdated;
        }
        
        $requirements = array_merge(
            $jsonData['require'] ?? [],
            $jsonData['require-dev'] ?? []
        );
        
        foreach ($lockData['packages'] as $package) {
            $packageName = $package['name'];
            $installedVersion = $package['version'];
            
            if (isset($requirements[$packageName])) {
                $constraint = $requirements[$packageName];
                
                // This is a simplified check - in practice, you'd query
                // package repositories for the latest versions
                $outdated[] = [
                    'package' => $packageName,
                    'installed' => $installedVersion,
                    'constraint' => $constraint,
                    'type' => 'potentially_outdated'
                ];
            }
        }
        
        return $outdated;
    }
}