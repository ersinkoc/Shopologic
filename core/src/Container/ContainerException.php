<?php

declare(strict_types=1);

namespace Shopologic\Core\Container;

use Shopologic\PSR\Container\ContainerExceptionInterface;

class ContainerException extends \Exception implements ContainerExceptionInterface
{
}