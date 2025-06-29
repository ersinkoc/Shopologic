<?php

declare(strict_types=1);

namespace Shopologic\Core\I18n\Loaders;

use Shopologic\Core\I18n\TranslationLoaderInterface;

/**
 * Loads translations from JSON files
 */
class JsonFileLoader implements TranslationLoaderInterface
{
    public function load(string $file): array
    {
        if (!file_exists($file)) {
            return [];
        }
        
        $content = file_get_contents($file);
        $translations = json_decode($content, true);
        
        return is_array($translations) ? $translations : [];
    }
}