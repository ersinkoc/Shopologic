<?php

declare(strict_types=1);

namespace Tests\Unit\advanced-email-marketing\Services;

use PHPUnit\Framework\TestCase;
use Shopologic\Plugins\AdvancedEmailMarketing\Services\EmailSender;

/**
 * Unit tests for EmailSender
 */
class EmailSenderTest extends TestCase
{
    private EmailSender $service;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock dependencies as needed
        $this->service = new EmailSender();
    }
    
    public function testServiceInstantiation(): void
    {
        $this->assertInstanceOf(EmailSender::class, $this->service);
    }
    
    // Add specific service tests here
    public function testServiceMethods(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        
        $this->assertNotEmpty($methods, 'Service should have public methods');
    }
}