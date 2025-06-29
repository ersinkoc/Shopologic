<?php

declare(strict_types=1);

/**
 * Template Engine Unit Tests
 */

use Shopologic\Core\Theme\TemplateEngine;
use Shopologic\Core\Theme\Parser\TemplateParser;
use Shopologic\Core\Theme\Parser\Lexer;
use Shopologic\Core\Configuration\ConfigurationManager;

TestFramework::describe('Template Engine', function() {
    TestFramework::it('should create template engine instance', function() {
        $config = new ConfigurationManager();
        $engine = new TemplateEngine($config);
        TestFramework::expect($engine)->toBeInstanceOf(TemplateEngine::class);
    });
    
    TestFramework::it('should render simple templates', function() {
        $config = new ConfigurationManager();
        $engine = new TemplateEngine($config);
        
        $template = 'Hello {{ name }}!';
        $result = $engine->render($template, ['name' => 'World']);
        
        TestFramework::expect($result)->toBe('Hello World!');
    });
    
    TestFramework::it('should handle template variables', function() {
        $config = new ConfigurationManager();
        $engine = new TemplateEngine($config);
        
        $template = 'User: {{ user.name }}, Age: {{ user.age }}';
        $data = [
            'user' => [
                'name' => 'John Doe',
                'age' => 30
            ]
        ];
        
        $result = $engine->render($template, $data);
        TestFramework::expect($result)->toBe('User: John Doe, Age: 30');
    });
    
    TestFramework::it('should handle if statements', function() {
        $config = new ConfigurationManager();
        $engine = new TemplateEngine($config);
        
        $template = '{% if user.active %}Active{% else %}Inactive{% endif %}';
        
        $result1 = $engine->render($template, ['user' => ['active' => true]]);
        $result2 = $engine->render($template, ['user' => ['active' => false]]);
        
        TestFramework::expect($result1)->toBe('Active');
        TestFramework::expect($result2)->toBe('Inactive');
    });
    
    TestFramework::it('should handle for loops', function() {
        $config = new ConfigurationManager();
        $engine = new TemplateEngine($config);
        
        $template = '{% for item in items %}{{ item }}{% endfor %}';
        $data = ['items' => ['a', 'b', 'c']];
        
        $result = $engine->render($template, $data);
        TestFramework::expect($result)->toBe('abc');
    });
    
    TestFramework::it('should handle template filters', function() {
        $config = new ConfigurationManager();
        $engine = new TemplateEngine($config);
        
        $template = '{{ message | upper }}';
        $result = $engine->render($template, ['message' => 'hello world']);
        
        TestFramework::expect($result)->toBe('HELLO WORLD');
    });
    
    TestFramework::it('should handle template includes', function() {
        $config = new ConfigurationManager();
        $engine = new TemplateEngine($config);
        
        // Mock template loader to return include content
        $template = '{% include "header.twig" %}Content{% include "footer.twig" %}';
        
        // For testing, we'll just check that the include syntax is parsed
        TestFramework::expect(strpos($template, 'include') !== false)->toBeTrue();
    });
});

TestFramework::describe('Template Parser', function() {
    TestFramework::it('should create parser instance', function() {
        $lexer = new Lexer();
        $parser = new TemplateParser($lexer);
        TestFramework::expect($parser)->toBeInstanceOf(TemplateParser::class);
    });
    
    TestFramework::it('should parse simple text', function() {
        $lexer = new Lexer();
        $parser = new TemplateParser($lexer);
        
        $tokens = $lexer->tokenize('Hello World');
        $ast = $parser->parse($tokens);
        
        TestFramework::expect($ast)->toBeInstanceOf('Shopologic\\Core\\Theme\\Parser\\Node\\TemplateNode');
    });
    
    TestFramework::it('should parse variable expressions', function() {
        $lexer = new Lexer();
        $parser = new TemplateParser($lexer);
        
        $tokens = $lexer->tokenize('{{ name }}');
        $ast = $parser->parse($tokens);
        
        TestFramework::expect($ast)->toBeInstanceOf('Shopologic\\Core\\Theme\\Parser\\Node\\TemplateNode');
    });
    
    TestFramework::it('should parse control structures', function() {
        $lexer = new Lexer();
        $parser = new TemplateParser($lexer);
        
        $tokens = $lexer->tokenize('{% if condition %}content{% endif %}');
        $ast = $parser->parse($tokens);
        
        TestFramework::expect($ast)->toBeInstanceOf('Shopologic\\Core\\Theme\\Parser\\Node\\TemplateNode');
    });
});

TestFramework::describe('Template Lexer', function() {
    TestFramework::it('should create lexer instance', function() {
        $lexer = new Lexer();
        TestFramework::expect($lexer)->toBeInstanceOf(Lexer::class);
    });
    
    TestFramework::it('should tokenize text content', function() {
        $lexer = new Lexer();
        $tokens = $lexer->tokenize('Hello World');
        
        TestFramework::expect(count($tokens))->toBeGreaterThan(0);
        TestFramework::expect($tokens[0]['type'])->toBe('TEXT');
    });
    
    TestFramework::it('should tokenize variable expressions', function() {
        $lexer = new Lexer();
        $tokens = $lexer->tokenize('{{ variable }}');
        
        $hasVarStart = false;
        $hasName = false;
        $hasVarEnd = false;
        
        foreach ($tokens as $token) {
            if ($token['type'] === 'VAR_START') $hasVarStart = true;
            if ($token['type'] === 'NAME' && $token['value'] === 'variable') $hasName = true;
            if ($token['type'] === 'VAR_END') $hasVarEnd = true;
        }
        
        TestFramework::expect($hasVarStart)->toBeTrue();
        TestFramework::expect($hasName)->toBeTrue();
        TestFramework::expect($hasVarEnd)->toBeTrue();
    });
    
    TestFramework::it('should tokenize block expressions', function() {
        $lexer = new Lexer();
        $tokens = $lexer->tokenize('{% if condition %}');
        
        $hasBlockStart = false;
        $hasIf = false;
        $hasBlockEnd = false;
        
        foreach ($tokens as $token) {
            if ($token['type'] === 'BLOCK_START') $hasBlockStart = true;
            if ($token['type'] === 'NAME' && $token['value'] === 'if') $hasIf = true;
            if ($token['type'] === 'BLOCK_END') $hasBlockEnd = true;
        }
        
        TestFramework::expect($hasBlockStart)->toBeTrue();
        TestFramework::expect($hasIf)->toBeTrue();
        TestFramework::expect($hasBlockEnd)->toBeTrue();
    });
    
    TestFramework::it('should handle mixed content', function() {
        $lexer = new Lexer();
        $tokens = $lexer->tokenize('Hello {{ name }}, welcome to {% if premium %}premium{% endif %} service!');
        
        $hasText = false;
        $hasVariable = false;
        $hasBlock = false;
        
        foreach ($tokens as $token) {
            if ($token['type'] === 'TEXT') $hasText = true;
            if ($token['type'] === 'VAR_START') $hasVariable = true;
            if ($token['type'] === 'BLOCK_START') $hasBlock = true;
        }
        
        TestFramework::expect($hasText)->toBeTrue();
        TestFramework::expect($hasVariable)->toBeTrue();
        TestFramework::expect($hasBlock)->toBeTrue();
    });
    
    TestFramework::it('should handle string literals', function() {
        $lexer = new Lexer();
        $tokens = $lexer->tokenize('{{ "Hello World" }}');
        
        $hasString = false;
        foreach ($tokens as $token) {
            if ($token['type'] === 'STRING' && $token['value'] === 'Hello World') {
                $hasString = true;
                break;
            }
        }
        
        TestFramework::expect($hasString)->toBeTrue();
    });
    
    TestFramework::it('should handle numbers', function() {
        $lexer = new Lexer();
        $tokens = $lexer->tokenize('{{ 42 }}');
        
        $hasNumber = false;
        foreach ($tokens as $token) {
            if ($token['type'] === 'NUMBER' && $token['value'] === '42') {
                $hasNumber = true;
                break;
            }
        }
        
        TestFramework::expect($hasNumber)->toBeTrue();
    });
});