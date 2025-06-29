<?php

declare(strict_types=1);

namespace Shopologic\Core\Http;

class JsonResponse extends Response
{
    public function __construct(mixed $data = null, int $status = 200, array $headers = [])
    {
        $headers['Content-Type'] = 'application/json';
        
        $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        if ($body === false) {
            throw new \RuntimeException('Failed to encode JSON: ' . json_last_error_msg());
        }
        
        $stream = new Stream('php://memory', 'rw');
        $stream->write($body);
        $stream->rewind();
        
        parent::__construct($status, $headers, $stream);
    }

    /**
     * Create a new JSON response from array
     */
    public static function fromArray(array $data, int $status = 200, array $headers = []): self
    {
        return new self($data, $status, $headers);
    }

    /**
     * Get the JSON decoded body
     */
    public function getData(): mixed
    {
        $body = (string) $this->getBody();
        return json_decode($body, true);
    }
}