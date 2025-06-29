<?php

declare(strict_types=1);

namespace Shopologic\Core\Kernel\Events;

use Shopologic\Core\Events\Event;
use Shopologic\PSR\Http\Message\RequestInterface;

class ExceptionOccurred extends Event
{
    public function __construct(
        public readonly \Throwable $exception,
        public readonly RequestInterface $request
    ) {}
}