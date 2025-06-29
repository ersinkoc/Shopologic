<?php

declare(strict_types=1);

namespace Shopologic\Core\Kernel;

use Shopologic\PSR\Http\Message\RequestInterface;
use Shopologic\PSR\Http\Message\ResponseInterface;

interface HttpKernelInterface
{
    public function handle(RequestInterface $request): ResponseInterface;
}