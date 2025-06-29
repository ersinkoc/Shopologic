<?php

declare(strict_types=1);

namespace Shopologic\Core\I18n;

/**
 * Interface for translation file loaders
 */
interface TranslationLoaderInterface
{
    /**
     * Load translations from a file
     */
    public function load(string $file): array;
}