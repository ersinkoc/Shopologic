<?php

declare(strict_types=1);

use Shopologic\Core\Kernel\Application;

$app = new Application(dirname(__DIR__));

$app->singleton(
    \Shopologic\Core\Kernel\HttpKernelInterface::class,
    \Shopologic\Core\Kernel\HttpKernel::class
);

return $app;