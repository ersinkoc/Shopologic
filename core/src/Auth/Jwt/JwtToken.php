<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth\Jwt;

class JwtToken
{
    protected array $header = [
        'typ' => 'JWT',
        'alg' => 'HS256'
    ];
    
    protected array $payload = [];
    protected string $signature = '';
    protected string $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    /**
     * Set token payload
     */
    public function payload(array $payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    /**
     * Set specific claim
     */
    public function claim(string $key, mixed $value): self
    {
        $this->payload[$key] = $value;
        return $this;
    }

    /**
     * Set token subject (user ID)
     */
    public function subject(mixed $sub): self
    {
        return $this->claim('sub', $sub);
    }

    /**
     * Set token issuer
     */
    public function issuer(string $iss): self
    {
        return $this->claim('iss', $iss);
    }

    /**
     * Set token audience
     */
    public function audience(string|array $aud): self
    {
        return $this->claim('aud', $aud);
    }

    /**
     * Set token expiration time
     */
    public function expiresAt(int|\DateTime $exp): self
    {
        if ($exp instanceof \DateTime) {
            $exp = $exp->getTimestamp();
        }
        return $this->claim('exp', $exp);
    }

    /**
     * Set token not before time
     */
    public function notBefore(int|\DateTime $nbf): self
    {
        if ($nbf instanceof \DateTime) {
            $nbf = $nbf->getTimestamp();
        }
        return $this->claim('nbf', $nbf);
    }

    /**
     * Set token issued at time
     */
    public function issuedAt(int|\DateTime $iat): self
    {
        if ($iat instanceof \DateTime) {
            $iat = $iat->getTimestamp();
        }
        return $this->claim('iat', $iat);
    }

    /**
     * Set token ID
     */
    public function jti(string $jti): self
    {
        return $this->claim('jti', $jti);
    }

    /**
     * Generate the JWT token
     */
    public function generate(): string
    {
        // Set default times if not set
        if (!isset($this->payload['iat'])) {
            $this->issuedAt(time());
        }
        
        if (!isset($this->payload['jti'])) {
            $this->jti(bin2hex(random_bytes(16)));
        }

        $headerEncoded = $this->base64UrlEncode(json_encode($this->header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($this->payload));
        
        $signature = hash_hmac(
            'sha256',
            $headerEncoded . '.' . $payloadEncoded,
            $this->secret,
            true
        );
        
        $signatureEncoded = $this->base64UrlEncode($signature);
        
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    /**
     * Parse and validate a JWT token
     */
    public function parse(string $token): ?array
    {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return null;
        }
        
        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;
        
        // Verify signature
        $signature = $this->base64UrlDecode($signatureEncoded);
        $expectedSignature = hash_hmac(
            'sha256',
            $headerEncoded . '.' . $payloadEncoded,
            $this->secret,
            true
        );
        
        if (!hash_equals($signature, $expectedSignature)) {
            return null;
        }
        
        // Decode payload
        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);
        
        if (!$payload) {
            return null;
        }
        
        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }
        
        // Check not before
        if (isset($payload['nbf']) && $payload['nbf'] > time()) {
            return null;
        }
        
        return $payload;
    }

    /**
     * Base64 URL encode
     */
    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode
     */
    protected function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}