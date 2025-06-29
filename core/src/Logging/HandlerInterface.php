<?php

declare(strict_types=1);

namespace Shopologic\Core\Logging;

interface HandlerInterface
{
    public function handle(array $record): void;
}