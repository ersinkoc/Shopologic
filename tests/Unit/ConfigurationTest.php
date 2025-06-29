<?php

declare(strict_types=1);

/**
 * Configuration Unit Tests
 */

use Shopologic\Core\Configuration\ConfigurationManager;

TestFramework::describe('Configuration Manager', function() {
    TestFramework::it('should create configuration instance', function() {
        $config = new ConfigurationManager();
        TestFramework::expect($config)->toBeInstanceOf(ConfigurationManager::class);
    });
    
    TestFramework::it('should get configuration values', function() {
        $config = new ConfigurationManager();
        
        // Mock environment variable
        putenv('APP_NAME=Shopologic Test');
        
        $appName = $config->get('app.name', 'Default App');
        TestFramework::expect($appName)->toBe('Shopologic Test');
    });
    
    TestFramework::it('should return default values when config not found', function() {
        $config = new ConfigurationManager();
        
        $value = $config->get('non.existent.config', 'default value');
        TestFramework::expect($value)->toBe('default value');
    });
    
    TestFramework::it('should handle nested configuration keys', function() {
        $config = new ConfigurationManager();
        
        // Mock database config
        putenv('DB_HOST=localhost');
        putenv('DB_PORT=5432');
        
        $host = $config->get('database.host', 'localhost');
        $port = $config->get('database.port', '5432');
        
        TestFramework::expect($host)->toBe('localhost');
        TestFramework::expect($port)->toBe('5432');
    });
    
    TestFramework::it('should handle boolean values', function() {
        $config = new ConfigurationManager();
        
        putenv('APP_DEBUG=true');
        putenv('CACHE_ENABLED=false');
        
        $debug = $config->get('app.debug', false);
        $cacheEnabled = $config->get('cache.enabled', true);
        
        TestFramework::expect($debug)->toBeTrue();
        TestFramework::expect($cacheEnabled)->toBeFalse();
    });
    
    TestFramework::it('should handle numeric values', function() {
        $config = new ConfigurationManager();
        
        putenv('CACHE_TTL=3600');
        putenv('MAX_UPLOAD_SIZE=2048');
        
        $ttl = $config->get('cache.ttl', 1800);
        $maxSize = $config->get('upload.max_size', 1024);
        
        TestFramework::expect($ttl)->toBe(3600);
        TestFramework::expect($maxSize)->toBe(2048);
    });
    
    TestFramework::it('should set configuration values', function() {
        $config = new ConfigurationManager();
        
        $config->set('test.dynamic.value', 'dynamic content');
        $value = $config->get('test.dynamic.value');
        
        TestFramework::expect($value)->toBe('dynamic content');
    });
    
    TestFramework::it('should check if configuration exists', function() {
        $config = new ConfigurationManager();
        
        putenv('EXISTING_CONFIG=value');
        $config->set('runtime.config', 'test');
        
        TestFramework::expect($config->has('app.name'))->toBeTrue();
        TestFramework::expect($config->has('runtime.config'))->toBeTrue();
        TestFramework::expect($config->has('non.existent'))->toBeFalse();
    });
});