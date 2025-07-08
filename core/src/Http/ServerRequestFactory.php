<?php

declare(strict_types=1);

namespace Shopologic\Core\Http;

use Shopologic\PSR\Http\Message\ServerRequestInterface;
use Shopologic\PSR\Http\Message\UploadedFileInterface;

/**
 * Factory for creating ServerRequest instances from globals
 */
class ServerRequestFactory
{
    /**
     * Create a new server request from PHP globals
     */
    public static function fromGlobals(): ServerRequestInterface
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = self::getUriFromGlobals();
        $headers = self::getHeadersFromGlobals();
        $body = new Stream('php://input', 'r');
        $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? str_replace('HTTP/', '', $_SERVER['SERVER_PROTOCOL']) : '1.1';
        
        $request = new ServerRequest($method, $uri, $headers, $body, $protocol, $_SERVER);
        
        return $request
            ->withCookieParams($_COOKIE)
            ->withQueryParams($_GET)
            ->withParsedBody($_POST)
            ->withUploadedFiles(self::normalizeFiles($_FILES));
    }
    
    /**
     * Get URI from globals
     */
    private static function getUriFromGlobals(): Uri
    {
        $uri = new Uri();
        
        // Scheme
        $scheme = 'http';
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $scheme = 'https';
        }
        $uri = $uri->withScheme($scheme);
        
        // Host
        if (isset($_SERVER['HTTP_HOST'])) {
            $uri = $uri->withHost($_SERVER['HTTP_HOST']);
        } elseif (isset($_SERVER['SERVER_NAME'])) {
            $uri = $uri->withHost($_SERVER['SERVER_NAME']);
        }
        
        // Port
        if (isset($_SERVER['SERVER_PORT'])) {
            $port = (int) $_SERVER['SERVER_PORT'];
            if (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)) {
                $uri = $uri->withPort($port);
            }
        }
        
        // Path
        $path = '/';
        if (isset($_SERVER['REQUEST_URI'])) {
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
        }
        $uri = $uri->withPath($path);
        
        // Query
        if (isset($_SERVER['QUERY_STRING'])) {
            $uri = $uri->withQuery($_SERVER['QUERY_STRING']);
        }
        
        return $uri;
    }
    
    /**
     * Get headers from globals
     */
    private static function getHeadersFromGlobals(): array
    {
        $headers = [];
        
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $name = str_replace('_', '-', $key);
                $headers[$name] = $value;
            }
        }
        
        return $headers;
    }
    
    /**
     * Normalize uploaded files
     */
    private static function normalizeFiles(array $files): array
    {
        $normalized = [];
        
        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFileInterface) {
                $normalized[$key] = $value;
            } elseif (is_array($value) && isset($value['tmp_name'])) {
                if (is_array($value['tmp_name'])) {
                    $normalized[$key] = self::normalizeNestedFileSpec($value);
                } else {
                    $normalized[$key] = self::createUploadedFileFromSpec($value);
                }
            } elseif (is_array($value)) {
                $normalized[$key] = self::normalizeFiles($value);
            }
        }
        
        return $normalized;
    }
    
    /**
     * Normalize nested file specifications
     */
    private static function normalizeNestedFileSpec(array $files): array
    {
        $normalized = [];
        
        foreach (array_keys($files['tmp_name']) as $key) {
            $spec = [
                'tmp_name' => $files['tmp_name'][$key],
                'size' => $files['size'][$key],
                'error' => $files['error'][$key],
                'name' => $files['name'][$key],
                'type' => $files['type'][$key],
            ];
            
            $normalized[$key] = self::createUploadedFileFromSpec($spec);
        }
        
        return $normalized;
    }
    
    /**
     * Create an UploadedFile instance from a $_FILES specification
     */
    private static function createUploadedFileFromSpec(array $value): UploadedFileInterface
    {
        $stream = new Stream($value['tmp_name'], 'r');
        
        return new UploadedFile(
            $stream,
            $value['size'],
            $value['error'],
            $value['name'],
            $value['type']
        );
    }
}