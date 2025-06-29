<?php

declare(strict_types=1);

namespace Shopologic\Core\Session;

class SessionManager
{
    protected array $data = [];
    protected string $id;
    protected bool $started = false;

    public function __construct()
    {
        $this->id = $this->generateId();
    }

    /**
     * Start the session
     */
    public function start(): void
    {
        if ($this->started) {
            return;
        }

        if (PHP_SAPI !== 'cli') {
            session_id($this->id);
            session_start();
            $this->data = $_SESSION;
        }

        $this->started = true;
    }

    /**
     * Get session ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Set session ID
     */
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * Get a value from the session
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Put a value in the session
     */
    public function put(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
        
        if (PHP_SAPI !== 'cli' && $this->started) {
            $_SESSION[$key] = $value;
        }
    }

    /**
     * Check if a key exists in the session
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Remove a value from the session
     */
    public function remove(string $key): void
    {
        unset($this->data[$key]);
        
        if (PHP_SAPI !== 'cli' && $this->started) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * Flash data to the session
     */
    public function flash(string $key, mixed $value): void
    {
        $this->put('_flash.' . $key, $value);
    }

    /**
     * Get flash data from the session
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $this->get('_flash.' . $key, $default);
        $this->remove('_flash.' . $key);
        return $value;
    }

    /**
     * Get all session data
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Clear all session data
     */
    public function flush(): void
    {
        $this->data = [];
        
        if (PHP_SAPI !== 'cli' && $this->started) {
            session_unset();
        }
    }

    /**
     * Regenerate the session ID
     */
    public function regenerate(bool $deleteOld = false): void
    {
        $this->id = $this->generateId();
        
        if (PHP_SAPI !== 'cli' && $this->started) {
            session_regenerate_id($deleteOld);
            $this->id = session_id();
        }
    }

    /**
     * Migrate the session to a new ID
     */
    public function migrate(bool $destroy = false): void
    {
        $this->regenerate($destroy);
    }

    /**
     * Save the session data
     */
    public function save(): void
    {
        if (PHP_SAPI !== 'cli' && $this->started) {
            session_write_close();
        }
    }

    /**
     * Age the flash data
     */
    public function ageFlashData(): void
    {
        $flash = $this->get('_flash', []);
        $aged = $this->get('_flash.old', []);

        $this->put('_flash.old', array_keys($flash));

        foreach ($aged as $key) {
            $this->remove('_flash.' . $key);
        }
    }

    /**
     * Generate a new session ID
     */
    protected function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Destroy the session
     */
    public function destroy(): void
    {
        $this->flush();
        
        if (PHP_SAPI !== 'cli' && $this->started) {
            session_destroy();
        }
        
        $this->started = false;
    }
}