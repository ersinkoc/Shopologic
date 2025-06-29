<?php

declare(strict_types=1);

namespace Shopologic\Core\Http;

use Shopologic\PSR\Http\Message\RequestInterface;

class ServerRequestFactory
{
    public static function fromGlobals(): RequestInterface
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = self::createUriFromGlobals();
        $headers = self::getHeadersFromGlobals();
        $body = self::createBodyFromGlobals();
        $protocol = self::getProtocolVersion();

        $request = new Request($method, $uri, $headers, $body, $protocol);

        if (isset($_SERVER['HTTP_HOST'])) {
            $request = $request->withHeader('Host', $_SERVER['HTTP_HOST']);
        }

        return $request;
    }

    private static function createUriFromGlobals(): Uri
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $port = isset($_SERVER['SERVER_PORT']) ? (int)$_SERVER['SERVER_PORT'] : null;
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $query = $_SERVER['QUERY_STRING'] ?? '';

        if ($port !== null && (
            ($scheme === 'http' && $port === 80) ||
            ($scheme === 'https' && $port === 443)
        )) {
            $port = null;
        }

        $uri = new Uri();
        $uri = $uri->withScheme($scheme);
        $uri = $uri->withHost($host);
        
        if ($port !== null) {
            $uri = $uri->withPort($port);
        }
        
        $uri = $uri->withPath($path);
        
        if ($query !== '') {
            $uri = $uri->withQuery($query);
        }

        return $uri;
    }

    private static function getHeadersFromGlobals(): array
    {
        $headers = [];

        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $headerName = str_replace('_', '-', substr($name, 5));
                $headerName = ucwords(strtolower($headerName), '-');
                $headers[$headerName] = [$value];
            } elseif (in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headerName = str_replace('_', '-', $name);
                $headerName = ucwords(strtolower($headerName), '-');
                $headers[$headerName] = [$value];
            }
        }

        return $headers;
    }

    private static function createBodyFromGlobals(): Stream
    {
        $body = new Stream('php://input', 'r');
        return $body;
    }

    private static function getProtocolVersion(): string
    {
        if (isset($_SERVER['SERVER_PROTOCOL'])) {
            if (preg_match('/HTTP\/(\d(?:\.\d)?)/', $_SERVER['SERVER_PROTOCOL'], $matches)) {
                return $matches[1];
            }
        }

        return '1.1';
    }
}