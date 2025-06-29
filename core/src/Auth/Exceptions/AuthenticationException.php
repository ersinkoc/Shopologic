<?php

declare(strict_types=1);

namespace Shopologic\Core\Auth\Exceptions;

use Shopologic\Core\Http\Response;

class AuthenticationException extends \Exception
{
    protected array $guards;
    protected ?Response $response;

    public function __construct(
        string $message = 'Unauthenticated.',
        array $guards = [],
        ?Response $response = null
    ) {
        parent::__construct($message);
        
        $this->guards = $guards;
        $this->response = $response;
    }

    /**
     * Get the guards that were checked
     */
    public function guards(): array
    {
        return $this->guards;
    }

    /**
     * Get the response
     */
    public function response(): ?Response
    {
        return $this->response;
    }
}