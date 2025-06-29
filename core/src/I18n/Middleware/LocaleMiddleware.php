<?php

declare(strict_types=1);

namespace Shopologic\Core\I18n\Middleware;

use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use Shopologic\Core\Http\Middleware\MiddlewareInterface;
use Shopologic\Core\I18n\Locale\LocaleManager;

/**
 * Middleware for locale detection and switching
 */
class LocaleMiddleware implements MiddlewareInterface
{
    private LocaleManager $localeManager;

    public function __construct(LocaleManager $localeManager)
    {
        $this->localeManager = $localeManager;
    }

    public function handle(Request $request, callable $next): Response
    {
        // Check if locale is explicitly requested
        $requestedLocale = $request->get('locale');
        
        if ($requestedLocale && $this->localeManager->isAvailable($requestedLocale)) {
            $this->localeManager->setCurrentLocale($requestedLocale);
        } else {
            // Try to detect locale from request
            $detectedLocale = $this->localeManager->detectFromRequest($request);
            
            if ($detectedLocale) {
                $this->localeManager->setCurrentLocale($detectedLocale);
            }
        }
        
        // Add locale to request attributes
        $request->setAttribute('locale', $this->localeManager->getCurrentLocale());
        
        // Process request
        $response = $next($request);
        
        // Add locale headers
        $locale = $this->localeManager->getCurrentLocale();
        $response->header('Content-Language', $locale);
        
        // Add HTML direction attribute for RTL languages
        if ($this->localeManager->isRtl()) {
            $response->header('X-Text-Direction', 'rtl');
        }
        
        return $response;
    }
}