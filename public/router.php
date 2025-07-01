<?php

/**
 * Router script for PHP built-in server
 * This ensures all requests go through our application router
 */

// Get the requested URI
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Handle theme assets by looking in the correct location
if (preg_match('/^\/themes\/([^\/]+)\/assets\/(.+)$/', $uri, $matches)) {
    $themeName = $matches[1];
    $assetPath = $matches[2];
    $fullPath = dirname(__DIR__) . "/themes/{$themeName}/assets/{$assetPath}";
    
    if (file_exists($fullPath) && is_file($fullPath)) {
        // Determine content type
        $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
        $contentTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject'
        ];
        
        $contentType = $contentTypes[$extension] ?? 'application/octet-stream';
        
        // Set headers
        header('Content-Type: ' . $contentType);
        header('Cache-Control: public, max-age=31536000'); // Cache for 1 year
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($fullPath)) . ' GMT');
        
        // Output file content
        readfile($fullPath);
        return true; // Handled by router
    }
}

// If it's a static file that exists in public, serve it directly
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false; // Let PHP server handle static files
}

// Otherwise, route through our application
require_once __DIR__ . '/index.php';