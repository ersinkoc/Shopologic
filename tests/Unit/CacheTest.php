<?php

declare(strict_types=1);

/**
 * Cache Unit Tests
 */

use Shopologic\Core\Cache\CacheManager;
use Shopologic\Core\Cache\ArrayStore;
use Shopologic\Core\Configuration\ConfigurationManager;

TestFramework::describe('Cache Manager', function() {
    TestFramework::it('should create cache manager instance', function() {
        $config = new ConfigurationManager();
        $cache = new CacheManager($config);
        TestFramework::expect($cache)->toBeInstanceOf(CacheManager::class);
    });
    
    TestFramework::it('should store and retrieve values', function() {
        $config = new ConfigurationManager();
        $cache = new CacheManager($config);
        
        $cache->put('test.key', 'test value', 60);
        $value = $cache->get('test.key');
        
        TestFramework::expect($value)->toBe('test value');
    });
    
    TestFramework::it('should return default value for missing keys', function() {
        $config = new ConfigurationManager();
        $cache = new CacheManager($config);
        
        $value = $cache->get('missing.key', 'default value');
        TestFramework::expect($value)->toBe('default value');
    });
    
    TestFramework::it('should check if key exists', function() {
        $config = new ConfigurationManager();
        $cache = new CacheManager($config);
        
        $cache->put('existing.key', 'value', 60);
        
        TestFramework::expect($cache->has('existing.key'))->toBeTrue();
        TestFramework::expect($cache->has('missing.key'))->toBeFalse();
    });
    
    TestFramework::it('should forget cache keys', function() {
        $config = new ConfigurationManager();
        $cache = new CacheManager($config);
        
        $cache->put('forget.test', 'value', 60);
        TestFramework::expect($cache->has('forget.test'))->toBeTrue();
        
        $cache->forget('forget.test');
        TestFramework::expect($cache->has('forget.test'))->toBeFalse();
    });
    
    TestFramework::it('should clear all cache', function() {
        $config = new ConfigurationManager();
        $cache = new CacheManager($config);
        
        $cache->put('key1', 'value1', 60);
        $cache->put('key2', 'value2', 60);
        
        TestFramework::expect($cache->has('key1'))->toBeTrue();
        TestFramework::expect($cache->has('key2'))->toBeTrue();
        
        $cache->clear();
        
        TestFramework::expect($cache->has('key1'))->toBeFalse();
        TestFramework::expect($cache->has('key2'))->toBeFalse();
    });
    
    TestFramework::it('should handle remember function', function() {
        $config = new ConfigurationManager();
        $cache = new CacheManager($config);
        
        $callCount = 0;
        $value = $cache->remember('remember.test', 60, function() use (&$callCount) {
            $callCount++;
            return 'computed value';
        });
        
        TestFramework::expect($value)->toBe('computed value');
        TestFramework::expect($callCount)->toBe(1);
        
        // Second call should use cached value
        $value2 = $cache->remember('remember.test', 60, function() use (&$callCount) {
            $callCount++;
            return 'computed value';
        });
        
        TestFramework::expect($value2)->toBe('computed value');
        TestFramework::expect($callCount)->toBe(1);
    });
    
    TestFramework::it('should handle increment and decrement', function() {
        $config = new ConfigurationManager();
        $cache = new CacheManager($config);
        
        $cache->put('counter', 5, 60);
        
        $incremented = $cache->increment('counter', 3);
        TestFramework::expect($incremented)->toBe(8);
        
        $decremented = $cache->decrement('counter', 2);
        TestFramework::expect($decremented)->toBe(6);
    });
});

TestFramework::describe('Array Store', function() {
    TestFramework::it('should create array store instance', function() {
        $store = new ArrayStore();
        TestFramework::expect($store)->toBeInstanceOf(ArrayStore::class);
    });
    
    TestFramework::it('should store and retrieve values', function() {
        $store = new ArrayStore();
        
        $store->put('test', 'value', 60);
        $value = $store->get('test');
        
        TestFramework::expect($value)->toBe('value');
    });
    
    TestFramework::it('should handle expiration', function() {
        $store = new ArrayStore();
        
        // Store with immediate expiration
        $store->put('expired', 'value', -1);
        $value = $store->get('expired');
        
        TestFramework::expect($value)->toBeNull();
    });
    
    TestFramework::it('should handle multiple values', function() {
        $store = new ArrayStore();
        
        $store->put('key1', 'value1', 60);
        $store->put('key2', 'value2', 60);
        
        $values = $store->many(['key1', 'key2', 'key3']);
        
        TestFramework::expect($values['key1'])->toBe('value1');
        TestFramework::expect($values['key2'])->toBe('value2');
        TestFramework::expect($values['key3'])->toBeNull();
    });
    
    TestFramework::it('should flush all data', function() {
        $store = new ArrayStore();
        
        $store->put('key1', 'value1', 60);
        $store->put('key2', 'value2', 60);
        
        TestFramework::expect($store->get('key1'))->toBe('value1');
        
        $store->flush();
        
        TestFramework::expect($store->get('key1'))->toBeNull();
        TestFramework::expect($store->get('key2'))->toBeNull();
    });
});