<?php

declare(strict_types=1);

namespace Shopologic\Core\Security;

use Shopologic\Core\Session\SessionManager;

class CsrfProtection
{
    private SessionManager $session;
    private string $tokenKey = '_csrf_token';
    private int $tokenLength = 32;

    public function __construct(SessionManager $session)
    {
        $this->session = $session;
    }

    /**
     * Generate a new CSRF token
     */
    public function generateToken(): string
    {
        $token = bin2hex(random_bytes($this->tokenLength));
        $this->session->set($this->tokenKey, $token);
        return $token;
    }

    /**
     * Get the current CSRF token, generating one if needed
     */
    public function getToken(): string
    {
        $token = $this->session->get($this->tokenKey);
        
        if (!$token) {
            $token = $this->generateToken();
        }
        
        return $token;
    }

    /**
     * Validate a CSRF token
     */
    public function validateToken(string $token): bool
    {
        $sessionToken = $this->session->get($this->tokenKey);
        
        if (!$sessionToken) {
            return false;
        }

        // Use hash_equals to prevent timing attacks
        return hash_equals($sessionToken, $token);
    }

    /**
     * Validate token from request
     */
    public function validateRequest(array $data, array $headers = []): bool
    {
        // Check POST data first
        $token = $data[$this->tokenKey] ?? null;
        
        // Check X-CSRF-Token header if not in POST
        if (!$token && isset($headers['X-CSRF-Token'])) {
            $token = $headers['X-CSRF-Token'];
        }

        if (!$token) {
            return false;
        }

        return $this->validateToken($token);
    }

    /**
     * Get HTML hidden input field for forms
     */
    public function getFormField(): string
    {
        $token = $this->getToken();
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars($this->tokenKey, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Get token for JavaScript/AJAX requests
     */
    public function getTokenForAjax(): array
    {
        return [
            'token' => $this->getToken(),
            'header' => 'X-CSRF-Token',
        ];
    }

    /**
     * Regenerate token (call after login/logout)
     */
    public function regenerateToken(): string
    {
        return $this->generateToken();
    }
}
