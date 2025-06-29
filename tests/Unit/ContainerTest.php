<?php

declare(strict_types=1);

/**
 * Container Unit Tests
 */

use Shopologic\Core\Container\Container;
use Shopologic\Core\Container\ContainerException;
use Shopologic\Core\Container\NotFoundException;

TestFramework::describe('Container', function() {
    TestFramework::it('should create container instance', function() {
        $container = new Container();
        TestFramework::expect($container)->toBeInstanceOf(Container::class);
    });
    
    TestFramework::it('should bind and resolve simple values', function() {
        $container = new Container();
        $container->bind('test.value', 'hello world');
        
        $value = $container->get('test.value');
        TestFramework::expect($value)->toBe('hello world');
    });
    
    TestFramework::it('should bind and resolve closures', function() {
        $container = new Container();
        $container->bind('test.closure', function() {
            return 'closure result';
        });
        
        $value = $container->get('test.closure');
        TestFramework::expect($value)->toBe('closure result');
    });
    
    TestFramework::it('should resolve class instances', function() {
        $container = new Container();
        
        // Mock a simple class
        $container->bind('TestClass', function() {
            return new class {
                public function getValue() {
                    return 'test instance';
                }
            };
        });
        
        $instance = $container->get('TestClass');
        TestFramework::expect($instance->getValue())->toBe('test instance');
    });
    
    TestFramework::it('should handle singletons', function() {
        $container = new Container();
        
        $container->singleton('singleton.test', function() {
            return new class {
                public $id;
                public function __construct() {
                    $this->id = uniqid();
                }
            };
        });
        
        $instance1 = $container->get('singleton.test');
        $instance2 = $container->get('singleton.test');
        
        TestFramework::expect($instance1->id)->toBe($instance2->id);
    });
    
    TestFramework::it('should throw NotFoundException for unknown services', function() {
        $container = new Container();
        
        TestFramework::expect(function() use ($container) {
            $container->get('unknown.service');
        })->toThrow(NotFoundException::class);
    });
    
    TestFramework::it('should check if service exists', function() {
        $container = new Container();
        $container->bind('existing.service', 'value');
        
        TestFramework::expect($container->has('existing.service'))->toBeTrue();
        TestFramework::expect($container->has('non.existing.service'))->toBeFalse();
    });
    
    TestFramework::it('should handle service tagging', function() {
        $container = new Container();
        
        $container->bind('service1', 'value1');
        $container->bind('service2', 'value2');
        $container->tag(['service1', 'service2'], 'test.tag');
        
        $tagged = $container->tagged('test.tag');
        TestFramework::expect(count($tagged))->toBe(2);
    });
    
    TestFramework::it('should resolve dependencies with container injection', function() {
        $container = new Container();
        
        $container->bind('dependency.test', function($c) {
            TestFramework::expect($c)->toBeInstanceOf(Container::class);
            return 'dependency resolved';
        });
        
        $value = $container->get('dependency.test');
        TestFramework::expect($value)->toBe('dependency resolved');
    });
});