<?php

declare(strict_types=1);

namespace Shopologic\Core\I18n\Loaders;

use Shopologic\Core\I18n\TranslationLoaderInterface;

/**
 * Loads translations from PHP files
 */
class PhpFileLoader implements TranslationLoaderInterface
{
    public function load(string $file): array
    {
        if (!file_exists($file)) {
            return [];
        }
        
        $translations = require $file;
        
        return is_array($translations) ? $translations : [];
    }
}