<?php

declare(strict_types=1);

namespace Shopologic\PSR\Http\Message;

interface ResponseInterface extends MessageInterface
{
    public function getStatusCode(): int;
    public function withStatus(int $code, string $reasonPhrase = ''): static;
    public function getReasonPhrase(): string;
}