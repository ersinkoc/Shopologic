<?php

namespace Shopologic\Core\Backup;

class Backup
{
    private string $id;
    private string $type;
    private string $storage;
    private string $path;
    private string $description;
    private bool $encrypted;
    private bool $compressed;
    private int $size = 0;
    private string $status;
    private ?string $error = null;
    private \DateTime $createdAt;
    private ?\DateTime $completedAt = null;
    
    public function getId(): string
    {
        return $this->id;
    }
    
    public function setId(string $id): void
    {
        $this->id = $id;
    }
    
    public function getType(): string
    {
        return $this->type;
    }
    
    public function setType(string $type): void
    {
        $this->type = $type;
    }
    
    public function getStorage(): string
    {
        return $this->storage;
    }
    
    public function setStorage(string $storage): void
    {
        $this->storage = $storage;
    }
    
    public function getPath(): string
    {
        return $this->path;
    }
    
    public function setPath(string $path): void
    {
        $this->path = $path;
    }
    
    public function getDescription(): string
    {
        return $this->description;
    }
    
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }
    
    public function isEncrypted(): bool
    {
        return $this->encrypted;
    }
    
    public function setEncrypted(bool $encrypted): void
    {
        $this->encrypted = $encrypted;
    }
    
    public function isCompressed(): bool
    {
        return $this->compressed;
    }
    
    public function setCompressed(bool $compressed): void
    {
        $this->compressed = $compressed;
    }
    
    public function getSize(): int
    {
        return $this->size;
    }
    
    public function setSize(int $size): void
    {
        $this->size = $size;
    }
    
    public function getStatus(): string
    {
        return $this->status;
    }
    
    public function setStatus(string $status): void
    {
        $this->status = $status;
    }
    
    public function getError(): ?string
    {
        return $this->error;
    }
    
    public function setError(?string $error): void
    {
        $this->error = $error;
    }
    
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }
    
    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
    
    public function getCompletedAt(): ?\DateTime
    {
        return $this->completedAt;
    }
    
    public function setCompletedAt(?\DateTime $completedAt): void
    {
        $this->completedAt = $completedAt;
    }
}