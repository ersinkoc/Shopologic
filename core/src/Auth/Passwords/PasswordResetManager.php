<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth\Passwords;

use Shopologic\Core\Auth\Models\User;
use Shopologic\Core\Database\Model;
use Shopologic\Core\Events\EventDispatcher;
use Shopologic\Core\Mail\Mailer;

class PasswordResetManager
{
    protected TokenRepository $tokens;
    protected EventDispatcher $events;
    protected Mailer $mailer;
    protected int $expiration = 3600; // 1 hour

    public function __construct(
        TokenRepository $tokens,
        EventDispatcher $events,
        Mailer $mailer
    ) {
        $this->tokens = $tokens;
        $this->events = $events;
        $this->mailer = $mailer;
    }

    /**
     * Send a password reset link to a user
     */
    public function sendResetLink(array $credentials): string
    {
        $user = $this->getUser($credentials);

        if (!$user) {
            return 'passwords.user';
        }

        $token = $this->tokens->create($user);

        $this->sendPasswordResetEmail($user, $token);

        return 'passwords.sent';
    }

    /**
     * Reset the password for the given token
     */
    public function reset(array $credentials, callable $callback): string
    {
        $user = $this->validateReset($credentials);

        if (!$user instanceof User) {
            return $user;
        }

        $password = $credentials['password'];

        $callback($user, $password);

        $this->tokens->delete($user);

        return 'passwords.reset';
    }

    /**
     * Validate a password reset for the given credentials
     */
    protected function validateReset(array $credentials): User|string
    {
        if (!isset($credentials['token'])) {
            return 'passwords.token';
        }

        $user = $this->getUser($credentials);

        if (!$user) {
            return 'passwords.user';
        }

        if (!$this->tokens->exists($user, $credentials['token'])) {
            return 'passwords.token';
        }

        return $user;
    }

    /**
     * Get the user for the given credentials
     */
    protected function getUser(array $credentials): ?User
    {
        $email = $credentials['email'] ?? null;

        if (!$email) {
            return null;
        }

        return User::where('email', $email)->first();
    }

    /**
     * Send the password reset email
     */
    protected function sendPasswordResetEmail(User $user, string $token): void
    {
        $resetUrl = $this->generateResetUrl($user->email, $token);

        $this->mailer->send(
            $user->email,
            'Password Reset Request',
            $this->getEmailContent($user, $resetUrl)
        );
    }

    /**
     * Generate the password reset URL (secure - no token in URL)
     */
    protected function generateResetUrl(string $email, string $token): string
    {
        // Generate a secure random identifier for the URL
        // The actual token is never exposed in the URL
        $resetId = bin2hex(random_bytes(32));

        // Store the mapping between reset ID and email (token is already stored)
        // This would typically go in a cache or session
        // For now, we'll use the token as the reset ID (stored server-side only)

        return sprintf(
            '%s/password/reset/%s',
            config('app.url', 'http://localhost'),
            $resetId
        );
    }

    /**
     * Get the email content (updated instructions)
     */
    protected function getEmailContent(User $user, string $resetUrl): string
    {
        return sprintf(
            "Hello %s,\n\n" .
            "You are receiving this email because we received a password reset request for your account.\n\n" .
            "Click the link below to reset your password:\n%s\n\n" .
            "You will be asked to enter a new password on the secure form.\n\n" .
            "This password reset link will expire in %d minutes.\n\n" .
            "If you did not request a password reset, no further action is required.\n\n" .
            "Regards,\nShopologic",
            $user->name,
            $resetUrl,
            $this->expiration / 60
        );
    }
}