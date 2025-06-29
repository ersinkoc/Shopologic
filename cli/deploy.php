<?php

declare(strict_types=1);

/**
 * Shopologic Deployment CLI Tool
 * 
 * Manages deployments and releases
 */

define('SHOPOLOGIC_ROOT', dirname(__DIR__));

require_once SHOPOLOGIC_ROOT . '/core/src/Autoloader.php';

use Shopologic\Core\Autoloader;

Autoloader::register();

$command = $argv[1] ?? 'help';

switch ($command) {
    case 'prepare':
        prepareDeployment($argv[2] ?? 'production');
        break;
        
    case 'check':
        checkDeploymentReadiness();
        break;
        
    case 'deploy':
        deploy($argv[2] ?? 'production', array_slice($argv, 3));
        break;
        
    case 'rollback':
        rollback($argv[2] ?? null);
        break;
        
    case 'status':
        showDeploymentStatus();
        break;
        
    case 'release':
        createRelease($argv[2] ?? null);
        break;
        
    case 'migrate':
        runMigrations($argv[2] ?? 'production');
        break;
        
    case 'health':
        checkHealth($argv[2] ?? 'production');
        break;
        
    case 'help':
    default:
        showHelp();
        break;
}

/**
 * Prepare deployment package
 */
function prepareDeployment(string $environment): void
{
    echo "Preparing deployment for $environment environment...\n";
    
    // Check if all tests pass
    echo "Running tests...\n";
    exec('php cli/test.php', $output, $testResult);
    
    if ($testResult !== 0) {
        echo "❌ Tests failed! Cannot proceed with deployment.\n";
        exit(1);
    }
    
    // Run security scan
    echo "Running security scan...\n";
    exec('php cli/security.php scan --format=json', $output, $securityResult);
    
    if ($securityResult !== 0) {
        echo "❌ Security issues found! Cannot proceed with deployment.\n";
        exit(1);
    }
    
    // Build assets
    echo "Building assets...\n";
    buildAssets();
    
    // Create deployment package
    $version = getVersion();
    $packageName = "shopologic-{$version}-{$environment}.tar.gz";
    
    echo "Creating deployment package: $packageName\n";
    
    $excludes = [
        '.git',
        '.github',
        'node_modules',
        'tests',
        '*.log',
        '.env.local',
        'storage/logs/*',
        'storage/cache/*',
        'storage/sessions/*',
        'docker-compose.yml',
        'Dockerfile'
    ];
    
    $excludeArgs = array_map(fn($e) => "--exclude='$e'", $excludes);
    $excludeString = implode(' ', $excludeArgs);
    
    $cmd = "tar -czf $packageName $excludeString .";
    exec($cmd, $output, $result);
    
    if ($result === 0) {
        echo "✅ Deployment package created: $packageName\n";
        echo "Size: " . formatBytes(filesize($packageName)) . "\n";
        
        // Generate deployment manifest
        generateManifest($version, $environment, $packageName);
    } else {
        echo "❌ Failed to create deployment package\n";
        exit(1);
    }
}

/**
 * Check deployment readiness
 */
function checkDeploymentReadiness(): void
{
    echo "Checking deployment readiness...\n\n";
    
    $checks = [
        'PHP Version' => checkPhpVersion(),
        'Required Extensions' => checkPhpExtensions(),
        'Database Connection' => checkDatabaseConnection(),
        'Redis Connection' => checkRedisConnection(),
        'File Permissions' => checkFilePermissions(),
        'Configuration' => checkConfiguration(),
        'Dependencies' => checkDependencies(),
        'Migrations' => checkMigrations(),
        'Assets Built' => checkAssets()
    ];
    
    $ready = true;
    
    foreach ($checks as $check => $result) {
        $icon = $result['passed'] ? '✅' : '❌';
        echo "$icon $check: {$result['message']}\n";
        
        if (!$result['passed']) {
            $ready = false;
        }
    }
    
    echo "\n";
    
    if ($ready) {
        echo "✅ System is ready for deployment!\n";
        exit(0);
    } else {
        echo "❌ System is not ready for deployment. Please fix the issues above.\n";
        exit(1);
    }
}

/**
 * Deploy to environment
 */
function deploy(string $environment, array $options): void
{
    echo "Deploying to $environment environment...\n";
    
    $dryRun = in_array('--dry-run', $options);
    $skipBackup = in_array('--skip-backup', $options);
    $skipMigrations = in_array('--skip-migrations', $options);
    
    if ($dryRun) {
        echo "🔍 Running in dry-run mode\n";
    }
    
    // Load deployment configuration
    $config = loadDeploymentConfig($environment);
    
    if (!$config) {
        echo "❌ No deployment configuration found for $environment\n";
        exit(1);
    }
    
    // Create deployment ID
    $deploymentId = date('YmdHis');
    
    // Step 1: Backup current state
    if (!$skipBackup && !$dryRun) {
        echo "Creating backup...\n";
        createBackup($environment, $deploymentId);
    }
    
    // Step 2: Enable maintenance mode
    if (!$dryRun) {
        echo "Enabling maintenance mode...\n";
        exec('php cli/maintenance.php enable');
    }
    
    try {
        // Step 3: Deploy code
        echo "Deploying code...\n";
        deployCode($config, $dryRun);
        
        // Step 4: Install dependencies
        echo "Installing dependencies...\n";
        if (!$dryRun) {
            exec('composer install --no-dev --optimize-autoloader');
        }
        
        // Step 5: Run migrations
        if (!$skipMigrations && !$dryRun) {
            echo "Running migrations...\n";
            exec("php cli/migrate.php up --env=$environment", $output, $migrationResult);
            
            if ($migrationResult !== 0) {
                throw new Exception("Migrations failed!");
            }
        }
        
        // Step 6: Build and deploy assets
        echo "Building assets...\n";
        if (!$dryRun) {
            buildAssets();
        }
        
        // Step 7: Clear and warm caches
        echo "Clearing caches...\n";
        if (!$dryRun) {
            exec('php cli/cache.php clear');
            exec('php cli/cache.php warm');
        }
        
        // Step 8: Run health checks
        echo "Running health checks...\n";
        $healthResult = runHealthChecks($environment);
        
        if (!$healthResult['passed']) {
            throw new Exception("Health checks failed!");
        }
        
        // Step 9: Disable maintenance mode
        if (!$dryRun) {
            echo "Disabling maintenance mode...\n";
            exec('php cli/maintenance.php disable');
        }
        
        // Step 10: Create deployment record
        if (!$dryRun) {
            createDeploymentRecord($environment, $deploymentId, 'success');
        }
        
        echo "\n✅ Deployment completed successfully!\n";
        echo "Deployment ID: $deploymentId\n";
        
    } catch (Exception $e) {
        echo "\n❌ Deployment failed: " . $e->getMessage() . "\n";
        
        // Rollback
        if (!$dryRun) {
            echo "Rolling back...\n";
            rollbackDeployment($environment, $deploymentId);
            
            // Disable maintenance mode
            exec('php cli/maintenance.php disable');
        }
        
        exit(1);
    }
}

/**
 * Rollback deployment
 */
function rollback(?string $deploymentId = null): void
{
    echo "Rolling back deployment...\n";
    
    if (!$deploymentId) {
        // Get last deployment
        $deployments = getDeploymentHistory();
        
        if (empty($deployments)) {
            echo "❌ No deployments found to rollback\n";
            exit(1);
        }
        
        $deploymentId = $deployments[0]['id'];
    }
    
    echo "Rolling back to deployment: $deploymentId\n";
    
    // Enable maintenance mode
    exec('php cli/maintenance.php enable');
    
    try {
        // Restore from backup
        restoreBackup($deploymentId);
        
        // Run rollback migrations if needed
        exec('php cli/migrate.php rollback');
        
        // Clear caches
        exec('php cli/cache.php clear');
        
        // Run health checks
        $healthResult = runHealthChecks('production');
        
        if (!$healthResult['passed']) {
            echo "⚠️  Health checks failed after rollback\n";
        }
        
        // Disable maintenance mode
        exec('php cli/maintenance.php disable');
        
        echo "✅ Rollback completed successfully!\n";
        
    } catch (Exception $e) {
        echo "❌ Rollback failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

/**
 * Show deployment status
 */
function showDeploymentStatus(): void
{
    echo "Deployment Status\n";
    echo "================\n\n";
    
    // Current version
    $version = getVersion();
    echo "Current Version: $version\n";
    
    // Environment info
    $env = getenv('APP_ENV') ?: 'production';
    echo "Environment: $env\n";
    
    // Last deployment
    $deployments = getDeploymentHistory();
    
    if (!empty($deployments)) {
        $last = $deployments[0];
        echo "\nLast Deployment:\n";
        echo "  ID: {$last['id']}\n";
        echo "  Date: {$last['date']}\n";
        echo "  Status: {$last['status']}\n";
        echo "  Duration: {$last['duration']}s\n";
    }
    
    // System health
    echo "\nSystem Health:\n";
    $health = runHealthChecks($env);
    
    foreach ($health['checks'] as $check => $result) {
        $icon = $result['passed'] ? '✅' : '❌';
        echo "  $icon $check\n";
    }
    
    // Recent deployments
    echo "\nRecent Deployments:\n";
    $recent = array_slice($deployments, 0, 5);
    
    foreach ($recent as $deployment) {
        echo "  {$deployment['date']} - {$deployment['id']} ({$deployment['status']})\n";
    }
}

/**
 * Create release
 */
function createRelease(?string $version = null): void
{
    if (!$version) {
        // Determine next version
        $currentVersion = getVersion();
        list($major, $minor, $patch) = explode('.', $currentVersion);
        
        echo "Current version: $currentVersion\n";
        echo "Select version bump:\n";
        echo "  1. Patch ($major.$minor." . ($patch + 1) . ")\n";
        echo "  2. Minor ($major." . ($minor + 1) . ".0)\n";
        echo "  3. Major (" . ($major + 1) . ".0.0)\n";
        echo "  4. Custom\n";
        
        $choice = trim(fgets(STDIN));
        
        switch ($choice) {
            case '1':
                $version = "$major.$minor." . ($patch + 1);
                break;
            case '2':
                $version = "$major." . ($minor + 1) . ".0";
                break;
            case '3':
                $version = ($major + 1) . ".0.0";
                break;
            case '4':
                echo "Enter version: ";
                $version = trim(fgets(STDIN));
                break;
            default:
                echo "Invalid choice\n";
                exit(1);
        }
    }
    
    echo "Creating release v$version...\n";
    
    // Update version file
    file_put_contents(SHOPOLOGIC_ROOT . '/VERSION', $version);
    
    // Generate changelog
    echo "Generating changelog...\n";
    generateChangelog($version);
    
    // Create git tag
    echo "Creating git tag...\n";
    exec("git add -A");
    exec("git commit -m 'Release v$version'");
    exec("git tag -a v$version -m 'Release v$version'");
    
    echo "✅ Release v$version created!\n";
    echo "Don't forget to push: git push && git push --tags\n";
}

/**
 * Run migrations for environment
 */
function runMigrations(string $environment): void
{
    echo "Running migrations for $environment environment...\n";
    
    exec("php cli/migrate.php up --env=$environment", $output, $result);
    
    if ($result === 0) {
        echo "✅ Migrations completed successfully!\n";
    } else {
        echo "❌ Migrations failed!\n";
        echo implode("\n", $output) . "\n";
        exit(1);
    }
}

/**
 * Check health for environment
 */
function checkHealth(string $environment): void
{
    echo "Checking health for $environment environment...\n";
    
    $health = runHealthChecks($environment);
    
    if ($health['passed']) {
        echo "✅ All health checks passed!\n";
    } else {
        echo "❌ Some health checks failed!\n";
        
        foreach ($health['checks'] as $check => $result) {
            if (!$result['passed']) {
                echo "  - $check: {$result['message']}\n";
            }
        }
        
        exit(1);
    }
}

/**
 * Show help
 */
function showHelp(): void
{
    echo "Shopologic Deployment Tool\n";
    echo "========================\n\n";
    echo "Usage: php cli/deploy.php <command> [options]\n\n";
    echo "Commands:\n";
    echo "  prepare [env]    Prepare deployment package\n";
    echo "  check            Check deployment readiness\n";
    echo "  deploy [env]     Deploy to environment\n";
    echo "  rollback [id]    Rollback to previous deployment\n";
    echo "  status           Show deployment status\n";
    echo "  release [ver]    Create new release\n";
    echo "  migrate [env]    Run migrations\n";
    echo "  health [env]     Check system health\n";
    echo "  help             Show this help\n\n";
    echo "Options:\n";
    echo "  --dry-run        Run without making changes\n";
    echo "  --skip-backup    Skip backup creation\n";
    echo "  --skip-migrations Skip database migrations\n\n";
    echo "Examples:\n";
    echo "  php cli/deploy.php prepare production\n";
    echo "  php cli/deploy.php deploy staging --dry-run\n";
    echo "  php cli/deploy.php rollback\n";
    echo "  php cli/deploy.php release 2.0.0\n";
}

/**
 * Helper functions
 */

function checkPhpVersion(): array
{
    $required = '8.3.0';
    $current = PHP_VERSION;
    
    return [
        'passed' => version_compare($current, $required, '>='),
        'message' => "PHP $current (required: $required+)"
    ];
}

function checkPhpExtensions(): array
{
    $required = ['pdo', 'pdo_pgsql', 'redis', 'gd', 'zip', 'opcache', 'intl'];
    $missing = [];
    
    foreach ($required as $ext) {
        if (!extension_loaded($ext)) {
            $missing[] = $ext;
        }
    }
    
    return [
        'passed' => empty($missing),
        'message' => empty($missing) ? 'All required' : 'Missing: ' . implode(', ', $missing)
    ];
}

function checkDatabaseConnection(): array
{
    try {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            getenv('DB_HOST') ?: 'localhost',
            getenv('DB_PORT') ?: '5432',
            getenv('DB_DATABASE') ?: 'shopologic'
        );
        
        $pdo = new PDO($dsn, getenv('DB_USERNAME'), getenv('DB_PASSWORD'));
        $pdo->query('SELECT 1');
        
        return ['passed' => true, 'message' => 'Connected'];
    } catch (Exception $e) {
        return ['passed' => false, 'message' => 'Connection failed'];
    }
}

function checkRedisConnection(): array
{
    try {
        $redis = new Redis();
        $redis->connect(getenv('REDIS_HOST') ?: 'localhost', (int)(getenv('REDIS_PORT') ?: 6379));
        $redis->ping();
        
        return ['passed' => true, 'message' => 'Connected'];
    } catch (Exception $e) {
        return ['passed' => false, 'message' => 'Connection failed'];
    }
}

function checkFilePermissions(): array
{
    $directories = ['storage', 'public/uploads'];
    $issues = [];
    
    foreach ($directories as $dir) {
        $path = SHOPOLOGIC_ROOT . '/' . $dir;
        if (!is_writable($path)) {
            $issues[] = $dir;
        }
    }
    
    return [
        'passed' => empty($issues),
        'message' => empty($issues) ? 'All writable' : 'Not writable: ' . implode(', ', $issues)
    ];
}

function checkConfiguration(): array
{
    $required = ['APP_KEY', 'DB_DATABASE', 'DB_USERNAME'];
    $missing = [];
    
    foreach ($required as $key) {
        if (empty(getenv($key))) {
            $missing[] = $key;
        }
    }
    
    return [
        'passed' => empty($missing),
        'message' => empty($missing) ? 'Complete' : 'Missing: ' . implode(', ', $missing)
    ];
}

function checkDependencies(): array
{
    $composerLock = SHOPOLOGIC_ROOT . '/composer.lock';
    
    if (!file_exists($composerLock)) {
        return ['passed' => false, 'message' => 'composer.lock not found'];
    }
    
    exec('composer check-platform-reqs 2>&1', $output, $result);
    
    return [
        'passed' => $result === 0,
        'message' => $result === 0 ? 'All satisfied' : 'Platform requirements not met'
    ];
}

function checkMigrations(): array
{
    exec('php cli/migrate.php status --format=json 2>&1', $output, $result);
    
    if ($result !== 0) {
        return ['passed' => false, 'message' => 'Cannot check migration status'];
    }
    
    $status = json_decode(implode('', $output), true);
    $pending = $status['pending'] ?? 0;
    
    return [
        'passed' => true,
        'message' => $pending > 0 ? "$pending migrations pending" : 'All migrations run'
    ];
}

function checkAssets(): array
{
    $distDir = SHOPOLOGIC_ROOT . '/themes/default/dist';
    
    if (!is_dir($distDir)) {
        return ['passed' => false, 'message' => 'Assets not built'];
    }
    
    $jsFiles = glob($distDir . '/js/*.js');
    $cssFiles = glob($distDir . '/css/*.css');
    
    return [
        'passed' => !empty($jsFiles) && !empty($cssFiles),
        'message' => 'Built and ready'
    ];
}

function buildAssets(): void
{
    $themeDir = SHOPOLOGIC_ROOT . '/themes/default';
    
    if (!is_dir($themeDir . '/node_modules')) {
        echo "Installing npm dependencies...\n";
        exec("cd $themeDir && npm ci");
    }
    
    exec("cd $themeDir && npm run build");
}

function getVersion(): string
{
    $versionFile = SHOPOLOGIC_ROOT . '/VERSION';
    
    if (file_exists($versionFile)) {
        return trim(file_get_contents($versionFile));
    }
    
    return '1.0.0';
}

function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}

function generateManifest(string $version, string $environment, string $packageName): void
{
    $manifest = [
        'version' => $version,
        'environment' => $environment,
        'package' => $packageName,
        'created_at' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION,
        'commit_hash' => trim(exec('git rev-parse HEAD')),
        'files' => [
            'count' => count(glob(SHOPOLOGIC_ROOT . '/**/*')),
            'size' => filesize($packageName)
        ]
    ];
    
    file_put_contents('deployment-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
}

function loadDeploymentConfig(string $environment): ?array
{
    $configFile = SHOPOLOGIC_ROOT . "/deployment/config.$environment.json";
    
    if (!file_exists($configFile)) {
        return null;
    }
    
    return json_decode(file_get_contents($configFile), true);
}

function createBackup(string $environment, string $deploymentId): void
{
    $backupDir = SHOPOLOGIC_ROOT . "/storage/backups/$deploymentId";
    
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    // Backup database
    $dbBackup = "$backupDir/database.sql";
    exec("pg_dump -h localhost -U postgres shopologic > $dbBackup");
    
    // Backup uploads
    exec("cp -r " . SHOPOLOGIC_ROOT . "/public/uploads $backupDir/");
    
    // Backup configuration
    exec("cp " . SHOPOLOGIC_ROOT . "/.env $backupDir/");
    
    // Create backup manifest
    $manifest = [
        'deployment_id' => $deploymentId,
        'environment' => $environment,
        'created_at' => date('Y-m-d H:i:s'),
        'files' => [
            'database' => 'database.sql',
            'uploads' => 'uploads/',
            'config' => '.env'
        ]
    ];
    
    file_put_contents("$backupDir/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT));
}

function deployCode(array $config, bool $dryRun): void
{
    // This would deploy code to servers
    // Implementation depends on deployment method (git, rsync, etc.)
}

function runHealthChecks(string $environment): array
{
    $checks = [
        'database' => checkDatabaseConnection(),
        'redis' => checkRedisConnection(),
        'storage' => checkFilePermissions(),
        'configuration' => checkConfiguration()
    ];
    
    $passed = true;
    foreach ($checks as $check) {
        if (!$check['passed']) {
            $passed = false;
            break;
        }
    }
    
    return ['passed' => $passed, 'checks' => $checks];
}

function createDeploymentRecord(string $environment, string $deploymentId, string $status): void
{
    $record = [
        'id' => $deploymentId,
        'environment' => $environment,
        'status' => $status,
        'date' => date('Y-m-d H:i:s'),
        'duration' => time() - strtotime($deploymentId),
        'version' => getVersion()
    ];
    
    $historyFile = SHOPOLOGIC_ROOT . '/storage/deployments.json';
    $history = [];
    
    if (file_exists($historyFile)) {
        $history = json_decode(file_get_contents($historyFile), true) ?: [];
    }
    
    array_unshift($history, $record);
    $history = array_slice($history, 0, 50); // Keep last 50 deployments
    
    file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT));
}

function rollbackDeployment(string $environment, string $deploymentId): void
{
    // This would rollback to previous deployment
    // Implementation depends on deployment method
}

function restoreBackup(string $deploymentId): void
{
    $backupDir = SHOPOLOGIC_ROOT . "/storage/backups/$deploymentId";
    
    if (!is_dir($backupDir)) {
        throw new Exception("Backup not found for deployment: $deploymentId");
    }
    
    // Restore database
    exec("psql -h localhost -U postgres shopologic < $backupDir/database.sql");
    
    // Restore uploads
    exec("rm -rf " . SHOPOLOGIC_ROOT . "/public/uploads");
    exec("cp -r $backupDir/uploads " . SHOPOLOGIC_ROOT . "/public/");
    
    // Restore configuration
    exec("cp $backupDir/.env " . SHOPOLOGIC_ROOT . "/");
}

function getDeploymentHistory(): array
{
    $historyFile = SHOPOLOGIC_ROOT . '/storage/deployments.json';
    
    if (!file_exists($historyFile)) {
        return [];
    }
    
    return json_decode(file_get_contents($historyFile), true) ?: [];
}

function generateChangelog(string $version): void
{
    $changelog = "# Changelog\n\n";
    $changelog .= "## v$version - " . date('Y-m-d') . "\n\n";
    
    // Get commits since last tag
    $lastTag = trim(exec('git describe --tags --abbrev=0'));
    $commits = [];
    exec("git log $lastTag..HEAD --pretty=format:'%s' --no-merges", $commits);
    
    // Group commits by type
    $features = [];
    $fixes = [];
    $other = [];
    
    foreach ($commits as $commit) {
        if (str_starts_with($commit, 'feat:') || str_starts_with($commit, 'feature:')) {
            $features[] = $commit;
        } elseif (str_starts_with($commit, 'fix:')) {
            $fixes[] = $commit;
        } else {
            $other[] = $commit;
        }
    }
    
    if (!empty($features)) {
        $changelog .= "### Features\n";
        foreach ($features as $feature) {
            $changelog .= "- $feature\n";
        }
        $changelog .= "\n";
    }
    
    if (!empty($fixes)) {
        $changelog .= "### Bug Fixes\n";
        foreach ($fixes as $fix) {
            $changelog .= "- $fix\n";
        }
        $changelog .= "\n";
    }
    
    if (!empty($other)) {
        $changelog .= "### Other Changes\n";
        foreach ($other as $change) {
            $changelog .= "- $change\n";
        }
        $changelog .= "\n";
    }
    
    // Prepend to existing changelog
    $existingChangelog = '';
    $changelogFile = SHOPOLOGIC_ROOT . '/CHANGELOG.md';
    
    if (file_exists($changelogFile)) {
        $existingChangelog = file_get_contents($changelogFile);
        $existingChangelog = preg_replace('/^# Changelog\n+/', '', $existingChangelog);
    }
    
    file_put_contents($changelogFile, $changelog . $existingChangelog);
}