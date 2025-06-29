<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Extension;

interface ExtensionInterface
{
    /**
     * Get filters provided by this extension
     */
    public function getFilters(): array;

    /**
     * Get functions provided by this extension
     */
    public function getFunctions(): array;

    /**
     * Get global variables provided by this extension
     */
    public function getGlobals(): array;
}