<?php

declare(strict_types=1);

namespace Tests\Unit\ab-testing-framework;

use PHPUnit\Framework\TestCase;
use Shopologic\Plugins\AbTestingFramework\AbTestingFrameworkPlugin;
use Shopologic\Core\Container\Container;

/**
 * Unit tests for AbTestingFrameworkPlugin
 */
class AbTestingFrameworkPluginTest extends TestCase
{
    private AbTestingFrameworkPlugin $plugin;
    private Container $container;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->container = $this->createMock(Container::class);
        $this->plugin = new AbTestingFrameworkPlugin($this->container, '/fake/path');
    }
    
    public function testPluginInstantiation(): void
    {
        $this->assertInstanceOf(AbTestingFrameworkPlugin::class, $this->plugin);
    }
    
    public function testGetName(): void
    {
        $this->assertIsString($this->plugin->getName());
        $this->assertNotEmpty($this->plugin->getName());
    }
    
    public function testGetVersion(): void
    {
        $version = $this->plugin->getVersion();
        $this->assertIsString($version);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);
    }
    
    public function testGetDescription(): void
    {
        $description = $this->plugin->getDescription();
        $this->assertIsString($description);
    }
    
    public function testLifecycleMethods(): void
    {
        // Test that lifecycle methods can be called without errors
        $this->expectNotToPerformAssertions();
        
        $this->plugin->install();
        $this->plugin->activate();
        $this->plugin->deactivate();
        $this->plugin->uninstall();
    }
    
    public function testRegistrationMethods(): void
    {
        // Test that registration methods exist and are callable
        $reflection = new \ReflectionClass($this->plugin);
        
        $requiredMethods = [
            'registerServices',
            'registerEventListeners',
            'registerHooks',
            'registerRoutes',
            'registerPermissions',
            'registerScheduledJobs'
        ];
        
        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "Method $method should exist"
            );
            
            $methodReflection = $reflection->getMethod($method);
            $this->assertTrue(
                $methodReflection->isProtected(),
                "Method $method should be protected"
            );
        }
    }
}