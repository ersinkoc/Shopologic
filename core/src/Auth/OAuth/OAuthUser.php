<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth\OAuth;

class OAuthUser
{
    protected string $id;
    protected ?string $nickname = null;
    protected ?string $name = null;
    protected ?string $email = null;
    protected ?string $avatar = null;
    protected array $raw = [];
    protected ?string $token = null;

    /**
     * Set user ID
     */
    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get user ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Set nickname
     */
    public function setNickname(?string $nickname): self
    {
        $this->nickname = $nickname;
        return $this;
    }

    /**
     * Get nickname
     */
    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    /**
     * Set name
     */
    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get name
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Set email
     */
    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    /**
     * Get email
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Set avatar
     */
    public function setAvatar(?string $avatar): self
    {
        $this->avatar = $avatar;
        return $this;
    }

    /**
     * Get avatar
     */
    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    /**
     * Set raw user data
     */
    public function setRaw(array $raw): self
    {
        $this->raw = $raw;
        return $this;
    }

    /**
     * Get raw user data
     */
    public function getRaw(): array
    {
        return $this->raw;
    }

    /**
     * Get specific raw data
     */
    public function getRawAttribute(string $key, mixed $default = null): mixed
    {
        return $this->raw[$key] ?? $default;
    }

    /**
     * Set access token
     */
    public function setToken(?string $token): self
    {
        $this->token = $token;
        return $this;
    }

    /**
     * Get access token
     */
    public function getToken(): ?string
    {
        return $this->token;
    }
}