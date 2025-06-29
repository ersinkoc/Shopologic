<?php

declare(strict_types=1);

namespace Shopologic\Core\Container;

use Shopologic\PSR\Container\NotFoundExceptionInterface;

class NotFoundException extends ContainerException implements NotFoundExceptionInterface
{
}