<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth\TwoFactor;

use Shopologic\Core\Auth\Models\User;

class TwoFactorAuthManager
{
    protected string $issuer;
    protected int $window = 1;

    public function __construct(string $issuer = 'Shopologic')
    {
        $this->issuer = $issuer;
    }

    /**
     * Generate a new secret key
     */
    public function generateSecretKey(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        
        for ($i = 0; $i < 16; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        
        return $secret;
    }

    /**
     * Generate QR code URL for Google Authenticator
     */
    public function getQrCodeUrl(User $user, string $secret): string
    {
        $label = urlencode($this->issuer . ':' . $user->email);
        $issuer = urlencode($this->issuer);
        
        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s',
            $label,
            $secret,
            $issuer
        );
    }

    /**
     * Verify a TOTP code
     */
    public function verifyCode(string $secret, string $code): bool
    {
        $timestamp = time();
        
        for ($i = -$this->window; $i <= $this->window; $i++) {
            $time = $timestamp + ($i * 30);
            $expectedCode = $this->generateCode($secret, $time);
            
            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Generate a TOTP code
     */
    public function generateCode(string $secret, ?int $timestamp = null): string
    {
        if ($timestamp === null) {
            $timestamp = time();
        }
        
        $time = floor($timestamp / 30);
        $secretKey = $this->base32Decode($secret);
        
        // Pack time into binary string
        $time = pack('N*', 0) . pack('N*', $time);
        
        // Generate HMAC
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        
        // Get offset
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        
        // Get 4 bytes from hash starting at offset
        $binary = substr($hash, $offset, 4);
        $binary = unpack('N', $binary);
        $binary = $binary[1] & 0x7FFFFFFF;
        
        // Generate 6-digit code
        $code = $binary % 1000000;
        
        return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Enable two-factor authentication for a user
     */
    public function enable(User $user, string $secret): void
    {
        $user->two_factor_secret = encrypt($secret);
        $user->two_factor_confirmed_at = new \DateTime();
        $user->save();
    }

    /**
     * Disable two-factor authentication for a user
     */
    public function disable(User $user): void
    {
        $user->two_factor_secret = null;
        $user->two_factor_confirmed_at = null;
        $user->save();
    }

    /**
     * Check if user has two-factor enabled
     */
    public function isEnabled(User $user): bool
    {
        return !empty($user->two_factor_secret) && !empty($user->two_factor_confirmed_at);
    }

    /**
     * Get user's decrypted secret
     */
    public function getSecret(User $user): ?string
    {
        if (!$user->two_factor_secret) {
            return null;
        }
        
        return decrypt($user->two_factor_secret);
    }

    /**
     * Generate recovery codes
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        
        for ($i = 0; $i < $count; $i++) {
            $codes[] = $this->generateRecoveryCode();
        }
        
        return $codes;
    }

    /**
     * Generate a single recovery code
     */
    protected function generateRecoveryCode(): string
    {
        $code = '';
        
        for ($i = 0; $i < 10; $i++) {
            $code .= random_int(0, 9);
            
            if ($i === 4) {
                $code .= '-';
            }
        }
        
        return $code;
    }

    /**
     * Base32 decode
     */
    protected function base32Decode(string $input): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $buffer = 0;
        $bits = 0;
        
        $input = strtoupper($input);
        
        for ($i = 0; $i < strlen($input); $i++) {
            $val = strpos($alphabet, $input[$i]);
            
            if ($val === false) {
                continue;
            }
            
            $buffer = ($buffer << 5) | $val;
            $bits += 5;
            
            if ($bits >= 8) {
                $bits -= 8;
                $output .= chr(($buffer >> $bits) & 0xFF);
            }
        }
        
        return $output;
    }
}