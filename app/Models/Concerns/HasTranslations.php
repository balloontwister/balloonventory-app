<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Add to models that have a one-to-many translations relation.
 *
 * The using model must define:
 *   public function translations(): HasMany
 *
 * The translations table must have: locale, name (and optionally description).
 */
trait HasTranslations
{
    /**
     * Resolve the translated value for a field in the current locale,
     * falling back to the English value on the parent row.
     */
    public function translated(string $field = 'name'): string
    {
        $locale = app()->getLocale();

        if ($locale === 'en') {
            return $this->{$field};
        }

        $translation = $this->translations->firstWhere('locale', $locale);

        if ($translation && $translation->{$field}) {
            return $translation->{$field};
        }

        return $this->{$field};
    }

    /**
     * Eager-load the translations for the current request locale.
     */
    public function scopeWithTranslations(Builder $query, ?string $locale = null): Builder
    {
        $locale ??= app()->getLocale();

        if ($locale === 'en') {
            return $query;
        }

        return $query->with(['translations' => fn ($q) => $q->where('locale', $locale)]);
    }

    /**
     * Load translations for the given locale onto an already-retrieved collection.
     */
    public function loadTranslations(?string $locale = null): void
    {
        $locale ??= app()->getLocale();

        if ($locale === 'en') {
            return;
        }

        $this->loadMissing(['translations' => fn ($q) => $q->where('locale', $locale)]);
    }
}
