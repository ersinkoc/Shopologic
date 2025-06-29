<?php

declare(strict_types=1);

/**
 * Shopologic Security Scanner CLI
 * 
 * Comprehensive security scanning and hardening tool
 */

// Define root path
define('SHOPOLOGIC_ROOT', dirname(__DIR__));

// Register autoloader
require_once SHOPOLOGIC_ROOT . '/core/src/Autoloader.php';

use Shopologic\Core\Autoloader;
use Shopologic\Core\Configuration\ConfigurationManager;
use Shopologic\Core\Events\EventDispatcher;
use Shopologic\Core\Security\SecurityManager;

// Initialize autoloader
$autoloader = new Autoloader();
$autoloader->register();
$autoloader->addNamespace('Shopologic\\Core', SHOPOLOGIC_ROOT . '/core/src');

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
    // Initialize security manager
    $config = new ConfigurationManager();
    $events = new EventDispatcher();
    $security = new SecurityManager($config, $events);
    
    // Parse command line arguments
    $command = $argv[1] ?? 'help';
    $arguments = array_slice($argv, 2);
    
    switch ($command) {
        case 'scan':
            $scanTypes = $arguments ?: [];
            
            echo "Shopologic Security Scanner\n";
            echo "==========================\n\n";
            
            if (empty($scanTypes)) {
                echo "Running comprehensive security scan...\n\n";
            } else {
                echo "Running security scan for: " . implode(', ', $scanTypes) . "\n\n";
            }
            
            $report = $security->runScan($scanTypes);
            displaySecurityReport($report);
            break;
            
        case 'config':
            echo "Configuration Security Check\n";
            echo "============================\n\n";
            
            $issues = $security->validateConfiguration();
            displayConfigurationIssues($issues);
            break;
            
        case 'headers':
            echo "Security Headers\n";
            echo "================\n\n";
            
            $headers = $security->getSecurityHeaders();
            displaySecurityHeaders($headers);
            break;
            
        case 'recommendations':
            echo "Security Recommendations\n";
            echo "=========================\n\n";
            
            $recommendations = $security->getSecurityRecommendations();
            displayRecommendations($recommendations);
            break;
            
        case 'file':
            $filePath = $arguments[0] ?? null;
            if (!$filePath) {
                echo "Error: File path is required.\n";
                echo "Usage: php cli/security.php file /path/to/file.php\n";
                exit(1);
            }
            
            echo "File Security Scan\n";
            echo "==================\n\n";
            
            $violations = $security->scanFile($filePath);
            displayFileViolations($violations, $filePath);
            break;
            
        case 'harden':
            echo "Security Hardening\n";
            echo "==================\n\n";
            
            performSecurityHardening();
            break;
            
        case 'report':
            $format = $arguments[0] ?? 'text';
            
            echo "Generating security report...\n";
            
            $report = $security->runScan();
            generateSecurityReport($report, $format);
            break;
            
        default:
            echo "Shopologic Security Scanner\n";
            echo "==========================\n\n";
            echo "Available commands:\n";
            echo "  scan [types]         Run security scan (types: code, dependency, configuration, input, file)\n";
            echo "  config               Check configuration security\n";
            echo "  headers              Display security headers\n";
            echo "  recommendations      Show security recommendations\n";
            echo "  file <path>          Scan specific file\n";
            echo "  harden               Apply security hardening\n";
            echo "  report [format]      Generate security report (formats: text, json, html)\n";
            echo "  help                 Show this help message\n\n";
            echo "Examples:\n";
            echo "  php cli/security.php scan\n";
            echo "  php cli/security.php scan code dependency\n";
            echo "  php cli/security.php file core/src/Security/SecurityManager.php\n";
            echo "  php cli/security.php report json\n";
            break;
    }
    
} catch (Exception $e) {
    echo "Security Error: " . $e->getMessage() . "\n";
    
    if (getenv('APP_DEBUG') === 'true') {
        echo "\nStack trace:\n";
        echo $e->getTraceAsString() . "\n";
    }
    
    exit(1);
}

/**
 * Display security report
 */
function displaySecurityReport($report): void
{
    $results = $report->getResults();
    $summary = $report->getSummary();
    
    // Display summary
    echo "Security Scan Summary\n";
    echo "=====================\n";
    echo "Total Issues: " . $report->getTotalIssues() . "\n";
    echo "Critical: " . $report->getCriticalIssues() . "\n";
    echo "High: " . $report->getHighIssues() . "\n";
    echo "Medium: " . $report->getMediumIssues() . "\n";
    echo "Low: " . $report->getLowIssues() . "\n\n";
    
    // Display results by category
    foreach ($results as $scanType => $violations) {
        if (empty($violations)) {
            echo "✅ {$scanType}: No issues found\n";
            continue;
        }
        
        echo "❌ {$scanType}: " . count($violations) . " issues found\n";
        echo str_repeat('-', 50) . "\n";
        
        foreach ($violations as $violation) {
            $severity = strtoupper($violation['severity'] ?? 'unknown');
            $icon = getSeverityIcon($violation['severity'] ?? 'unknown');
            
            echo "{$icon} [{$severity}] {$violation['message']}\n";
            
            if (isset($violation['file'])) {
                echo "    File: {$violation['file']}";
                if (isset($violation['line'])) {
                    echo ":{$violation['line']}";
                }
                echo "\n";
            }
            
            if (isset($violation['recommendation'])) {
                echo "    Fix: {$violation['recommendation']}\n";
            }
            
            if (isset($violation['context'])) {
                echo "    Context: " . substr($violation['context'], 0, 100) . "...\n";
            }
            
            echo "\n";
        }
        
        echo "\n";
    }
    
    // Exit with error code if critical or high issues found
    if ($report->getCriticalIssues() > 0 || $report->getHighIssues() > 0) {
        exit(1);
    }
}

/**
 * Display configuration issues
 */
function displayConfigurationIssues(array $issues): void
{
    if (empty($issues)) {
        echo "✅ No configuration security issues found\n";
        return;
    }
    
    foreach ($issues as $issue) {
        $severity = strtoupper($issue['severity']);
        $icon = getSeverityIcon($issue['severity']);
        
        echo "{$icon} [{$severity}] {$issue['message']}\n";
        
        if (isset($issue['config_key'])) {
            echo "    Config: {$issue['config_key']}\n";
        }
        
        if (isset($issue['current_value'])) {
            echo "    Current: {$issue['current_value']}\n";
        }
        
        if (isset($issue['recommended_value'])) {
            echo "    Recommended: {$issue['recommended_value']}\n";
        }
        
        if (isset($issue['recommendation'])) {
            echo "    Fix: {$issue['recommendation']}\n";
        }
        
        echo "\n";
    }
}

/**
 * Display security headers
 */
function displaySecurityHeaders(array $headers): void
{
    echo "Recommended security headers for your web server:\n\n";
    
    foreach ($headers as $header => $value) {
        echo "{$header}: {$value}\n";
    }
    
    echo "\nAdd these headers to your web server configuration:\n\n";
    echo "Apache (.htaccess):\n";
    echo "Header always set X-Content-Type-Options nosniff\n";
    echo "Header always set X-Frame-Options DENY\n";
    echo "# ... add other headers\n\n";
    
    echo "Nginx:\n";
    echo "add_header X-Content-Type-Options nosniff;\n";
    echo "add_header X-Frame-Options DENY;\n";
    echo "# ... add other headers\n";
}

/**
 * Display security recommendations
 */
function displayRecommendations(array $recommendations): void
{
    foreach ($recommendations as $category) {
        echo "{$category['category']}\n";
        echo str_repeat('-', strlen($category['category'])) . "\n";
        
        foreach ($category['recommendations'] as $recommendation) {
            echo "• {$recommendation}\n";
        }
        
        echo "\n";
    }
}

/**
 * Display file violations
 */
function displayFileViolations(array $violations, string $filePath): void
{
    if (empty($violations)) {
        echo "✅ No security issues found in {$filePath}\n";
        return;
    }
    
    echo "❌ Found " . count($violations) . " security issues in {$filePath}\n\n";
    
    foreach ($violations as $violation) {
        $severity = strtoupper($violation['severity']);
        $icon = getSeverityIcon($violation['severity']);
        
        echo "{$icon} [{$severity}] {$violation['message']}\n";
        
        if (isset($violation['line'])) {
            echo "    Line: {$violation['line']}\n";
        }
        
        if (isset($violation['context'])) {
            echo "    Context: {$violation['context']}\n";
        }
        
        echo "\n";
    }
}

/**
 * Perform security hardening
 */
function performSecurityHardening(): void
{
    $hardeningSteps = [
        'Create secure upload directory protection',
        'Generate secure .htaccess rules',
        'Create security configuration template',
        'Set secure file permissions',
        'Generate CSP header template'
    ];
    
    foreach ($hardeningSteps as $step) {
        echo "⏳ {$step}...\n";
        
        switch ($step) {
            case 'Create secure upload directory protection':
                createUploadProtection();
                break;
                
            case 'Generate secure .htaccess rules':
                generateSecureHtaccess();
                break;
                
            case 'Create security configuration template':
                createSecurityConfig();
                break;
                
            case 'Set secure file permissions':
                setSecurePermissions();
                break;
                
            case 'Generate CSP header template':
                generateCSPTemplate();
                break;
        }
        
        echo "✅ {$step} completed\n";
    }
    
    echo "\n🛡️  Security hardening completed!\n";
}

/**
 * Create upload directory protection
 */
function createUploadProtection(): void
{
    $uploadDirs = [
        SHOPOLOGIC_ROOT . '/storage/uploads',
        SHOPOLOGIC_ROOT . '/public/uploads'
    ];
    
    $htaccessContent = <<<HTACCESS
# Prevent script execution
php_flag engine off
RemoveHandler .php .phtml .php3 .php4 .php5 .php6
RemoveType .php .phtml .php3 .php4 .php5 .php6

# Allow only specific file types
<FilesMatch "\\.(jpg|jpeg|png|gif|svg|pdf|doc|docx|xls|xlsx)$">
    Order Allow,Deny
    Allow from all
</FilesMatch>

# Deny everything else
<FilesMatch "^.*$">
    Order Deny,Allow
    Deny from all
</FilesMatch>
HTACCESS;
    
    foreach ($uploadDirs as $dir) {
        if (is_dir($dir)) {
            file_put_contents($dir . '/.htaccess', $htaccessContent);
        }
    }
}

/**
 * Generate secure .htaccess
 */
function generateSecureHtaccess(): void
{
    $htaccessPath = SHOPOLOGIC_ROOT . '/.htaccess.security';
    
    $htaccessContent = <<<HTACCESS
# Security Headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
</IfModule>

# Hide server information
ServerTokens Prod
ServerSignature Off

# Protect sensitive files
<Files .env>
    Order Allow,Deny
    Deny from all
</Files>

<Files composer.json>
    Order Allow,Deny
    Deny from all
</Files>

<Files composer.lock>
    Order Allow,Deny
    Deny from all
</Files>

# Disable directory browsing
Options -Indexes

# Prevent access to PHP files in uploads
<DirectoryMatch "^.*/uploads/">
    php_flag engine off
    RemoveHandler .php
</DirectoryMatch>
HTACCESS;
    
    file_put_contents($htaccessPath, $htaccessContent);
}

/**
 * Create security configuration template
 */
function createSecurityConfig(): void
{
    $configPath = SHOPOLOGIC_ROOT . '/core/config/security.php';
    
    $configContent = <<<PHP
<?php

return [
    // Encryption settings
    'encryption_key' => env('ENCRYPTION_KEY'),
    'cipher' => 'AES-256-CBC',
    
    // JWT settings
    'jwt_secret' => env('JWT_SECRET'),
    'jwt_expiry' => env('JWT_EXPIRY', 3600),
    
    // Password hashing
    'bcrypt_rounds' => env('BCRYPT_ROUNDS', 12),
    
    // Session security
    'session' => [
        'secure' => env('SESSION_SECURE', true),
        'http_only' => env('SESSION_HTTP_ONLY', true),
        'same_site' => env('SESSION_SAME_SITE', 'strict'),
        'lifetime' => env('SESSION_LIFETIME', 1440)
    ],
    
    // CSRF protection
    'csrf' => [
        'enabled' => true,
        'token_lifetime' => 3600
    ],
    
    // Rate limiting
    'rate_limit' => [
        'enabled' => true,
        'max_attempts' => 100,
        'decay_minutes' => 60
    ],
    
    // Security headers
    'headers' => [
        'x_content_type_options' => 'nosniff',
        'x_frame_options' => 'DENY',
        'x_xss_protection' => '1; mode=block',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'permissions_policy' => 'geolocation=(), microphone=(), camera=()'
    ]
];
PHP;
    
    if (!file_exists($configPath)) {
        file_put_contents($configPath, $configContent);
    }
}

/**
 * Set secure file permissions
 */
function setSecurePermissions(): void
{
    $files = [
        SHOPOLOGIC_ROOT . '/.env' => 0600,
        SHOPOLOGIC_ROOT . '/composer.json' => 0644,
        SHOPOLOGIC_ROOT . '/composer.lock' => 0644
    ];
    
    foreach ($files as $file => $perms) {
        if (file_exists($file)) {
            chmod($file, $perms);
        }
    }
    
    $dirs = [
        SHOPOLOGIC_ROOT . '/storage' => 0755,
        SHOPOLOGIC_ROOT . '/storage/logs' => 0755,
        SHOPOLOGIC_ROOT . '/storage/cache' => 0755
    ];
    
    foreach ($dirs as $dir => $perms) {
        if (is_dir($dir)) {
            chmod($dir, $perms);
        }
    }
}

/**
 * Generate CSP template
 */
function generateCSPTemplate(): void
{
    $cspPath = SHOPOLOGIC_ROOT . '/csp-template.txt';
    
    $cspContent = <<<CSP
Content-Security-Policy: 
    default-src 'self';
    script-src 'self' 'unsafe-inline' 'unsafe-eval' *.googleapis.com *.gstatic.com;
    style-src 'self' 'unsafe-inline' *.googleapis.com;
    img-src 'self' data: https:;
    font-src 'self' *.googleapis.com *.gstatic.com;
    connect-src 'self';
    frame-src 'none';
    object-src 'none';
    base-uri 'self';
    
Adjust this CSP header according to your application's needs.
Remove 'unsafe-inline' and 'unsafe-eval' when possible for better security.
CSP;
    
    file_put_contents($cspPath, $cspContent);
}

/**
 * Generate security report
 */
function generateSecurityReport($report, string $format): void
{
    $filename = 'security-report-' . date('Y-m-d-H-i-s');
    
    switch ($format) {
        case 'json':
            $data = [
                'timestamp' => date('c'),
                'summary' => $report->getSummary(),
                'results' => $report->getResults()
            ];
            
            file_put_contents("{$filename}.json", json_encode($data, JSON_PRETTY_PRINT));
            echo "JSON report saved to {$filename}.json\n";
            break;
            
        case 'html':
            generateHtmlReport($report, $filename);
            echo "HTML report saved to {$filename}.html\n";
            break;
            
        default:
            file_put_contents("{$filename}.txt", formatTextReport($report));
            echo "Text report saved to {$filename}.txt\n";
            break;
    }
}

/**
 * Generate HTML report
 */
function generateHtmlReport($report, string $filename): void
{
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Shopologic Security Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .summary { background: #f5f5f5; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .critical { color: #d32f2f; }
        .high { color: #f57c00; }
        .medium { color: #fbc02d; }
        .low { color: #388e3c; }
        .violation { margin-bottom: 15px; padding: 10px; border-left: 4px solid #ccc; }
        .violation.critical { border-left-color: #d32f2f; }
        .violation.high { border-left-color: #f57c00; }
        .violation.medium { border-left-color: #fbc02d; }
        .violation.low { border-left-color: #388e3c; }
    </style>
</head>
<body>
    <h1>Shopologic Security Report</h1>
    <p>Generated: {date('Y-m-d H:i:s')}</p>
    
    <div class="summary">
        <h2>Summary</h2>
        <p>Total Issues: {$report->getTotalIssues()}</p>
        <p><span class="critical">Critical: {$report->getCriticalIssues()}</span></p>
        <p><span class="high">High: {$report->getHighIssues()}</span></p>
        <p><span class="medium">Medium: {$report->getMediumIssues()}</span></p>
        <p><span class="low">Low: {$report->getLowIssues()}</span></p>
    </div>
HTML;
    
    foreach ($report->getResults() as $scanType => $violations) {
        $html .= "<h2>" . ucfirst($scanType) . " Issues</h2>";
        
        foreach ($violations as $violation) {
            $severity = $violation['severity'] ?? 'unknown';
            $html .= "<div class=\"violation {$severity}\">";
            $html .= "<strong>[" . strtoupper($severity) . "] " . htmlspecialchars($violation['message']) . "</strong><br>";
            
            if (isset($violation['file'])) {
                $html .= "File: " . htmlspecialchars($violation['file']);
                if (isset($violation['line'])) {
                    $html .= ":" . $violation['line'];
                }
                $html .= "<br>";
            }
            
            if (isset($violation['recommendation'])) {
                $html .= "Recommendation: " . htmlspecialchars($violation['recommendation']) . "<br>";
            }
            
            $html .= "</div>";
        }
    }
    
    $html .= "</body></html>";
    
    file_put_contents("{$filename}.html", $html);
}

/**
 * Format text report
 */
function formatTextReport($report): string
{
    $text = "Shopologic Security Report\n";
    $text .= "=========================\n";
    $text .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
    
    $text .= "Summary:\n";
    $text .= "Total Issues: " . $report->getTotalIssues() . "\n";
    $text .= "Critical: " . $report->getCriticalIssues() . "\n";
    $text .= "High: " . $report->getHighIssues() . "\n";
    $text .= "Medium: " . $report->getMediumIssues() . "\n";
    $text .= "Low: " . $report->getLowIssues() . "\n\n";
    
    foreach ($report->getResults() as $scanType => $violations) {
        $text .= ucfirst($scanType) . " Issues:\n";
        $text .= str_repeat('-', 20) . "\n";
        
        foreach ($violations as $violation) {
            $text .= "[" . strtoupper($violation['severity'] ?? 'unknown') . "] " . $violation['message'] . "\n";
            
            if (isset($violation['file'])) {
                $text .= "File: " . $violation['file'];
                if (isset($violation['line'])) {
                    $text .= ":" . $violation['line'];
                }
                $text .= "\n";
            }
            
            if (isset($violation['recommendation'])) {
                $text .= "Fix: " . $violation['recommendation'] . "\n";
            }
            
            $text .= "\n";
        }
        
        $text .= "\n";
    }
    
    return $text;
}

/**
 * Get severity icon
 */
function getSeverityIcon(string $severity): string
{
    switch ($severity) {
        case 'critical':
            return '🔴';
        case 'high':
            return '🟠';
        case 'medium':
            return '🟡';
        case 'low':
            return '🟢';
        default:
            return '⚪';
    }
}