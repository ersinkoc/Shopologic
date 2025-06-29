<?php

declare(strict_types=1);

namespace Shopologic\Core\I18n\Traits;

use Shopologic\Core\I18n\Locale\LocaleManager;

/**
 * Trait for models with translatable fields
 */
trait Translatable
{
    /**
     * Current locale for translations
     */
    protected ?string $translationLocale = null;
    
    /**
     * Loaded translations cache
     */
    protected array $translations = [];
    
    /**
     * Get translatable attributes
     */
    abstract public function getTranslatableAttributes(): array;
    
    /**
     * Get translation model name
     */
    public function getTranslationModelName(): string
    {
        return get_class($this) . 'Translation';
    }
    
    /**
     * Get translations relationship
     */
    public function translations()
    {
        return $this->hasMany($this->getTranslationModelName());
    }
    
    /**
     * Get translation for locale
     */
    public function translate(?string $locale = null, bool $withFallback = true)
    {
        $locale = $locale ?? $this->getLocale();
        
        if (!isset($this->translations[$locale])) {
            $this->translations[$locale] = $this->getTranslation($locale, $withFallback);
        }
        
        return $this->translations[$locale] ?? $this;
    }
    
    /**
     * Get translation by locale
     */
    public function getTranslation(string $locale, bool $withFallback = true)
    {
        $translation = $this->translations()
            ->where('locale', $locale)
            ->first();
        
        if (!$translation && $withFallback) {
            $fallbackLocale = $this->getFallbackLocale();
            if ($fallbackLocale && $fallbackLocale !== $locale) {
                $translation = $this->translations()
                    ->where('locale', $fallbackLocale)
                    ->first();
            }
        }
        
        return $translation;
    }
    
    /**
     * Has translation for locale
     */
    public function hasTranslation(string $locale): bool
    {
        return $this->translations()
            ->where('locale', $locale)
            ->exists();
    }
    
    /**
     * Create or update translation
     */
    public function translateOrNew(string $locale)
    {
        $translation = $this->getTranslation($locale, false);
        
        if (!$translation) {
            $modelName = $this->getTranslationModelName();
            $translation = new $modelName();
            $translation->locale = $locale;
            $translation->{$this->getForeignKey()} = $this->getKey();
        }
        
        return $translation;
    }
    
    /**
     * Delete translation
     */
    public function deleteTranslation(string $locale): bool
    {
        return $this->translations()
            ->where('locale', $locale)
            ->delete() > 0;
    }
    
    /**
     * Get translatable attribute
     */
    public function getTranslatedAttribute(string $key, ?string $locale = null)
    {
        $locale = $locale ?? $this->getLocale();
        $translation = $this->translate($locale);
        
        if ($translation && $translation !== $this) {
            return $translation->$key;
        }
        
        return $this->attributes[$key] ?? null;
    }
    
    /**
     * Set translation locale
     */
    public function setLocale(string $locale): self
    {
        $this->translationLocale = $locale;
        return $this;
    }
    
    /**
     * Get current locale
     */
    public function getLocale(): string
    {
        if ($this->translationLocale) {
            return $this->translationLocale;
        }
        
        $localeManager = app(LocaleManager::class);
        return $localeManager->getCurrentLocale();
    }
    
    /**
     * Get fallback locale
     */
    protected function getFallbackLocale(): ?string
    {
        $localeManager = app(LocaleManager::class);
        return $localeManager->getDefaultLocale();
    }
    
    /**
     * Override getAttribute to return translated value
     */
    public function getAttribute($key)
    {
        if (in_array($key, $this->getTranslatableAttributes())) {
            return $this->getTranslatedAttribute($key);
        }
        
        return parent::getAttribute($key);
    }
    
    /**
     * Fill model with translation data
     */
    public function fillWithTranslation(array $attributes, string $locale): self
    {
        $translatableAttributes = $this->getTranslatableAttributes();
        $translationData = [];
        $modelData = [];
        
        foreach ($attributes as $key => $value) {
            if (in_array($key, $translatableAttributes)) {
                $translationData[$key] = $value;
            } else {
                $modelData[$key] = $value;
            }
        }
        
        // Fill model attributes
        $this->fill($modelData);
        
        // Create or update translation
        if (!empty($translationData)) {
            $translation = $this->translateOrNew($locale);
            $translation->fill($translationData);
            
            // Save after model is saved
            static::saved(function ($model) use ($translation) {
                $translation->{$model->getForeignKey()} = $model->getKey();
                $translation->save();
            });
        }
        
        return $this;
    }
    
    /**
     * Scope to with translations
     */
    public function scopeWithTranslation($query, ?string $locale = null)
    {
        $locale = $locale ?? $this->getLocale();
        
        return $query->with(['translations' => function ($query) use ($locale) {
            $query->where('locale', $locale);
        }]);
    }
    
    /**
     * Scope to with all translations
     */
    public function scopeWithAllTranslations($query)
    {
        return $query->with('translations');
    }
    
    /**
     * Get all locales this model is translated in
     */
    public function getAvailableLocales(): array
    {
        return $this->translations()
            ->pluck('locale')
            ->toArray();
    }
    
    /**
     * Replicate with translations
     */
    public function replicateWithTranslations(array $locales = []): self
    {
        $newModel = $this->replicate();
        $newModel->save();
        
        $translations = $this->translations();
        
        if (!empty($locales)) {
            $translations = $translations->whereIn('locale', $locales);
        }
        
        foreach ($translations->get() as $translation) {
            $newTranslation = $translation->replicate();
            $newTranslation->{$this->getForeignKey()} = $newModel->getKey();
            $newTranslation->save();
        }
        
        return $newModel;
    }
}