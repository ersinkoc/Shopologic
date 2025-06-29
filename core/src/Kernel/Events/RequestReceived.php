<?php

declare(strict_types=1);

namespace Shopologic\Core\Kernel\Events;

use Shopologic\Core\Events\Event;
use Shopologic\PSR\Http\Message\RequestInterface;

class RequestReceived extends Event
{
    public function __construct(
        public readonly RequestInterface $request
    ) {}
}