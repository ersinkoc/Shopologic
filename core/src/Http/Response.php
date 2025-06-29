<?php

declare(strict_types=1);

namespace Shopologic\Core\Http;

use Shopologic\PSR\Http\Message\ResponseInterface;
use Shopologic\PSR\Http\Message\StreamInterface;

class Response extends Message implements ResponseInterface
{
    private int $statusCode;
    private string $reasonPhrase;

    private static array $reasonPhrases = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        304 => 'Not Modified',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout'
    ];

    public function __construct(
        int $statusCode = 200,
        array $headers = [],
        StreamInterface $body = null,
        string $protocolVersion = '1.1',
        string $reasonPhrase = ''
    ) {
        $this->statusCode = $statusCode;
        $this->reasonPhrase = $reasonPhrase !== '' ? $reasonPhrase : (self::$reasonPhrases[$statusCode] ?? '');
        $this->protocolVersion = $protocolVersion;
        
        parent::__construct($body);
        $this->setHeaders($headers);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): static
    {
        $new = clone $this;
        $new->statusCode = $code;
        $new->reasonPhrase = $reasonPhrase !== '' ? $reasonPhrase : (self::$reasonPhrases[$code] ?? '');
        return $new;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    public function send(): void
    {
        // Send status line
        http_response_code($this->statusCode);
        
        // Send headers
        foreach ($this->headers as $name => $values) {
            foreach ($values as $value) {
                header($name . ': ' . $value);
            }
        }
        
        // Send body
        if ($this->body !== null) {
            if ($this->body->isSeekable()) {
                $this->body->rewind();
            }
            
            while (!$this->body->eof()) {
                echo $this->body->read(8192);
            }
        }
    }
}