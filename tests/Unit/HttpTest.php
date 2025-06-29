<?php

declare(strict_types=1);

/**
 * HTTP Unit Tests
 */

use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use Shopologic\Core\Http\JsonResponse;
use Shopologic\Core\Http\Uri;
use Shopologic\Core\Http\Stream;

TestFramework::describe('HTTP Request', function() {
    TestFramework::it('should create request instance', function() {
        $request = new Request();
        TestFramework::expect($request)->toBeInstanceOf(Request::class);
    });
    
    TestFramework::it('should handle request method', function() {
        $request = new Request('POST');
        TestFramework::expect($request->getMethod())->toBe('POST');
    });
    
    TestFramework::it('should handle request URI', function() {
        $uri = new Uri('https://example.com/path?query=value');
        $request = new Request('GET', $uri);
        
        TestFramework::expect($request->getUri()->getHost())->toBe('example.com');
        TestFramework::expect($request->getUri()->getPath())->toBe('/path');
        TestFramework::expect($request->getUri()->getQuery())->toBe('query=value');
    });
    
    TestFramework::it('should handle request headers', function() {
        $request = new Request('GET', null, ['Content-Type' => 'application/json']);
        
        TestFramework::expect($request->hasHeader('Content-Type'))->toBeTrue();
        TestFramework::expect($request->getHeaderLine('Content-Type'))->toBe('application/json');
    });
    
    TestFramework::it('should handle request body', function() {
        $body = new Stream('php://memory', 'w+');
        $body->write('{"test": "data"}');
        $body->rewind();
        
        $request = new Request('POST', null, [], $body);
        
        TestFramework::expect($request->getBody()->getContents())->toBe('{"test": "data"}');
    });
    
    TestFramework::it('should create from globals', function() {
        // Mock global variables
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['HTTP_HOST'] = 'localhost';
        
        $request = Request::createFromGlobals();
        
        TestFramework::expect($request->getMethod())->toBe('GET');
        TestFramework::expect($request->getUri()->getPath())->toBe('/test');
    });
});

TestFramework::describe('HTTP Response', function() {
    TestFramework::it('should create response instance', function() {
        $response = new Response();
        TestFramework::expect($response)->toBeInstanceOf(Response::class);
    });
    
    TestFramework::it('should handle status codes', function() {
        $response = new Response(404);
        TestFramework::expect($response->getStatusCode())->toBe(404);
        TestFramework::expect($response->getReasonPhrase())->toBe('Not Found');
    });
    
    TestFramework::it('should handle response headers', function() {
        $response = new Response(200, ['Content-Type' => 'text/html']);
        
        TestFramework::expect($response->hasHeader('Content-Type'))->toBeTrue();
        TestFramework::expect($response->getHeaderLine('Content-Type'))->toBe('text/html');
    });
    
    TestFramework::it('should handle response body', function() {
        $body = new Stream('php://memory', 'w+');
        $body->write('<h1>Hello World</h1>');
        $body->rewind();
        
        $response = new Response(200, [], $body);
        
        TestFramework::expect($response->getBody()->getContents())->toBe('<h1>Hello World</h1>');
    });
    
    TestFramework::it('should be immutable', function() {
        $response = new Response(200);
        $newResponse = $response->withStatus(404);
        
        TestFramework::expect($response->getStatusCode())->toBe(200);
        TestFramework::expect($newResponse->getStatusCode())->toBe(404);
        TestFramework::expect($response !== $newResponse)->toBeTrue();
    });
});

TestFramework::describe('JSON Response', function() {
    TestFramework::it('should create JSON response', function() {
        $data = ['message' => 'Hello World', 'status' => 'success'];
        $response = new JsonResponse($data);
        
        TestFramework::expect($response)->toBeInstanceOf(JsonResponse::class);
        TestFramework::expect($response->getHeaderLine('Content-Type'))->toBe('application/json');
    });
    
    TestFramework::it('should encode data as JSON', function() {
        $data = ['items' => [1, 2, 3], 'total' => 3];
        $response = new JsonResponse($data);
        
        $body = $response->getBody()->getContents();
        $decoded = json_decode($body, true);
        
        TestFramework::expect($decoded)->toEqual($data);
    });
    
    TestFramework::it('should handle different status codes', function() {
        $response = new JsonResponse(['error' => 'Not found'], 404);
        
        TestFramework::expect($response->getStatusCode())->toBe(404);
    });
});

TestFramework::describe('URI', function() {
    TestFramework::it('should create URI instance', function() {
        $uri = new Uri('https://example.com/path?query=value#fragment');
        
        TestFramework::expect($uri)->toBeInstanceOf(Uri::class);
        TestFramework::expect($uri->getScheme())->toBe('https');
        TestFramework::expect($uri->getHost())->toBe('example.com');
        TestFramework::expect($uri->getPath())->toBe('/path');
        TestFramework::expect($uri->getQuery())->toBe('query=value');
        TestFramework::expect($uri->getFragment())->toBe('fragment');
    });
    
    TestFramework::it('should handle ports', function() {
        $uri = new Uri('http://example.com:8080/path');
        
        TestFramework::expect($uri->getPort())->toBe(8080);
        TestFramework::expect($uri->getAuthority())->toBe('example.com:8080');
    });
    
    TestFramework::it('should be immutable', function() {
        $uri = new Uri('http://example.com');
        $newUri = $uri->withPath('/new-path');
        
        TestFramework::expect($uri->getPath())->toBe('');
        TestFramework::expect($newUri->getPath())->toBe('/new-path');
        TestFramework::expect($uri !== $newUri)->toBeTrue();
    });
});

TestFramework::describe('Stream', function() {
    TestFramework::it('should create stream instance', function() {
        $stream = new Stream('php://memory', 'w+');
        TestFramework::expect($stream)->toBeInstanceOf(Stream::class);
    });
    
    TestFramework::it('should handle reading and writing', function() {
        $stream = new Stream('php://memory', 'w+');
        
        $stream->write('Hello World');
        $stream->rewind();
        
        TestFramework::expect($stream->read(5))->toBe('Hello');
        TestFramework::expect($stream->read(6))->toBe(' World');
    });
    
    TestFramework::it('should handle stream metadata', function() {
        $stream = new Stream('php://memory', 'w+');
        
        TestFramework::expect($stream->isReadable())->toBeTrue();
        TestFramework::expect($stream->isWritable())->toBeTrue();
        TestFramework::expect($stream->isSeekable())->toBeTrue();
    });
    
    TestFramework::it('should handle stream size', function() {
        $stream = new Stream('php://memory', 'w+');
        $stream->write('Hello World');
        
        TestFramework::expect($stream->getSize())->toBe(11);
    });
    
    TestFramework::it('should handle stream contents', function() {
        $stream = new Stream('php://memory', 'w+');
        $stream->write('Test Content');
        
        TestFramework::expect($stream->getContents())->toBe('Test Content');
    });
});