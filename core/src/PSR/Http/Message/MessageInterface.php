<?php

declare(strict_types=1);

namespace Shopologic\PSR\Http\Message;

interface MessageInterface
{
    public function getProtocolVersion(): string;
    public function withProtocolVersion(string $version): static;
    public function getHeaders(): array;
    public function hasHeader(string $name): bool;
    public function getHeader(string $name): array;
    public function getHeaderLine(string $name): string;
    public function withHeader(string $name, $value): static;
    public function withAddedHeader(string $name, $value): static;
    public function withoutHeader(string $name): static;
    public function getBody(): StreamInterface;
    public function withBody(StreamInterface $body): static;
}