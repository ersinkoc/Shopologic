<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth\Passwords;

use Shopologic\Core\Auth\Models\User;
use Shopologic\Core\Database\ConnectionInterface;

class TokenRepository
{
    protected ConnectionInterface $connection;
    protected string $table = 'password_resets';
    protected int $expires = 3600; // 1 hour

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Create a new password reset token
     */
    public function create(User $user): string
    {
        $email = $user->email;
        
        $this->deleteExisting($user);

        $token = $this->createNewToken();

        $this->insert($email, $token);

        return $token;
    }

    /**
     * Determine if a token record exists and is valid
     */
    public function exists(User $user, string $token): bool
    {
        $record = $this->getRecord($user);

        return $record &&
               !$this->tokenExpired($record['created_at']) &&
               password_verify($token, $record['token']);
    }

    /**
     * Delete a token record
     */
    public function delete(User $user): void
    {
        $this->deleteExisting($user);
    }

    /**
     * Delete expired tokens
     */
    public function deleteExpired(): void
    {
        $expiredAt = time() - $this->expires;
        
        $this->connection->execute(
            "DELETE FROM {$this->table} WHERE created_at < ?",
            [$expiredAt]
        );
    }

    /**
     * Create a new token
     */
    protected function createNewToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Get the record for the given user
     */
    protected function getRecord(User $user): ?array
    {
        $result = $this->connection->query(
            "SELECT * FROM {$this->table} WHERE email = ? LIMIT 1",
            [$user->email]
        );

        return $result->fetch();
    }

    /**
     * Determine if the token has expired
     */
    protected function tokenExpired(int $createdAt): bool
    {
        return $createdAt < (time() - $this->expires);
    }

    /**
     * Delete existing tokens for the user
     */
    protected function deleteExisting(User $user): void
    {
        $this->connection->execute(
            "DELETE FROM {$this->table} WHERE email = ?",
            [$user->email]
        );
    }

    /**
     * Insert a new token record
     */
    protected function insert(string $email, string $token): void
    {
        $this->connection->execute(
            "INSERT INTO {$this->table} (email, token, created_at) VALUES (?, ?, ?)",
            [$email, password_hash($token, PASSWORD_BCRYPT), time()]
        );
    }
}